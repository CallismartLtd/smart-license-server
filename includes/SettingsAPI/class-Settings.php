<?php
/**
 * The Settings API file
 *
 * This class is the Settings Manager/Service Locator, responsible for detecting
 * the current environment and initializing the correct persistence adapter.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\SettingsAPI;

/**
 * The Settings class handles the default and end-user defined settings for this application.
 *
 * It is a Singleton that manages the primary persistence adapter instance.
 *
 * @method mixed get( string $key, mixed $default, bool $use_prefix ) Retrieves the value of a specific setting key from storage.
 * @method bool set( string $key, mixed $value, bool $use_prefix ) Stores or updates the value of a specific setting key in storage (persistence).
 * @method bool delete( string $key, bool $use_prefix )Removes a specific setting key and its value from storage.
 * @method bool has( string $key, bool $use_prefix ) Checks if a specific setting key exists in the storage.
 * @since 0.2.0
 */
class Settings {

    /**
     * The active settings adapter (must implement SettingsStorageInterface).
     *
     * @var SettingsStorageInterface
     */
    protected $adapter;

    /**
     * Private constructor to enforce the Singleton pattern.
     * Initializes the correct adapter based on environment detection.
     */
    public function __construct( SettingsStorageInterface $storage ) {
        $this->adapter = $storage;
    }

    /**
     * Prevents cloning of the instance.
     *
     * @return void
     */
    public function __clone() {
        // Not allowed.
    }

    /**
     * Prevents deserialization of the instance.
     *
     * @return void
     */
    public function __wakeup() {
        // Not allowed.
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     */
    public function __call( $method, $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        throw new \BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( $this->adapter ), $method )
        );
    }
}