<?php
/**
 * Cache manager class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Cache
 */

namespace SmartLicenseServer\Cache;

use SmartLicenseServer\Cache\Adapters\CacheAdapterInterface;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\Exceptions\ProxyMethodException;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Cache manager singleton.
 *
 * Provides a unified cache API for Smart License Server. The instance is
 * created once at boot with the adapter resolved from the cache adapter
 * registry; later calls to instance() return that same instance.
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
final class Cache {

	/**
	 * Singleton instance.
	 *
	 * @var Cache|null
	 */
	private static ?Cache $instance = null;

	/**
	 * Private constructor — use instance().
	 *
	 * @param CacheAdapterInterface $adapter  The cache adapter instance.
	 * @param Settings              $settings The settings API.
	 */
	private function __construct(
		protected CacheAdapterInterface $adapter,
		protected Settings $settings
	) {}

	/**
	 * Prevent cloning, which would create a second instance.
	 */
	private function __clone() {}

	/*
	|------------------
	| SINGLETON ACCESS
	|------------------
	*/

	/**
	 * Return the singleton instance, creating it on the first call.
	 *
	 * The first call must supply both the adapter and settings. Once the
	 * instance exists, arguments are ignored — use reset_instance() to
	 * rebuild with a different adapter.
	 *
	 * @param CacheAdapterInterface|null $adapter  Required on the first call.
	 * @param Settings|null              $settings Required on the first call.
	 * @return self
	 * @throws EnvironmentBootstrapException If first called without an adapter and settings.
	 */
	public static function instance( ?CacheAdapterInterface $adapter = null, ?Settings $settings = null ) : self {
		if ( null === self::$instance ) {
			if ( null === $adapter || null === $settings ) {
				throw new EnvironmentBootstrapException(
					'misconfiguration',
					'Cache must be initialized at boot with a cache adapter and settings.'
				);
			}

			self::$instance = new self( $adapter, $settings );
		}

		return self::$instance;
	}

	/**
	 * Discard the singleton so the next instance() call rebuilds it.
	 *
	 * Intended for tests and for switching adapters after the cache
	 * settings change.
	 *
	 * @return void
	 */
	public static function reset_instance() : void {
		self::$instance = null;
	}

	/*
	|----------
	| SETTINGS
	|----------
	*/

	/**
	 * Default cache TTL in seconds (0 = forever).
	 *
	 * @return int
	 */
	public function default_ttl() : int {
		return (int) \max( 0, $this->settings->get( 'default_cache_ttl', 0 ) );
	}

	/*
	|--------
	| PROXY
	|--------
	*/

	/**
	 * Proxy calls to the adapter methods.
	 *
	 * @param string            $method Method name.
	 * @param array<int, mixed> $args   Method arguments.
	 * @return mixed
	 * @throws ProxyMethodException If the method does not exist on the adapter.
	 */
	public function __call( string $method, array $args ) : mixed {
		if ( \method_exists( $this->adapter, $method ) ) {
			return \call_user_func_array( [ $this->adapter, $method ], $args );
		}

		throw new ProxyMethodException( static::class, $method );
	}
}