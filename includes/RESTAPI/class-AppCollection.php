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
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\SmliserSoftwareCollection;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Dedicated WordPress REST API endpoint for perform CRUD operations on hosted apps. 
 */
class AppCollection {

    /**
     * Repository REST API Route permission handler
     *
     * @param WP_REST_Request $request The current request object.
     * @return bool
     */
    public static function repository_access_permission( WP_REST_Request $request ) : bool|WP_Error {
        if ( 'GET' === \strtoupper( $request->get_method() ) ) {
            return true;
        }

        return RESTAuthentication::authenticate( $request );
    }

    /**
     * Repository REST API handler.
     *
     * @param WP_REST_Request $request The current request object.
     * @return WP_REST_Response
     */
    public static function repository_response( $request ) : WP_REST_Response {
        $search = $request->get_param( 'search' );
        $page   = $request->get_param( 'page' ) ?? 1;
        $limit  = $request->get_param( 'limit' ) ?? 25;
        $status = $request->get_param( 'status' ) ?: AbstractHostedApp::STATUS_ACTIVE;
        $types  = $request->get_param( 'types' ) ?: array( 'plugin','theme','software' );

        // Query repository
        $results = $search
            ? SmliserSoftwareCollection::search_apps( array(
                'term'   => $search,
                'page'   => $page,
                'limit'  => $limit,
                'status' => $status,
                'types'  => (array) $types,
            ) )
            : SmliserSoftwareCollection::get_apps( array(
                'page'   => $page,
                'limit'  => $limit,
                'status' => $status,
                'types'  => (array) $types,
            ) );

        // Convert objects → REST-ready arrays
        $apps_data = array();
        if ( ! empty( $results['items'] ) ) {
            foreach ( $results['items'] as $app ) {
                AppsAnalytics::log_client_access( $app, 'app_listing' );
                $apps_data[] = $app->get_rest_response();
                
            }
        }

        $response = array(
            'apps'       => $apps_data,
            'pagination' => $results['pagination'],
        );

        return new WP_REST_Response( $response, 200 );
    }

    /**
     * Perform CRUD operation on a single hosted app
     * 
     * @param WP_REST_Request $request The REST API request object.
     */
    public static function single_app_crud( WP_REST_Request $request ) : WP_Error|WP_REST_Response {
        $app_type   = $request->get_param( 'app_type' );
        $app_slug   = $request->get_param( 'app_slug' );

        $app        = SmliserSoftwareCollection::get_app_by_slug( $app_type, $app_slug );

        if ( ! $app ) {
            return new WP_Error( 'app_not_found', __( 'The requested app could not be found', 'smliser' ), ['status' => 404] );
        }

        AppsAnalytics::log_client_access( $app, \sprintf( '%s_info', $app->get_type() ) );
        return new WP_REST_Response( $app->get_rest_response(), 200 );
    }
}



