<?php
/**
 * The settings interface file.
 *
 * This file defines the contract for all settings storage adapters,
 * ensuring the application remains environment-agnostic.
 * 
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

/**
 * The settings interface defines the contracts all settings adapters must implement.
 *
 * It enforces methods necessary for CRUD operations (saving, retrieving, deleting)
 * of application settings, ensuring persistence in the host environment's storage.
 *
 * @since 0.2.0
 */
interface SettingsInterface {
	/**
	 * Retrieves the value of a specific setting key from storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key     The unique identifier/name of the setting.
	 * @param mixed  $default Optional. The value to return if the key is not found.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return mixed The stored setting value, or the $default value if not found.
	 */
	public function get( string $key, $default = null, bool $use_prefix = true );

	/**
	 * Stores or updates the value of a specific setting key in storage (persistence).
	 *
	 * @since 0.2.0
	 *
	 * @param string $key   The unique identifier/name of the setting.
	 * @param mixed  $value The data to be stored.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return bool True on successful storage/update, false otherwise.
	 */
	public function set( string $key, $value,bool $use_prefix = true ): bool;

	/**
	 * Removes a specific setting key and its value from storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting to delete.
	 * @param bool $use_prefix	Optional flag to use the `smliser_` prefix (default true)
	 * @return bool True on successful deletion, false if the key wasn't found or deletion failed.
	 */
	public function delete( string $key, bool $use_prefix = true ): bool;

	/**
	 * Checks if a specific setting key exists in the storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( string $key, bool $use_prefix = true ): bool;
}