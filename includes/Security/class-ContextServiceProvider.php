<?php
/**
 * The security context service file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security;
 */

namespace SmartLicenseServer\Security;

use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use function defined, class_exists, parse_args_recursive;

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
     * * @param string $entity The name of the security entity.
     * - valid names are `owner`, `user`, `organization`, `service_account`, and `role`.
     * * @return class-string<Owner|Organization|User|ServiceAccount|Role>|null
     */
    public static function get_entity_classname( $entity ) {
        if ( ! is_string( $entity ) ) {
            return null;
        }

        if ( Owner::TYPE_INDIVIDUAL === $entity ) {
            $entity = 'User';
        }

        // Logic for service_account (snake_case to PascalCase)
        $formatted_entity = str_replace('_', '', ucwords($entity, '_') );
        $class_name = '\\SmartLicenseServer\\Security\\' . $formatted_entity;

        if ( ! class_exists( $class_name, true ) ) {
            return null;
        }

        return $class_name;
    }

}