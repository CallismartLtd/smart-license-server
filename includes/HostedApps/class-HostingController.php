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
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\Plugin;
use SmartLicenseServer\HostedApps\Theme;
use SmartLicenseServer\HostedApps\Software;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Software Hosting Controller handles HTTP request and response for hosted applications.
 */
class HostingController {
    use SanitizeAwareTrait, SecurityAwareTrait;

    /*
    |---------------------------
    | CREATE OPERATION METHODS
    |---------------------------
    */

    /**
     * Responds to requests to create or update an application.
     * 
     * @param Request $request
     */
    public static function save_app( Request $request ) {
        try {
            static::check_permissions( 'hosted_apps.create', 'hosted_apps.update' );

            $app_type = $request->get( 'app_type', null );

            if ( ! $app_type ) {
                throw new RequestException( 'invalid_parameter_type', 'The app_type parameter is required.' , array( 'status' => 400 ) );
            }

            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" is not supported', $app_type ) , array( 'status' => 400 ) );
            }
            
            $app_id     = static::sanitize_int( $request->get( 'app_id', 0 ) );
            $app_slug   = static::sanitize_text( $request->get( 'app_slug' ) );
            $app_class  = HostedApplicationService::get_app_class( static::sanitize_text( $app_type ) );

            $init_method    = $app_id ? "get_{$app_type}" : "get_by_slug";
            $arg            = $app_id ? $app_id : $app_slug;

            if ( ! class_exists( $app_class ) || ! method_exists( $app_class, $init_method ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The app type "%s" class did not define the required method "%s::%s($id)."', $app_type, $app_class, $init_method ) , array( 'status' => 500 ) );
            }
            
            if ( $arg ) {
                $app    = $app_class::$init_method( $arg );
            } else {
                $app    = new $app_class;
            }

            if ( ! ( $app instanceof AbstractHostedApp ) ) {
                throw new RequestException( 'invalid_input', 'Sorry there is no app matching the provided slug or ID.' , array( 'status' => 404 ) );
            }

            static::check_app_ownership( $app );

            $name   = $request->get( 'app_name', null );

            if ( empty( $name ) && ! $app->get_id() ) {
                throw new RequestException( 'invalid_input', 'Application name is required.' , array( 'status' => 400 ) );
            }

            $author = $request->get( 'app_author', null );

            if ( empty( $author ) && ! $app->get_id() ) {
                throw new RequestException( 'invalid_input', 'Application author name is required.' , array( 'status' => 400 ) );
            }

            $app_zip_file   = $request->get_file( 'app_zip_file' );
            $author_url     = $request->get( 'app_author_url', '' );
            $version        = $request->get( 'app_version', '' );

            if ( ! $app->get_slug() && ! $request->isEmpty( 'app_slug' ) ) {
                $app->set_slug( $request->get( 'app_slug' ) );
            }

            $app->set_name( $name );
            $app->set_author( $author );
            $app->set_author_profile( $author_url );
            $app->set_version( $version );
            $app->set_file( $app_zip_file ?? '' );
        
            if ( $app->exists() ) {

                $update_method = "update_{$app_type}";

                if ( ! method_exists( __CLASS__, $update_method ) ) {
                    throw new RequestException( 'internal_server_error', sprintf( 'The update method for the application type "%s" was not found!', $app_type ) , array( 'status' => 500 ) );
                }

                $updated = self::$update_method( $app, $request );

                if ( is_smliser_error( $updated ) ) {
                    throw $updated;
                }

                if ( static::is_system_admin() && $request->hasValue( 'app_owner_id' ) ) {
                    $app->set_owner_id( $request->get( 'app_owner_id' ) );
                }
                
            } else if ( $request->hasValue( 'app_owner_id' ) ) {
                $app->set_owner_id( $request->get( 'app_owner_id' ) );
            } else {
                $app->set_owner_id( Guard::get_principal()?->get_owner()->get_id() ?? 0 );
            }

            $result = $app->save();

            if ( is_smliser_error( $result ) ) {
                throw new RequestException(
                    $result->get_error_code() ?: 'save_failed', $result->get_error_message(),
                    $result->get_error_data()
                );
            }

            if ( ! $request->isEmpty( 'app_status' ) && AbstractHostedApp::STATUS_TRASH !== $request->get( 'app_status' ) ) {
                static::change_app_status( $request, $app );
            }

            \smliser_cache()->clear();

            $data = array(
                'success'   => true,
                'data'      => array(
                    'message' => sprintf( '%s Saved', ucfirst( $app_type ) ),
                    'redirect_url' => smliser_admin_repo_tab( 'edit', array( 'type' => $app_type, 'app_id' => $app->get_id() )
                )
            ));

            $request->set( 'smliser_resource', $app );
            return ( new Response( 200, array(), $data ) )
            ->set_response_data( $request )
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

        $manifest   = $request->get_file( 'app_json_file' );

        // Manifest file for WordPress plugins are optional, if valid manifest file is uploaded,
        // update the plugin manifest.
        if ( $manifest && $manifest->is_upload_successful() ) {
            $plugin_manifest = static::extract_app_json_content( $request );

            if ( $plugin_manifest instanceof RequestException ) {
                return $plugin_manifest;
            }

            $plugin->set_manifest( $plugin_manifest );
        }

        $plugin->set_download_url( $request->getTyped( 'app_download_url', 'string', '' ) );
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

        // Manifest file for WordPress themes are optional, if valid manifest file is uploaded,
        // update the theme manifest.
        $manifest   = $request->get_file( 'app_json_file' );

        if ( $manifest && $manifest->is_upload_successful() ) {
            $theme_manifest = static::extract_app_json_content( $request );

            if ( $theme_manifest instanceof RequestException ) {
                return $theme_manifest;
            }

            $theme->set_manifest( $theme_manifest );
        }
    
        $theme->set_download_url( $request->getTyped( 'app_download_url', 'string', '' ) );
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

        $manifest   = static::extract_app_json_content( $request );

        // Manifest file is required during software update.
        if ( $manifest instanceof RequestException ) {
            return $manifest;
        }

        $software->set_manifest( $manifest );
        $software->set_download_url( $request->getTyped( 'app_download_url', 'string', '' ) );
        $software->update_meta( 'support_url', $request->get( 'app_support_url' ) );
        $software->update_meta( 'homepage_url', $request->get( 'app_homepage_url', null ) );
        $software->update_meta( 'documentation_url', $request->get( 'app_documentation_url', null ) );
        return true;
    }

    /**
     * Helper method to extract the app.json file contents from the request.
     * 
     * @param Request $request The request object.
     * @return RequestException|array Returns the app.json contents as an associative array on success, or a RequestException on failure.
     */
    private static function extract_app_json_content( Request $request ) : RequestException|array {
        $uploaded_json = $request->get_file( 'app_json_file' );

        if ( ! $uploaded_json || ! $uploaded_json->is_upload_successful() ) {
            return new RequestException(
                'missing_file',
                'Please upload app.json file using "app_json_file" key in your file parameter.'
            );
        }

        if ( ! $uploaded_json->is_uploaded_file() ) {
            return new RequestException(
                'missing_file',
                'app.json files must be correctly uploaded.'
            );
        }

        if ( 'json' !== $uploaded_json->get_canonical_extension() ) {
            return new RequestException(
                'missing_file',
                'app.json files must be a json file.'
            );
        }

        $contents = $uploaded_json->get_contents();

        if ( false === $contents ) {
            return new RequestException( 'file_read_error', 'Unable to read uploaded app.json file.' );
        }

        $manifest = json_decode( $contents, true );

        if ( ! is_array( $manifest ) ) {
            return new RequestException( 'invalid_app_json', 'Invalid app.json file. JSON could not be parsed.' );
        }

        return (array) static::sanitize_deep( $manifest );
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
            static::check_permissions( 'hosted_apps.upload_assets', 'hosted_apps.edit_assets' );

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_type = $request->get( 'asset_type' );
            $asset_name = $request->get( 'asset_name', '' );

            $missing    = [];
            foreach ( \compact( 'app_slug', 'app_type', 'asset_type' ) as $key => $value ) {
                if ( empty( $value ) ) {
                    $missing[] = $key;
                }
            }

            if ( ! empty( $missing ) ) {
                throw new RequestException(
                    'required_param',
                    sprintf(
                        'Missing required parameters: %s',
                        implode( ', ', $missing )
                    )
                );
            }

            unset( $missing );
            
            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException( 
                    'invalid_input', 
                    sprintf( 'The app type "%s" is not supported.', $app_type ) 
                );
            }

            $app    = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new RequestException( 'app_not_found' );
            }

            static::check_app_ownership( $app );

            $asset_file = $request->get_files( 'asset_file' );
            
            if ( ! $asset_file || ! ( $asset_file->count() > 0 ) ) {
                throw new RequestException( 'invalid_input', 'Upload at least one asset using "asset_file" or "asset_file[]" key in your file parameter name.' );
            }

            $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
            
            if ( ! $repo_class ) {
                throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
            }
            
            if ( $request->isPost() ) {
                $result = $repo_class->safe_assets_upload( $app_slug, $asset_file, $asset_type );

            } else {
                static::check_permissions( 'hosted_apps.edit_assets' );

                $result = $repo_class->put_app_asset( $app_slug, $asset_file->get(0), $asset_type );
            }

            \smliser_cache()->clear();
            
            $response   = [
                'success'   => true,
                'result'    => $result
            ];

            return ( new Response( 200, array(), $response ) )
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
            static::check_permissions( 'hosted_apps.delete_assets' );

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_name = $request->get( 'asset_name' );

            $missing    = [];
            foreach ( \compact( 'app_slug', 'app_type' ) as $key => $value ) {
                if ( empty( $value ) ) {
                    $missing[] = $key;
                }
            }

            if ( ! empty( $missing ) ) {
                throw new RequestException(
                    'required_param',
                    sprintf(
                        'Missing required parameters: %s',
                        implode( ', ', $missing )
                    ),
                    ['status' => 400]
                );
            }

            unset( $missing );

            if ( ! HostedApplicationService::app_type_is_allowed( $app_type ) ) {
                throw new RequestException(
                    'invalid_input',
                    sprintf( 'The app type "%s" is not supported', $app_type ),
                    ['status' => 400]
                );
            }

            $app    = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new RequestException( 'app_not_found' );
            }

            static::check_app_ownership( $app );

            $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
            
            if ( ! $repo_class ) {
                throw new RequestException(
                    'internal_server_error',
                    'Unable to resolve repository class.',
                    ['status' => 500]
                );
            }

            $result = $repo_class->delete_asset( $app_slug, $asset_name );

            if ( is_smliser_error( $result ) ) {
                throw new RequestException(
                    $result->get_error_code() ?: 'asset_deletion_failed',
                    $result->get_error_message(),
                    $result->get_error_data()
                );
            }

            \smliser_cache()->clear();

            $data       = array( 'message' => 'Asset deleted successfully.' );
            $response   = [
                'success'   => true,
                'data'      => $data
            ];

            return ( new Response( 200, array(), $response ) )
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
    public static function change_app_status( Request $request, ?AbstractHostedApp $app = null ) : Response {
        try {
            static::check_permissions( 'hosted_apps.change_status' );

            $app_slug   = $request->get( 'app_slug' );
            $app_type   = $request->get( 'app_type' );
            $app        = $app ?? HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new RequestException( 'resource_not_found', sprintf( 'The %s with slug %s was not found', $app_type, $app_slug ), array( 'status' => 404 ) );
            }

            static::check_app_ownership( $app );

            $status             = $request->get( 'app_status' );
            $knowned_statuses   = array_keys( $app->get_statuses() );

            if ( ! in_array( $status, $knowned_statuses, true ) ) {
                throw new RequestException( 'invalid_input', sprintf( 'The status "%s" is not valid for the %s with slug "%s"', $status, $app_type, $app_slug ), array( 'status' => 400 ) );
            }

            $old_status = $app->get_status();
            if ( $old_status === $status ) {
                throw new RequestException( 'precondition_failed', sprintf( 'Status is already "%s".', $status ) );
            }
            
            $app->set_status( $status );
            $saved  = $app->save();

            if ( is_smliser_error( $saved ) ) {
                throw new RequestException( 'status_change_failed', sprintf( 'Failed to change status for the %s from %s to %s, error: %s', $app_type, $old_status, $status, $saved->get_error_message() ), array( 'status' => 500 ) );
            }

            // Deal with trashed apps.
            if ( in_array( AbstractHostedApp::STATUS_TRASH, array( $old_status, $status ), true ) ) {
                static::check_permissions( 'hosted_apps.delete' );

                $repo_class = HostedApplicationService::get_app_repository_class( $app_type );
                
                if ( ! $repo_class ) {
                    throw new RequestException( 'internal_server_error', 'Unable to resolve repository class.' );
                }

                if ( $old_status === AbstractHostedApp::STATUS_TRASH && $status !== AbstractHostedApp::STATUS_TRASH ) {
                    // Restoring from trash.
                    $repo_class->restore_from_trash( $app->get_slug() );
                } elseif ( $status === AbstractHostedApp::STATUS_TRASH ) {
                    // Moving to trash.
                    $repo_class->queue_app_for_deletion( $app->get_slug() );
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
        try {
            static::is_system_admin();

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

            $url = new URL( smliser_repo_page() );
            $url->add_query_param( 'message', \sprintf( '%s affected!', $affected ) );

            $response = ( new Response( 200, [], '' ) )
                ->set_response_data( $request )
                ->set_header( 'Location', $url->get_href() );

            \smliser_cache()->clear();
            return $response;
        } catch ( Exception $e ) {
            $url = new URL( smliser_repo_page() );
            $url->add_query_param(
                'message', 
                \sprintf( 'Error: %s', $e->get_error_message() )
            );

            return ( new Response() )
                ->set_header( 'Location', $url->get_href() );
                
        }

    }

}