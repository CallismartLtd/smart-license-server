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

namespace SmartLicenseServer\SettingsAPI\Providers;

/**
 * The settings storage interface defines the contracts all settings storage providers must implement.
 *
 * It enforces methods necessary for CRUD operations (saving, retrieving, deleting)
 * of application settings, ensuring persistence in the host environment's storage.
 *
 * @since 0.2.0
 */
interface SettingsStorageInterface {
	/**
	 * Retrieves the value of a specific setting key from storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key     The unique identifier/name of the setting.
	 * @param mixed  $default Optional. The value to return if the key is not found.
	 * @return mixed The stored setting value, or the $default value if not found.
	 */
	public function get( string $key, $default = null );

	/**
	 * Stores or updates the value of a specific setting key in storage (persistence).
	 *
	 * @since 0.2.0
	 *
	 * @param string $key   The unique identifier/name of the setting.
	 * @param mixed  $value The data to be stored.
	 * @return bool True on successful storage/update, false otherwise.
	 */
	public function set( string $key, mixed $value ): bool;

	/**
	 * Removes a specific setting key and its value from storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting to delete.
	 * @return bool True on successful deletion, false if the key wasn't found or deletion failed.
	 */
	public function delete( string $key ): bool;

	/**
	 * Checks if a specific setting key exists in the storage.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return bool True if the key exists, false otherwise.
	 */
	public function has( string $key ): bool;

	/**
	 * Retrieves a paginated collection of settings from storage.
	 *
	 * @since 0.2.0
	 *
	 * @param int $page  The page number to retrieve. Must be greater than zero.
	 * @param int $limit The maximum number of settings to return per page.
	 * @return array<string, mixed> Associative array of settings keyed by setting name.
	 */
	public function all( int $page = 1, int $limit = 20 ): array;

	/**
	 * Searches for settings matching a given query.
	 *
	 * Implementations may use exact matching, partial matching,
	 * wildcard matching, or storage-specific search capabilities.
	 *
	 * Results should be returned in a paginated format.
	 *
	 * @since 0.2.0
	 *
	 * @param string $query      The search term or pattern to match against setting keys.
	 * @param int    $page       The page number to retrieve. Must be greater than zero.
	 * @param int    $limit      The maximum number of matching settings to return per page.
	 *                           should be applied to the search query.
	 * @return array<string, mixed> Associative array of matching settings keyed by setting name.
	 */
	public function search( string $query, int $page, int $limit = 50 ): array;
}