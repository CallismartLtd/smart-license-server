<?php
/**
 * The plugin REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\Plugin;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Handles REST API Requests for hosted plugins.
 */
class Plugins {

    /**
     * Plugin info endpoint permission callback.
     * 
     * @param Request $request The REST API request object.
     * @return RequestException|false Error object if permission is denied, false otherwise.
     */
    public static function info_permission_callback( Request $request ) : RequestException|bool {
        /**
         * We handle the required parameters here.
         */
        $plugin_id  = $request->get( 'id' );
        $slug       = $request->get( 'slug' );

        if ( empty( $plugin_id ) && empty( $slug ) ) {
            return new RequestException(
                'smliser_plugin_info_error',
                __( 'You must provide either the plugin ID or the plugin slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        
        $arg    = $plugin_id ? $plugin_id : $slug;
        
        $method = $plugin_id ? "get_plugin" : "get_by_slug";
        /** @var \SmartLicenseServer\HostedApps\AbstractHostedApp|null $plugin */
        $plugin = Plugin::$method( $arg );

        if ( ! $plugin ) {
            $message = __( 'The plugin does not exist, please check the typography or the plugin slug.', 'smliser' );
            return new RequestException( 'plugin_not_found', $message, array( 'status' => 404 ) );
        }

        $request->set( 'smliser_resource', $plugin );
        return true;

    }

    /**
     * Plugin info response handler.
     * 
     * @param Request $request The REST API request object.
     * @return Response The REST API response object.
     */
    public static function plugin_info_response( Request $request ) {
        /** @var Plugin $plugin */
        $plugin = $request->get( 'smliser_resource' );

        $response = new Response( 200, array(), $plugin->formalize_response() );
        AppsAnalytics::log_client_access( $plugin, 'plugin_info' );
        return $response;

    }

}