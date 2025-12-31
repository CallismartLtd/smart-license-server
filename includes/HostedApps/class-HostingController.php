<?php
/**
 * Software Hosting Controller class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes\HostedApps
 * @since 0.2.0
 */
namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\HostedApps\Theme;
use SmartLicenseServer\HostedApps\Software;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Software Hosting Controller handles HTTP request and response for hosted applications.
 */
class HostingController {
    use SanitizeAwareTrait;
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
                throw new RequestException( 'invalid_parameter_type', 'The app_type parameter is required.' , array( 'status' => 400 ) );
            }

            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" is not supported', $app_type ) , array( 'status' => 400 ) );
            }
            $app_id         = $request->get( 'app_id', 0 );
            $app_class      = HostedApplicationService::get_app_class( $app_type );
            $init_method    = "get_{$app_type}";

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $init_method ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" class did not define the required method "%s::%s($id)"', $app_type, $app_class, $init_method ) , array( 'status' => 500 ) );
            }
            
            if ( $app_id ) {
                $class = $app_class::$init_method( $app_id );
            } else {
                $class = new $app_class();
            }

            /**
             * The app instance
             * 
             * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $class
             */
            
            $name   = $request->get( 'app_name', null );

            if ( empty( $name ) ) {
                throw new RequestException( 'invalid_input', 'Application name parameter is required' , array( 'status' => 400 ) );
            }
            $author     = $request->get( 'app_author', null );

            if ( empty( $author ) ) {
                throw new RequestException( 'invalid_input', 'Application author name is required' , array( 'status' => 400 ) );
            }

            $app_zip_file   = $request->get( 'app_zip_file' );
            $author_url     = $request->get( 'app_author_url', '' );
            $version        = $request->get( 'app_version', '' );

            $class->set_name( $name );
            $class->set_author( $author );
            $class->set_author_profile( $author_url );
            $class->set_version( $version );
            $class->set_file( $app_zip_file );
        
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
                throw new RequestException( $result->get_error_code() ?: 'save_failed', $result->get_error_message(), array( 'status' => 500 )  );
            }

            \smliser_cache()->clear();

            $data = array(
                'success'   => true,
                'data'      => array(
                    'message' => sprintf( '%s Saved', ucfirst( $app_type ) ),
                    'redirect_url' => smliser_admin_repo_tab( 'edit', array( 'type' => $app_type, 'app_id' => $class->get_id() )
                )
            ));

            return ( new Response( 200, array(), smliser_safe_json_encode( $data ) ) )
            ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Update a plugin.
     * 
     * @param Plugin $plugin The plugin ID.
     * @param Request $request The request object.
     * @return true|RequestException
     */
    private static function update_plugin( &$plugin, Request $request ) {
        if ( ! $plugin instanceof Plugin ) {
            return new RequestException( 'message', 'Wrong plugin object passed' );
        }

        $plugin->set_download_url( $request->get( 'app_download_url' ) );
        $plugin->update_meta( 'support_url', $request->get( 'app_support_url' ) );
        $plugin->update_meta( 'homepage_url', $request->get( 'app_homepage_url', null ) );

        return true;
    }

    /**
     * Update a theme.
     * 
     * @param Theme $theme
     * @param Request $request
     * @return true|RequestException
     */
    private static function update_theme( &$theme, Request $request ) {
        if ( ! $theme instanceof Theme ) {
            return new RequestException( 'message', 'Wrong theme object passed' );
        }

        $theme->set_download_url( $request->get( 'app_download_url' ) );
        $theme->update_meta( 'support_url', $request->get( 'app_support_url' ) );
        $theme->update_meta( 'homepage_url', $request->get( 'app_homepage_url', '' ) );
        $theme->update_meta( 'preview_url', $request->get( 'app_preview_url', '' ) );
        $theme->update_meta( 'external_repository_url', $request->get( 'app_external_repository_url', '' ) );

        return true;
    }

     /**
     * Update software
     *
     * @param Software $software
     * @param Request  $request
     * @return true|Exception
     */
    private static function update_software( &$software, $request ) {

        if ( ! $software instanceof Software ) {
            return new RequestException(
                'invalid_software',
                'Wrong software object passed.'
            );
        }

        $uploaded_json = $request->get( 'app_json_file' );

        if ( ! is_array( $uploaded_json ) ) {
            return new RequestException(
                'missing_file',
                'Please upload app.json file using "app_json_file" file key.'
            );
        }

        try {
            $tmp_name = FileSystemHelper::validate_uploaded_file(
                $uploaded_json,
                'app.json'
            );
        } catch ( Exception $e ) {
            return new RequestException( $e->get_error_code(), $e->get_error_message(), ['status' => 400] );
        }

        $contents = file_get_contents( $tmp_name );

        if ( false === $contents ) {
            return new RequestException( 'file_read_error', 'Unable to read uploaded app.json file.' );
        }

        $manifest = json_decode( $contents, true );

        if ( ! is_array( $manifest ) ) {
            return new RequestException( 'invalid_app_json', 'Invalid app.json file. JSON could not be parsed.' );
        }

        $software->set_manifest( self::sanitize_deep( $manifest ) );
        $software->set_download_url( $request->get( 'app_download_url' ) );
        $software->update_meta( 'support_url', $request->get( 'app_support_url' ) );
        $software->update_meta( 'homepage_url', $request->get( 'app_homepage_url', null ) );
        $software->update_meta( 'documentation_url', $request->get( 'app_documentation_url', null ) );
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
            
            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 
                    'invalid_input', 
                    sprintf( 'The app type "%s" is not supported.', $app_type ) 
                );
            }
            
            if ( ! is_array( $asset_file ) || ! isset( $asset_file['tmp_name'] ) ) {
                throw new RequestException( 'invalid_input', 'Uploaded asset file is invalid or missing.' );
            }

            $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
            if ( ! $repo_class ) {
                throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
            }
            
            $url = $repo_class->upload_asset( $app_slug, $asset_file, $asset_type, $asset_name );

            if ( is_smliser_error( $url ) ) {
                throw new RequestException( $url->get_error_code() ?: 'remote_download_failed', $url->get_error_message() );
            }

            \smliser_cache()->clear();
            
            $url = ( new URL( $url ) )
                ->add_query_param( 'ver', time() )
            ->__toString();

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
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Handles an application's asset deletion using a standardized Request object.
     *
     * @param Request $request The standardized request object.
     * @return Response Returns a Response object on success.
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

            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException(
                    'invalid_input',
                    sprintf( 'The app type "%s" is not supported', $app_type )
                );
            }

            $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
            if ( ! $repo_class ) {
                throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
            }

            $result = $repo_class->delete_asset( $app_slug, $asset_name );

            if ( is_smliser_error( $result ) ) {
                throw new RequestException( $result->get_error_code() ?: 'asset_deletion_failed', $result->get_error_message() );
            }
            \smliser_cache()->clear();
            $data       = array( 'message' => 'Asset deleted successfully.' );
            $response   = [
                'success'   => true,
                'data'      => $data
            ];

            return ( new Response( 200, array(), smliser_safe_json_encode( $response ) ) )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );

        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Perform a status change on a hosted app.
     *
     * @param Request $request The request object.
     * @return Response
     */
    public static function change_app_status( Request $request ) : Response {
        try {
            if ( ! $request->is_authorized() ) {
                throw new RequestException( 'unauthorized_request', 'You do not have the required permission to perform this operation' , array( 'status' => 403 ) );
            }

            $app_slug   = $request->get( 'slug' );
            $app_type   = $request->get( 'type' );

            $app        = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new RequestException( 'resource_not_found', sprintf( 'The %s with slug %s was not found', $app_type, $app_slug ), array( 'status' => 404 ) );
            }

            $status             = $request->get( 'status' );
            $knowned_statuses   = array_keys( $app->get_statuses() );

            if ( ! in_array( $status, $knowned_statuses, true ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The status "%s" is not valid for the %s with slug "%s"', $status, $app_type, $app_slug ), array( 'status' => 400 ) );
            }

            $old_status = $app->get_status();
            $app->set_status( $status );

            if ( is_smliser_error( $app->save() ) ) {
                throw new RequestException( 'status_change_failed', sprintf( 'Failed to change status for the %s from %s to %s', $app_type, $old_status, $status ), array( 'status' => 500 ) );
            }

            // Deal with trashed apps.
            if ( in_array( AbstractHostedApp::STATUS_TRASH, array( $old_status, $status ), true ) ) {
                $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
                
                if ( ! $repo_class ) {
                    throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
                }

                if ( $old_status === AbstractHostedApp::STATUS_TRASH && $status !== AbstractHostedApp::STATUS_TRASH ) {
                    // Restoring from trash
                    @$repo_class->restore_from_trash( $app->get_slug() );
                } elseif ( $status === AbstractHostedApp::STATUS_TRASH ) {
                    // Moving to trash
                    @$repo_class->queue_app_for_deletion( $app->get_slug() );
                }

            }

            \smliser_cache()->clear();

            $data = array(
                'message'       => sprintf( '%s status changed from %s to %s', ucfirst( $app_type ), $old_status, $status ),
                'redirect_url'  => \smliser_admin_repo_tab( 'view', array( 'type' => $app->get_type(), 'app_id' => $app->get_id()) )
            );
            $response   = [
                'success'   => true,
                'data'      => $data
            ];

            return ( new Response( 200, array(), smliser_safe_json_encode( $response ) ) )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );

        }  catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Perform bulk action on hosted apps
     * 
     * @param Request $request The request object.
     * @return Response
     */
    public static function app_bulk_action( Request $request ) : Response {
        $app_ids    = $request->get( 'ids', [] );
        $action     = $request->get( 'bulk_action' );
        $affected   = 0;

        foreach( (array) $app_ids as $data ) {
            $type   = $data['app_type'] ?? '';
            $slug   = $data['app_slug'] ?? '';

            if ( empty( $type ) || empty( $slug ) ) {
                continue;
            }

            $app    = HostedApplicationService::get_app_by_slug( $type, $slug );

            if ( ! $app ) {
                continue;
            }

            if ( 'delete' === strtolower( $action ) ) {
                $app->delete() && $affected++;
                continue;
            }

            $app->set_status( $action );
            $app->save() && $affected++;
        }

        $request->set( 'message', \sprintf( '%s affected!', $affected ) );
        $request->set( 'redirect_url', \smliser_repo_page() );
        $response = ( new Response( 200, [], '' ) )
            ->set_response_data( $request );

        \smliser_cache()->clear();
        return $response;
    }

}