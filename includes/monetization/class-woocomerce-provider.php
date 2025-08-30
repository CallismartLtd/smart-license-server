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
     * @var string
     */
    protected $store_url = 'https://callismart.local';

    /**
     * Construct provider with target store URL.
     *
     */
    public function __construct() {}

    /**
     * {@inheritdoc}
     */
    public function get_provider_id() {
        return 'woocommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function get_provider_name() {
        return 'WooCommerce';
    }

    /**
     * {@inheritdoc}
     */
    public function get_provider_url() {
        return $this->store_url;
    }

    /**
     * {@inheritdoc}
     */
    public function get_products() {
        $endpoint = $this->store_url . '/wp-json/wc/store/v1/products?type=sw_product';
        $response = wp_remote_get( $endpoint );

        if ( is_wp_error( $response ) ) {
            return [];
        }

        $products = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $products ) ) {
            return [];
        }

        $result = [];
        foreach ( $products as $product ) {
            $result[] = $this->normalize_product( $product );
        }

        return $result;
    }

    /**
     * Get a single product by ID (with caching and error handling).
     *
     * @param string|int $product_id
     * @param bool       $force_refresh Optional. If true, bypass cache. Default false.
     * @return array|null
     */
    public function get_product( $product_id, $force_refresh = false ) {
        $product_id   = absint( $product_id );
        $cache_key    = 'smliser_wc_product_' . md5( $this->store_url . '_' . $product_id );
        $cache_expiry = HOUR_IN_SECONDS; // Cache for 1 hour

        // Return cached result unless bypassing
        if ( ! $force_refresh ) {
            $cached = get_transient( $cache_key );
            if ( false !== $cached ) {
                return $cached;
            }
        }

        $endpoint = $this->store_url . '/wp-json/wc/store/v1/products/' . $product_id;
        $response = wp_remote_get( $endpoint, [
            'timeout' => 10, // 10s max wait
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
     * Get a checkout URL for a given pricing product.
     *
     * @param string|int $product_id
     * @return string  Checkout URL.
     */
    public function get_checkout_url( $product_id = '{{product_id}}' ) {
        return $this->store_url . '/checkout/?add-to-cart=' . $product_id;
    }

    protected function normalize_product( array $product ) {
        $prices = $product['prices'] ?? [];

        $pricing = [
            'price'         => isset( $prices['price'] ) ? floatval( $prices['price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) : 0,
            'regular_price' => isset( $prices['regular_price'] ) ? floatval( $prices['regular_price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) : 0,
            'sale_price'    => isset( $prices['sale_price'] ) ? floatval( $prices['sale_price'] ) / pow( 10, $prices['currency_minor_unit'] ?? 2 ) : 0,
            'currency'      => [
                'code'              => $prices['currency_code'] ?? 'USD',
                'symbol'            => $prices['currency_symbol'] ?? '$',
                'symbol_position'   => $this->determine_symbol_position( $prices ),
                'decimals'          => $prices['currency_minor_unit'] ?? 2,
                'decimal_separator' => $prices['currency_decimal_separator'] ?? '.',
                'thousand_separator'=> $prices['currency_thousand_separator'] ?? ',',
            ],
        ];

        return [
            'id'                => $product['id'] ?? 0,
            'name'              => $product['name'] ?? '',
            'slug'              => $product['slug'] ?? '',
            'type'              => $product['type'] ?? 'simple',
            'description'       => $product['description'] ?? '',
            'short_description' => $product['short_description'] ?? '',
            'permalink'         => $product['permalink'] ?? '',
            'pricing'           => $pricing,
            'images'            => $product['images'] ?? [],
            'categories'        => $product['categories'] ?? [],
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

}
