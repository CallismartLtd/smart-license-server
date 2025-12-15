<?php
/**
 * The theme REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\HostedApps\Theme;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Handles REST API Requests for hosted themes.
 */
class Themes {    
    /**
     * Theme info endpoint permission callback.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_Error|false WordPress error object if permission is denied, false otherwise.
     */
    public static function info_permission_callback( WP_REST_Request $request ) {
        /**
         * We handle the required parameters here.
         */
        $theme_id   = $request->get_param( 'theme_id' );
        $slug       = $request->get_param( 'slug' );

        if ( empty( $theme_id ) && empty( $slug ) ) {
            return new WP_Error(
                'smliser_theme_info_error',
                __( 'You must provide either the theme ID or the theme slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        // Let's identify the theme.
        $theme = Theme::get_theme( $theme_id ) ?? Theme::get_by_slug( $slug );

        if ( ! $theme ) {
            $message = __( 'The theme does not exist.', 'smliser' );
            return new WP_Error( 'theme_not_found', $message, array( 'status' => 404 ) );
        }

        $request->set_param( 'smliser_resource', $theme );

        return true;

    }

    /**
     * Theme info response handler.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response object.
     */
    public static function theme_info_response( WP_REST_Request $request ) {
        /** @var Theme $theme */
        $theme = $request->get_param( 'smliser_resource' );

        $response = new WP_REST_Response( $theme->get_rest_response(), 200 );
        AppsAnalytics::log_client_access( $theme, 'theme_info' );    
        return $response;
    }

}