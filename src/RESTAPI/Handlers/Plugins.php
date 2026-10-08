<?php
/**
 * The plugin REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\RESTAPI
 */

namespace SmartLicenseServer\RESTAPI\Handlers;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\HostedApps\HostedApplicationService;

/**
 * Handles REST API Requests for hosted plugins.
 */
class Plugins extends DataStore {

    protected mixed $response_data = [];

    public function __construct(
        protected AppsAnalytics $apps_analytics
    ) {}

    /**
     * Plugin info endpoint permission callback.
     * 
     * @param Request $request The REST API request object.
     * @return RequestException|false Error object if permission is denied, false otherwise.
     */
    public function info_permission_callback( Request $request ) : RequestException|bool {
        /**
         * We handle the required parameters here.
         */
        $plugin_id  = (int) $request->get( 'id' );
        $slug       = (string) $request->get( 'slug' );

        if ( empty( $plugin_id ) && empty( $slug ) ) {
            return new RequestException(
                'smliser_plugin_info_error',
                __( 'You must provide either the plugin ID or the plugin slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        
        $arg    = $plugin_id ? $plugin_id : $slug;
        $cache_key  =   $this->make_cache_key( __METHOD__, [$arg] );

        /** @var \SmartLicenseServer\Exceptions\RequestException|false|array $data */
        $data   = $this->cache_get( $cache_key );

        if ( false === $data ) {
            if ( $plugin_id ) {
                $plugin = HostedApplicationService::get_app_by_id( 'plugin', $plugin_id );
            } else {
                $plugin = HostedApplicationService::get_app_by_slug( 'plugin', $slug );
            }

            if ( ! $plugin ) {
                $message    = __( 'The plugin does not exist, please check the typography or the plugin slug.', 'smliser' );
                $plugin     = new RequestException( 'plugin_not_found', $message, array( 'status' => 404 ) );
            } else {
                $this->apps_analytics->log_client_access(
                    $plugin,
                    'plugin_info',
                    $request->ip(),
                    $request->userAgent()
                );

                $data = [
                    'success'   => true,
                    'plugin'    => $plugin->get_rest_response()
                ];
            }

            $this->cache_set( $cache_key, $data, $this->default_ttl() );
        }

        if ( $data instanceof RequestException ) {
            return $data;
        }
        
        $request->set( 'smliser_resource', $data );
        return true;

    }

    /**
     * Plugin info response handler.
     * 
     * @param Request $request The REST API request object.
     * @return Response The REST API response object.
     */
    public static function plugin_info_response( Request $request ) {
        $response_data = $request->get( 'smliser_resource' );

        return Response::json( $response_data, 200 );
    }

}