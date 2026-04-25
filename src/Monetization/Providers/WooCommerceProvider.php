<?php
/**
 * WooCommerce Monetization Provider.
 *
 * Fetches products from a WooCommerce site via the Store API.
 *
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization\Providers;

use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Http\HttpClient;
use SmartLicenseServer\Http\HttpRequest;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * WooCommerce Monetization Provider Class
 */
class WooCommerceProvider implements MonetizationProviderInterface {
    use SanitizeAwareTrait, CacheAwareTrait;

    /**
     * WooCommerce site URL.
     *
     * @var string $store_url
     */
    protected static $store_url = '';

    /**
     * Checkout URL.
     * 
     * @var string $checkout_endpoint
     */
    protected static $checkout_endpoint = '';

    /**
     * HTTP client client API.
     */
    private HttpClient $http_client;

    /**
     * Construct provider with target store URL.
     *
     */
    public function __construct() {
        $this->http_client = smliser_http_client();
    }

    /**
     * {@inheritdoc}
     */
    public static function get_id() : string {
        return 'woocommerce';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_name() : string {
        return 'WooCommerce';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_url() : string {
        return self::$store_url;
    }

    /**
     * Get a checkout URL for a given pricing product.
     *
     * @param string|int $product_id.
     * @param string $context The context, valid options are `edit` and `view`.
     * @return string  Checkout URL.
     */
    public static function get_checkout_url( $product_id = '', $context = 'view' ) {

        if ( 'edit' === $context ) {
            return static::$checkout_endpoint;
        }

        $product_id = ! empty( $product_id ) ?  static::finish( $product_id, '/' ) : '{{product_id}}';
        $base_url   = ! empty( static::$store_url ) ? static::finish( static::$store_url, '/' ) : '';
        $checkout   = ! empty( static::$store_url ) ? static::finish( static::$checkout_endpoint, '/' ) : '';
        
        return $base_url . $checkout . $product_id;
    }

    /**
     * Get a single product by ID (with caching and error handling).
     *
     * @param string|int $product_id
     * @param bool       $force_refresh Optional. If true, bypass cache. Default false.
     * @return array|null
     */
    public function get_product( $product_id, $force_refresh = false ) :?array {
        $product_id = static::sanitize_int( $product_id );
        $cache_key  = static::make_cache_key( __METHOD__, [$product_id, $force_refresh] );
        $product    = $force_refresh ? false : static::cache_get( $cache_key );

        // Return cached result unless bypassing
        if ( false !== $product ) {
            return $product;
        }

        $cache_expiry   = static::default_ttl();
        $endpoint       = static::$store_url . '/wp-json/wc/store/v1/products/' . $product_id;
        $request        = HttpRequest::get( $endpoint )
        ->with_options( [ 'timeout' => 30 ] );

        $response = $this->http_client->send( $request );

        if ( ! $response->ok() ) {
            static::cache_set( $cache_key, null, $cache_expiry );
            return null;
        }

        $product = $response->json();

        if ( ! is_array( $product ) ) {
            error_log( sprintf(
                'WooCommerceProvider::get_product() - Failed to decode JSON for product %d',
                $product_id
            ) );

            static::cache_set( $cache_key, null, $cache_expiry );
            return null;
        }

        $normalized = $this->normalize_product( $product );

        // Cache normalized product for future requests
        static::cache_set( $cache_key, $normalized, $cache_expiry );

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
            'id'            => $product['id'] ?? 0,
            'url'           => $product['permalink'] ?? '',
            'currency'      => $currency,
            'pricing'       => $pricing,
            'checkout_url'  => $this->get_checkout_url( $product['id'] ?? '' ),
            'images'        => $product['images'] ?? [],
            'categories'    => $product['categories'] ?? [],
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
     * Get provider settings schema.
     */
    public function get_settings_schema() : array {
        return [
            'store_url' => array(
                'type'          => 'text',
                'label'         => 'Store URL',
                'default'       => static::$store_url,
                'description'   => 'The base URL of your WooCommerce store (e.g. https://example.com).',
                'required'      => true,
            ),
            'checkout_url' => array(
                'type'          => 'text',
                'label'         => 'Checkout URL/Endpoint',
                'default'       => $this->get_checkout_url( '', 'edit' ),
                'description'   => 'The URL path or endpoint for product checkouts (e.g. "checkout" or "cart").',
                'required'      => true,
            ),
        ];
    }

    /**
     * Set settings.
     * 
     * @param array $settings
     */
    public function set_settings( array $settings ) : void {
        static::$store_url          = $settings['store_url'] ?? '';
        static::$checkout_endpoint  = $settings['checkout_url'] ?? '';
    }

}
