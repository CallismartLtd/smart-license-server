<?php
/**
 * Monetization Request Controller
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.0.5 
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * Handles all requests related to software monetization
 */
class Controller {
    /**
     * Initialize the controller.
     */
    public static function init() {
        add_action( 'wp_ajax_smliser_get_product_data', [ __CLASS__, 'get_provider_product' ] );
        add_action( 'wp_ajax_smliser_save_monetization_tier', [ __CLASS__, 'save_monetization' ] );
        add_action( 'wp_ajax_smliser_delete_monetization_tier', [ __CLASS__, 'delete_monetization_tier' ] );
        add_action( 'wp_ajax_smliser_toggle_monetization', [ __CLASS__, 'toggle_monetization' ] );
    }

    /**
     * Handle save monetization request
     */
    public static function save_monetization() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( [ 'message' => 'This action failed basic security check' ], 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'You do not have permission to perform this action' ], 403 );
        }

        // Collect POST params with required validation inline.
        $monetization_id = smliser_get_post_param( 'monetization_id', 0 );
        $item_id         = smliser_get_post_param( 'item_id', 0 );
        $tier_id         = smliser_get_post_param( 'tier_id', 0 );

        $item_type   = smliser_get_post_param( 'item_type' )
            ?: wp_send_json_error( [ 'message' => 'Item type is required', 'field_id' => 'item_type' ], 400 );

        $tier_name   = smliser_get_post_param( 'tier_name' )
            ?: wp_send_json_error( [ 'message' => 'Tier name is required', 'field_id' => 'tier_name' ], 400 );

        $product_id  = smliser_get_post_param( 'product_id' )
            ?: wp_send_json_error( [ 'message' => 'Product ID is required', 'field_id' => 'product_id' ], 400 );

        $billing_cycle = smliser_get_post_param( 'billing_cycle' )
            ?: wp_send_json_error( [ 'message' => 'Billing cycle is required', 'field_id' => 'billing_cycle' ], 400 );

        $provider_id = smliser_get_post_param( 'provider_id' )
            ?: wp_send_json_error( [ 'message' => 'Please select a monetization provider', 'field_id' => 'provider_id' ], 400 );

        $max_sites   = smliser_get_post_param( 'max_sites', -1 );

        $features    = smliser_get_post_param( 'features' )
            ?: wp_send_json_error( [ 'message' => 'Enter at least one feature', 'field_id' => 'features' ], 400 );

        $features_array = array_map( 'trim', explode( ',', $features ) );

        // Check provider exists.
        if ( ! Provider_Collection::instance()->has_provider( $provider_id ) ) {
            wp_send_json_error( [ 
                'message'  => 'The selected monetization provider does not exist.',
                'field_id' => 'provider_id'
            ], 400 );
        }

        // Fetch or create monetization object.
        $monetization = Monetization::get_by_item( $item_type, $item_id ) ?: new Monetization();
        $monetization->set_item_id( $item_id )
                    ->set_item_type( $item_type );

        // Fetch or create pricing tier.
        $pricing_tier = Pricing_Tier::get_by_id( $tier_id ) ?: new Pricing_Tier();
        $pricing_tier->set_monetization_id( $monetization_id )
                    ->set_id( $tier_id )
                    ->set_name( $tier_name )
                    ->set_product_id( $product_id )
                    ->set_billing_cycle( $billing_cycle )
                    ->set_provider_id( $provider_id )
                    ->set_max_sites( $max_sites )
                    ->set_features( $features_array );

        // Attach and persist.
        $monetization->add_tier( $pricing_tier );

        if ( $monetization->save() ) {
            wp_send_json_success( [ 'message' => 'Saved' ] );
        }

        wp_send_json_error( [ 'message' => 'Something went wrong' ] );
    }

    /**
     * Handle delete monetization tier request.
     */
    public static function delete_monetization_tier() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array(
                'message'  => 'This action failed basic security check.',
                'field_id' => 'security',
            ), 401 );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array(
                'message'  => 'You do not have permission to perform this action.',
            ), 403 );
        }

        $monetization_id = smliser_get_post_param( 'monetization_id', 0 );
        $tier_id         = smliser_get_post_param( 'tier_id', 0 );

        if ( ! $monetization_id ) {
            wp_send_json_error( array(
                'message'  => 'Monetization ID is required.',
                'field_id' => 'monetization_id',
            ), 400 );
        }

        if ( ! $tier_id ) {
            wp_send_json_error( array(
                'message'  => 'Tier ID is required.',
                'field_id' => 'tier_id',
            ), 400 );
        }

        $monetization = Monetization::get_by_id( $monetization_id );
        if ( ! $monetization ) {
            wp_send_json_error( array(
                'message'  => 'The specified monetization record does not exist.',
            ), 404 );
        }

        $deleted = $monetization->delete_tier( $tier_id );

        if ( ! $deleted ) {
            wp_send_json_error( array(
                'message'  => 'Failed to delete the pricing tier. Please try again.',
            ), 500 );
        }

        wp_send_json_success( array(
            'message' => 'Pricing tier deleted successfully.',
        ) );
    }

    /**
     * Handle Ajax request to fetch provider product data.
     *
     * @return void
     */
    public static function get_provider_product() {

        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array(
                'message'  => 'This action failed basic security check.',
                'field_id' => 'security',
            ), 401 );
        }

        $provider_id = smliser_get_query_param( 'provider_id' );
        $product_id  = smliser_get_query_param( 'product_id' );

        if ( empty( $provider_id ) ) {
            wp_send_json_error( array(
                'message'  => __( 'Provider ID is required.', 'smliser' ),
                'field_id' => 'provider_id',
            ) );
        }

        if ( empty( $product_id ) ) {
            wp_send_json_error( array(
                'message'  => __( 'Product ID is required.', 'smliser' ),
                'field_id' => 'product_id',
            ) );
        }

        // Resolve provider
        $provider = Provider_Collection::instance()->get_provider( $provider_id );
        
        if ( ! $provider ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid provider specified.', 'smliser' ),
                'field_id' => 'provider_id',
            ) );
        }

        // Fetch product data
        $product = $provider->get_product( $product_id );
        
        if ( empty( $product ) ) {
            wp_send_json_error( array(
                'message' => __( 'Product not found from provider.', 'smliser' ),
                'field_id' => 'product_id',
            ) );
        }

        $valid_product = Provider_Collection::validate_product_data( $product );

        if ( is_wp_error( $valid_product ) ) {
            wp_send_json_error( array(
                'message' => $valid_product->get_error_message(),
            ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Product data retrieved successfully.', 'smliser' ),
            'product' => $valid_product,
        ) );
    }

    /**
     * Handle monetization status toggling
     */
    public static function toggle_monetization() {

        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array(
                'message'  => __( 'Security check failed.', 'smliser' ),
                'field_id' => 'security',
            ), 401 );
        }

        $monetization_id = absint( smliser_get_post_param( 'monetization_id' ) );
        $enabled         = absint( smliser_get_post_param( 'enabled' ) );

        if ( ! $monetization_id ) {
            wp_send_json_error( array(
                'message'  => __( 'Invalid monetization ID.', 'smliser' ),
                'field_id' => 'monetization_id',
            ) );
        }

        $monetization = Monetization::get_by_id( $monetization_id );
        if ( ! $monetization ) {
            wp_send_json_error( array(
                'message'  => __( 'Monetization not found.', 'smliser' ),
                'field_id' => 'monetization_id',
            ) );
        }

        $monetization->set_enabled( $enabled );
        $monetization->save();

        wp_send_json_success( array(
            'message' => $enabled
                ? __( 'Monetization enabled successfully.', 'smliser' )
                : __( 'Monetization disabled successfully.', 'smliser' ),
        ) );
    }

}

Controller::init();