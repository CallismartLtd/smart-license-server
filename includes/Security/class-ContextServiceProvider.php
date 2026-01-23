<?php
/**
 * The security context service file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security;
 */

namespace SmartLicenseServer\Security;

use InvalidArgumentException;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use const SMLISER_ROLE_ASSIGNMENT_TABLE;
use function defined, class_exists, parse_args_recursive, smliser_dbclass;

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
            'term'      => '',
            'page'      => 1,
            'limit'     => 20,
            'status'    => User::STATUS_ACTIVE,
            'types'     => Owner::get_allowed_owner_types()
        );

        $args       = parse_args_recursive( $args, $defaults );
        $term       = self::sanitize_text( $args['term'] );
        $page       = max( 1, (int) $args['page'] );
        $limit      = max( 1, (int) $args['limit'] );
        $offset     = $db->calculate_query_offset( $page, $limit );
        $status     = $args['status'];
        $types      = array_filter( (array) $args['types'] );
        $key        = self::make_cache_key( __METHOD__, \compact( 'term', 'page', 'limit', 'status', 'types' ) );

        $results    = false; //self::cache_get( $key );

        if ( false === $results ) {
            
            if ( empty( $term ) || empty( $types ) ) {
                $results    = array(
                    'items'      => array(),
                    'pagination' => array(
                        'page'        => $page,
                        'limit'       => $limit,
                        'total'       => 0,
                        'total_pages' => 0,
                    ),
                );
                
            } else {
                $like        = '%' . $term . '%';
                $sql_parts   = [];
                $count_parts = [];
                $params_sql  = [];
                $params_count = [];

                foreach ( $types as $type ) {
                    switch ( $type ) {
                        case Owner::TYPE_ORGANIZATION:
                            $table = SMLISER_ORGANIZATIONS_TABLE;
                            break;
                        case Owner::TYPE_INDIVIDUAL:
                            $table = SMLISER_USERS_TABLE;
                            break;
                        default:
                            continue 2;
                    }
                
                    $name   = match( $type ) {
                        default                 => 'name',
                        Owner::TYPE_INDIVIDUAL  => 'display_name'
                    };
                    // Query for fetching IDs
                    $sql_parts[]    = "SELECT id, '{$type}' AS type, `updated_at` FROM `{$table}` WHERE status = ? AND ( `{$name}` LIKE ? OR `id` LIKE ?  )";
                    $params_sql     = array_merge( $params_sql, [ $status, $like, $like ] );

                    // Query for counting matches
                    $count_parts[]  = "SELECT COUNT(*) AS total FROM {$table} WHERE status = ? AND ( `{$name}` LIKE ? OR `id` LIKE ? )";
                    $params_count   = array_merge( $params_count, [ $status, $like, $like ] );
                }

                // Build union query
                $union_sql = implode( " UNION ALL ", $sql_parts );
                $query_sql = "{$union_sql} ORDER BY `updated_at` DESC LIMIT ? OFFSET ?";
                $params_sql = array_merge( $params_sql, [ $limit, $offset ] );
                
                // Fetch rows via adapter
                $rows = $db->get_results( "SELECT * FROM ( {$query_sql} ) AS entities", $params_sql, ARRAY_A );

                // Aggregate count
                $count_sql = "SELECT SUM(total) FROM (" . implode( " UNION ALL ", $count_parts ) . ") AS counts";
                $total = (int) $db->get_var( $count_sql, $params_count );

                // Instantiate app objects
                $objects = [];
                foreach ( $rows as $row ) {
                    $class  = (string) self::get_entity_classname( $row['type'] );
                    $method = "get_by_id";

                    if ( method_exists( $class, $method ) ) {
                        $objects[] = $class::$method( (int) $row['id'] );
                    }
                }

                $results    = array(
                    'items'      => $objects,
                    'pagination' => array(
                        'page'        => $page,
                        'limit'       => $limit,
                        'total'       => $total,
                        'total_pages' => $limit > 0 ? ceil( $total / $limit ) : 0,
                    ),
                );                
            }


            // self::cache_set( $key, $results, 30 * \MINUTE_IN_SECONDS );
        }

        return $results;
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

        if ( Owner::TYPE_INDIVIDUAL === $entity ) {
            $entity = 'User';
        }

        $formatted_entity = str_replace('_', '', ucwords($entity, '_') );
        $class_name = __NAMESPACE__. '\\' . $formatted_entity;

        if ( ! class_exists( $class_name, true ) ) {
            return null;
        }

        return $class_name;
    }

    /**
     * Saves the role of a rosource owner.
     * 
     * @param Owner $owner
     * @param Role $role
     * @param User|Organization $owner_principal
     * @throws InvalidArgumentException When one required field is missing.
     */
    public static function save_resource_owner_role( Owner $owner, Role $role, User|Organization $owner_principal ) : bool {
        $db             = smliser_dbclass();
        $table          = SMLISER_ROLE_ASSIGNMENT_TABLE;
        $principal_type = ( $owner_principal instanceof User ) ? Owner::TYPE_INDIVIDUAL : Owner::TYPE_ORGANIZATION;
        
        $data   = array(
            'role_id'           => $role->get_id(),
            'principal_id'      => $owner_principal->get_id(),
            'principal_type'    => $principal_type,
            'owner_type'        => $owner->get_type(),
            'owner_id'          => $owner->get_id(),
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

        $existing_role  = self::get_principal_role( $owner, $owner_principal );

        if ( $existing_role && $role->get_id() !== $existing_role->get_id() ) {
            // Only the role assigned to this owner changes.
            // Existing owner and principal data remains immutable.
            $where = [
                'principal_id'   => $owner_principal->get_id(),
                'principal_type' => $principal_type,
                'owner_type'     => $owner->get_type(),
                'owner_id'       => $owner->get_id(),
            ];

            $result = $db->update( $table, ['role_id' => $role->get_id()], $where );
            
        } else {
            $data['created_at']   = gmdate( 'Y-m-d H:i:s' );
            
            $result = $db->insert( $table, $data );
            
        }

        return false !== $result;
    }

    /**
     * Get principal role.
     * 
     * @param Owner $owner The resource owner object.
     * @param User|Organization $owner_principal
     * @return Role|null
     */
    public static function get_principal_role( Owner $owner, User|Organization $owner_principal ) : ?Role {
        $db     = smliser_dbclass();
        $table  = SMLISER_ROLE_ASSIGNMENT_TABLE;

        $principal_type = ( $owner_principal instanceof User ) ? Owner::TYPE_INDIVIDUAL : Owner::TYPE_ORGANIZATION;
        $principal_id   = $owner_principal->get_id();
        $owner_type     = $owner->get_type();
        $owner_id       = $owner->get_id();

        $sql    = 
        "SELECT `role_id` FROM `{$table}` WHERE `principal_id` = ? AND `principal_type` = ? 
        AND `owner_type` = ? AND `owner_id` = ?";

        $role_id    = $db->get_var( $sql, [ $principal_id, $principal_type, $owner_type, $owner_id ] );

        $role       = null;

        if ( $role_id ) {
            $role = Role::get_by_id( (int) $role_id );
        }

        return $role;
    }

}