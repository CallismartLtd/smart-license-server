<?php
/**
 * WordPress Adapter for the Settings Interface.
 *
 * Implements the abstract persistence methods using the WordPress Options API.
 * It inherits caching and key prefixing logic from AbstractSettings.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\SettingsAPI
 * @since   0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\SettingsAPI;

/**
 * Concrete adapter class to manage settings storage using the WordPress Options API.
 *
 * This class implements the abstract do_* methods defined in AbstractSettings.
 *
 * @since 0.2.0
 */
class WPSettingsAdapter extends AbstractSettings {

	/**
	 * Concrete implementation for retrieving a setting from the WordPress options table.
	 *
	 * Note: Caching is handled by the parent AbstractSettings class.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key     The unique identifier/name of the setting.
	 * @param mixed  $default The value to return if the key is not found.
	 * @return mixed The stored setting value.
	 */
	protected function do_get( string $key, $default = null ) {
		return \get_option( $key, $default );
	}

	/**
	 * Concrete implementation for storing or updating a setting in the WordPress options table.
	 *
	 * Note: Cache update is handled by the parent AbstractSettings class.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key   The unique identifier/name of the setting.
	 * @param mixed  $value The data to be stored.
	 * @return bool True on successful storage/update, false otherwise.
	 */
	protected function do_set( string $key, $value ): bool {
		$result = \update_option( $key, $value );

		// We treat null (no change) as success, as the desired state is persisted.
		return false !== $result;
	}

	/**
	 * Concrete implementation for removing a setting from the WordPress options table.
	 *
	 * Note: Cache clearing is handled by the parent AbstractSettings class.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting to delete.
	 * @return bool True on successful deletion, false otherwise.
	 */
	protected function do_delete( string $key ): bool {
		return \delete_option( $key );
	}

	/**
	 * Concrete implementation for checking existence in the WordPress options table.
	 *
	 * Note: Cache check is handled by the parent AbstractSettings class.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The unique identifier/name of the setting.
	 * @return bool True if the key exists, false otherwise.
	 */
	protected function do_has( string $key ): bool {
		// Use a unique sentinel value that is highly unlikely to be a stored setting value.
		$not_found_sentinel = new \stdClass();
		$value              = \get_option( $key, $not_found_sentinel );

		return $value !== $not_found_sentinel;
	}
}