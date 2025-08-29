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
    protected $store_url;

    /**
     * Construct provider with target store URL.
     *
     * @param string $store_url WooCommerce store base URL (without trailing slash).
     */
    public function __construct( $store_url ) {
        $this->store_url = untrailingslashit( esc_url_raw( $store_url ) );
    }

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
     * {@inheritdoc}
     */
    public function get_product( $product_id ) {
        $endpoint = $this->store_url . '/wp-json/wc/store/v1/products/' . absint( $product_id );
        $response = wp_remote_get( $endpoint );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $product = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! is_array( $product ) ) {
            return null;
        }

        return $this->normalize_product( $product );
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
     * {@inheritdoc}
     */
    public function get_checkout_url( $tier_id ) {
        $endpoint = $this->store_url . '/wp-json/wc/store/v1/products/' . absint( $tier_id );
        $response = wp_remote_get( $endpoint );

        if ( is_wp_error( $response ) ) {
            return null;
        }

        $product = json_decode( wp_remote_retrieve_body( $response ), true );

        return isset( $product['permalink'] ) ? esc_url_raw( $product['permalink'] ) : null;
    }

    /**
     * Normalize a product from the Store API into our expected provider structure.
     *
     * @param array $product Product data from WooCommerce Store API.
     * @return array Normalized product.
     */
    protected function normalize_product( array $product ) {
        $pricing = [
            'billing_cycle' => $product['billing_cycle'] ?? '',
            'sign_up_fee'   => isset( $product['sign_up_fee'] ) ? floatval( $product['sign_up_fee'] ) : 0,
            'price'         => isset( $product['prices']['price'] ) ? floatval( $product['prices']['price'] ) : 0,
            'currency'      => $product['prices']['currency_code'] ?? 'USD',
        ];

        return [
            'id'          => $product['id'] ?? 0,
            'name'        => $product['name'] ?? '',
            'slug'        => $product['slug'] ?? '',
            'type'        => $product['type'] ?? 'sw_product',
            'description' => $product['description'] ?? '',
            'short_description' => $product['short_description'] ?? '',
            'permalink'   => $product['permalink'] ?? '',
            'pricing'     => $pricing,
            'images'      => $product['images'] ?? [],
            'categories'  => $product['categories'] ?? [],
        ];
    }
}
