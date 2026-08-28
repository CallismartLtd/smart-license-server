<?php
/**
 * Cache manager class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Cache manager singleton.
 *
 * Provides a unified cache API for Smart License Server.
 *
 * Methods are proxied to the underlying adapter:
 *
 * @method void register() Register service dependencies.
 * @method void boot() Boot service components.
 * @method mixed get( string $key ) Retrieve a cached value by key.
 * @method bool set( string $key, mixed $value, int $ttl = 0 ) Store a value in the cache.
 * @method bool delete( string $key ) Delete a cache entry by key.
 * @method bool has( string $key ) Check if a cache entry exists.
 * @method bool clear() Clear the entire cache.
 * @method mixed modify( string $key, callable $callback, int $ttl = 0, mixed $default = null ) Atomically read, modify, and rewrite a cached value via a callback.
 * @method int|bool increment( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ) Atomically increment a numeric cache value.
 * @method int|bool decrement( string $key, int $offset = 1, int $initial = 0, int $ttl = 0 ) Atomically decrement a numeric cache value.
 * @method array<string, array<string, mixed>> get_settings_schema() Return required configuration fields.
 * @method void set_settings( array<string, mixed> $settings ) Set adapter configuration.
 * @method bool is_supported() Tells whether the adapter can run in the host environment.
 * @method bool is_active() Tells whether the cache is active.
 * @method CacheStats get_stats() Return runtime statistics for this cache adapter.
 * @method bool test( array<string, mixed> $settings ) Test whether the adapter can connect and operate with the supplied settings.
 * @method string get_name() Get the cache adapter name.
 * @method string get_id() Get the cache adapter id.
 */
class Cache {

    /**
     * Singleton instance.
     *
     * @var Cache|null
     */
    protected static ?Cache $instance = null;

    /**
     * Private constructor to enforce singleton.
     *
     * @param CacheAdapterInterface $adapter The cache adapter instance.
     */
    public function __construct(
        protected CacheAdapterInterface $adapter,
        protected Settings $settings
        
    ) {}

    /**
     * Default cache ttl
     * 
     * @return int
     */
    public function default_ttl() : int {
        return (int) max( 0, $this->settings->get( 'default_cache_ttl', 0 ) );
    }

    /**
     * Proxy calls to the adapter methods.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     *
     * @return mixed
     *
     * @throws \ErrorException If the method does not exist in the adapter.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        $backtrace  = \debug_backtrace( \DEBUG_BACKTRACE_IGNORE_ARGS, 3 );
        $file       = $backtrace[0]['file'] ?? null;
        $line       = $backtrace[0]['line'] ?? null;
        $message    = sprintf(
            'Method %s::%s does not exist.', 
            get_class( $this ),
            $method
        );

        throw new \ErrorException( $message, 0, 1, $file, $line );
    }
}