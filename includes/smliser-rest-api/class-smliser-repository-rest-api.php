<?php
/**
 * The Repository REST API class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Handles the repository REST API route.
 */
class Smliser_Repository_Rest_API {

    /**
     * Repository REST API Route permission handler
     *
     * @param WP_REST_Request $request The current request object.
     * @return bool
     */
    public static function repository_access_permission( WP_REST_Request $request ) {
        return true; // Public endpoint for now
    }

    /**
     * Repository REST API handler.
     *
     * @param WP_REST_Request $request The current request object.
     * @return WP_REST_Response
     */
    public static function repository_response( $request ) {
        $search = $request->get_param( 'search' );
        $page   = $request->get_param( 'page' ) ?? 1;
        $limit  = $request->get_param( 'limit' ) ?? 25;
        $status = $request->get_param( 'status' ) ?: 'active';
        $types  = $request->get_param( 'types' ) ?: array( 'plugin','theme','software' );

        // Query repository
        $results = $search
            ? Smliser_Software_Collection::search_apps( array(
                'term'   => $search,
                'page'   => $page,
                'limit'  => $limit,
                'status' => $status,
                'types'  => (array) $types,
            ) )
            : Smliser_Software_Collection::get_apps( array(
                'page'   => $page,
                'limit'  => $limit,
                'status' => $status,
                'types'  => (array) $types,
            ) );

        // Convert objects → REST-ready arrays
        $apps_data = array();
        if ( ! empty( $results['items'] ) ) {
            foreach ( $results['items'] as $app ) {
                
                $apps_data[] = $app->get_rest_response();
                
            }
        }

        $response = array(
            'apps'       => $apps_data,
            'pagination' => $results['pagination'],
        );

        return new WP_REST_Response( $response, 200 );
    }
}



