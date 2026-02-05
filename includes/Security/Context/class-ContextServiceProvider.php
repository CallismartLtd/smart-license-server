<?php
/**
 * The security context service file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security;
 */

namespace SmartLicenseServer\Security\Context;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\SecurityException;
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
use function defined, class_exists, parse_args_recursive, smliser_dbclass, strtolower, gmdate,
sprintf, class_implements, in_array;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The security context service provider holds the context of authenticated actor/principal
 * and provides unified methods to access security entities.
 */
class ContextServiceProvider {
    use CacheAwareTrait, SanitizeAwareTrait;

    /**
     * Search security entities across multiple db tables with pagination.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type string $term   Search term to match against name, slug. Required.
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Entity status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type array $items      Instantiated entity objects.
     *     @type array $pagination Pagination info (page, limit, total, total_pages).
     * }
     */
    public static function search( array $args = array() ) {
        $db = smliser_dbclass();

        $defaults = array(
            'term'   => '',
            'page'   => 1,
            'limit'  => 20,
            'types'  => Owner::get_allowed_owner_types()
        );

        $args   = parse_args( $args, $defaults );
        $term   = self::sanitize_text( $args['term'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );
        $types  = array_filter( (array) $args['types'] );

        if ( empty( $term ) || empty( $types ) ) {
            return [
                'items'      => [],
                'pagination' => [ 'page' => $page, 'limit' => $limit, 'total' => 0, 'total_pages' => 0 ]
            ];
        }

        $like         = '%' . $term . '%';
        $sql_parts    = [];
        $count_parts  = [];
        $params_sql   = [];
        $params_count = [];

        foreach ( $types as $type ) {
            $table = match ( $type ) {
                Owner::TYPE_ORGANIZATION => SMLISER_ORGANIZATIONS_TABLE,
                Owner::TYPE_INDIVIDUAL   => SMLISER_USERS_TABLE,
                default                  => null
            };

            if ( ! $table ) continue;
            $sql_parts[]   = "( SELECT `id`, '{$type}' AS type, `updated_at` 
                            FROM `{$table}` 
                            WHERE `display_name` LIKE ? 
                            ORDER BY `updated_at` DESC 
                            LIMIT {$limit} OFFSET {$offset} )";
            $params_sql[]  = $like;

            $count_parts[] = "SELECT COUNT(*) AS total FROM `{$table}` WHERE `display_name` LIKE ?";
            $params_count[] = $like;
        }

        // 1. Fetch Items: Merge subqueries and re-sort the final slice
        $union_sql = implode( " UNION ALL ", $sql_parts );
        $final_sql = "{$union_sql} ORDER BY `updated_at` DESC LIMIT ? OFFSET 0";
        $rows      = $db->get_results( $final_sql, array_merge( $params_sql, [ $limit ] ), ARRAY_A );

        // 2. Aggregate Count
        $count_sql = "SELECT SUM(total) FROM (" . implode( " UNION ALL ", $count_parts ) . ") AS counts";
        $total     = (int) $db->get_var( $count_sql, $params_count );
        
        // 3. Instantiate Objects
        $objects = [];
        foreach ( $rows as $row ) {
            $class  = self::get_entity_classname( $row['type'] );
            if ( $class && method_exists( $class, 'get_by_id' ) ) {
                $objects[] = $class::get_by_id( (int) $row['id'] );
            }
        }

        return array(
            'items'      => $objects,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $limit > 0 ? ceil( $total / $limit ) : 0,
            ),
        );
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
     * @return array {
     *      @type Owner[] $items An array of owner objects.
     *      @type array $pagination Pagination info (page, limit, total, total_pages).
     * }
     */
    public static function search_owners( array $args = [] ) : array {
        $db = smliser_dbclass();
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

        $where_clauses = [];
        $params        = [];

        // Focus strictly on display_name and type (logical search)
        if ( ! empty( $term ) ) {
            $like = '%' . $term . '%';
            $where_clauses[] = "( `name` LIKE ? OR `type` LIKE ? )";
            $params = array_merge( $params, [ $like, $like ] );
        }

        $where_sql = ! empty( $where_clauses ) ? ' WHERE ' . implode( ' AND ', $where_clauses ) : '';
        $sql  = "SELECT `id` FROM `{$table}` {$where_sql} ORDER BY `updated_at` DESC LIMIT ? OFFSET ?";
        $rows = $db->get_results( $sql, array_merge( $params, [ $limit, $offset ] ), ARRAY_A );

        // Get total count
        $total = (int) $db->get_var( "SELECT COUNT(`id`) FROM `{$table}` {$where_sql}", $params );

        $owners = [];
        if ( ! empty( $rows ) ) {
            foreach ( $rows as $row ) {
                $owner = Owner::get_by_id( (int) $row['id'] );
                if ( $owner instanceof Owner ) {
                    $owners[] = $owner;
                }
            }
        }

        return [
            'items'      => $owners,
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $limit > 0 ? ceil( $total / $limit ) : 0,
            ],
        ];
    }

    /**
     * Get a security entity class name.
     * @param string $entity The name of the security entity.
     * - valid names are `owner`, `user`, `organization`, `service_account`, and `role`.
     * @return class-string<Owner|Organization|User|ServiceAccount|Role>|null
     */
    public static function get_entity_classname( $entity ) {
        if ( ! is_string( $entity ) ) {
            return null;
        }

        $entity = str_replace( '_', '', ucwords( $entity, '_' ) );

        $class_name = match( strtolower( $entity ) ) {
            Owner::TYPE_INDIVIDUAL, 'user'  => 'Actors\\User',
            Owner::TYPE_ORGANIZATION        => 'OwnerSubjects\\Organization',
            'serviceaccount'                => 'Actors\\ServiceAccount',
            'owner'                         => 'Owner',
            default                         => ''
        };

        $class_name = '\\SmartLicenseServer\\Security\\' . $class_name;

        if ( ! class_exists( $class_name, true ) ) {
            return null;
        }

        return $class_name;
    }

    /**
     * Saves the role of an actor.
     * 
     * @param ActorInterface $actor The actor that can authenticate.
     * @param Role $role
     * @param OwnerSubjectInterface|null $org The subject entity associated with resource owner.
     * @throws InvalidArgumentException When one required field is missing.
     * @throws SecurityException When there is no valid resource owner in context.
     */
    public static function save_actor_role( ActorInterface $actor, Role $role, ?OwnerSubjectInterface $subject ) : bool {
        $db             = smliser_dbclass();
        $table          = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $subject_type   = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
        
        $data   = array(
            'role_id'               => $role->get_id(),
            'principal_id'          => $actor->get_id(),
            'principal_type'        => $actor->get_type(),
            'owner_subject_type'    => $subject_type,
            'owner_subject_id'      => $subject ? $subject->get_id() : $actor->get_id(),
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

        $existing_role  = self::get_principal_role( $actor, $subject );

        if ( $existing_role ) {
            if ( $role->get_id() !== $existing_role->get_id() ) {
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

        } else {
            $data['created_at']   = gmdate( 'Y-m-d H:i:s' );
            
            $result = $db->insert( $table, $data );
        }

        return false !== $result;
    }

    /**
     * Delete an actors' assignedrole from the assignment table.
     * 
     * @param ActorInterface $actor
     * @param OwnerSubjectInterface|null $subject
     * @throws SecurityException On failure.
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
        $db     = smliser_dbclass();
        $subject_type   = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;

        $deleted    = $db->delete( $table,[
            'principal_id'          => $actor->get_id(),
            'principal_type'        => $actor->get_type(),
            'owner_subject_type'    => $subject_type,
            'owner_subject_id'      => $subject ? $subject->get_id() : $actor->get_id(),
        ]);

        if ( ! $deleted ) {
            throw new SecurityException( 
                'delete_error', 
                'Unable to delete the role of this actor.',
                ['status' => 500]
            );
        }
    }

    /**
     * Get the role assigned to an actor/principal.
     * 
     * @param ActorInterface $actor The authenticatable actor.
     * @param OwnerSubjectInterface|null $subject The owner subject (organization or user).
     * @return Role|null
     */
    public static function get_principal_role( ActorInterface $actor, ?OwnerSubjectInterface $subject = null ) : ?Role {
        $db             = smliser_dbclass();
        $table          = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $principal_type = $actor->get_type();
        $principal_id   = $actor->get_id();
        
        $subject_type = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
        $sbj_owner_id       = $subject ? $subject->get_id() : $actor->get_id();

        $sql    = 
        "SELECT `role_id` FROM `{$table}` WHERE `principal_id` = ? AND `principal_type` = ? 
        AND `owner_subject_type` = ? AND `owner_subject_id` = ?";

        $role_id    = $db->get_var( $sql, [ $principal_id, $principal_type, $subject_type, $sbj_owner_id ] );

        $role       = null;

        if ( $role_id ) {
            $role = Role::get_by_id( (int) $role_id );
        }

        return $role;
    }

    /**
     * Get either the organization or the individual user associated with a
     * resource owner object.
     * 
     * @param Owner $owner
     */
    public static function get_owner_subject( Owner $owner ) : ?OwnerSubjectInterface {

        return match( $owner->get_type() ) {
            Owner::TYPE_ORGANIZATION    => Organization::get_by_id( $owner->get_id() ),
            Owner::TYPE_INDIVIDUAL      => User::get_by_id( $owner->get_id() ),
            default                     => null
        };
    }

    /**
     * Save a single member of an organization with their role.
     * 
     * @param OrganizationMember $member
     * @param Organization $organization
     * @param Role $role
     * @throws Exception|InvalidArgumentException
     */
    public static function save_organization_member( OrganizationMember $member, Organization $organization, Role $role ) {

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

        $db     = smliser_dbclass();
        $table  = SMLISER_ORGANIZATION_MEMBERS_TABLE;
        $now    = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

        $id     = $db->get_var(
            "SELECT `id` FROM {$table} WHERE `organization_id` = ? AND `member_id` = ?",
            [$organization->get_id(), $member->get_user()->get_id()]
        );

        if ( $id ) {
            $data   = ['updated_at' => $now->format( 'Y-m-d H:i:s' ) ];
           $db->update( $table, $data, ['id' => $id] ); 
        } else {
            $data   = [
                'organization_id'   => $organization->get_id(),
                'member_id'         => $member->get_user()->get_id(),
                'created_at'        => $now->format( 'Y-m-d H:i:s' ),
                'updated_at'        => $now->format( 'Y-m-d H:i:s' )
            ];

            $db->insert( $table, $data );

            if ( ! $db->get_insert_id() ) {
                throw new Exception( 'saving_error', 'Unable to save member' );
            }
        }
        
        static::save_actor_role( $member->get_user(), $role, $organization );
    }

    /**
     * Get organization members with their roles
     *
     * @param Organization $organization
     * @return OrganizationMembers
     */
    public static function get_organization_members( Organization $organization ): OrganizationMembers {
        $table   = SMLISER_ORGANIZATION_MEMBERS_TABLE;
        $db      = smliser_dbclass();

        $sql      = "SELECT * FROM `{$table}` WHERE `organization_id` = ?";
        $results  = $db->get_results( $sql, [$organization->get_id() ]);
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
        $db     = smliser_dbclass();

        $sql    = "SELECT `organization_id` FROM `{$table}` WHERE `member_id` = ?";
        $results    = $db->get_col( $sql, [$user->get_id()] );

        $organizations = array();

        foreach ( $results as $result ) {
            $organization[] = Organization::get_by_id( $result['id'] ?? 0 );
        }

        return empty( $organizations ) ? null : $organizations;
    }

    /**
     * Deletes an organization member and their roles
     * 
     * @param OrganizationMember $member
     * @param Organization $organization
     */
    public static function delete_organization_member( OrganizationMember $member, Organization $organization ) {
        if ( ! $organization->is_member( $member ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    '%s does not belong to this organization "%s"',
                    $member->get_display_name(),
                    $organization->get_display_name()
                )
            );
        }
        $db     = smliser_dbclass();
        $table  = SMLISER_ORGANIZATION_MEMBERS_TABLE;

        $deleted    = $db->delete( $table, [
            'id'                => $member->get_id(),
            'member_id'         => $member->get_user()->get_id(),
            'organization_id'   => $organization->get_id()
        ]);

        if ( ! $deleted ) {
            throw new SecurityException( 'delete_error', 'Unable to delete member', ['status' => 500] );
        }

        static::delete_actor_role( $member, $organization );
    }

    /**
     * Delete a security entity and all its related data in a single query where possible.
     *
     * @param User|Organization|ServiceAccount|Owner $entity
     * @return void
     * @throws SecurityException On failed or partial delete.
     */
    public static function delete_entity( User|Organization|ServiceAccount|Owner $entity ) : void {
        $interfaces = class_implements( $entity );
        $can_delete = ( $entity instanceof Owner )
            || in_array( ActorInterface::class, $interfaces, true )
            || in_array( OwnerSubjectInterface::class, $interfaces, true );

        if ( ! $can_delete ) {
            throw new SecurityException(
                'delete_error',
                'The provided entity cannot be deleted.',
                ['status' => 404]
            );
        }

        $db = smliser_dbclass();

        try {
            $db->begin_transaction();

            switch ( true ) {
                case $entity instanceof User:
                    // Delete user and related role assignments + memberships in a single transaction
                    $sql = "
                        DELETE u, ra, om
                        FROM `" . SMLISER_USERS_TABLE . "` AS u
                        LEFT JOIN `" . SMLISER_ROLE_ASSIGNMENT_TABLE . "` AS ra
                            ON ra.principal_type = 'individual' AND ra.principal_id = u.id
                        LEFT JOIN `" . SMLISER_ORGANIZATION_MEMBERS_TABLE . "` AS om
                            ON om.member_id = u.id
                        WHERE u.id = ?
                    ";
                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
                    break;

                case $entity instanceof Organization:
                    // Delete organization, its members, and role assignments in one query
                    $sql = "
                        DELETE o, om, ra
                        FROM `" . SMLISER_ORGANIZATIONS_TABLE . "` AS o
                        LEFT JOIN `" . SMLISER_ORGANIZATION_MEMBERS_TABLE . "` AS om
                            ON om.organization_id = o.id
                        LEFT JOIN `" . SMLISER_ROLE_ASSIGNMENT_TABLE . "` AS ra
                            ON ra.owner_subject_type = 'organization' AND ra.owner_subject_id = o.id
                        WHERE o.id = ?
                    ";
                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
                    break;

                case $entity instanceof ServiceAccount:
                    // Delete service account and its role assignment
                    $owner   = $entity->get_owner();
                    $subject = $owner ? static::get_owner_subject( $owner ) : null;

                    static::delete_actor_role( $entity, $subject );

                    $deleted = $db->delete( SMLISER_SERVICE_ACCOUNTS_TABLE, ['id' => $entity->get_id()] );
                    break;

                case $entity instanceof Owner:
                    // Delete from owners table
                    $deleted = $db->delete( SMLISER_OWNERS_TABLE, ['id' => $entity->get_id()] );
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

            $db->commit();
        } catch ( \Exception $e ) {
            $db->rollback();
            throw new SecurityException(
                'delete_error',
                'Failed to delete entity. Transaction rolled back.',
                ['status' => 500, 'error' => $e->getMessage()]
            );
        }
    }

}