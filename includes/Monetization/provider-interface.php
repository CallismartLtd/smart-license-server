<?php
/**
 * Smart License Server Monetization Provider Interface.
 *
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * Monetization Provider Interface
 *
 * Defines the contract all monetization providers must follow.
 * 
 * A provider is an external system or platform responsible for 
 * handling pricing, currency, checkout, and product data through which an app in the repository is sold.
 *
 * Examples:
 * - WooCommerce store
 * - EDD store
 * - SureCart
 * - Freemius
 * - Gumroad
 * - Local Smart License Server provider
 */
interface Monetization_Provider_Interface {

    /**
     * Get the provider unique key (e.g. 'woocommerce', 'edd', 'local').
     *
     * @return string
     */
    public function get_id();

    /**
     * Get the provider name (human-readable).
     *
     * @return string
     */
    public function get_name();

    /**
     * Get the provider base URL or API endpoint.
     *
     * @return string
     */
    public function get_url();

    /**
     * Get product information from this provider.
     *
     * Providers MUST normalize the product data into the following format:
     *
     * {
     *   id:                (int|string) Unique product ID in the provider system
     *   permalink:         (string) Public-facing product URL
     *   currency: {
     *       code:              (string) ISO currency code (e.g. 'USD')
     *       symbol:            (string) Currency symbol (e.g. '$','€','₦')
     *       symbol_position:   (string) One of: 'left' | 'left_space' | 'right' | 'right_space'
     *       decimals:          (int) Decimal precision (default 2)
     *       decimal_separator: (string) Usually '.' or ','
     *       thousand_separator:(string) Usually ',' or '.'
     *   }
     *   pricing: {
     *       price:           (float) Current/active price
     *       regular_price:   (float) Base/regular price (non-sale)
     *       sale_price:      (float) Discounted/sale price (or 0 if none)
     *       is_on_sale:      (bool)  True if product is on sale
     *   }
     *   images:      (array[]) List of images, each { id, src, alt, name }
     *   categories:  (array[]) List of categories, each { id, name, slug }
     * }
     *
     * @param string|int $product_id Product identifier in provider system.
     * @return array|null Normalized product array or null if not found.
     */
    public function get_product( $product_id );



    /**
     * Get normalized pricing tiers for a product.
     *
     * @param string|int $product_id
     * @return array
     */
    public function get_pricing( $product_id );

    /**
     * Get a checkout URL for a given pricing tier/product.
     *
     * @param string|int $product_id
     * @return string|null  Checkout URL, or null if not applicable.
     */
    public function get_checkout_url( $product_id = '' );

    /**
     * Get the provider settings
     * 
     * @return array $settings
     */
    public function get_settings();

    /**
     * Get provider allowed options
     * 
     * @return array
     */
    public function get_allowed_options();
}

