<?php
/*
Plugin Name: Smart license Server
Description: license REST API server for WordPress premium plugins.
Version: 1.0
Author: Callistus Nwachukwu
*/
defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_PATH' ) ) {
    return;
} 

define( 'SMLISER_PATH', __DIR__ . '/' );

require_once SMLISER_PATH . 'includes/class-license-server.php';
smartwoo_license_server::instance();

// Register REST API endpoint
add_action( 'rest_api_init', 'smliser_validator_route' );

function smliser_validator_route() {
    register_rest_route( 'smartwoo-api/v1', '/license-validator/', array(
        'methods'             => 'GET',
        'callback'            => 'smartwoo_handle_api_request',
        'permission_callback' => 'smartwoo_check_api_permission',
    ) );
}

// Register REST API endpoint
add_action( 'rest_api_init', 'smliser_deactivation_route' );

function smliser_deactivation_route() {
    register_rest_route( 'smartwoo-api/v1', '/license-deactivator/', array(
        'methods'             => 'GET',
        'callback'            => 'smartwoo_handle_api_request',
        'permission_callback' => 'smartwoo_check_api_permission',
    ) );
}

function smartwoo_check_api_permission( $request ) {
    // Get the Authorization header from the request
    $authorization_header = $request->get_header( 'authorization' );
    $service_id     =  $request->get_param( 'service_id' );
    $item_id        =  $request->get_param( 'item_id' );
    $license_key    =  $request->get_param( 'licence_key' );
    $callback_url   =  $request->get_param( 'callback_url' );
    
    if ( empty( $authorization_header ) ) {
        return false;
    }

    $authorization_parts = explode( ' ', $authorization_header );

    if ( count( $authorization_parts ) !== 2 && $authorization_parts[0] !== 'SmartWoo' ) {
        return false;
    }

    if ( 
         empty( $service_id ) 
         && empty( $service_id ) 
         && empty( $item_id ) 
         && empty( $license_key ) 
         && empty( $callback_url ) 
        ) {
            return false;
    }
    

    return true;
}

function smartwoo_handle_api_request( $request ) {
    $request_params = $request->get_params();
    $service_id     = $request_params['service_id'];
    $license_key    = $request_params['license_key'];
    $item_id        = $request_params['item_id'];
    $callback_url   = $request_params['callback_url'];
    $authorization_header   = $request->get_header( 'authorization' );
    $token_parts    = explode( ' ', $authorization_header );
    $token          = $token_parts[1];
    $license_data   = get_license_data( $service_id, $license_key, $item_id );
    
    if ( ! $license_data ) {
        $response_data = array(
            'code'      => 'license_error',
            'message'   => 'Invalid License key or service ID'
        );
        $response = new WP_REST_Response( $response_data, 404 );
        $response->header( 'Content-Type', 'application/json' );

        return $response;
    }

    if ( $license_data['expiry_date'] <= current_time( 'Y-m-d' ) ) {
        $response_data = array(
            'code'      => 'license_expired',
            'message'   => 'License has expired, log into you account to renew your license'
        );
        $response = new WP_REST_Response( $response_data, 402 );
        $response->header( 'Content-Type', 'application/json' );

        return $response;
    }

    $encoded_data   = json_encode( $license_data );
    $waiting_period = generate_wait_period();
    $local_duration = preg_replace( '/\D/', '', $waiting_period );
    // add new task.
    $license_server = new smartwoo_license_server();
    $license_server->add_task_queue(
        $local_duration, array(
            'licence_key'   => $license_key,
            'token'         => $token,
            'expiry_date'   => $license_data['expiry_date'],
            'callback_url'  => $callback_url,
            'data'          => $encoded_data

        )
    );


    $response_data = array(
        'waiting_period' => $waiting_period,
        'message'           => 'License is being validated',
    );
    $response = new WP_REST_Response( $response_data, 200 );

}
 
function get_license_data( $anything, $license_key ) {
    $service_id = 'CWS-6578nndjs';
    $lkey       = '1234567890';

    $data = array(
        'license_key' => '1234567890',
        'service_id'   => 'CWS-6578nndjs',
        'item_id'   => 987654,
        'expiry_date' => '2024-05-17',
    );

    if ( $anything === $service_id  && $lkey === $license_key ) {
        return $data;
    }
    return false;
}

function generate_wait_period() {
    
    $random_seconds = rand( 60, 600 );

    // Format the random seconds into ISO 8601 duration format
    $wait_duration = 'PT' . $random_seconds . 'S';

    return $wait_duration;
}
