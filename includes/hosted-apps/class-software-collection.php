<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

defined( 'ABSPATH' ) || exit;

/**
 * Software collection class is used to perform CRUD opertions on softwares hosted in this repository
 */
class Smliser_Software_Collection {

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
     *     @type array $items        Instantiated application objects.
     *     @type array $pagination {
     *         @type int $total       Total number of matching items.
     *         @type int $page        Current page number.
     *         @type int $limit       Number of items per page.
     *         @type int $total_pages Total number of pages.
     *     }
     * }
     */
    public static function get_apps( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'page'   => 1,
            'limit'  => 20,
            'status' => 'active',
            'types'  => array( 'plugin', 'theme', 'software' ),
        );
        $args = wp_parse_args( $args, $defaults );

        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = ( $page - 1 ) * $limit;
        $status = $args['status'];
        $types  = (array) $args['types'];

        $sql_parts = array();

        if ( in_array( 'plugin', $types, true ) ) {
            $table_name = SMLISER_PLUGIN_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'plugin' AS type, last_updated
                FROM {$table_name}
                WHERE status = %s",
                $status
            );
        }

        if ( in_array( 'theme', $types, true ) ) {
            $table_name = SMLISER_THEME_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'theme' AS type, last_updated
                FROM {$table_name}
                WHERE status = %s",
                $status
            );
        }

        if ( in_array( 'software', $types, true ) ) {
            $table_name = SMLISER_APPS_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'software' AS type, last_updated
                FROM {$table_name}
                WHERE status = %s",
                $status
            );
        }

        if ( empty( $sql_parts ) ) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'total'       => 0,
                    'page'        => $page,
                    'limit'       => $limit,
                    'total_pages' => 0,
                ),
            );
        }

        // Build union
        $union_sql = implode( " UNION ALL ", $sql_parts );

        
        // Total count
        $count_sql = "SELECT COUNT(*) FROM ( {$union_sql} ) AS apps_count ";
        
        $total     = (int) $wpdb->get_var( $count_sql );
        

        // Fetch paginated rows
        $sql = $wpdb->prepare(
            "SELECT * FROM ( {$union_sql} ) AS apps
            ORDER BY last_updated DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $rows    = $wpdb->get_results( $sql, ARRAY_A );
        $objects = array();

        foreach ( $rows as $row ) {
            $class  = self::get_app_class( $row['type'] );
            $method = "get_" . $row['type'];

            if ( method_exists( $class, $method ) ) {
                $objects[] = $class::$method( (int) $row['id'] );
            }
        }

        return array(
            'items'      => $objects,
            'pagination' => array(
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ( $limit > 0 ) ? ceil( $total / $limit ) : 1,
            ),
        );
    }
        
    /**
     * Get hosted applications across multiple types with balanced pagination.
     *
     * Each type is given an equal share of the limit (rounded).
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
    public static function get_apps_balanced( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'page'   => 1,
            'limit'  => 20,
            'status' => 'active',
            'types'  => array( 'plugin', 'theme', 'software' ),
        );
        $args = wp_parse_args( $args, $defaults );

        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $status = $args['status'];
        $types  = array_values( (array) $args['types'] );

        $objects = array();
        $total   = 0;

        // Distribute limit equally per type
        $per_type_limit = (int) ceil( $limit / count( $types ) );
        $offset         = ( $page - 1 ) * $per_type_limit;

        foreach ( $types as $type ) {
            switch ( $type ) {
                case 'plugin':
                    $table_name = SMLISER_PLUGIN_ITEM_TABLE;
                    break;
                case 'theme':
                    $table_name = SMLISER_THEME_ITEM_TABLE;
                    break;
                case 'software':
                    $table_name = SMLISER_APPS_ITEM_TABLE;
                    break;
                default:
                    continue 2;
            }

            // Count query for this type
            $count_sql = $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table_name} WHERE status = %s",
                $status
            );
            $type_total = (int) $wpdb->get_var( $count_sql );
            $total     += $type_total;

            // Paginated query for this type
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT id FROM {$table_name}
                    WHERE status = %s
                    ORDER BY last_updated DESC
                    LIMIT %d OFFSET %d",
                    $status,
                    $per_type_limit,
                    $offset
                ),
                ARRAY_A
            );

            foreach ( $rows as $row ) {
                $class  = self::get_app_class( $type );
                $method = "get_" . $type;

                if ( method_exists( $class, $method ) ) {
                    $objects[] = $class::$method( (int) $row['id'] );
                }
            }
        }

        // Slice in case we went slightly over limit
        $objects = array_slice( $objects, 0, $limit );

        return array(
            'items'      => $objects,
            'pagination' => array(
                'total'       => $total,
                'page'        => $page,
                'limit'       => $limit,
                'total_pages' => ( $limit > 0 ) ? ceil( $total / $limit ) : 1,
            ),
        );
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
    public static function get_plugins( $args = array() ) {
        $args['types'] = array( 'plugin' );
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
    public static function get_themes( $args = array() ) {
        $args['types'] = array( 'theme' );
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
    public static function get_softwares( $args = array() ) {
        $args['types'] = array( 'software' );
        return self::get_apps( $args );
    }

    /**
     * Search hosted applications across multiple types with pagination.
     *
     * @param array $args {
     *     Optional. Arguments to filter results.
     *
     *     @type string $term   Search term to match against name, slug, or author. Required.
     *     @type int    $page   Current page number. Default 1.
     *     @type int    $limit  Number of items per page. Default 20.
     *     @type string $status Application status filter. Default 'active'.
     *     @type array  $types  List of types to query. Default all ['plugin','theme','software'].
     * }
     * @return array {
     *     @type array $items      Instantiated application objects.
     *     @type array $pagination Pagination info (page, limit, total, total_pages).
     * }
     */
    public static function search_apps( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'term'   => '',
            'page'   => 1,
            'limit'  => 20,
            'status' => 'active',
            'types'  => array( 'plugin', 'theme', 'software' ),
        );
        $args = wp_parse_args( $args, $defaults );

        $term   = sanitize_text_field( $args['term'] );
        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $offset = ( $page - 1 ) * $limit;
        $status = $args['status'];
        $types  = (array) $args['types'];

        if ( empty( $term ) ) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'page'        => $page,
                    'limit'       => $limit,
                    'total'       => 0,
                    'total_pages' => 0,
                ),
            );
        }

        $like        = '%' . $wpdb->esc_like( $term ) . '%';
        $sql_parts   = array();
        $count_parts = array();

        // Plugins
        if ( in_array( 'plugin', $types, true ) ) {
            $table = SMLISER_PLUGIN_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'plugin' AS type, last_updated
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
            $count_parts[] = $wpdb->prepare(
                "SELECT COUNT(*) AS total
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
        }

        // Themes
        if ( in_array( 'theme', $types, true ) ) {
            $table = SMLISER_THEME_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'theme' AS type, last_updated
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
            $count_parts[] = $wpdb->prepare(
                "SELECT COUNT(*) AS total
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
        }

        // Software
        if ( in_array( 'software', $types, true ) ) {
            $table = SMLISER_APPS_ITEM_TABLE;
            $sql_parts[] = $wpdb->prepare(
                "SELECT id, 'software' AS type, last_updated
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
            $count_parts[] = $wpdb->prepare(
                "SELECT COUNT(*) AS total
                FROM {$table}
                WHERE status = %s
                AND ( name LIKE %s OR slug LIKE %s OR author LIKE %s )",
                $status, $like, $like, $like
            );
        }

        if ( empty( $sql_parts ) ) {
            return array(
                'items'      => array(),
                'pagination' => array(
                    'page'        => $page,
                    'limit'       => $limit,
                    'total'       => 0,
                    'total_pages' => 0,
                ),
            );
        }

        // Main query (fetch matching IDs + types)
        $union_sql = implode( " UNION ALL ", $sql_parts );
        $sql       = $wpdb->prepare(
            "SELECT * FROM ( {$union_sql} ) AS apps
            ORDER BY last_updated DESC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        );

        $rows = $wpdb->get_results( $sql, ARRAY_A );

        // Count query (aggregate totals across all tables)
        $count_sql = "SELECT SUM(total) FROM ( " . implode( " UNION ALL ", $count_parts ) . " ) AS counts";
        $total     = (int) $wpdb->get_var( $count_sql );
        $total_pages = ( $total > 0 ) ? ceil( $total / $limit ) : 0;

        // Instantiate app objects
        $objects = array();
        foreach ( $rows as $row ) {
            $class  = self::get_app_class( $row['type'] );
            $method = "get_" . $row['type'];

            if ( method_exists( $class, $method ) ) {
                $objects[] = $class::$method( (int) $row['id'] );
            }
        }

        return array(
            'items'      => $objects,
            'pagination' => array(
                'page'        => $page,
                'limit'       => $limit,
                'total'       => $total,
                'total_pages' => $total_pages,
            ),
        );
    }



    /*
    |---------------------------
    | CREATE OPERATION METHODS
    |
    */

    /**
     * Ajax callback method to handle app form submission.
     */
    public static function save_app() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have the required permission to perform this operation.' ), 403 );
        }

        $app_type   = smliser_get_post_param( 'app_type', null ) ?? wp_send_json_error( array( 'message' => 'Application type is missing' ) );
        $app_file   = isset( $_FILES['app_file'] ) && UPLOAD_ERR_OK === $_FILES['app_file']['error'] ? $_FILES['app_file'] : null;
        
        $app_class = self::get_app_class( $app_type );

        if ( ! class_exists( $app_class ) ) {
            wp_send_json_error( array( 'message' => 'The app type is not supported' ) );
        }

        $name       = smliser_get_post_param( 'app_name', null ) ?? wp_send_json_error( array( 'message' => 'Application name is required' ) );
        $author     = smliser_get_post_param( 'app_author', null ) ?? wp_send_json_error( array( 'message' => 'Application author name is required' ) );
    
        /**
         * The app instance
         * 
         * @var \SmartLicenseServer\HostedApps\Smliser_Hosted_Apps_Interface $class
         */
        $class = new $app_class();

        $author_url = smliser_get_post_param( 'app_author_url', '' );
        $version    = smliser_get_post_param( 'app_version', '' );

        $class->set_name( $name );
        $class->set_author( $author );
        $class->set_author_profile( $author_url );
        $class->set_version( $version );
        $class->set_file( $app_file );
        $class->set_download_url();

        $result = $class->save();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => sprintf( '%s Saved', ucfirst( $app_type ) ), 'redirect_url' => smliser_admin_repo_tab( 'edit', array( 'type' => $app_type, 'item_id' => $class->get_id() ) ) ) );

    }

    /**
     * Update an existing application.
     */
    public static function update_app() {

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
     * @return string $class_name The app's class name.
     */
    public static function get_app_class( $type ) {
        $class = 'Smliser_' . ucfirst(  $type );

        return $class;

    }

    /**
     * Run hooks
     */
    public static function run_hooks() {
        add_action( 'wp_ajax_smliser_save_plugin', [__CLASS__, 'save_app'] );
        add_action( 'wp_ajax_smliser_save_theme', [__CLASS__, 'save_app'] );
        add_action( 'wp_ajax_smliser_save_software', [__CLASS__, 'save_app'] );

        add_action( 'wp_ajax_smliser_update_plugin', [__CLASS__, 'update_plugin'] );
        add_action( 'wp_ajax_smliser_update_theme', [__CLASS__, 'update_theme'] );
        add_action( 'wp_ajax_smliser_update_software', [__CLASS__, 'update_software'] );
    }
}

Smliser_Software_Collection::run_hooks();
