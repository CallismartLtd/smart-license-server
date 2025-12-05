<?php
/**
 * The REST API bulk messages class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\classes
 */

namespace SmartLicenseServer\RESTAPI;

use SmartLicenseServer\Bulk_Messages,
WP_REST_Request,
WP_REST_Response,
WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * The REST API bulk messages class.
 */
class Bulk_Messages_API {
    /**
     * The bulk messages instance.
     * 
     * @var Bulk_Messages $bulk_messages
     */
    protected static $bulk_messages;

    /**
     * Keeps track of request start time.
     * 
     * @var float $request_start_time
     */
    public static $request_start_time;

    /**
     * Bulk messages permission callback.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return bool|WP_Error True if permitted, WP_Error otherwise.
     */
    public static function permission_callback( WP_REST_Request $request ) {
        self::$request_start_time = microtime( true );

        $page       = max( 1, (int) $request->get_param( 'page' ) );
        $limit      = max( 50, (int) $request->get_param( 'limit' ) );

        // Normalize array inputs.
        $app_slugs  = (array) $request->get_param( 'slug' );
        $app_types  = (array) $request->get_param( 'type' );

        // Remove empty strings or invalid entries.
        $app_slugs  = array_filter( $app_slugs, 'sanitize_text_field' );
        $app_types  = array_filter( $app_types, 'sanitize_text_field' );

        if ( empty( $app_types ) && empty( $app_slugs ) ) {
            self::$bulk_messages = Bulk_Messages::get_all( compact( 'page', 'limit' ) );
        } else {
            self::$bulk_messages = Bulk_Messages::get_for_slugs( compact( 'app_slugs', 'app_types', 'page', 'limit' ) );
        }

        if ( empty( self::$bulk_messages ) ) {
            return new WP_Error( 'no_bulk_messages', 'No bulk messages found.', [ 'status' => 404 ] );
        }

        return true;
    }

    /**
     * Dispatch bulk messages response.
     * 
     * @param WP_REST_Request $request The REST API request object.
     * @return WP_REST_Response The REST API response object.
     */
    public static function dispatch_response( WP_REST_Request $request ) {
        $data = array();

        foreach ( self::$bulk_messages as $message ) {
            $data[] = $message->to_array();
        }

        $response = new WP_REST_Response( $data );
        $response->set_status( 200 );

        return $response;
    }
}