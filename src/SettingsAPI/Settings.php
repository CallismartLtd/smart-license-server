<?php
/**
 * The Settings API file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\SettingsAPI;

use RuntimeException;
use SmartLicenseServer\Exceptions\ProxyMethodException;
use SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface;

/**
 * The Settings class is the wrapper around the settings API.
 *
 * All settings methods are delegated to the underlying storage provider.
 *
 * @method mixed get(string $key, mixed $default = null)
 *         Retrieves the value of a specific setting key from storage.
 *
 * @method bool set(string $key, mixed $value)
 *         Stores or updates the value of a specific setting key in storage (persistence).
 *
 * @method bool delete(string $key)
 *         Removes a specific setting key and its value from storage.
 *
 * @method bool has(string $key)
 *         Checks if a specific setting key exists in the storage.
 *
 * @method array all(int $page = 1, int $limit = 20)
 *         Retrieves a paginated list of all settings.
 *
 * @method array search(string $query, int $page = 1, int $limit = 20)
 *         Searches settings by key using a partial match query.
 *
 * @since 0.2.0
 */
class Settings {
    /*
    |-------------------------------------
    | CORE SETTINGS OPTION NAME CONSTANTS
    |-------------------------------------
    */

    /**
     * The option name for the repository name.
     */
    public const REPOSITORY_NAME = 'repository_name';

    /**
     * The option name for the administration email.
     */
    public const ADMIN_EMAIL = 'admin_email';

    /**
     * The option name for the hosting email.
     */
    public const HOSTING_EMAIL = 'hosting_email';

    /**
     * The option name for the support email.
     */
    public const SUPPORT_EMAIL = 'support_email';

    /**
     * The option name for the license key prefix.
     */
    public const LICENSE_KEY_PREFIX = 'license_key_prefix';

    /**
     * The option name for the default license duration.
     */
    public const DEFAULT_LICENSE_DURATION = 'default_license_duration';

    /**
     * The option name for the default license activation limit.
     */
    public const DEFAULT_ACTIVATION_LIMIT = 'default_activation_limit';

    /**
     * The option name for the API rate limit.
     */
    public const API_RATE_LIMIT = 'api_rate_limit';

    /**
     * The option name for log retention.
     */
    public const LOG_RETENTION_DAYS = 'log_retention_days';

    /**
     * The option name for the environment mode.
     */
    public const ENVIRONMENT_MODE = 'environment_mode';

    /**
     * The option name for the Terms URL.
     */
    public const TERMS_URL = 'terms_url';

    /**
     * The option name for the privacy policy URL.
     */
    public const PRIVACY_POLICY_URL = 'privacy_policy_url';

    /**
     * Private constructor to enforce the Singleton pattern.
     * Initializes the correct adapter based on environment detection.
     */
    private function __construct( protected SettingsStorageInterface $adapter ) {}

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

        throw new ProxyMethodException( static::class, $method );
    }

    /**
     * Get the underlying adapter
     * 
     * @return \SmartLicenseServer\SettingsAPI\Providers\SettingsStorageInterface
     */
    public function get_adapter() : SettingsStorageInterface {
        return $this->adapter;
    }

    /**
     * Singleton instance.
     * 
     * @param SettingsStorageInterface|null $adapter
     * @return static
     */
    public static function instance( ?SettingsStorageInterface $adapter = null ) : static {
        static $static;

        if ( ! isset( $static ) ) {
            if ( ! $adapter ) {
                throw new RuntimeException( 'Settings API must be called early with a storage adapter.');
            }

            $static = new static( $adapter );
        }

        return $static;
    }
}