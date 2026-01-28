<?php
/**
 * The Smart License Server request class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The classical representation a request object that is undrstood by all core models.
 * 
 * An object of this class should be prepared by the environment adapter and passed to the core controller.
 */
class Request {
    use SanitizeAwareTrait;

    /**
     * Internal storage for dynamic properties.
     *
     * @var array
     */
    private array $props = [];

    /**
     * Constructor.
     *
     * @param array $args Optional initial property values.
     */
    public function __construct( array $args = [] ) {
        $source = empty( $args ) ? $_REQUEST : $args;

        foreach ( $source as $key => $value ) {
            $this->set( $key, static::sanitize_auto( $value ) );
        }

    }

    /**
     * Set a property value.
     *
     * @param string $property
     * @param mixed  $value
     */
    public function set( string $property, $value ): void {
        $this->props[ $property ] = $value;
    }

    /**
     * Get a property value.
     *
     * @param string $property
     * @param mixed  $default Optional default value if property is not set.
     *
     * @return mixed
     */
    public function get( string $property, $default = null ) {
        return $this->props[ $property ] ?? $default;
    }

    /**
     * Magic getter.
     */
    public function __get( string $name ) {
        return $this->get( $name );
    }

    /**
     * Magic setter.
     */
    public function __set( string $name, $value ) {
        $this->set( $name, $value );
    }

    /**
     * Check if a property exists.
     *
     * @param string $property
     *
     * @return bool
     */
    public function has( string $property ): bool {
        return array_key_exists( $property, $this->props );
    }

    /**
     * Tells whether the specified property exists and not empty.
     * 
     * @param string $property The property name.
     * @return bool
     */
    public function isEmpty( string $property ) : bool {
        return empty( $this->get( $property ) );
    }

    /**
     * Tells whethe the specified properties are all present and not empty.
     * 
     * @param array $properties The property names.
     * @return bool
     */
    public function hasAll( array $properties ) : bool {
        foreach ( $properties as $property ) {
            if ( $this->isEmpty( $property ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return all properties as array.
     *
     * @return array
     */
    public function all(): array {
        return $this->props;
    }

    /**
     * Whether a request is authorized
     * 
     * @return boolean
     */
    public function is_authorized() : bool {
        return boolval( $this->get( 'is_authorized' ) ); // @todo Refactor to use the security context or deprecate
    }
}
