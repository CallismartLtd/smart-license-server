<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Software collection class is used to perform CRUD opertions on softwares hosted in this repository.
 * 
 * Provides methods to perform CRUD operations across multiple hosted application types (plugins, themes, software) and their assets.
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
     *     @type SmartLicenseServer\HostedApps\Hosted_Apps_Interface[] $items        Instantiated application objects.
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
        $args = parse_args( $args, $defaults );

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
        $args = parse_args( $args, $defaults );

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
        $args = parse_args( $args, $defaults );

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
    |---------------------------
    */

    /**
     * Ajax callback method to handle app form submission.
     */
    public static function save_app() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this operation.' ), 403 );
        }

        $app_type   = smliser_get_post_param( 'app_type', null ) ?? smliser_send_json_error( array( 'message' => 'Application type is missing' ) );
        
        if ( ! self::app_type_is_allowed( $app_type ) ) {
            smliser_send_json_error( array( 'message' => sprintf( 'The app type "%s" is not supported', $app_type ) ) );
        }
        
        $app_id         = smliser_get_post_param( 'app_id', 0 );
        $app_class      = self::get_app_class( $app_type );
        $init_method    = "get_{$app_type}";

        if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $init_method ) ) {
            smliser_send_json_error( array( 'message' => 'The app type is not supported' ) );
        }
        
        /**
         * The app instance
         * 
         * @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface $class
         */
        if ( $app_id ) {
            $class = $app_class::$init_method( $app_id );
        } else {
            $class = new $app_class();
        }
        
        $name       = smliser_get_post_param( 'app_name', null ) ?? smliser_send_json_error( array( 'message' => 'Application name is required' ) );
        $author     = smliser_get_post_param( 'app_author', null ) ?? smliser_send_json_error( array( 'message' => 'Application author name is required' ) );
        $app_file   = isset( $_FILES['app_file'] ) && UPLOAD_ERR_OK === $_FILES['app_file']['error'] ? $_FILES['app_file'] : null;

        $author_url = smliser_get_post_param( 'app_author_url', '' );
        $version    = smliser_get_post_param( 'app_version', '' );

        $class->set_name( $name );
        $class->set_author( $author );
        $class->set_author_profile( $author_url );
        $class->set_version( $version );
        $class->set_file( $app_file );

        if ( ! empty( $app_id ) ) {
            $class->set_id( $app_id );

            $update_method = "update_{$app_type}";

            if ( ! method_exists( __CLASS__, $update_method ) ) {
                smliser_send_json_error( array( 'message' => sprintf( 'The update method for the application type "%s" was not found!', $app_type ) ) );
            }

            $updated = self::$update_method( $class );

            if ( is_smliser_error( $updated ) ) {
                smliser_send_json_error( array( 'message' => $updated->get_error_message() ), 503 );
            }
            
        }

        $result = $class->save();

        if ( is_smliser_error( $result ) ) {
            smliser_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        smliser_send_json_success( array( 'message' => sprintf( '%s Saved', ucfirst( $app_type ) ), 'redirect_url' => smliser_admin_repo_tab( 'edit', array( 'type' => $app_type, 'item_id' => $class->get_id() ) ) ) );
    }

    /**
     * Update a plugin.
     * 
     * @param Smliser_Plugin $class The plugin ID.
     * @return true|Exception
     */
    public static function update_plugin( &$class ) {
        if ( ! $class instanceof Smliser_Plugin ) {
            return new Exception( 'message', 'Wrong plugin object passed' );
        }

        $class->set_required_php( smliser_get_post_param( 'app_required_php_version' ) );
        $class->set_required( smliser_get_post_param( 'app_required_wp_version' ) );
        $class->set_tested( smliser_get_post_param( 'app_tested_wp_version' ) );
        $class->set_download_link( smliser_get_post_param( 'app_download_url' ) );

        $class->update_meta( 'support_url', smliser_get_post_param( 'app_support_url' ) );
        $class->update_meta( 'homepage_url', smliser_get_post_param( 'app_homepage_url', '' ) );

        return true;
    }

    /**
     * Handles an application's asset upload
     */
    public static function app_asset_upload() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this operation.' ), 403 );
        }

        $app_type   = smliser_get_post_param( 'app_type', null ) ?? smliser_send_json_error( array( 'message' => 'Application type is required' ) );
        if ( ! self::app_type_is_allowed( $app_type ) ) {
            smliser_send_json_error( array( 'message' => sprintf( 'The app type "%s" is not supported', $app_type ) ) );
        }

        $app_slug   = smliser_get_post_param( 'app_slug', null ) ?? smliser_send_json_error( array( 'message' => 'Application slug is required' ) );
        $asset_type = smliser_get_post_param( 'asset_type', null ) ?? smliser_send_json_error( array( 'message' => 'Asset type is required' ) );
        $asset_name = smliser_get_post_param( 'asset_name', '' );
        
        $asset_file = isset( $_FILES['asset_file'] ) && UPLOAD_ERR_OK === $_FILES['asset_file']['error'] ? $_FILES['asset_file'] : smliser_send_json_error( array( 'message' => 'Uploaded file missing or corrupted' ) );

        $repo_class = self::get_app_repository_class( $app_type ) ?? smliser_send_json_error( array( 'message' => 'Unable to reolve repository class' ), 500 );
        
        $url = $repo_class->upload_asset( $app_slug, $asset_file, $asset_type, $asset_name );

        if ( is_smliser_error( $url ) ) {
            smliser_send_json_error( array( 'message' => $url->get_error_message() ), $url->get_error_code() );
        }
        
        $config = array(
            'asset_type'    => $asset_type,
            'app_slug'      => $app_slug,
            'app_type'      => $app_type,
            'asset_name'    => basename( $url ),
            'asset_url'     => $url
        );

        smliser_send_json_success( array( 'message' => 'Uploaded', 'config' => $config ) );
    }

    /**
     * Handles an application's asset deletion
     */
    public static function app_asset_delete() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            smliser_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            smliser_send_json_error( array( 'message' => 'You do not have the required permission to perform this operation.' ), 403 );
        }

        $app_type   = smliser_get_post_param( 'app_type', null ) ?? smliser_send_json_error( array( 'message' => 'Application type is required' ) );
        if ( ! self::app_type_is_allowed( $app_type ) ) {
            smliser_send_json_error( array( 'message' => sprintf( 'The app type "%s" is not supported', $app_type ) ) );
        }

        $app_slug   = smliser_get_post_param( 'app_slug', null ) ?? smliser_send_json_error( array( 'message' => 'Application slug is required' ) );
        $asset_name = smliser_get_post_param( 'asset_name', null ) ?? smliser_send_json_error( array( 'message' => 'Asset name is required' ) );
        

        $repo_class = self::get_app_repository_class( $app_type ) ?? smliser_send_json_error( array( 'message' => 'Unable to reolve repository class' ), 500 );
        
        $url = $repo_class->delete_asset( $app_slug, $asset_name );

        if ( is_smliser_error( $url ) ) {
            smliser_send_json_error( array( 'message' => $url->get_error_message() ), $url->get_error_code() );
        }
        
        smliser_send_json_success( array( 'message' => 'Uploaded', 'image_url' => $url ) );

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
        $class = 'Smliser_' . ucfirst( $type );

        return $class;
    }

    /**
     * Get the repository class for a hosted application type
     * 
     * @param string $type The type name.
     * @return SmartLicenseServer\Repository|null $class_name The app's class name.
     */
    public static function get_app_repository_class( $type ) {
        $class = 'SmartLicenseServer\\' . ucfirst( $type ) . 'Repository';

        if ( class_exists( $class ) ) {
            return new $class();
        }

        return null;
    }

    /**
     * Run hooks
     */
    public static function run_hooks() {
        add_action( 'wp_ajax_smliser_save_plugin', [__CLASS__, 'save_app'] );
        add_action( 'wp_ajax_smliser_save_theme', [__CLASS__, 'save_app'] );
        add_action( 'wp_ajax_smliser_save_software', [__CLASS__, 'save_app'] );

        add_action( 'wp_ajax_smliser_app_asset_upload', [__CLASS__, 'app_asset_upload'] );
        add_action( 'wp_ajax_smliser_app_asset_delete', [__CLASS__, 'app_asset_delete'] );
        // add_action( 'wp_ajax_smliser_update_software', [__CLASS__, 'update_software'] );
    }

    /**
     * Check whether the given app type is supported.
     * 
     * @param mixed $app_type The app type to check.
     */
    public static function app_type_is_allowed( $app_type ) {

        $allowed_types  = apply_filters( 'smliser_allowed_app_types', array( 'plugin', 'theme', 'software' ) );

        return in_array( $app_type, $allowed_types, true );
    }
}

Smliser_Software_Collection::run_hooks();
