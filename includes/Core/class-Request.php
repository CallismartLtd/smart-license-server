<?php
/**
 * The Smart License Server request class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\Core;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The classical representation a request object that is undrstood by all core models.
 * 
 * An object of this class should be prepared by the environment adapter and passed to the core controller.
 */
class Request {

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
        foreach ( $args as $key => $value ) {
            $this->set( $key, $value );
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
