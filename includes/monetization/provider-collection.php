<?php
/**
 * Monetization Provider Collection Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 1.0.0
 */

namespace SmartLicenseServer\Monetization;

defined( 'ABSPATH' ) || exit;

/**
 * Collection of available monetization providers.
 * 
 * This class manages the registration and retrieval of different
 * monetization providers integrated with the Smart License Server.
 */
class Provider_Collection {
    /**
     * Singleton instance.
     * 
     * @var Provider_Collection $instance
     */
    protected static $instance = null;

    /**
     * Private constructor to prevent direct instantiation.
     */
    private function __construct() {}

    /**
     * Get the singleton instance of the Provider_Collection.
     * 
     * @return self
     */
    public static function instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Registered providers.
     *
     * @var Monetization_Provider_Interface[]
     */
    protected $providers = [];

    /**
     * Register a new monetization provider.
     *
     * @param Monetization_Provider_Interface $provider
     * @return void
     */
    public function register_provider( Monetization_Provider_Interface $provider ) {
        $id = $provider->get_id();

        $this->providers[ $id ] = $provider;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $provider_id
     * @return bool
     */
    public function has_provider( $provider_id ) {
        return isset( $this->providers[ $provider_id ] );
    }

    /**
     * Unregister a provider by its ID.
     * 
     * @param string $provider_id
     * @return bool True if the provider was found and removed, false otherwise.
     */
    public function unregister_provider( $provider_id ) {
        if ( isset( $this->providers[ $provider_id ] ) ) {
            unset( $this->providers[ $provider_id ] );
            return true;
        }
        return false;
    }

    /**
     * Get a registered provider by its ID.
     *
     * @param string $provider_id
     * @return Monetization_Provider_Interface|null
     */
    public function get_provider( $provider_id ) {
        return $this->providers[ $provider_id ] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @param bool $assoc Whether to preserve keys by provider_id.
     * @return Monetization_Provider_Interface[]
     */
    public function get_providers( $assoc = true ) {
        return $assoc ? $this->providers : array_values( $this->providers );
    }

    /**
     * Validate and normalize a product data array against the declared schema.
     *
     * @param array $product
     * @return array|\WP_Error Normalized product array or WP_Error if invalid.
     */
    public static function validate_product_data( $product ) {
        if ( ! is_array( $product ) ) {
            return new \WP_Error( 'invalid_product', __( 'Product data must be an array.', 'smliser' ) );
        }

        // Required top-level fields
        $required_top = [ 'id', 'permalink', 'currency', 'pricing', 'checkout_url' ];
        foreach ( $required_top as $key ) {
            if ( ! array_key_exists( $key, $product ) ) {
                return new \WP_Error(
                    'missing_field',
                    sprintf( __( 'Missing required product field: %s', 'smliser' ), $key )
                );
            }
        }

        // Validate currency block
        $currency = $product['currency'];
        if ( ! is_array( $currency ) ) {
            return new \WP_Error( 'invalid_currency', __( 'Currency must be an array.', 'smliser' ) );
        }

        $currency_required = [ 'code', 'symbol', 'symbol_position', 'decimals', 'decimal_separator', 'thousand_separator' ];
        foreach ( $currency_required as $key ) {
            if ( ! array_key_exists( $key, $currency ) ) {
                return new \WP_Error(
                    'missing_currency_field',
                    sprintf( __( 'Missing required currency field: %s', 'smliser' ), $key )
                );
            }
        }

        // Validate pricing block
        $pricing = $product['pricing'];
        if ( ! is_array( $pricing ) ) {
            return new \WP_Error( 'invalid_pricing', __( 'Pricing must be an array.', 'smliser' ) );
        }

        $pricing_required = [ 'price', 'regular_price', 'sale_price', 'is_on_sale' ];
        foreach ( $pricing_required as $key ) {
            if ( ! array_key_exists( $key, $pricing ) ) {
                return new \WP_Error(
                    'missing_pricing_field',
                    sprintf( __( 'Missing required pricing field: %s', 'smliser' ), $key )
                );
            }
        }

        // Enforce schema: keep only allowed keys
        $allowed_keys = [ 'id', 'permalink', 'checkout_url', 'currency', 'pricing', 'images', 'categories' ];
        $product      = array_intersect_key( $product, array_flip( $allowed_keys ) );

        // Normalize images
        $images         = $product['images'] ?? [];
        $placeholder    = SMLISER_URL . 'assets/images/software-placeholder.svg';
        if ( ! is_array( $images ) || empty( $images ) ) {
            // Fallback placeholder
            $images = [
                [
                    'id'        => 0,
                    'src'       => $placeholder,
                    'thumbnail' => $placeholder,
                    'srcset'    => '',
                    'sizes'     => '',
                    'name'      => __( 'Placeholder', 'smliser' ),
                    'alt'       => __( 'Product image not available', 'smliser' ),
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
            $all_options = get_option( 'smliser_monetization_providers_options', array() );
            $provider_options[ $provider_id ] = $all_options[ $provider_id ] ?? array();
        }

        return $provider_options[ $provider_id ][ $option_name ] ?? '';
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
        $all_options = get_option( 'smliser_monetization_providers_options', array() );

        if ( ! isset( $all_options[ $provider_id ] ) || ! is_array( $all_options[ $provider_id ] ) ) {
            $all_options[ $provider_id ] = array();
        }

        $all_options[ $provider_id ][ $option_name ] = $value;
        $updated = update_option( 'smliser_monetization_providers_options', $all_options );

        return $updated;
    }

    /**
     * Save Monetization Provider options via ajax
     */
    public static function save_provider_options() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        $provider_id = smliser_get_post_param( 'provider_id', null );

        if ( ! $provider_id || ! self::instance()->has_provider( $provider_id ) ) {
            wp_send_json_error( array( 'message' => sprintf( 'The provider "%s" is not supported.', $provider_id ?? 'Unknown' ) ) );
        }

        $provider           = self::instance()->get_provider( $provider_id );
        $allowed_options    = array_keys( (array) $provider->get_allowed_options() );

        foreach( $allowed_options as $name ) {
            if ( $value = smliser_get_post_param( $name, null ) ) {
                self::update_option( $provider->get_id(), $name, $value );
            } else {
                self::update_option( $provider->get_id(), $name, '' );
            }
        }

        wp_send_json_success( array( 'message' => 'Saved' ) );
    }

    /**
     * Autoload providers
     */
    public static function auto_load() {
        $classes = get_declared_classes();

        foreach ( $classes as $class ) {
            if ( in_array( Monetization_Provider_Interface::class, class_implements( $class ) ) ) {
                self::instance()->register_provider( new $class );
            }
        }
    }
}

add_action( 'init', array( Provider_Collection::class, 'auto_load' ) );
add_action( 'wp_ajax_smliser_save_monetization_provider_options', array( Provider_Collection::class, 'save_provider_options' ) );