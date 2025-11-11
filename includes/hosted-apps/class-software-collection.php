<?php
/**
 * Hosted Application collection class file
 * 
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exception;
use SmartLicenseServer\Exception\RequestException;

defined( 'ABSPATH' ) || exit;

/**
 * Software collection class is used to perform CRUD opertions on softwares hosted in this repository.
 * 
 * Provides methods to perform CRUD operations across multiple hosted application types (plugins, themes, software) and their assets.
 */
class Smliser_Software_Collection {
    /**
     * Allowed app types
     * 
     * @var array $allowed_app_types
     */
    protected Static $allowed_app_types = [ 'plugin', 'theme', 'software' ];

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
    public static function get_apps( array $args = array() ) {
        $db = smliser_dbclass();

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

        $sql_parts = [];
        $params    = [];

        if ( in_array( 'plugin', $types, true ) ) {
            $table_name     = SMLISER_PLUGIN_ITEM_TABLE;
            $sql_parts[]    = "SELECT id, 'plugin' AS type, last_updated FROM {$table_name} WHERE status = ?";
            $params[]       = $status;
            
        }

        if ( in_array( 'theme', $types, true ) ) {
            $table_name = SMLISER_THEME_ITEM_TABLE;
            $sql_parts[] = "SELECT id, 'theme' AS type, last_updated FROM {$table_name} WHERE status = ?";
            $params[] = $status;
            
        }

        if ( in_array( 'software', $types, true ) ) {
            $table_name     = SMLISER_APPS_ITEM_TABLE;
            $sql_parts[]    = "SELECT id, 'software' AS type, last_updated FROM {$table_name} WHERE status = ?";
            $params[]       = $status;
            
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
        $total     = (int) $db->get_var( $count_sql, $params );

        // Fetch paginated rows
        $sql            = "SELECT * FROM ( {$union_sql} ) AS apps ORDER BY last_updated DESC LIMIT ? OFFSET ?";
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
            'status' => 'active',
            'types'  => ['plugin', 'theme', 'software'],
        ];
        $args = parse_args( $args, $defaults );

        $page   = max( 1, (int) $args['page'] );
        $limit  = max( 1, (int) $args['limit'] );
        $status = $args['status'];
        $types  = array_values( (array) $args['types'] );

        $type_tables = [
            'plugin'   => SMLISER_PLUGIN_ITEM_TABLE,
            'theme'    => SMLISER_THEME_ITEM_TABLE,
            'software' => SMLISER_APPS_ITEM_TABLE,
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
                        ORDER BY last_updated DESC 
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
    public static function get_themes( array $args = array() ) {
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
    public static function get_softwares( array $args = array() ) {
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
    public static function search_apps( array $args = array() ) {
        $db = smliser_dbclass();

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
        $types  = array_filter( (array) $args['types'] );

        if ( empty( $term ) || empty( $types ) ) {
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

        $like        = '%' . $term . '%';
        $sql_parts   = [];
        $count_parts = [];
        $params_sql  = [];
        $params_count = [];

        foreach ( $types as $type ) {
            switch ( $type ) {
                case 'plugin':
                    $table = SMLISER_PLUGIN_ITEM_TABLE;
                    break;
                case 'theme':
                    $table = SMLISER_THEME_ITEM_TABLE;
                    break;
                case 'software':
                    $table = SMLISER_APPS_ITEM_TABLE;
                    break;
                default:
                    continue 2;
            }

            // Query for fetching IDs
            $sql_parts[]    = "SELECT id, '{$type}' AS type, last_updated FROM {$table} WHERE status = ? AND ( name LIKE ? OR slug LIKE ? OR author LIKE ? )";
            $params_sql     = array_merge( $params_sql, [ $status, $like, $like, $like ] );

            // Query for counting matches
            $count_parts[]  = "SELECT COUNT(*) AS total FROM {$table} WHERE status = ? AND ( name LIKE ? OR slug LIKE ? OR author LIKE ? )";
            $params_count   = array_merge( $params_count, [ $status, $like, $like, $like ] );
        }

        // Build union query
        $union_sql = implode( " UNION ALL ", $sql_parts );
        $query_sql = "{$union_sql} ORDER BY last_updated DESC LIMIT ? OFFSET ?";
        $params_sql = array_merge( $params_sql, [ $limit, $offset ] );

        // Fetch rows via adapter
        $rows = $db->get_results( "SELECT * FROM ( {$query_sql} ) AS apps", $params_sql, ARRAY_A );

        // Aggregate count
        $count_sql = "SELECT SUM(total) FROM (" . implode( " UNION ALL ", $count_parts ) . ") AS counts";
        $total = (int) $db->get_var( $count_sql, $params_count );

        // Instantiate app objects
        $objects = [];
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
                'total_pages' => $limit > 0 ? ceil( $total / $limit ) : 0,
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
     * 
     * @param Request $request
     */
    public static function save_app( Request $request ) {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'unauthorized_request', 'You do not have the required permission to perform this operation' , array( 'status' => 403 ) );
            }

            $app_type = $request->get( 'app_type', null );

            if ( ! $app_type ) {
                throw new RequestException( 'invalid_parameter_type', 'The app type parameter is required.' , array( 'status' => 400 ) );
            }

            if ( ! self::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" is not supported', $app_type ) , array( 'status' => 400 ) );
            }
            $app_id         = $request->get( 'app_id', 0 );
            $app_class      = self::get_app_class( $app_type );
            $init_method    = "get_{$app_type}";

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $init_method ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" is not supported', $app_type ) , array( 'status' => 400 ) );
            }
            
            if ( $app_id ) {
                $class = $app_class::$init_method( $app_id );
            } else {
                $class = new $app_class();
            }

            /**
             * The app instance
             * 
             * @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface $class
             */
            
            $name       = $request->get( 'app_name', null );

            if ( empty( $name ) ) {
                throw new RequestException( 'invalid_input', 'Application name parameter is required' , array( 'status' => 400 ) );
            }
            $author     = $request->get( 'app_author', null );

            if ( empty( $author ) ) {
                throw new RequestException( 'invalid_input', 'Application author name is required' , array( 'status' => 400 ) );
            }

            $app_file   = $request->get( 'app_file' );

            $author_url = $request->get( 'app_author_url', '' );
            $version    = $request->get( 'app_version', '' );

            $class->set_name( $name );
            $class->set_author( $author );
            $class->set_author_profile( $author_url );
            $class->set_version( $version );
            $class->set_file( $app_file );
        
            if ( ! empty( $app_id ) ) {
                $class->set_id( $app_id );

                $update_method = "update_{$app_type}";

                if ( ! method_exists( __CLASS__, $update_method ) ) {
                    throw new RequestException( 'internal_server_error', sprintf( 'The update method for the application type "%s" was not found!', $app_type ) , array( 'status' => 500 ) );
                }

                $updated = self::$update_method( $class, $request );

                if ( is_smliser_error( $updated ) ) {
                    throw $updated;
                }
                
            }

            $result = $class->save();

            if ( is_smliser_error( $result ) ) {
                throw $result;
            }

            $data = array( 'data' => array(
                'message' => sprintf( '%s Saved', ucfirst( $app_type ) ),
                'redirect_url' => smliser_admin_repo_tab( 'edit', array( 'type' => $app_type, 'item_id' => $class->get_id() ) )
            ));

            return ( new Response( 200, array(), smliser_safe_json_encode( $data ) ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Update a plugin.
     * 
     * @param Smliser_Plugin $class The plugin ID.
     * @param Request $request The request object.
     * @return true|RequestException
     */
    public static function update_plugin( &$class, Request $request ) {
        if ( ! $class instanceof Smliser_Plugin ) {
            return new RequestException( 'message', 'Wrong plugin object passed' );
        }

        $class->set_required_php( $request->get( 'app_required_php_version' ) );
        $class->set_required( $request->get( 'app_required_wp_version' ) );
        $class->set_tested( $request->get( 'app_tested_wp_version' ) );
        $class->set_download_link( $request->get( 'app_download_url' ) );

        $class->update_meta( 'support_url', $request->get( 'app_support_url' ) );
        $class->update_meta( 'homepage_url', $request->get( 'app_homepage_url', '' ) );

        return true;
    }

    /**
     * Handles an application's asset upload using a standardized Request object.
     *
     * @param Request $request The standardized request object.
     * @return Response Returns a Response object on success.
     * @throws RequestException On business logic failure.
     */
    public static function app_asset_upload( Request $request ) {
        try {
            // must still enforce the permission if the adapter missed it (defense-in-depth).
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'permission_denied', 'Missing required authorization flag.' ); 
            }

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_type = $request->get( 'asset_type' );
            $asset_name = $request->get( 'asset_name', '' );
            $asset_file = $request->get( 'asset_file' );

            if ( empty( $app_type ) || empty( $app_slug ) || empty( $asset_type ) || empty( $asset_file ) ) {
                throw new RequestException( 'missing_data', 'Missing required application, slug, asset type, or file data.' );
            }
            
            if ( ! self::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 
                    'invalid_input', 
                    sprintf( 'The app type "%s" is not supported.', $app_type ) 
                );
            }
            
            if ( ! is_array( $asset_file ) || ! isset( $asset_file['tmp_name'] ) ) {
                 throw new RequestException( 'invalid_input', 'Uploaded asset file is invalid or missing.' );
            }

            $repo_class = self::get_app_repository_class( $app_type );
            if ( ! $repo_class ) {
                throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
            }
            
            $url = $repo_class->upload_asset( $app_slug, $asset_file, $asset_type, $asset_name );

            if ( is_smliser_error( $url ) ) {
                throw new RequestException( $url->get_error_code() ?: 'remote_download_failed', $url->get_error_message() );
            }
            
            $config = array(
                'asset_type'    => $asset_type,
                'app_slug'      => $app_slug,
                'app_type'      => $app_type,
                'asset_name'    => basename( $url ),
                'asset_url'     => $url
            );

            // Return a success JSON response object
            $data   = array( 'message' => 'Asset uploaded successfully', 'config' => $config );
            $response   = [
                'success'   => true,
                'data'      => $data
            ];

            return ( new Response( 200, array(), smliser_safe_json_encode( $response ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
    }

    /**
     * Handles an application's asset deletion using a standardized Request object.
     *
     * @param Request $request The standardized request object.
     * @return Response Returns a Response object on success.
     * @throws RequestException On business logic failure.
     */
    public static function app_asset_delete( Request $request ) {
        try {
            // Check authorization flag passed by the adapter (defense-in-depth)
            if ( ! $request->get( 'is_authorized' ) ) {
                throw new RequestException( 'permission_denied', 'Missing required authorization flag.' );
            }

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_name = $request->get( 'asset_name' );

            if ( empty( $app_type ) || empty( $app_slug ) || empty( $asset_name ) ) {
                throw new RequestException( 'missing_data', 'Application type, slug, and asset name are required.' );
            }

            if ( ! self::app_type_is_allowed( $app_type ) ) {
                throw new RequestException(
                    'invalid_input',
                    sprintf( 'The app type "%s" is not supported', $app_type )
                );
            }

            $repo_class = self::get_app_repository_class( $app_type );
            if ( ! $repo_class ) {
                throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
            }

            $result = $repo_class->delete_asset( $app_slug, $asset_name );

            if ( is_smliser_error( $result ) ) {
                throw new RequestException( $result->get_error_code() ?: 'asset_deletion_failed', $result->get_error_message() );
            }

            $data       = array( 'message' => 'Asset deleted successfully.' );
            $response   = [
                'success'   => true,
                'data'      => $data
            ];

            return ( new Response( 200, array(), smliser_safe_json_encode( $response ) ) )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
        }
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
     * @return SmartLicenseServer\PluginRepository|SmartLicenseServer\ThemeRepository|SmartLicenseServer\SoftwareRepository|null The app's repository class instance.
     */
    public static function get_app_repository_class( $type ) {
        if ( ! $type || ! is_string( $type ) ) {
            return null;
        }

        $class = 'SmartLicenseServer\\' . ucfirst( $type ) . 'Repository';

        if ( class_exists( $class ) ) {
            return new $class();
        }

        return null;
    }

    /**
     * Get allowed app types
     * 
     * @return array
     */
    public function get_allowed_app_types() {
        return self::$allowed_app_types;
    }

    /**
     * Add allowed app types
     * 
     * @param string $value
     */
    public function add_allowed_app_types( $value ) {
        $types = self::$allowed_app_types;

        $types[] = $value;

        self::$allowed_app_types = array_filter( $types );
    }

    /**
     * Remove allowed app types
     * 
     * @param string $value
     */
    public function remove_allowed_app_type( $value ) {
        foreach ( self::$allowed_app_types as $k => $v ) {
            if ( $v === $value ){
                unset( self::$allowed_app_types[$k] );
            }
        }
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