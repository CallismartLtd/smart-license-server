<?php
/**
 * Smart License Server Monetization Provider Interface.
 *
 * @package Smart_License_Server
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace Smart_License_Server\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * The monetization provider interface defines the contract all providers must follow.
 */
interface Monetization_Provider_Interface {

    /**
     * Get the provider unique key (e.g. 'woocommerce', 'edd', 'local').
     *
     * @return string
     */
    public function get_provider_id();

    /**
     * Get the provider name (human-readable).
     *
     * @return string
     */
    public function get_provider_name();

    /**
     * Get the provider base URL or API endpoint.
     *
     * @return string
     */
    public function get_provider_url();

    /**
     * Get all products from this provider (normalized).
     *
     * @return array
     */
    public function get_products();

    /**
     * Get a single product by ID.
     *
     * @param string|int $product_id
     * @return array
     */
    public function get_product( $product_id );

    /**
     * Get normalized pricing tiers for a product.
     *
     * @param string|int $product_id
     * @return array
     */
    public function get_pricing( $product_id );
}
