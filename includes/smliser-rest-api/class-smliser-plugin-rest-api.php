<?php
/**
 * The plugin REST API class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_Plugin_REST_API {
    /**
     * The plugin Object
     * 
     * @var Smliser_Plugin $plugin
     */
    protected $plugin;

    /**
     * A flag to monitor response duration.
     * 
     * @var int $start_time
     */
    protected $start_time = 0;

    /**
     * The current REST API user object.
     * 
     * @var Smliser_Rest_User $user
     */
    protected $user;
    
    /**
     * Instance of the class.
     * 
     * @var Smliser_Plugin_REST_API $instance
     */
    protected static $instance = null;

    /**
     * Class constructor.
     */
    private function __construct() {
        $this->plugin = new Smliser_Plugin();
    }

    /**
     * Get the instance of Smliser_Plugin_REST_API class.
     * 
     * @return Smliser_Plugin_REST_API
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Plugin info endpoint permission callback.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_Error|false WordPress error object if permission is denied, false otherwise.
     */
    public static function plugin_info_permission_callback( WP_REST_Request $request ) {
        self::instance();
        self::$instance->start_time = microtime( true );
        /**
         * We handle the required parameters here.
         */
        $item_id = $request->get_param( 'item_id' );
        $slug    = $request->get_param( 'slug' );

        if ( empty( $item_id ) && empty( $slug ) ) {
            return new WP_Error(
                'smliser_plugin_info_error',
                __( 'You must provide either the item ID or the plugin slug.', 'smliser' ),
                array( 'status' => 400 )
            );
        }

        // Let's identify the plugin.
        $plugin =  self::$instance->plugin->get_plugin( $item_id ) ? self::$instance->plugin->get_plugin( $item_id ) : self::$instance->plugin::get_plugin_by( 'slug', $slug );

        if ( ! $plugin ) {
            $message = __( 'The plugin does not exist, please check the typography or the plugin slug.', 'smliser' );
            $reasons = array(
                '',
                array( 
                    'route'         => Smliser_Stats::$plugin_update,
                    'status_code'   => 404,
                    'request_data'  => 'Plugin update response',
                    'response_data' => array( 'reason' => $message )
                )
            );
            do_action( 'smliser_stats', 'denied_access', '', '', $reasons );

            return new WP_Error( 'plugin_not_found', $message, array( 'status' => 404 ) );
        }

        self::$instance->plugin = $plugin;
        return true;

    }

    /**
     * Plugin info response handler.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response object.
     */
    public static function plugin_info( WP_REST_Request $request ) {
        $the_plugin = self::$instance->plugin;
        
        /**
         * Fires for stats syncronization.
         * 
         * @param string $context The context which the hook is fired.
         * @param Smliser_Plugin.
         */
        do_action( 'smliser_stats', 'plugin_update', $the_plugin );
        $response = new WP_REST_Response( $the_plugin->formalize_response(), 200 );
        $response->header( 'content-type', 'application/json' );
        
        return $response;

    }

}