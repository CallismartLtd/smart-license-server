<?php
/**
 * The theme REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\Theme;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Handles REST API Requests for hosted themes.
 */
class Themes {    
    use CacheAwareTrait;

    /**
     * Theme info endpoint permission callback.
     * 
     * @param Request $request The REST API request object.
     * @return RequestException|false Error object if permission is denied, false otherwise.
     */
    public static function info_permission_callback( Request $request ) : bool|RequestException {
        /**
         * We handle the required parameters here.
         */
        $theme_id   = $request->get( 'theme_id' );
        $slug       = $request->get( 'slug' );

        if ( empty( $theme_id ) && empty( $slug ) ) {
            return new RequestException(
                'smliser_theme_info_error',
                __( 'You must provide either the theme ID or the theme slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        $arg        = $theme_id ? $theme_id : $slug;
        $cache_key  =   static::make_cache_key( __METHOD__, [$arg] );

        /** @var \SmartLicenseServer\Exceptions\RequestException|false|array $theme */
        $theme      = static::cache_get( $cache_key );

        if ( false === $theme ) {
            $theme = Theme::get_theme( $theme_id ) ?? Theme::get_by_slug( $slug );

            if ( ! $theme ) {
                $message    = __( 'The theme does not exist.', 'smliser' );
                $theme      = new RequestException( 'theme_not_found', $message, array( 'status' => 404 ) );
            } else {
                AppsAnalytics::log_client_access( $theme, 'theme_info' ); 
                $theme  = $theme->get_rest_response();
            }

            static::cache_set( $cache_key, $theme, static::default_ttl() );

        }

        if ( $theme instanceof RequestException ) {
            return $theme;
        }

        $request->set( 'smliser_resource', $theme );

        return true;

    }

    /**
     * Theme info response handler.
     * 
     * @param Request $request The REST API request object.
     * @return Response The REST API response object.
     */
    public static function theme_info_response( Request $request ) : Response {
        /** @var array $theme */
        $theme = $request->get( 'smliser_resource' );

        $response = new Response( 200, array(), $theme );
        return $response;
    }

}