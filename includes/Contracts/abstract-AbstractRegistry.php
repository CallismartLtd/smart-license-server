<?php
/**
 * Abstract Registry class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts
 */
namespace SmartLicenseServer\Contracts;

use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides abstract implementation of service provider registry. 
 */
abstract class AbstractRegistry implements RegistryInterface {
    /**
     * Core providers/adapters.
     *
     * @var array<string, class-string> $core
     */
    protected $core = [];

    /**
     * Custom providers/adapters.
     *
     * @var array<string, class-string> $custom
     */
    protected $custom   = [];

    /**
     * Tracks whether core providers/adapters have been loaded.
     * 
     * @var bool $core_loaded
     */
    protected $core_loaded = false;

    /**
     * {@inheritDoc}.
     * 
     * If a core provider with the same name exists, registration is
     * silently skipped — core providers always take precedence.
     * 
     * If a custom provider with the same name already exists it is
     * replaced without error.
     *
     * @param class-string $class_string
     * @return self
     */
    final public function add( string $class_string ) : self {
        $this->ensure_core();
        $this->assert_implements_interface( $class_string );
        $id = $class_string::get_id();

        // Core providers always win.
        if ( isset( $this->core[$id] ) ) {
            return $this;
        }

        $this->custom[$id] = $class_string;

        return $this;
    }

    /**
     * Check if a provider is registered.
     *
     * @param string $provider_id
     * @return bool
     */
    public function has( $provider_id ) : bool{
        $this->ensure_core();
        return isset( $this->core[ $provider_id ] ) || isset( $this->custom[ $provider_id ] );
    }

    /**
     * Unregister a provider by its ID.
     * 
     * @param string $provider_id
     * @return bool True if the provider was found and removed, false otherwise.
     */
    public function remove( $provider_id ) : bool {
        $this->ensure_core();
        // Guard against core providers removal.
        if ( isset( $this->core[$provider_id] ) ) {
            return false;
        }

        if ( isset( $this->custom[ $provider_id ] ) ) {
            unset( $this->custom[ $provider_id ] );
            return true;
        }

        return false;
    }

    /**
     * Get a registered provider by its ID.
     *
     * @param string $provider_id
     * @return class-string|null
     */
    public function get( $provider_id ) : ?string {
        $this->ensure_core();
        return $this->core[ $provider_id ] ?? $this->custom[$provider_id] ?? null;
    }

    /**
     * Get all registered providers.
     *
     * @param bool $assoc Whether to preserve keys by provider_id(default: true).
     * @param bool $objects Whether to instanciat the providers(default: false).
     * @return array<int|string, class-string<ServiceProviderInterface>|ServiceProviderInterface>
     */
    public function all( bool $assoc = true, bool $objects = false ) : array {
        $this->ensure_core();
        $all    = array_merge( $this->custom, $this->core );

        if ( $objects ) {
            foreach ( $all as $id => &$value ) {
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
    private function ensure_core()  : void {
        if ( ! $this->core_loaded ) {
            $this->load_core();
        }
    }

    /**
     * Load core adapters/providers.
     * 
     * @return void
     */
    abstract protected function load_core() : void;
} 