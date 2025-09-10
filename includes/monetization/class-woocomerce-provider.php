<?php
/**
 * WooCommerce Monetization Provider.
 *
 * Fetches SmartWoo products from a WooCommerce site via the Store API.
 *
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Provider Class
 *
 * Implements Monetization_Provider_Interface for fetching SmartWoo products
 * from a WooCommerce store using the Store API.
 */
class WooCommerce_Provider implements Monetization_Provider_Interface {

    /**
     * WooCommerce site URL.
     *
     * @var string $store_url
     */
    protected $store_url = '';

    /**
     * Checkout URL.
     * 
     * @var string $checkout_endpoint
     */
    protected $checkout_endpoint = '';

    /**
     * Construct provider with target store URL.
     *
     */
    public function __construct() {
        $this->store_url            = sanitize_url( Provider_Collection::get_option( $this->get_id(), 'store_url' ), array( 'https' ) );
        $this->checkout_endpoint    = sanitize_text_field( wp_unslash( Provider_Collection::get_option( $this->get_id(), 'checkout_url' ) ) );
    }

    /**
     * {@inheritdoc}
     */
    public function get_id() {
        return 'woocommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function get_name() {
        return 'WooCommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function get_url() {
        return $this->store_url;
    }

    /**
     * Get a checkout URL for a given pricing product.
     *
     * @param string|int $product_id.
     * @param string $context The context, valid options are `edit` and `view`.
     * @return string  Checkout URL.
     */
    public function get_checkout_url( $product_id = '', $context = 'view' ) {

        if ( 'edit' === $context ) {
            return $this->checkout_endpoint;
        }

        $product_id = ! empty( $product_id ) ?  trailingslashit( $product_id ) : '{{product_id}}';
        $base_url   = ! empty( $this->store_url ) ? trailingslashit( $this->store_url ) : '';
        $checkout   = ! empty( $this->store_url ) ? trailingslashit( $this->checkout_endpoint ) : '';
        
        return $base_url . $checkout . $product_id;
    }

    /**
     * Get a single product by ID (with caching and error handling).
     *
     * @param string|int $product_id
     * @param bool       $force_refresh Optional. If true, bypass cache. Default false.
     * @return array|null
     */
    public function get_product( $product_id, $force_refresh = true ) {
        $product_id   = absint( $product_id );
        $cache_key    = 'smliser_wc_product_' . md5( $this->store_url . '_' . $product_id );
        $cache_expiry = 3 * HOUR_IN_SECONDS;

        // Return cached result unless bypassing
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $endpoint = $this->store_url . '/wp-json/wc/store/v1/products/' . $product_id;
        $response = wp_remote_get( $endpoint, [
            'timeout' => 30,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( sprintf(
                'WooCommerce_Provider::get_product() - Request failed for product %d. Error: %s Endpoint: %s',
                $product_id,
                $response->get_error_message(),
                $endpoint
            ) );
            return null;
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $code ) {
            error_log( sprintf(
                'WooCommerce_Provider::get_product() - Invalid response (%d) for product %d Endpoint: %s',
                $code,
                $product_id,
                $endpoint
            ) );
            return null;
        }

        $product = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $product ) ) {
            error_log( sprintf(
                'WooCommerce_Provider::get_product() - Failed to decode JSON for product %d',
                $product_id
            ) );
            return null;
        }

        $normalized = $this->normalize_product( $product );

        // Cache normalized product for future requests
        set_transient( $cache_key, $normalized, $cache_expiry );

        return $normalized;
    }


    /**
     * {@inheritdoc}
     */
    public function get_pricing( $product_id ) {
        $product = $this->get_product( $product_id );
        if ( ! $product ) {
            return [];
        }

        return isset( $product['pricing'] ) ? $product['pricing'] : [];
    }

    /**
     * Normalize a WooCommerce product into the required format.
     *
     * @param array $product Raw product data from WooCommerce Store API.
     * @return array Normalized product.
     */
    protected function normalize_product( array $product ) {
        $prices = $product['prices'] ?? [];

        // Currency block
        $currency = [
            'code'               => $prices['currency_code'] ?? 'USD',
            'symbol'             => $prices['currency_symbol'] ?? '$',
            'symbol_position'    => $this->determine_symbol_position( $prices ),
            'decimals'           => $prices['currency_minor_unit'] ?? 2,
            'decimal_separator'  => $prices['currency_decimal_separator'] ?? '.',
            'thousand_separator' => $prices['currency_thousand_separator'] ?? ',',
        ];

        // Pricing block
        $regular = isset( $prices['regular_price'] ) 
            ? floatval( $prices['regular_price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) 
            : 0;

        $sale    = isset( $prices['sale_price'] ) 
            ? floatval( $prices['sale_price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) 
            : 0;

        $price   = isset( $prices['price'] ) 
            ? floatval( $prices['price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) 
            : $regular;

        $pricing = [
            'price'         => $price,
            'regular_price' => $regular,
            'sale_price'    => $sale,
            'is_on_sale'    => $product['on_sale'] ?? false,
        ];

        return [
            'id'         => $product['id'] ?? 0,
            'permalink'  => $product['permalink'] ?? '',
            'currency'   => $currency,
            'pricing'    => $pricing,
            'checkout_url'  => $this->get_checkout_url( $product['id'] ?? '' ),
            'images'     => $product['images'] ?? [],
            'categories' => $product['categories'] ?? [],
        ];
    }

    /**
     * Infer currency symbol position from Store API fields.
     *
     * @param array $prices
     * @return string one of left, left_space, right, right_space
     */
    protected function determine_symbol_position( array $prices ) {
        $prefix = $prices['currency_prefix'] ?? '';
        $suffix = $prices['currency_suffix'] ?? '';

        if ( $prefix && trim( $prefix ) ) {
            return strpos( $prefix, ' ' ) !== false ? 'left_space' : 'left';
        }

        if ( $suffix && trim( $suffix ) ) {
            return strpos( $suffix, ' ' ) !== false ? 'right_space' : 'right';
        }

        return 'left'; // fallback
    }

    /**
     * Get WooCommerce Settings
     */
    public function get_settings() {
        return array(
            array(
                'label' => __( 'Store URL', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'store_url',
                    'value' => $this->get_url(),
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Checkout URL/Endpoint', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'checkout_url',
                    'value' => $this->get_checkout_url( '', 'edit' ),
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
        );
    }

    /**
     * WooCommerce allowed options
     */
    public function get_allowed_options() {
        return array(
            'store_url' => 'Store URL',
            'checkout_url' => 'Checkout URL/Endpoint',
        );
    }

}
