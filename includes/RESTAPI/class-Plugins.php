<?php
/**
 * The plugin REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\RESTAPI;

use Smliser_Plugin;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'ABSPATH' ) || exit;

class Plugin {

    /**
     * Plugin info endpoint permission callback.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_Error|false WordPress error object if permission is denied, false otherwise.
     */
    public static function info_permission_callback( WP_REST_Request $request ) {
        /**
         * We handle the required parameters here.
         */
        $plugin_id  = $request->get_param( 'plugin_id' );
        $slug       = $request->get_param( 'slug' );

        if ( empty( $plugin_id ) && empty( $slug ) ) {
            return new WP_Error(
                'smliser_plugin_info_error',
                __( 'You must provide either the plugin ID or the plugin slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        // Let's identify the plugin.
        $plugin = Smliser_Plugin::get_plugin( $plugin_id ) ?? Smliser_Plugin::get_by_slug( $slug );

        if ( ! $plugin ) {
            $message = __( 'The plugin does not exist, please check the typography or the plugin slug.', 'smliser' );
            return new WP_Error( 'plugin_not_found', $message, array( 'status' => 404 ) );
        }

        $request->set_param( 'smliser_resource', $plugin );
        return true;

    }

    /**
     * Plugin info response handler.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response object.
     */
    public static function plugin_info( WP_REST_Request $request ) {
        /** @var Smliser_Plugin $plugin */
        $plugin = $request->get_param( 'smliser_resource' );

        $response = new WP_REST_Response( $plugin->formalize_response(), 200 );        
        return $response;

    }

}