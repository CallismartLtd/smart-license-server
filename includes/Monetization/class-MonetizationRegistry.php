<?php
/**
 * Monetization Provider Registry Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization;

use RuntimeException;
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Monetization\Providers\MonetizationProviderInterface;
use SmartLicenseServer\Monetization\Providers\WooCommerceProvider;
use SmartLicenseServer\SettingsAPI\Settings;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Registry of available monetization providers.
 * 
 * This class manages the registration and retrieval of different
 * monetization providers integrated with the Smart License Server.
 */
final class MonetizationRegistry extends AbstractRegistry {
    /**
     * Singleton instance.
     * 
     * @var self $instance
     */
    private static $instance = null;

    /**
     * The settings storage adapter instance.
     * 
     * @var Settings $storage
     */
    private static Settings $storage;

    /**
     * Private constructor to prevent direct instantiation.
     * 
     * @param Settings $storage
     */
    private function __construct( Settings $storage ) {
        self::$storage = $storage;
        $this->load_core();
    }

    /**
     * Get the singleton instance of the MonetizationRegistry.
     * 
     * @param Settings|null $storage The settings storage adapter to use for provider options.
     * @return self
     */
    public static function instance( ?Settings $storage = null ) {
        if ( self::$instance === null ) {
            if ( null === $storage ) {
                throw new RuntimeException(
                    'MonetizationRegistry instance not initialized. A Settings storage API must be provided on first call.'
                );
            }
            self::$instance = new self( $storage );
        }
        return self::$instance;
    }

    /**
     * Load core monetization providers into the registry.
     * 
     * @return void
     */
    protected function load_core() : void {
        if ( $this->core_loaded ) {
            return;
        }

        // All core providers.
        $core_providers = [
            WooCommerceProvider::get_id() => WooCommerceProvider::class,
        ];

        foreach ( $core_providers as $id => $class ) {
            $this->assert_implements_interface( $class );

            $this->core[$id] = $class;
        }

        $this->core_loaded = true;
    }

    /**
     * Validate and normalize a product data array against the declared schema.
     *
     * @param array $product
     * @return array|Exception Normalized product array or Exception if invalid.
     */
    public static function validate_product_data( $product ) {
        if ( ! is_array( $product ) ) {
            return new Exception( 'invalid_product', 'Product data must be an array.' );
        }

        // Required top-level fields
        $required_top = [ 'id', 'url', 'currency', 'pricing', 'checkout_url' ];
        foreach ( $required_top as $key ) {
            if ( ! array_key_exists( $key, $product ) ) {
                return new Exception(
                    'missing_field',
                    sprintf( 'Missing required product field: %s', $key )
                );
            }
        }

        // Validate currency block
        $currency = $product['currency'];
        if ( ! is_array( $currency ) ) {
            return new Exception( 'invalid_currency', 'Currency must be an array.' );
        }

        $currency_required = [ 'code', 'symbol', 'symbol_position', 'decimals', 'decimal_separator', 'thousand_separator' ];
        foreach ( $currency_required as $key ) {
            if ( ! array_key_exists( $key, $currency ) ) {
                return new Exception(
                    'missing_currency_field',
                    sprintf( 'Missing required currency field: %s', $key )
                );
            }
        }

        // Validate pricing block
        $pricing = $product['pricing'];
        if ( ! is_array( $pricing ) ) {
            return new Exception( 'invalid_pricing', 'Pricing must be an array.' );
        }

        $pricing_required = [ 'price', 'regular_price', 'sale_price', 'is_on_sale' ];
        foreach ( $pricing_required as $key ) {
            if ( ! array_key_exists( $key, $pricing ) ) {
                return new Exception(
                    'missing_pricing_field',
                    sprintf( 'Missing required pricing field: %s', $key )
                );
            }
        }

        // Enforce schema: keep only allowed keys
        $allowed_keys = [ 'id', 'url', 'checkout_url', 'currency', 'pricing', 'images', 'categories' ];
        $product      = array_intersect_key( $product, array_flip( $allowed_keys ) );

        // Normalize images
        $images         = $product['images'] ?? [];
        $placeholder    = smliser_get_placeholder_icon();
        if ( ! is_array( $images ) || empty( $images ) ) {
            // Fallback placeholder
            $images = [
                [
                    'id'        => 0,
                    'src'       => $placeholder,
                    'thumbnail' => $placeholder,
                    'srcset'    => '',
                    'sizes'     => '',
                    'name'      => 'Placeholder',
                    'alt'       => 'Product image not available',
                ]
            ];
        } else {
            // Ensure each image has required keys
            $normalized_images = [];
            foreach ( $images as $img ) {
                $normalized_images[] = [
                    'id'        => $img['id'] ?? 0,
                    'src'       => $img['src'] ?? $placeholder,
                    'thumbnail' => $img['thumbnail'] ?? ( $img['src'] ?? $placeholder ),
                    'srcset'    => $img['srcset'] ?? '',
                    'sizes'     => $img['sizes'] ?? '',
                    'name'      => $img['name'] ?? '',
                    'alt'       => $img['alt'] ?? '',
                ];
            }
            $images = $normalized_images;
        }
        $product['images'] = $images;

        // Normalize categories
        $categories = $product['categories'] ?? [];
        if ( ! is_array( $categories ) ) {
            $categories = [];
        }
        $product['categories'] = $categories;

        return $product;
    }

    /**
     * Get options for a monetization provider.
     * 
     * @param string $provider_id  The provider ID.
     * @param string $option_name  The option name.
     * @return mixed The option value or empty string if not set.
     */
    public static function get_option( $provider_id, $option_name ) {
        static $provider_options = array();

        // Load this provider's options into cache if not already loaded
        if ( ! isset( $provider_options[ $provider_id ] ) ) {
            $all_options = self::$storage->get( 'smliser_monetization_providers_options', array() );
            $provider_options[ $provider_id ] = $all_options[ $provider_id ] ?? array();
        }

        return $provider_options[ $provider_id ][ $option_name ] ?? null;
    }

    /**
     * Update an option for a monetization provider.
     * 
     * @param string $provider_id  The provider ID.
     * @param string $option_name  The option name.
     * @param mixed  $value        The option value to set.
     * @return bool True if option value has changed, false if not or if update failed.
     */
    public static function update_option( $provider_id, $option_name, $value ) {
        $all_options = self::$storage->get( 'smliser_monetization_providers_options', array() );

        if ( ! isset( $all_options[ $provider_id ] ) || ! is_array( $all_options[ $provider_id ] ) ) {
            $all_options[ $provider_id ] = array();
        }

        $all_options[ $provider_id ][ $option_name ] = $value;
        $updated = self::$storage->set( 'smliser_monetization_providers_options', $all_options );

        return $updated;
    }

    /**
     * Get a monetization provider.
     * 
     * @param string $provider_id The provider ID
     * @return MonetizationProviderInterface|null
     */
    public function get_provider( string $provider_id ) : ?MonetizationProviderInterface {
        if ( '' === $provider_id ) {
            return null;
        }

        $class_string   = $this->get( $provider_id );

        if ( $class_string ) {
            /** @var  MonetizationProviderInterface $provider */
            $provider   = new $class_string;
            $settings   = [];

            foreach ( $provider->get_settings_schema() as $key => $_  ) {
                $settings[$key] = static::get_option( $provider::get_id(), $key );
            }

            $provider->set_settings( $settings );
        }

        return $provider;
    }
}
