<?php
/**
 * The security context service file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security;
 */

namespace SmartLicenseServer\Security\Context;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\QueryIntents\SelectionIntent;
use Callismart\DBPrism\Query\SQLBuilder;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\SecurityException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Security\Actors\ActorInterface;
use SmartLicenseServer\Security\Actors\OrganizationMember;
use SmartLicenseServer\Security\Actors\ServiceAccount;
use SmartLicenseServer\Utils\SanitizeAwareTrait;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Security\OwnerSubjects\Organization;
use SmartLicenseServer\Security\OwnerSubjects\OrganizationMembers;
use SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface;

use const SMLISER_ROLE_ASSIGNMENT_TABLE, SMLISER_ORGANIZATION_MEMBERS_TABLE, 
SMLISER_OWNERS_TABLE, SMLISER_ORGANIZATIONS_TABLE, SMLISER_USERS_TABLE, SMLISER_SERVICE_ACCOUNTS_TABLE;
use function class_exists, parse_args_recursive, smliser_db, strtolower, gmdate, method_exists,
sprintf, class_implements, in_array;

/**
 * The security context service provider holds the context of authenticated actor/principal
 * and provides unified methods to access security entities.
 */
class ContextServiceProvider {
    use CacheAwareTrait, SanitizeAwareTrait;

    /**
     * Search security entities across multiple db tables with pagination.
     *
     * @param array{
     *  search_term: string,
     *  page: int,
     *  limit: int,
     *  types: array
     * } $args
     * @return array
     */
    public static function search( array $args = [] ) {
        $db = smliser_db();

        $defaults = array(
            'search_term'   => '',
            'page'          => 1,
            'limit'         => 20,
            'types'         => Owner::get_allowed_owner_types(),
        );

        $args   = parse_args( $args, $defaults );
        $term   = self::sanitize_text( $args['search_term'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );

        /** @var ('individual'|'organization'|'platform')[] $types */
        $types  = array_filter( (array) $args['types'] );

        $cache_key  = static::make_cache_key( __METHOD__, \compact( 'term', 'page', 'limit', 'types' ) );

        $results    = static::cache_get( $cache_key );

        if ( false !== $results ) {
            return $results;
        }

        if ( empty( $term ) || empty( $types ) ) {
            return static::make_paginated_result( data: [], page: $page, limit: $limit );
        }

        $total_types    = count( $types );

        if ( 1 === $total_types ) {
            $type   = $types[0];
            $table  = static::get_entity_table( $type );

            if ( ! $table ) {
                return static::make_paginated_result( data: [], page: $page, limit: $limit );
            }

            $sql    = static::query()
                ->select( '*' )->from( $table )
                ->where_contains( 'display_name', $term )
                ->limit( $limit )->offset( $offset );

            $count_sql  = static::query()
                ->select( 'COUNT(*)' )->from( $table )
                ->where_contains( 'display_name', $term );

            $row    = $db->get_row( $sql->build(), $sql->get_bindings() );
            $total  = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

            $class_name = static::get_entity_classname( $type );

            $result = [];

            if ( $row && class_exists( $class_name ) && method_exists( $class_name, 'from_array' ) ) {
                $result = $class_name::from_array( (array) $row );
            }

            return static::make_paginated_result( data: $result, page: $page, limit: $limit, total: $total );

        }

        $sql_map   = fn( string $type, string $table ) => match( $type ) {
            Owner::TYPE_ORGANIZATION    => static::query()
                ->select(
                    "'{$type}' as type", 'id', 'display_name', 'slug', 'NULL as email', 
                    'NULL as status', 'created_at', 'updated_at' )->from( $table )
                ->where_contains( 'display_name', $term ),
            Owner::TYPE_INDIVIDUAL  => static::query()
                ->select(
                    "'{$type}' as type", 'id', 'display_name', 'NULL as slug', 'email',
                    'status', 'created_at', 'updated_at' )->from( $table )
                ->where_contains( 'display_name', $term ),
            default => null
        };

        $sqls       = [];
        $added_type = '';

        foreach ( $types as $type ) {
            $table  = static::get_entity_table( $type );

            if ( ! $table ) continue;

            $query  = $sql_map( type: $type, table: $table );

            if ( ! $query ) continue;

            $sqls[]     = $query;
            $added_type = $type;
        }

        if ( empty( $sqls ) ) {
            return static::make_paginated_result( data: [], page: $page, limit: $limit );
        }

        if ( count( $sqls ) === 1 ) {
            $sql        = $sqls[0];
            $count_sql  = ( clone $sql )->select( 'COUNT(*) as total_records' )->from( $sql->get_table_name() );

            $sql->limit( $limit )->offset( $offset );

            $row    = $db->get_row( $sql->build(), $sql->get_bindings() );
            $total  = $db->get_var( $count_sql->build(), $count_sql->get_bindings() );
            
            $class_name = static::get_entity_classname( $added_type );

            $result = [];

            if ( $row && class_exists( $class_name ) && method_exists( $class_name, 'from_array' ) ) {
                $result = $class_name::from_array( (array) $row );
            }

            return static::make_paginated_result( data: $result, page: $page, limit: $limit, total: $total );
        }

        $base_union = null;

        foreach( $sqls as $sql ) {
            if ( ! isset( $base_union ) ) {
                $base_union = $sql;
                continue;
            }

            $base_union = $base_union->union_all( $sql );
        }

        /** @var \Callismart\DBPrism\Query\QueryIntents\CompoundQueryIntent $base_union */
        $count_sql    = ( clone $base_union )->select( 'COUNT(*) as total' )->as( 'counts' );
        $data_sql   = $base_union
            ->select( '*' )
            ->as( 'combined' )->order_by( 'updated_at', 'DESC' )
            ->limit( $limit )->offset( $offset );

        $rows   = $db->get_results( $data_sql->build(), $data_sql->get_bindings() );
        $total  = $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

        foreach ( $rows as $index => &$row ) {

            $class = self::get_entity_classname( $row['type'] );

            if ( $class && method_exists( $class, 'from_array' ) ) {
                $row = $class::from_array( $row );
            } else {
                unset( $rows[$index] );
            }
        }

        return static::make_paginated_result( data: $rows, page: $page, limit: $limit, total: $total );
    }

    /**
     * Perform a search in the resource owners table.
     * 
     * @param array $args {
     *    Optional. Arguments to filter results.
     *    @type string $term   Search term to match against name, type, ids. Required.
     *    @type int    $page   Current page number. Default 1.
     *    @type int    $limit  Number of items per page. Default 20.
     *    @type string $status Entity status filter. Default 'active'.
     * }
     * 
     * @return array{items: array, pagination: array{total: int, page: int, limit: int, total_pages: int}}
     */
    public static function search_owners( array $args = [] ) : array {
        $db = smliser_db();
        $defaults = [
            'search_term' => '',
            'page'        => 1,
            'limit'       => 20,
        ];

        $args   = parse_args_recursive( $args, $defaults );
        $table  = SMLISER_OWNERS_TABLE;
        $term   = self::sanitize_text( $args['search_term'] );
        $limit  = max( 1, (int) $args['limit'] );
        $page   = max( 1, (int) $args['page'] );
        $offset = $db->calculate_query_offset( $page, $limit );

        if ( empty( $term ) ) {
            return static::make_paginated_result( data: [], page: $page, limit: $limit );
        }

        $sql   = static::query()
            ->select( '*' )->from( $table )
            ->where_contains( 'type', $term )
            ->or_where_contains( 'name', $term )
            ->order_by( 'updated_at', 'DESC' )
            ->limit( $limit )->offset( $offset );

        $count_sql   = static::query()
            ->select( 'COUNT(*)' )->from( $table )
            ->where_contains( 'type', $term )
            ->or_where_contains( 'name', $term );

        $rows   = $db->get_results( $sql->build(), $sql->get_bindings() );
        $total  = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

        $owners = array_map( [Owner::class, 'from_array'], $rows );

        return static::make_paginated_result( data: $owners, page: $page, limit: $limit, total: $total );
    }

    /**
     * Get a security entity class name.
     * 
     * @param string $entity The name of the security entity.
     * - valid names are `owner`, `user`, `individual`, `organization`, `service_account`, and `role`.
     * @return class-string<Owner|Organization|User|ServiceAccount|Role>|null
     */
    public static function get_entity_classname( $entity ) : ?string {
        if ( ! is_string( $entity ) ) {
            return null;
        }

        $entity = str_replace( '_', '', ucwords( $entity, '_' ) );

        return match( strtolower( $entity ) ) {
            Owner::TYPE_INDIVIDUAL, 'user'  => User::class,
            Owner::TYPE_ORGANIZATION        => Organization::class,
            'serviceaccount'                => ServiceAccount::class,
            'owner'                         => Owner::class,
            default                         => ''
        };

    }

    /**
     * Saves the role of an actor.
     * 
     * @param ActorInterface $actor The actor that can authenticate.
     * @param Role $role
     * @param OwnerSubjectInterface|null $subject The subject entity associated with resource owner.
     * @throws InvalidArgumentException When one required field is missing.
     * @throws DatabaseException Sensitive database error, caller must handle accordingly.
     */
    public static function save_actor_role( ActorInterface $actor, Role $role, ?OwnerSubjectInterface $subject = null ) : bool {
        $table          = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $subject_type   = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
        $subject_id     = $subject ? $subject->get_id() : $actor->get_id();

        $result = smliser_db()->transactional( function( Database $db ) use ( $role, $subject, $table, $actor, $subject_type, $subject_id) {
            $now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
            $data   = array(
                'role_id'               => $role->get_id(),
                'principal_id'          => $actor->get_id(),
                'principal_type'        => $actor->get_type(),
                'owner_subject_type'    => $subject_type,
                'owner_subject_id'      => $subject_id,
                'updated_at'            => $now->format( 'Y-m-d H:i:s' )
            );
            
            $missing_keys = Collection::make( $data )
                ->filter( fn( $value ) => empty( $value ) )
                ->keys()
                ->all();

            if ( ! empty( $missing_keys ) ) {
                throw new InvalidArgumentException(
                    sprintf(
                        'Role assignment failed. Missing required fields: %s',
                        implode( ', ', $missing_keys )
                    )
                );
            }

            $exists_sql    = static::query()
                ->select( 'role_id' )->from( $table )
                ->where( 'principal_id', '=', $actor->get_id() )
                ->where( 'principal_type', '=', $actor->get_type() )
                ->where( 'owner_subject_type', '=', $subject_type )
                ->where( 'owner_subject_id', '=', $subject_id )
                ->limit( 1 )->lock_for_update();

            $role_id    = (int) $db->get_var( $exists_sql->build(), $exists_sql->get_bindings() );

            if ( $role_id ) {
                if ( $role->get_id() !== $role_id ) {
                    // Only the role assigned to this owner changes.
                    // Existing owner and principal data remains immutable.
                    $where = [
                        'principal_id'          => $actor->get_id(),
                        'principal_type'        => $actor->get_type(),
                        'owner_subject_type'    => $subject_type,
                        'owner_subject_id'      => $subject ? $subject->get_id() : $actor->get_id(),
                    ];
                    
                    $data   = ['role_id' => $role->get_id()];
                    $result = $db->update( $table, $data, $where );
                } else{
                    $result = true;
                }

                $code   = 'updated_failed';

            } else {
                $data['created_at'] = $now->format( 'Y-m-d H:i:s' );
                $result             = $db->insert( $table, $data );

                $code               = 'insert_failed';
            }

            if ( ! $result ) {
                throw new DatabaseException( $code, $db->get_last_error() );
            }

            static::cache_clear();

            return $result;
        });
        
        return false !== $result;
    }

    /**
     * Delete an actors' assignedrole from the assignment table.
     * 
     * @param ActorInterface $actor
     * @param OwnerSubjectInterface|null $subject
     * @throws DatabaseException Sensitive database error, caller must handle accordingly.
     */
    public static function delete_actor_role( ActorInterface $actor, ?OwnerSubjectInterface $subject ) : void {
        if ( ! $subject && ! in_array( OwnerSubjectInterface::class, class_implements( $actor ), true ) ) {
            throw new SecurityException( 
                'invalid_scope', 
                'Principal must either be a valid resource owner or be acting for a resource owner.',
                ['status' => 500]
            );
        }

        $table  = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $db     = smliser_db();
        $subject_type   = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;

        $deleted    = $db->delete( $table,[
            'principal_id'          => $actor->get_id(),
            'principal_type'        => $actor->get_type(),
            'owner_subject_type'    => $subject_type,
            'owner_subject_id'      => $subject ? $subject->get_id() : $actor->get_id(),
        ]);

        if ( ! $deleted ) {
            throw new DatabaseException( 'delete_failed', $db->get_last_error() );
        }

        static::cache_clear();
    }

    /**
     * Get the role assigned to an actor/principal.
     * 
     * @param ActorInterface $actor The authenticatable actor.
     * @param OwnerSubjectInterface|null $subject The owner subject (organization or user).
     * @return Role|null
     */
    public static function get_principal_role( ActorInterface $actor, ?OwnerSubjectInterface $subject = null ) : ?Role {
        $db     = smliser_db();
        $table  = SMLISER_ROLE_ASSIGNMENT_TABLE;
                
        $subject_type   = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
        $sbj_owner_id   = $subject ? $subject->get_id() : $actor->get_id();

        $sql    = static::query()
            ->select( 'role_id' )->from( $table )
            ->where( 'principal_id', '=', $actor->get_id() )
            ->where( 'principal_type', '=', $actor->get_type() )
            ->where( 'owner_subject_type', '=', $subject_type )
            ->where( 'owner_subject_id', '=', $sbj_owner_id )
            ->limit( 1 );

        $role_id    = $db->get_var( $sql->build(), $sql->get_bindings() );

        return $role_id ? Role::get_by_id( (int) $role_id ) : null;
    }

    /**
     * Get the default owner entity this user object represents.
     * 
     * @param User $user The user object.
     * @return Owner|null The owner instance or null when the user is not a resource owner
     */
    public static function get_default_owner( User $user ) : ?Owner {
        $db     = smliser_db();
        $table  = SMLISER_OWNERS_TABLE;
        $sql    = static::query()
            ->select( 'id' )->from( $table )
            ->where( 'subject_id', '=', $user->get_id() )
            ->where( 'type', '=', Owner::TYPE_INDIVIDUAL )
            ->limit( 1 );
        $id     = (int) $db->get_var( $sql->build(), $sql->get_bindings() );

        return Owner::get_by_id( $id );
    }

    /**
     * Get either the organization or the individual user associated with a
     * resource owner object.
     * 
     * @param Owner $owner
     * @return null|\SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface
     */
    public static function get_owner_subject( Owner $owner ) : ?OwnerSubjectInterface {

        return match( $owner->get_type() ) {
            Owner::TYPE_ORGANIZATION    => Organization::get_by_id( $owner->get_id() ),
            Owner::TYPE_INDIVIDUAL      => User::get_by_id( $owner->get_subject_id() ),
            default                     => null
        };
    }

    /**
     * Save a single member of an organization with their role.
     * 
     * @param OrganizationMember $member
     * @param Organization $organization
     * @param Role $role
     * @throws DatabaseException|InvalidArgumentException
     */
    public static function save_organization_member( OrganizationMember $member, Organization $organization, Role $role ) {
        smliser_db()->transactional( function( Database $db ) use ( $member, $organization, $role ) {
            if ( ! $organization->is_member( $member ) ) {
                throw new InvalidArgumentException(
                    sprintf(
                        '%s does not belong to this organization "%s"',
                        $member->get_display_name(),
                        $organization->get_display_name()
                    )
                );
            }

            if ( ! $role->exists() ) {
                throw new InvalidArgumentException( 'The role assigned to this member does not exist.' );
            }

            $table  = SMLISER_ORGANIZATION_MEMBERS_TABLE;
            $now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

            $exists_sql = static::query()
                ->select( 'id' )->from( $table )
                ->where( 'organization_id', '=', $organization->get_id() )
                ->where( 'member_id', '=', $member->get_id() )
                ->limit( 1 )->lock_for_update();

            $id = $db->get_var( $exists_sql->build(), $exists_sql->get_bindings() );

            if ( $id ) {
                $data   = ['updated_at' => $now->format( 'Y-m-d H:i:s' ) ];
                
                if ( false === $db->update( $table, $data, ['id' => $id] ) ) {
                    throw new DatabaseException( 'update_failed', $db->get_last_error() );
                }

            } else {
                $data   = [
                    'organization_id'   => $organization->get_id(),
                    'member_id'         => $member->get_user()->get_id(),
                    'created_at'        => $now->format( 'Y-m-d H:i:s' ),
                    'updated_at'        => $now->format( 'Y-m-d H:i:s' )
                ];

                if ( false === $db->insert( $table, $data ) ) {
                    throw new DatabaseException( 'insert_failed', $db->get_last_error() );
                }
            }
            
            static::save_actor_role( $member->get_user(), $role, $organization );
            static::cache_clear();            
        });
    }

    /**
     * Get organization members with their roles
     *
     * @param Organization $organization
     * @return OrganizationMembers
     */
    public static function get_organization_members( Organization $organization ): OrganizationMembers {
        $table  = SMLISER_ORGANIZATION_MEMBERS_TABLE;
        $db     = smliser_db();

        $sql    = static::query()
            ->select( '*' )->from( $table )
            ->where( 'organization_id', '=', $organization->get_id() );
        $results  = $db->get_results( $sql->build(), $sql->get_bindings() );

        $members  = new OrganizationMembers();

        foreach ( $results as $result ) {
            $member_id = (int) $result['member_id'] ?? 0;
            $user      = User::get_by_id( $member_id );

            if ( ! $user ) {
                continue;
            }

            $role           = static::get_principal_role( $user, $organization );
            $result['role'] = $role;

            $collection     = new Collection( $result );
            $member         = new OrganizationMember( $user, $collection );

            $members->add( $member );
        }

        return $members;
    }

    /**
     * Get all the organization a user belongs to.
     * 
     * @param User $user
     * @return Organization[]|null
     */
    public static function get_user_organizations( User $user ) : ?array {
        $table  = SMLISER_ORGANIZATIONS_TABLE;
        $db     = smliser_db();

        $sql    = static::query()
            ->select( 'organization_id' )->from( $table )
            ->where( 'member_id', '=', $user->get_id() )
            ->limit( 1 );

        $results    = $db->get_col( $sql->build(), $sql->get_bindings() );

        foreach ( $results as $index => &$result ) {
            $org = Organization::get_by_id( $result['id'] ?? 0 );

            if ( ! $org ) {
                unset( $results[$index] );
            }

            $result = $org;
        }

        return empty( $results ) ? null : $results;
    }

    /**
     * Deletes an organization member and their roles
     * 
     * @param OrganizationMember $member
     * @param Organization $organization
     * @throws InvalidArgumentException
     * @throws DatabaseException Sensitive database error, caller must handle accordingly.
     */
    public static function delete_organization_member( OrganizationMember $member, Organization $organization ) {
        smliser_db()->transactional( function( Database $db ) use ( $member, $organization ) {
            if ( ! $organization->is_member( $member ) ) {
                throw new InvalidArgumentException(
                    sprintf(
                        '%s does not belong to this organization "%s"',
                        $member->get_display_name(),
                        $organization->get_display_name()
                    )
                );
            }
            $db     = smliser_db();
            $table  = SMLISER_ORGANIZATION_MEMBERS_TABLE;

            $deleted    = $db->delete( $table, [
                'id'                => $member->get_id(),
                'member_id'         => $member->get_user()->get_id(),
                'organization_id'   => $organization->get_id()
            ]);

            if ( ! $deleted ) {
                throw new DatabaseException( 'delete_failed', $db->get_last_error() );
            }

            static::delete_actor_role( $member, $organization );
            static::cache_clear();
        });
    }

    /**
     * Delete a security entity and all its related data in a single query where possible.
     *
     * @param User|Organization|ServiceAccount|Owner $entity
     * @return void
     * @throws SecurityException On failed or partial delete.
     */
    public static function delete_entity( User|Organization|ServiceAccount|Owner $entity ) : void {

        try {
            switch ( true ) {
                case $entity instanceof User:
                    $deleted    = static::delete_user( $entity );
                    break;

                case $entity instanceof Organization:
                    $deleted = static::delete_organization( $entity );
                    
                    break;

                case $entity instanceof ServiceAccount:
                    // Delete service account and all roles assigned to it.
                    $deleted    = static::delete_service_account( $entity );
                    break;

                case $entity instanceof Owner:

                    $deleted = static::delete_resource_owner( $entity );
                    break;


                default:
                    throw new SecurityException(
                        'delete_error',
                        'Unsupported entity type for deletion.',
                        ['status' => 400]
                    );
            }

            if ( false === $deleted ) {
                throw new SecurityException(
                    'delete_error',
                    'Unable to delete the provided entity from the database.',
                    ['status' => 500]
                );
            }

            static::cache_clear();
        } catch ( \Exception $e ) {
            throw new SecurityException(
                'delete_error',
                'Failed to delete entity. Transaction rolled back.',
                ['status' => 500, 'error' => $e->getMessage()]
            );
        }

        if ( method_exists( $entity, 'get_avatar' ) ) {
            $avatar     = $entity->get_avatar();
            $filename   = $avatar->basename();
            $avatar_type    = $entity instanceof User ? 'user' : $entity->get_type();
            
            FileSystemHelper::delete_avatar( $filename, $avatar_type );

        }
    }

    /**
     * Generate overview report for Accounts & Access dashboard.
     *
     * @return array<string, mixed>
     * @throws SecurityException
     */
    public static function get_accounts_summary_report() : array {
        $cache_key              = static::make_cache_key( __METHOD__ );
        $report                 = static::cache_get( $cache_key );

        if ( false !== $report ) {
            return $report;
        }

        $db                     = smliser_db();
        $users_table            = SMLISER_USERS_TABLE;
        $organizations_table    = SMLISER_ORGANIZATIONS_TABLE;
        $org_members_table      = SMLISER_ORGANIZATION_MEMBERS_TABLE;
        $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;
        $owners_table           = SMLISER_OWNERS_TABLE;

        try {
            $counts = $db->get_row(
                "SELECT
                    ( SELECT COUNT(*) FROM `{$users_table}` ) AS users_total,
                    ( SELECT COUNT(*) FROM `{$organizations_table}` ) AS organizations_total,
                    ( SELECT COUNT(*) FROM `{$service_accounts_table}` ) AS service_accounts_total,
                    ( SELECT COUNT(*) FROM `{$org_members_table}` ) AS organization_members_total,
                    ( SELECT COUNT(*) FROM `{$owners_table}` ) AS resource_owners_total
                ",
            );

            $integrity = $db->get_row(
                "SELECT
                    ( SELECT COUNT(*) 
                      FROM `{$service_accounts_table}` sa
                      LEFT JOIN `{$owners_table}` ro ON sa.owner_id = ro.id
                      WHERE ro.id IS NULL 
                    ) AS orphaned_service_accounts,
                    
                    ( SELECT COUNT(*) 
                      FROM `{$org_members_table}` om
                      LEFT JOIN `{$users_table}` u ON om.member_id = u.id
                      WHERE u.id IS NULL 
                    ) AS orphaned_members,
                    
                    ( SELECT COUNT(*) 
                      FROM `{$owners_table}` ro
                      LEFT JOIN `{$users_table}` u
                          ON ro.type = 'individual' AND ro.subject_id = u.id
                      LEFT JOIN `{$organizations_table}` o
                          ON ro.type = 'organization' AND ro.subject_id = o.id
                      WHERE
                          ( ro.type = 'individual' AND u.id IS NULL )
                          OR
                          ( ro.type = 'organization' AND o.id IS NULL )
                    ) AS orphaned_owners
                ",
            );

            $usage = $db->get_row(
                "SELECT
                    COUNT(*) AS total,
                    COUNT( last_used_at ) AS ever_used,
                    MAX( last_used_at ) AS most_recent_use,
                    MIN( last_used_at ) AS oldest_use
                FROM `{$service_accounts_table}`
                ",
            );
            
            $report = [
                'summary' => [
                    'users'                => (int) $counts['users_total'],
                    'organizations'        => (int) $counts['organizations_total'],
                    'service_accounts'     => (int) $counts['service_accounts_total'],
                    'organization_members' => (int) $counts['organization_members_total'],
                    'resource_owners'      => (int) $counts['resource_owners_total'],
                ],

                'integrity' => [
                    'orphaned_service_accounts' => (int) $integrity['orphaned_service_accounts'],
                    'orphaned_members'          => (int) $integrity['orphaned_members'],
                    'orphaned_owners'           => (int) $integrity['orphaned_owners'],
                    'has_issues'                => 
                        $integrity['orphaned_service_accounts'] > 0 ||
                        $integrity['orphaned_members'] > 0 ||
                        $integrity['orphaned_owners'] > 0,
                ],

                'usage' => [
                    'service_accounts' => [
                        'total'           => (int) $usage['total'],
                        'ever_used'       => (int) $usage['ever_used'],
                        'never_used'      => (int) $usage['total'] - (int) $usage['ever_used'],
                        'most_recent_use' => $usage['most_recent_use'],
                        'oldest_use'      => $usage['oldest_use'],
                    ],
                ],
            ];

            static::cache_set( $cache_key, $report, static::default_ttl() );
            return $report;

        } catch ( \Exception $e ) {
            throw new SecurityException(
                'accounts_summary_error',
                'Unable to build accounts summary report.',
                [
                    'status' => 500,
                    'error'  => $e->getMessage(),
                ]
            );
        }
    }

    /*
    |-----------------------
    | PRIVATE HELPERS
    |-----------------------
    */

    /**
     * Delete user and all related data cascading cleanly through relational IDs.
     * 
     * @param User $user
     * @return bool
     */
    public static function delete_user( User $user ) : bool {
        return smliser_db()->transactional( function ( Database $db ) use ( $user ) {
            $users_table            = SMLISER_USERS_TABLE;
            $role_assignment_table  = SMLISER_ROLE_ASSIGNMENT_TABLE;
            $resource_owners_table  = SMLISER_OWNERS_TABLE;
            $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;
            $org_member_table       = SMLISER_ORGANIZATION_MEMBERS_TABLE;

            $lock_sql = static::query()
                ->select( 'id' )->from( $users_table )
                ->where( 'id', '=', $user->get_id() )->lock_for_update();
            
            $lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
            if ( ! $lock ) {
                return false;
            }

            // Delete straight-linked user associations (Individual Roles & Org Memberships)
            $db->delete(
                $role_assignment_table,
                [
                    'principal_type' => Owner::TYPE_INDIVIDUAL,
                    'principal_id'   => $user->get_id()
                ]
            );
            
            $db->delete(
                $org_member_table,
                [
                    'member_id' => $user->get_id()
                ]
            );

            // Extract the Resource Owner IDs linked to this user (Matches: ON ro.subject_id = u.id)
            $owner_ids_sql = static::query()
                ->select( 'id' )->from( $resource_owners_table )
                ->where( 'subject_id', '=', $user->get_id() )
                ->where( 'type', '=', Owner::TYPE_INDIVIDUAL );
            
            $owner_ids = $db->get_col( $owner_ids_sql->build(), $owner_ids_sql->get_bindings() );

            // Process the deep dependent tables ONLY if resource owners exist
            if ( ! empty( $owner_ids ) ) {
                // Find all Service Account IDs pointing to these Resource Owners (Matches: ON sa.owner_id = ro.id)
                $sa_ids_sql = static::query()
                    ->select( 'id' )->from( $service_accounts_table )
                    ->where_in( 'owner_id', $owner_ids );
                
                $sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

                if ( ! empty( $sa_ids ) ) {
                    // Delete roles assigned to these specific service accounts.
                    $sa_roles_delete = static::query()
                        ->delete( $role_assignment_table )
                        ->where( 'principal_type', '=', 'service_account' )
                        ->where_in( 'principal_id', $sa_ids );
                    
                    $db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

                    // Delete the actual Service Accounts.
                    $sa_purge_delete = static::query()
                        ->delete( $service_accounts_table )
                        ->where_in( 'id', $sa_ids );

                    $db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
                }
            }

            // Delete the Resource Ownership records now that dependencies are gone
            $db->delete(
                $resource_owners_table,
                [
                    'type'       => Owner::TYPE_INDIVIDUAL,
                    'subject_id' => $user->get_id()
                ]
            );

            // Finally, safely delete primary user root identity.
            $db->delete( $users_table, [ 'id' => $user->get_id() ] );

            return true;
        } );
    }

    /**
     * Delete an organization and all cascading dependencies safely.
     * 
     * @param Organization $organization
     * @return bool
     */
    public static function delete_organization( Organization $organization ) : bool {
        return smliser_db()->transactional( function ( Database $db ) use ( $organization ) {
            $org_table              = SMLISER_ORGANIZATIONS_TABLE;
            $org_member_table       = SMLISER_ORGANIZATION_MEMBERS_TABLE;
            $role_assignment_table  = SMLISER_ROLE_ASSIGNMENT_TABLE;
            $resource_owners_table  = SMLISER_OWNERS_TABLE;
            $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;

            $org_id = $organization->get_id();

            // Lock the parent organization record for update safety.
            $lock_sql = static::query()
                ->select( 'id' )->from( $org_table )
                ->where( 'id', '=', $org_id )->lock_for_update();
            
            $lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
            if ( ! $lock ) {
                return false;
            }

            // Delete direct simple mappings (Memberships & Specific Org Role Assignments).
            $db->delete(
                $org_member_table,
                [
                    'organization_id' => $org_id
                ]
            );

            $db->delete(
                $role_assignment_table,
                [
                    'owner_subject_type' => Owner::TYPE_ORGANIZATION,
                    'owner_subject_id'   => $org_id
                ]
            );

            // Extract the Resource Owner IDs owned by this organization.
            $owner_ids_sql = static::query()
                ->select( 'id' )->from( $resource_owners_table )
                ->where( 'subject_id', '=', $org_id )
                ->where( 'type', '=', Owner::TYPE_ORGANIZATION );
            
            $owner_ids = $db->get_col( $owner_ids_sql->build(), $owner_ids_sql->get_bindings() );

            // Trace and flush nested service accounts and their permissions.
            if ( ! empty( $owner_ids ) ) {
                // Find all Service Accounts pointing to these Resource Owners.
                $sa_ids_sql = static::query()
                    ->select( 'id' )->from( $service_accounts_table )
                    ->where_in( 'owner_id', $owner_ids );
                
                $sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

                if ( ! empty( $sa_ids ) ) {
                    // Delete roles belonging to these service accounts via fluent DeleteIntent.
                    $sa_roles_delete = static::query()
                        ->delete( $role_assignment_table )
                        ->where( 'principal_type', '=', 'service_account' )
                        ->where_in( 'principal_id', $sa_ids );
                    
                    $db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

                    // Delete the actual Service Accounts via fluent DeleteIntent.
                    $sa_purge_delete = static::query()
                        ->delete( $service_accounts_table )
                        ->where_in( 'id', $sa_ids );

                    $db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
                }
            }

            // Delete the Resource Ownership base keys now that downstream tracks are wiped.
            $db->delete(
                $resource_owners_table,
                [
                    'type'       => Owner::TYPE_ORGANIZATION,
                    'subject_id' => $org_id
                ]
            );

            $db->delete( $org_table, [ 'id' => $org_id ] );

            return true;
        } );
    }

    /**
     * Delete a service account and all its assigned roles cleanly.
     * 
     * @param ServiceAccount $service_account
     * @return bool
     */
    public static function delete_service_account( ServiceAccount $service_account ) : bool {
        return smliser_db()->transactional( function ( Database $db ) use ( $service_account ) {
            $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;
            $role_assignment_table  = SMLISER_ROLE_ASSIGNMENT_TABLE;

            $sa_id = $service_account->get_id();

            $lock_sql = static::query()
                ->select( 'id' )->from( $service_accounts_table )
                ->where( 'id', '=', $sa_id )->lock_for_update();
            
            $lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
            if ( ! $lock ) {
                return false;
            }

            // Delete roles assigned to this specific service account via fluent DeleteIntent
            $sa_roles_delete = static::query()
                ->delete( $role_assignment_table )
                ->where( 'principal_type', '=', 'service_account' )
                ->where( 'principal_id', '=', $sa_id );
            
            $db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

            // Finally, delete the parent service account record safely
            $db->delete( $service_accounts_table, [ 'id' => $sa_id ] );

            return true;
        } );
    }

    /**
     * Delete a resource owner and all cascading service accounts and roles.
     * 
     * @param Owner $owner
     * @return bool
     */
    public static function delete_resource_owner( Owner $owner ) : bool {
        $owner_id   = $owner->get_id();
        return smliser_db()->transactional( function ( Database $db ) use ( $owner_id ) {
            $resource_owners_table  = SMLISER_OWNERS_TABLE;
            $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;
            $role_assignment_table  = SMLISER_ROLE_ASSIGNMENT_TABLE;

            $lock_sql = static::query()
                ->select( 'id' )->from( $resource_owners_table )
                ->where( 'id', '=', $owner_id )->lock_for_update();
            
            $lock = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );
            if ( ! $lock ) {
                return false;
            }

            // Extract Service Account IDs linked to this owner
            $sa_ids_sql = static::query()
                ->select( 'id' )->from( $service_accounts_table )
                ->where( 'owner_id', '=', $owner_id );
            
            $sa_ids = $db->get_col( $sa_ids_sql->build(), $sa_ids_sql->get_bindings() );

            // If Service Accounts exist, wipe their roles and the accounts themselves
            if ( ! empty( $sa_ids ) ) {
                // Delete roles assigned to these specific service accounts
                $sa_roles_delete = static::query()
                    ->delete( $role_assignment_table )
                    ->where( 'principal_type', '=', 'service_account' )
                    ->where_in( 'principal_id', $sa_ids );
                
                $db->execute( $sa_roles_delete->build(), $sa_roles_delete->get_bindings() );

                // Delete the actual Service Accounts
                $sa_purge_delete = static::query()
                    ->delete( $service_accounts_table )
                    ->where_in( 'id', $sa_ids );

                $db->execute( $sa_purge_delete->build(), $sa_purge_delete->get_bindings() );
            }

            //  Finally, delete the Resource Ownership record
            $db->delete( $resource_owners_table, [ 'id' => $owner_id ] );

            return true;
        } );
    }

    /**
     * Calculate total pages.
     * 
     * @param int $total The total items.
     * @param int $limit The pagination limit.
     */
    private static function cal_total_pages( int $total, int $limit ) : int {
        return ( $limit > 0 ) ? (int) ceil( $total / $limit ) : 1;
    }

    /**
     * Make a paginated result.
     * 
     * @param mixed $data
     * @param int $page
     * @param int $total
     * @param int $limit
     * @return array{items: mixed, pagination: array{total: int, page: int, limit: int, total_pages: int}}
     */
    private static function make_paginated_result( mixed $data, int $page = 1, int $limit = 20 , int $total = 0 ) : array {
        return [
            'items'      => $data,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => static::cal_total_pages( $total, $limit ),
            ],
        ];
    }

    protected static function query() : SQLBuilder {
        return \smliserQueryBuilder();
    }

    /**
     * Get entity table
     * 
     * @param string $type
     * @return string|null
     */
    protected static function get_entity_table( string $type ) : ?string {
        return match ( $type ) {
            Owner::TYPE_ORGANIZATION        => SMLISER_ORGANIZATIONS_TABLE,
            Owner::TYPE_INDIVIDUAL, 'user'  => SMLISER_USERS_TABLE,
            default                         => null,
        };
    }

}