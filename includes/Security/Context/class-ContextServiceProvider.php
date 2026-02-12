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
use function defined, class_exists, parse_args_recursive, smliser_dbclass, strtolower, gmdate, method_exists,
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

        // Fetch Items: Merge subqueries and re-sort the final slice.
        $union_sql = implode( " UNION ALL ", $sql_parts );
        $final_sql = "{$union_sql} ORDER BY `updated_at` DESC LIMIT ? OFFSET 0";
        $rows      = $db->get_results( $final_sql, array_merge( $params_sql, [ $limit ] ) );

        // Aggregate Count.
        $count_sql = "SELECT SUM(total) FROM (" . implode( " UNION ALL ", $count_parts ) . ") AS counts";
        $total     = (int) $db->get_var( $count_sql, $params_count );
        
        // Instantiate Objects.
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
        $rows = $db->get_results( $sql, array_merge( $params, [ $limit, $offset ] ) );

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
     * 
     * @param string $entity The name of the security entity.
     * - valid names are `owner`, `user`, `individual`, `organization`, `service_account`, and `role`.
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
        $subject_id     = $subject ? $subject->get_id() : $actor->get_id();
        
        $data   = array(
            'role_id'               => $role->get_id(),
            'principal_id'          => $actor->get_id(),
            'principal_type'        => $actor->get_type(),
            'owner_subject_type'    => $subject_type,
            'owner_subject_id'      => $subject_id,
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
        
        $exists_sql    = 
        "SELECT `role_id` FROM `{$table}` WHERE `principal_id` = ? AND `principal_type` = ? 
        AND `owner_subject_type` = ? AND `owner_subject_id` = ? LIMIT 1";

        $role_id    = (int) $db->get_var( 
            $exists_sql,
            [ $actor->get_id(), $actor->get_type(), $subject_type, $subject_id ]
        );

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

        } else {
            $data['created_at']   = gmdate( 'Y-m-d H:i:s' );
            
            $result = $db->insert( $table, $data );
        }

        static::cache_clear();
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
        $db             = smliser_dbclass();
        $table          = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $principal_type = $actor->get_type();
        $principal_id   = $actor->get_id();
        
        $subject_type = $subject ? $subject->get_type() : Owner::TYPE_INDIVIDUAL;
        $sbj_owner_id       = $subject ? $subject->get_id() : $actor->get_id();

        $sql    = 
        "SELECT `role_id` FROM `{$table}` WHERE `principal_id` = ? AND `principal_type` = ? 
        AND `owner_subject_type` = ? AND `owner_subject_id` = ? LIMIT 1";

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
     * @return null|\SmartLicenseServer\Security\OwnerSubjects\OwnerSubjectInterface
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
        static::cache_clear();
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
        static::cache_clear();
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

        $db                     = smliser_dbclass();
        $users_table            = SMLISER_USERS_TABLE;
        $org_table              = SMLISER_ORGANIZATIONS_TABLE;
        $org_member_table       = SMLISER_ORGANIZATION_MEMBERS_TABLE;
        $service_accounts_table = SMLISER_SERVICE_ACCOUNTS_TABLE;
        $role_assignment_table  = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $resource_owners_table  = SMLISER_OWNERS_TABLE;

        try {
            $db->begin_transaction();

            switch ( true ) {
                case $entity instanceof User:
                    // Delete user + related individual role assignments, organization memberships,
                    // resource ownership and owned service accounts + role assignment in a single transaction.
                    $sql = "DELETE u, ra, om, ro, sa, rsa
                        FROM `{$users_table}` AS u
                        LEFT JOIN `{$role_assignment_table}` AS ra
                            ON ra.principal_type = 'individual' AND ra.principal_id = u.id
                        LEFT JOIN `{$org_member_table}` AS om
                            ON om.member_id = u.id
                        LEFT JOIN `{$resource_owners_table}` AS ro
                            ON ro.type = 'individual' AND ro.subject_id = u.id
                        LEFT JOIN `{$service_accounts_table}` AS sa
                            ON sa.owner_id = ro.id
                        LEFT JOIN `{$role_assignment_table}` AS rsa
                            ON rsa.principal_type = 'service_account' AND rsa.principal_id = sa.id
                        WHERE u.id = ?
                    ";
                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
                    break;

                case $entity instanceof Organization:
                    // Delete organization, its members, direct role assignments,
                    // organization ownership records, owned service accounts,
                    // and roles assigned to those service accounts – all in one query.
                    $sql = "DELETE o, om, ra, ro, sa, rsa
                        FROM `{$org_table}` AS o

                        LEFT JOIN `{$org_member_table}` AS om
                            ON om.organization_id = o.id

                        LEFT JOIN `{$role_assignment_table}` AS ra
                            ON ra.owner_subject_type = 'organization'
                            AND ra.owner_subject_id = o.id

                        LEFT JOIN `{$resource_owners_table}` AS ro
                            ON ro.type = 'organization'
                            AND ro.subject_id = o.id

                        LEFT JOIN `{$service_accounts_table}` AS sa
                            ON sa.owner_id = ro.id

                        LEFT JOIN `{$role_assignment_table}` AS rsa
                            ON rsa.principal_type = 'service_account'
                            AND rsa.principal_id = sa.id

                        WHERE o.id = ?
                    ";

                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
                    break;

                case $entity instanceof ServiceAccount:
                    // Delete service account and all roles assigned to it.
                    $sql = "DELETE sa, ra
                        FROM `{$service_accounts_table}` AS sa
                        LEFT JOIN `{$role_assignment_table}` AS ra
                            ON ra.principal_type = 'service_account'
                            AND ra.principal_id = sa.id
                        WHERE sa.id = ?
                    ";

                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
                    break;

                case $entity instanceof Owner:
                    // Delete owner, all owned service accounts, and their role assignments.
                    $sql = "DELETE ro, sa, ra
                        FROM `{$resource_owners_table}` AS ro

                        LEFT JOIN `{$service_accounts_table}` AS sa
                            ON sa.owner_id = ro.id

                        LEFT JOIN `{$role_assignment_table}` AS ra
                            ON ra.principal_type = 'service_account'
                            AND ra.principal_id = sa.id

                        WHERE ro.id = ?
                    ";

                    $deleted = $db->query( $sql, [ $entity->get_id() ] );
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
            static::cache_clear();
        } catch ( \Exception $e ) {
            $db->rollback();
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

        $db                     = smliser_dbclass();
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

            static::cache_set( $cache_key, $report, 4 * HOUR_IN_SECONDS );
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

}