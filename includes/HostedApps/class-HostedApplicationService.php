<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\HostedApps\AbstractHostedApp;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The core API class for perform CRUD operations on hosted applications.
 * 
 * This class provides all methods required to manage hosted applications and their data, 
 * it also supports caching, pagination, searching, asset management, and bulk operations via the request API.
 */
class HostedApplicationService {
    use CacheAwareTrait;
    /**
     * Allowed app types
     * 
     * @var array $allowed_app_types
     */
    protected static $allowed_app_types = [ 'plugin', 'theme', 'software' ];

    /**
     * Get hosted applications across multiple types with pagination.
     *
     * @param array $args {
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type SmartLicenseServer\HostedApps\AbstractHostedApp[] $items        Instantiated application objects.
     *     @type array $pagination {
     *         @type int $total       Total number of matching items.
     *         @type int $page        Current page number.
     *         @type int $limit       Number of items per page.
     *         @type int $total_pages Total number of pages.
     *     }
     * }
     */
    public static function get_apps( array $args = array() ) {
        $db = smliser_dbclass();

        $defaults = array(
            'page'   => 1,
            'limit'  => 20,
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => self::$allowed_app_types,
        );
        $args = parse_args( $args, $defaults );

        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );
        $status = $args['status'];
        $types  = (array) $args['types'];

        $key        = self::make_cache_key( __METHOD__, \compact( 'page', 'limit', 'status', 'types' ) );

        $results    = self::cache_get( $key );

        if ( false === $results ) {
            $sql_parts = [];
            $params    = [];

            if ( in_array( 'plugin', $types, true ) ) {
                $table_name     = SMLISER_PLUGINS_TABLE;
                $sql_parts[]    = "SELECT id, 'plugin' AS type, updated_at FROM {$table_name} WHERE status = ?";
                $params[]       = $status;
                
            }

            if ( in_array( 'theme', $types, true ) ) {
                $table_name = SMLISER_THEMES_TABLE;
                $sql_parts[] = "SELECT id, 'theme' AS type, updated_at FROM {$table_name} WHERE status = ?";
                $params[] = $status;
                
            }

            if ( in_array( 'software', $types, true ) ) {
                $table_name     = SMLISER_SOFTWARE_TABLE;
                $sql_parts[]    = "SELECT id, 'software' AS type, updated_at as updated_at FROM {$table_name} WHERE status = ?";
                $params[]       = $status;
                
            }

            if ( empty( $sql_parts ) ) {
                $results    = array(
                    'items'      => array(),
                    'pagination' => array(
                        'total'       => 0,
                        'page'        => $page,
                        'limit'       => $limit,
                        'total_pages' => 0,
                    ),
                );
            } else {
                // Build union
                $union_sql = implode( " UNION ALL ", $sql_parts );

                // Total count
                $count_sql = "SELECT COUNT(*) FROM ( {$union_sql} ) AS apps_count ";
                $total     = (int) $db->get_var( $count_sql, $params );

                // Fetch paginated rows
                $sql            = "SELECT * FROM ( {$union_sql} ) AS apps ORDER BY updated_at DESC LIMIT ? OFFSET ?";
                $query_params   = array_merge( $params, [ $limit, $offset ] );

                $rows    = $db->get_results( $sql, $query_params );
                $objects = array();

                foreach ( $rows as $row ) {
                    $class  = self::get_app_class( $row['type'] );
                    $method = "get_" . $row['type'];

                    if ( method_exists( $class, $method ) ) {
                        $objects[] = $class::$method( (int) $row['id'] );
                    }
                }

                $results    = array(
                    'items'      => $objects,
                    'pagination' => array(
                        'total'       => $total,
                        'page'        => $page,
                        'limit'       => $limit,
                        'total_pages' => ( $limit > 0 ) ? ceil( $total / $limit ) : 1,
                    ),
                );                
            }

            self::cache_set( $key, $results, 30 * \MINUTE_IN_SECONDS );
        }

        return $results;
    }
        
    /**
     * Get hosted applications across multiple types with balanced pagination.
     *
     * Each type is given an approximately equal share of the total limit per page.
     *
     * @param array $args {
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Total number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type array $items        Instantiated application objects.
     *     @type array $pagination {
     *         @type int $total       Total number of matching items (all types combined).
     *         @type int $page        Current page number.
     *         @type int $limit       Total number of items per page.
     *         @type int $total_pages Total number of pages.
     *     }
     * }
     */
    public static function get_apps_balanced( array $args = [] ): array {
        $db = smliser_dbclass();

        $defaults = [
            'page'   => 1,
            'limit'  => 20,
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => ['plugin', 'theme', 'software'],
        ];
        $args = parse_args( $args, $defaults );

        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $status = $args['status'];
        $types  = array_values( (array) $args['types'] );

        $type_tables = [
            'plugin'   => SMLISER_PLUGINS_TABLE,
            'theme'    => SMLISER_THEMES_TABLE,
            'software' => SMLISER_SOFTWARE_TABLE,
        ];

        $objects = [];
        $total   = 0;

        $per_type_limit = (int) ceil( $limit / count( $types ) );

        foreach ( $types as $type ) {
            if ( ! isset( $type_tables[ $type ] ) ) {
                continue;
            }

            $table_name = $type_tables[ $type ];

            // Count total items for this type
            $count_sql   = "SELECT COUNT(*) FROM {$table_name} WHERE status = ?";
            $type_total  = (int) $db->get_var( $count_sql, [$status] );
            $total      += $type_total;

            // Calculate offset per type
            $offset = ( $page - 1 ) * $per_type_limit;

            // Fetch paginated rows for this type
            $select_sql = "SELECT id FROM {$table_name} 
                        WHERE status = ? 
                        ORDER BY updated_at DESC 
                        LIMIT ? OFFSET ?";
            $rows = $db->get_results( $select_sql, [$status, $per_type_limit, $offset] );

            foreach ( $rows as $row ) {
                $class  = self::get_app_class( $type );
                $method = "get_" . $type;

                if ( method_exists( $class, $method ) ) {
                    $objects[] = $class::$method( (int) $row['id'] );
                }
            }
        }

        // Ensure total limit is not exceeded
        $objects = array_slice( $objects, 0, $limit );

        return [
            'items'      => $objects,
            'pagination' => [
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ( $limit > 0 ) ? ceil( $total / $limit ) : 1,
            ],
        ];
    }

    /**
     * Get plugins from the repository.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     * }
     * @return array Array containing 'items' (plugin objects) and 'pagination' info.
     */
    public static function get_plugins( array $args = array() ) {
        $args['types']  = array( 'plugin' );
        return self::get_apps( $args );
    }

    /**
     * Get themes from the repository.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     * }
     * @return array Array containing 'items' (theme objects) and 'pagination' info.
     */
    public static function get_themes( array $args = array() ) {
        $args['types']  = array( 'theme' );
        return self::get_apps( $args );
    }

    /**
     * Get trashed applications across multiple types with pagination.
     *
     * @param array $args {
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type SmartLicenseServer\HostedApps\AbstractHostedApp[] $items        Instantiated application objects.
     *     @type array $pagination {
     *         @type int $total       Total number of matching items.
     *         @type int $page        Current page number.
     *         @type int $limit       Number of items per page.
     *         @type int $total_pages Total number of pages.
     *     }
     * }
     */
    public static function get_trashed_apps( array $args = array() ) {
        $args['status'] = AbstractHostedApp::STATUS_TRASH;
        return self::get_apps( $args );
    }

    /**
     * Get software applications from the repository.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     * }
     * @return array Array containing 'items' (software objects) and 'pagination' info.
     */
    public static function get_software( array $args = array() ) {
        $args['types']  = array( 'software' );
        return self::get_apps( $args );
    }

    /**
     * Search hosted applications across multiple types with pagination.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type string $term   Search term to match against name, slug, or author. Default empty.
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type array $items      Instantiated application objects (minimal/core fields only).
     *     @type array $pagination Pagination info (page, limit, total, total_pages).
     * }
     */
    public static function search_apps( array $args = array() ) {
        $db = smliser_dbclass();

        $defaults = array(
            'term'   => '',
            'page'   => 1,
            'limit'  => 20,
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => self::$allowed_app_types,
        );
        $args   = parse_args( $args, $defaults );
        $term   = sanitize_text_field( $args['term'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = $db->calculate_query_offset( $page, $limit );
        $status = $args['status'];
        $types  = array_filter( (array) $args['types'] );
        $key    = self::make_cache_key( __METHOD__, compact( 'term', 'page', 'limit', 'status', 'types' ) );

        $results = self::cache_get( $key );

        if ( false === $results ) {

            if ( empty( $term ) || empty( $types ) ) {
                $results = array(
                    'items' => array(),
                    'pagination' => array(
                        'page'        => $page,
                        'limit'       => $limit,
                        'total'       => 0,
                        'total_pages' => 0,
                    ),
                );
            } else {
                $like         = '%' . $term . '%';
                $sql_parts    = [];
                $count_parts  = [];
                $params_sql   = [];
                $params_count = [];

                foreach ( $types as $type ) {
                    switch ( $type ) {
                        case 'plugin':
                            $table = SMLISER_PLUGINS_TABLE;
                            break;
                        case 'theme':
                            $table = SMLISER_THEMES_TABLE;
                            break;
                        case 'software':
                            $table = SMLISER_SOFTWARE_TABLE;
                            break;
                        default:
                            continue 2;
                    }

                    // Query for fetching core fields
                    $sql_parts[] = "SELECT id, name, slug, author, status, download_link, created_at, updated_at, '{$type}' AS type
                                    FROM {$table} 
                                    WHERE status = ? AND ( name LIKE ? OR slug LIKE ? OR author LIKE ? )";
                    $params_sql  = array_merge( $params_sql, [ $status, $like, $like, $like ] );

                    // Query for counting matches
                    $count_parts[]  = "SELECT COUNT(*) AS total FROM {$table} WHERE status = ? AND ( name LIKE ? OR slug LIKE ? OR author LIKE ? )";
                    $params_count   = array_merge( $params_count, [ $status, $like, $like, $like ] );
                }

                // Build union query
                $union_sql  = implode( " UNION ALL ", $sql_parts );
                $query_sql  = "{$union_sql} ORDER BY updated_at DESC LIMIT ? OFFSET ?";
                $params_sql = array_merge( $params_sql, [ $limit, $offset ] );

                // Fetch rows
                $rows = $db->get_results( "SELECT * FROM ( {$query_sql} ) AS apps", $params_sql, ARRAY_A );

                // Aggregate count
                $count_sql = "SELECT SUM(total) FROM (" . implode( " UNION ALL ", $count_parts ) . ") AS counts";
                $total     = (int) $db->get_var( $count_sql, $params_count );

                // Hydrate objects using the new minimal/core method
                $objects = [];
                foreach ( $rows as $row ) {
                    $class = self::get_app_class( $row['type'] );

                    if ( method_exists( $class, 'from_array_minimal' ) ) {
                        $objects[] = $class::from_array_minimal( $row );
                    }
                }

                $results = array(
                    'items' => $objects,
                    'pagination' => array(
                        'page'        => $page,
                        'limit'       => $limit,
                        'total'       => $total,
                        'total_pages' => $limit > 0 ? ceil( $total / $limit ) : 0,
                    ),
                );
            }

            self::cache_set( $key, $results, 30 * MINUTE_IN_SECONDS );
        }

        return $results;
    }

    /**
     * Get a single hosted application using its slug.
     *
     * @param string $app_type The app type can be one of the allowed app types.
     * @param string $app_slug The app slug.
     * @return AbstractHostedApp|null The instance of a hosted application or null on failure.
     */
    public static function get_app_by_slug( $app_type, $app_slug ) : AbstractHostedApp|null {
        $key    = self::make_cache_key( __METHOD__, [$app_type, $app_slug] );
        $app    = self::cache_get( $key );

        if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {
            $app_class  = self::get_app_class( $app_type );

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, 'get_by_slug' ) ) {
                $app    = null;
            } else {
                $app    = $app_class::get_by_slug( $app_slug );
            }

            self::cache_set( $key, $app, 30 * \MINUTE_IN_SECONDS );
        }
        /** @var AbstractHostedApp|null $app */
        return $app;
    }
    
    /**
     * Get a single hosted application using its ID.
     *
     * @param string $app_type The app type can be one of the allowed app types.
     * @param int $id The app ID.
     * @return AbstractHostedApp|null The instance of a hosted application or null on failure.
     */
    public static function get_app_by_id( $app_type, $id ) : AbstractHostedApp|null {
        $key    = self::make_cache_key( __METHOD__, [$app_type, $id] );
        $app    = self::cache_get( $key );

        if ( false === $app || ! ( $app instanceof AbstractHostedApp ) ) {
            $app_class  = self::get_app_class( $app_type );
            $method     = "get_{$app_type}";

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $method ) ) {
                $app    = null;
            } else {
                $app    = $app_class::$method( $id );
            }

            self::cache_set( $key, $app, 30 * \MINUTE_IN_SECONDS );
        }

        /** @var AbstractHostedApp|null */
        return $app;
    }

    /**
     * Get app by owner ID
     * @param int $owner_id
     * @return AbstractHostedApp[]
     */
    public static function get_all_by_owner( $owner_id ) : array {
        return []; //TODO: find and hydrate.
    }

    /**
     * Count hosted applications across multiple types by status.
     *
     * @param array $args {
     *     @type string $status Application status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return int Total count of matching applications.
     */
    public static function count_apps( array $args = array() ) {
        $db = smliser_dbclass();

        $defaults = array(
            'status' => AbstractHostedApp::STATUS_ACTIVE,
            'types'  => self::$allowed_app_types,
        );
        $args = parse_args( $args, $defaults );

        $status = $args['status'];
        $types  = empty( $args['types'] ) ? self::$allowed_app_types : (array) $args['types'];

        $key = self::make_cache_key( __METHOD__, \compact( 'status', 'types' ) );

        $count = self::cache_get( $key );

        if ( false === $count ) {
            $sql_parts = [];
            $params    = [];

            if ( in_array( 'plugin', $types, true ) ) {
                $table_name  = SMLISER_PLUGINS_TABLE;
                $sql_parts[] = "SELECT COUNT(*) AS total FROM {$table_name} WHERE status = ?";
                $params[]    = $status;
            }

            if ( in_array( 'theme', $types, true ) ) {
                $table_name  = SMLISER_THEMES_TABLE;
                $sql_parts[] = "SELECT COUNT(*) AS total FROM {$table_name} WHERE status = ?";
                $params[]    = $status;
            }

            if ( in_array( 'software', $types, true ) ) {
                $table_name  = SMLISER_SOFTWARE_TABLE;
                $sql_parts[] = "SELECT COUNT(*) AS total FROM {$table_name} WHERE status = ?";
                $params[]    = $status;
            }

            if ( empty( $sql_parts ) ) {
                $count = 0;
            } else {
                // Build union and sum all counts
                $union_sql = implode( " UNION ALL ", $sql_parts );
                $sql       = "SELECT SUM(total) AS grand_total FROM ( {$union_sql} ) AS counts";

                $count = (int) $db->get_var( $sql, $params );
            }

            self::cache_set( $key, $count, 30 * \MINUTE_IN_SECONDS );
        }

        return $count;
    }

    /*
    |-------------------
    | UTILITY  METHODS
    |-------------------
    */

    /**
     * Get the class for a hosted application type
     * 
     * @param string $type The type name.
     * @return class-string<AbstractHostedApp> $class_name The app's class name.
     */
    public static function get_app_class( $type ) {
        $class = '\\SmartLicenseServer\\HostedApps\\' . ucfirst( (string) $type );

        return $class;
    }

    /**
     * Get the repository class for a hosted application type
     * 
     * @param string $type The type name.
     * @return \SmartLicenseServer\FileSystem\PluginRepository|\SmartLicenseServer\FileSystem\ThemeRepository|\SmartLicenseServer\FileSystem\SoftwareRepository|null The app's repository class instance.
     */
    public static function get_app_repository_class( $type ) {
        if ( ! $type || ! is_string( $type ) ) {
            return null;
        }

        $type = match( strtolower( $type ) ) {
            'plugin', 'plugins' => 'Plugin',
            'theme', 'themes'   => 'Theme',
            'software'          => 'Software',
            default             => $type,
        };

        $class = 'SmartLicenseServer\\FileSystem\\' . ucfirst( $type ) . 'Repository';

        if ( class_exists( $class, true ) ) {
            return new $class();
        }

        return null;
    }

    /**
     * Get allowed app types
     * 
     * @return array
     */
    public static function get_allowed_app_types() {
        return self::$allowed_app_types;
    }

    /**
     * Check whether the given app type is supported.
     * 
     * @param mixed $app_type The app type to check.
     */
    public static function app_type_is_allowed( $app_type ) {
        return in_array( $app_type, self::$allowed_app_types, true );
    }

}