<?php
/**
 * The Repository REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 1.0.0
 */
namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Collection;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostingController;
use SmartLicenseServer\Security\Context\SecurityGuard;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Dedicated REST API endpoint for perform CRUD operations on hosted apps. 
 */
class AppCollection {
    use CacheAwareTrait;

    /**
     * Guards the repository route against HTTP `Non-Safe Methods`.
     *
     * @param Request $request The current request object.
     * @return bool
     */
    public static function repository_get_guard( Request $request ) : bool|RequestException {
        if ( $request->is_method( 'GET' ) ) {
            return true;
        }

        // 405 errror.
        return new RequestException( 'method_not_allowed' );
    }

    /**
     * Guard against GET methods and safely check permissions on HTTP `Non-Safe Methods`.
     * 
     * @param Request $request The request object.
     * @return bool|RequestException
     */
    public static function repository_unsafe_method_guard( Request $request ) : bool|RequestException {
        if ( $request->is_method( 'GET' ) ) {
            // Edge-case 405 error.
            return new RequestException( 'method_not_allowed' );
        }

        $actor  = SecurityGuard::get_principal();

        if ( ! $actor ) {
            // Actor is not authenticated, return 401 response code.
            return new RequestException( 'missing_auth' );
        }

        $content_type   = strtolower( $request->contentType() );

        if ( ! \str_contains( $content_type, 'multipart/form-data' ) && ! $request->isDelete() ) {
            return new RequestException( 'requires_multipart_form_data' );
        }

        $has_permission = match( $request->method() ) {
            'POST'          => $actor->can( 'hosted_apps.create' ),
            'DELETE'        => $actor->can( 'hosted_apps.delete' ),
            'PUT', 'PATCH'  => $actor->can( 'hosted_apps.update' ),
            default         => false,
        };

        // Allow or deny with 403 response code.
        return $has_permission ?: new RequestException( 'unauthorized_scope' );
    }

    /**
     * Repository REST API handler.
     *
     * @param Request $request The current request object.
     * @return Response
     */
    public static function repository_response( Request $request ) : Response {
        $search = $request->get( 'search' );
        $page   = $request->get( 'page' ) ?? 1;
        $limit  = $request->get( 'limit' ) ?? 25;
        $status = $request->get( 'status' ) ?: AbstractHostedApp::STATUS_ACTIVE;
        $types  = $request->get( 'types' ) ?: array( 'plugin','theme','software' );

        $args   = $search ? array(
            'term'   => $search,
            'page'   => $page,
            'limit'  => $limit,
            'status' => $status,
            'types'  => (array) $types,
        ): array(
            'page'   => $page,
            'limit'  => $limit,
            'status' => $status,
            'types'  => (array) $types,
        );

        $cache_key  = static::make_cache_key( __METHOD__, $args );
        $data       = static::cache_get( $cache_key );

        if ( false === $data ) {
            // Query repository.
            $results = $search ? HostedApplicationService::search_apps( $args ) : HostedApplicationService::get_apps( $args );
            
            if ( ! empty( $results['items'] ) ) {
                foreach ( $results['items'] as &$app ) {                    
                    $app    = $app->get_rest_response();
                }
            }

            $data = array(
                'apps'       => $results['items'],
                'pagination' => $results['pagination'],
            );

            static::cache_set( $cache_key, $data, 2 * \HOUR_IN_SECONDS );
        }



        return new Response( 200, array(), $data );
    }

    /**
     * Responds to HTTP GET request method on the single app route.
     * This method responds to /repostory/{app-type}/{app-slug}/ 
     * path of the our REST API namespace 
     * 
     * @param Request $request The REST API request object.
     */
    public static function single_app_get( Request $request ) : RequestException|Response {
        $app_type   = $request->get( 'app_type' );
        $app_slug   = $request->get( 'app_slug' );

        $app        = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

        if ( ! $app ) {
            return new RequestException( 'app_not_found', __( 'The requested app could not be found', 'smliser' ), ['status' => 404] );
        }

        AppsAnalytics::log_client_access( $app, \sprintf( '%s_info', $app->get_type() ) );
        return new Response( 200, array(), $app->get_rest_response() );
    }

    /**
     * Responds to HTTP POST request on single app route.
     * 
     * @param Request $request The request object
     * @return Response
     */
    public static function create_app( Request $request ) : Response {
        try {
            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );

            $app_exists = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( $app_exists ) {
                throw new RequestException( 'app_slug_exists' );
            }

            $request->set( 'app_slug', $app_slug )
                    ->set( 'app_type', $app_type );

            $response = HostingController::save_app( $request );

            if ( $response->ok() ) {
                $resource_location  = $request->path();

                $response->set_header( 'Location', $resource_location );
                $response->set_status_code( 201 );
                $app            = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );
                $response_body  = $app->get_rest_response();
                
                $response->set_body( $response_body );

                static::cache_clear();
            }
            
            return $response;
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }

    }

    /**
     * Responds to HTTP PATCH and PUT request on single app route.
     * 
     * @param Request $request The request object.
     * @return Response
     */
    public static function update_app( Request $request ) : Response {
        try {
            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );

            $app_exists = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app_exists ) {
                if ( $request->isPut() ) {
                    return static::create_app( $request );
                }

                throw new RequestException( 'app_not_found' );
            }

            $request->set( 'app_slug', $app_slug )
            ->set( 'app_type', $app_type );

            $response = HostingController::save_app( $request );

            if ( $response->ok() ) {
                $response->remove_header( 'Location' );
                $response->set_status_code( 204 );
                
                $response->set_body( '' );
                static::cache_clear();
            }

            return $response;
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Responds to HTTP DELETE request to delete a hosted application.
     * 
     * @param Request $request The request object.
     * @return Response
     */
    public static function delete_app( Request $request ) : Response {
        try {
            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );

            $app_exists = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app_exists ) {
                throw new RequestException( 'app_not_found' );
            }

            $request->set( 'app_slug', $app_slug )
                    ->set( 'app_type', $app_type )
                    ->set( 'app_status', AbstractHostedApp::STATUS_TRASH );

            $response   = HostingController::change_app_status( $request );

            if ( $response->ok() ) {
                $response->set_status_code( 204 )         
                ->set_body( '' );
                
                static::cache_clear();
            }

            return $response;
        } catch ( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }

    /**
     * Guards asset upload for an application.
     * 
     * @param Request $request The request object.
     * @return RequestException|bool
     */
    public static function assets_management_guard( Request $request ) : RequestException|bool {
        if ( $request->is_method( 'GET' ) ) {
            // Edge-case 405 error.
            return new RequestException( 'method_not_allowed' );
        }

        $actor  = SecurityGuard::get_principal();

        if ( ! $actor ) {
            // Actor is not authenticated, return 401 response code.
            return new RequestException( 'missing_auth' );
        }

        $content_type   = strtolower( $request->contentType() );

        if ( ! \str_contains( $content_type, 'multipart/form-data' ) && ! $request->isDelete() ) {
            return new RequestException( 'requires_multipart_form_data' );
        }

        $has_permission = match( $request->method() ) {
            'POST'      => $actor->can( 'hosted_apps.upload_assets' ),
            'DELETE'    => $actor->can( 'hosted_apps.delete_assets' ),
            'PUT',      => $actor->can( 'hosted_apps.edit_assets' ),
            default     => false,
        };

        // Allow or deny with 403 response code.
        return $has_permission ?: new RequestException( 'unauthorized_scope' );
    }

    /**
     * Upload app assets.
     * 
     * @param Request $request The request object.
     * @return Response
     */
    public static function upload_app_assets( Request $request ) : Response {
        try {
            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );

            $app_exists = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app_exists ) {
                throw new RequestException( 'app_not_found' );
            }

            $response   = HostingController::app_asset_upload( $request );

            if ( $response->ok() ) {
                // $response_body  = (array) $response->get_body();
                // $data           = $response_body['data']['config'] ?? [];
                // $url            = $data['asset_url'] ?? '';

                // $response->set_header( 'Location', $url );
                // $new_body   = Collection::make( $data )
                //     ->only( ['app_slug', 'app_type', 'asset_name', 'asset_url'] );
                // $response->set_body( $new_body->toArray() );
            }

            return $response;     
        } catch( RequestException $e ) {
            return ( new Response() )
                ->set_exception( $e )
                ->set_header( 'Content-Type', \sprintf( 'application/json; charset=%s', \smliser_settings_adapter()->get( 'charset', 'UTF-8' ) ) );
        }
    }
}



