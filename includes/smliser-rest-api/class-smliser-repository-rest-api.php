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
 * Handles the respository REST API route. 
 */
class Smliser_Repository_Rest_API {
    /**
     * Repository REST API Route permission handler
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function repository_access_permission( WP_REST_Request $request ) {
        return true;
    }

    /**
     * Repository REST API handler
     * 
     * @param WP_REST_Request $request The current request object.
     */
    public static function repository_response( $request ) {
        $all_plugins    = Smliser_plugin::get_plugins();
        $plugins_info   = array();
        if ( ! empty( $all_plugins ) ) {
            foreach ( $all_plugins  as $plugin ) {
                $plugins_info[] = $plugin->formalize_response();
            }
        }
        
        $response = array(
            'plugins'   => $plugins_info,

        );
    
        return new WP_REST_Response( $response, 200 );

    }

}