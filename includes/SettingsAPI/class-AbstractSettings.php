<?php
/**
 * Abstract Settings Implementation with Caching.
 *
 * This class provides a centralized implementation for common utilities like
 * in-memory caching and key prefixing, minimizing redundant code in adapters.
 *
 * @package SmartLicenseServer\SettingsAPI
 * @author  Callistus Nwachukwu
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

/**
 * Provides caching and utility methods for all settings adapters.
 *
 * Concrete adapters must extend this class and implement the abstract do_* methods.
 *
 * @since 0.2.0
 */
abstract class AbstractSettings implements SettingsInterface {

	/**
	 * Local in-memory cache for settings loaded during the request lifecycle.
	 *
	 * @var array
	 */
	protected $cache = array();

	/**
	 * Prefix used for all option keys in the underlying storage.
	 *
	 * @var string
	 */
	protected $prefix = 'smliser_';

	/**
	 * Prepends the adapter's defined prefix to the setting key.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The original key name.
	 * @return string The prefixed key, ready for use in storage functions.
	 */
	protected function prefix_key( string $key ): string {

		if ( \str_starts_with( $key, $this->prefix  ) ) {
			return $key;
		}

		return $this->prefix . $key;
	}

	/**
	 * Retrieves the value of a specific setting key, prioritizing the cache.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key     The unique identifier/name of the setting.
	 * @param mixed  $default Optional. The value to return if the key is not found.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return mixed The stored setting value, or the $default value if not found.
	 */
	public function get( string $key, $default = null, bool $use_prefix = true ) {
		$key	= $use_prefix ? $this->prefix_key( $key ) : $key;
		if ( $this->has_in_cache( $key ) ) {
			return $this->cache[ $key ];
		}

		$value = $this->do_get( $key, $default );

		// Cache the retrieved value (even if it's the default).
		$this->cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Stores or updates the value of a specific setting key. Updates cache immediately.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key   The unique identifier/name of the setting.
	 * @param mixed  $value The data to be stored.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return bool True on successful storage/update, false otherwise.
	 */
	public function set( string $key, $value, bool $use_prefix = true ): bool {
		$key	= $use_prefix ? $this->prefix_key( $key ) : $key;
		$result = $this->do_set( $key, $value );

		if ( $result ) {
			// Update cache on successful persistence.
			$this->cache[ $key ] = $value;
		}

		return $result;
	}

	/**
	 * Removes a specific setting key and its value from storage. Clears cache immediately.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting to delete.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return bool True on successful deletion, false if the key wasn't found or deletion failed.
	 */
	public function delete( string $key, bool $use_prefix = true ): bool {
		$key	= $use_prefix ? $this->prefix_key( $key ) : $key;
		$result = $this->do_delete( $key );

		if ( $result ) {
			// Clear cache on successful deletion.
			$this->clear_from_cache( $key );
		}

		return $result;
	}

	/**
	 * Checks if a specific setting key exists in the storage, prioritizing the cache.
	 *
	 * Note: If the key is not in cache, we still must check the underlying storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( string $key, bool $use_prefix = true ): bool {
		$key	= $use_prefix ? $this->prefix_key( $key ) : $key;
		if ( $this->has_in_cache( $key ) ) {
			return true;
		}

		return $this->do_has( $key );
	}

	/**
	 * Checks if a specific setting key is currently held in the in-memory cache.
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return bool
	 */
	private function has_in_cache( string $key ): bool {
		return array_key_exists( $key, $this->cache );
	}

	/**
	 * Removes a specific setting key from the in-memory cache.
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return void
	 */
	private function clear_from_cache( string $key ): void {
		if ( $this->has_in_cache( $key ) ) {
			unset( $this->cache[ $key ] );
		}
	}

	/**
	 * Concrete implementation for retrieving a setting from the physical storage.
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @param mixed  $default The value to return if the key is not found.
	 * @return mixed The stored setting value.
	 */
	abstract protected function do_get( string $key, $default = null );

	/**
	 * Concrete implementation for storing or updating a setting in the physical storage.
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @param mixed  $value The data to be stored.
	 * @return bool True on success, false otherwise.
	 */
	abstract protected function do_set( string $key, $value ): bool;

	/**
	 * Concrete implementation for removing a setting from the physical storage.
	 *
	 * @param string $key The unique identifier/name of the setting to delete.
	 * @return bool True on success, false otherwise.
	 */
	abstract protected function do_delete( string $key ): bool;

	/**
	 * Concrete implementation for checking existence in the physical storage.
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return bool True if the key exists, false otherwise.
	 */
	abstract protected function do_has( string $key ): bool;
}