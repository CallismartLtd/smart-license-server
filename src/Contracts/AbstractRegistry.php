<?php
/**
 * Abstract Registry class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts
 */
namespace SmartLicenseServer\Contracts;

use InvalidArgumentException;

/**
 * Abstract implemetation of the RegistryInterface.
 */
abstract class AbstractRegistry implements RegistryInterface {
    /**
     * Core registry.
     *
     * @var array<string, class-string> $core
     */
    protected $core = [];

    /**
     * Custom registry.
     *
     * @var array<string, class-string> $custom
     */
    protected $custom   = [];

    /**
     * Tracks whether core registry have been loaded.
     * 
     * @var bool $core_loaded
     */
    protected $core_loaded = false;

    /**
     * 
     * If a core registry entry with the same name exists, registration is
     * silently skipped — core entries always take precedence.
     * 
     * If a custom entry with the same name already exists it is
     * replaced without error.
     *
     * @param class-string $class_string
     * @return static
     */
    public function add( string $class_string ) : static {
        $this->ensure_core();
        $this->assert_implements_interface( $class_string );
        $id = $class_string::get_id();

        // Core registry entries always win.
        if ( isset( $this->core[$id] ) ) {
            return $this;
        }

        $this->custom[$id] = $class_string;

        return $this;
    }

    /**
     * Check if a registry entry is registered.
     *
     * @param string $id
     * @return bool
     */
    public function has( $id ) : bool {
        $this->ensure_core();
        return isset( $this->core[ $id ] ) || isset( $this->custom[ $id ] );
    }

    /**
     * Remove a registry entry by its ID.
     * 
     * @param string $id
     * @return bool True if the entry was found and removed, false otherwise.
     */
    public function remove( $id ) : bool {
        $this->ensure_core();
        // Guard against core registry entry removal.
        if ( isset( $this->core[$id] ) ) {
            return false;
        }

        if ( isset( $this->custom[ $id ] ) ) {
            unset( $this->custom[ $id ] );
            return true;
        }

        return false;
    }

    /**
     * Get a registered registry entry by its ID.
     *
     * @param string $id
     * @return class-string|null
     */
    public function get( $id ) : ?string {
        $this->ensure_core();
        return $this->core[ $id ] ?? $this->custom[$id] ?? null;
    }

    /**
     * Get all registered entries.
     *
     * @param bool $assoc Whether to preserve keys by id(default: true).
     * @param bool $instantiate Whether to instanciate the entries(default: false).
     * @return array<int|string, class-string<ServiceProviderInterface>|ServiceProviderInterface>
     */
    public function all( bool $assoc = true, bool $instantiate = false ) : array {
        $this->ensure_core();
        /** @var array<string, class-string> $all */
        $all    = array_merge( $this->custom, $this->core );

        if ( $instantiate ) {
            foreach ( $all as $_ => &$value ) {
                $value = new $value;
            }
        }
        return $assoc ? $all : array_values( $all );
    }

    /**
     * Assert that a class implements ServiceProviderInterface.
     *
     * @param string $class_string
     * @throws InvalidArgumentException
     */
    protected function assert_implements_interface( string $class_string ): void {
        if ( ! class_exists( $class_string ) ) {
            throw new InvalidArgumentException(
                sprintf( '%s: class "%s" does not exist.', static::class, $class_string )
            );
        }

        if ( ! in_array( ServiceProviderInterface::class, class_implements( $class_string ) ?: [], true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'MonetizationRegistry: "%s" must implement %s.',
                    $class_string,
                    ServiceProviderInterface::class
                )
            );
        }
    }

    /*
    |---------------------
    | PRIVATE HELPERS
    |---------------------
    */

    /**
     * Ensure core providers are loaded
     */
    protected function ensure_core()  : void {
        if ( ! $this->core_loaded ) {
            $this->load_core();
        }

        $this->core_loaded = true;
    }

    /*
    |------------------
    | ABSTRACT METHODS
    |------------------
    */

    /**
     * Load core adapters/providers.
     * 
     * @return void
     */
    abstract protected function load_core() : void;

    /*
    |--------------------------------------------
    | RETRIEVAL
    |--------------------------------------------
    */

    /**
     * Return only core commands keyed by name.
     *
     * @return array<string, class-string>
     */
    public function core(): array {
        return $this->core;
    }

    /**
     * Return only custom commands keyed by name.
     *
     * @return array<string, class-string>
     */
    public function custom(): array {
        return $this->custom;
    }
} 