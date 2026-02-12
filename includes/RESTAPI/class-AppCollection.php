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
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Security\Context\SecurityGuard;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Dedicated WordPress REST API endpoint for perform CRUD operations on hosted apps. 
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

        return new RequestException( 'not_implemented' );

        $actor  = SecurityGuard::get_principal();

        if ( ! $actor ) {
            // Actor is not authenticated.
            return new RequestException( 'Unauthorized' );
        }

        $has_permission = match( $request->method() ) {
            'POST'   => $actor->can( 'hosted_apps.create' ),
            'DELETE' => $actor->can( 'hosted_apps.delete' ),
            'UPDATE' => $actor->can( 'hosted_apps.update' ),
            default  => false,
        };

        return $has_permission ?: new RequestException( 'permission_denied' );
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
     * Responds to GET HTTP request method on the single app route.
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
}



