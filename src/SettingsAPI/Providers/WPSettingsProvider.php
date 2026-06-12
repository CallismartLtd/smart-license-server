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

namespace SmartLicenseServer\SettingsAPI\Providers;

use SmartLicenseServer\Utils\Format;

/**
 * Concrete adapter class to manage settings storage using the WordPress Options API.
 *
 * @since 0.2.0
 */
class WPSettingsProvider extends AbstractSettings {

	/**
	 * Retrieve a single option from WordPress.
	 *
	 * @since 0.2.0
	 */
	protected function do_get( string $key, $default = null ) {
		return \get_option( $key, $default );
	}

	/**
	 * Store or update an option in WordPress.
	 *
	 * @since 0.2.0
	 */
	protected function do_set( string $key, $value ): bool {
		$result = \update_option( $key, $value );
		return false !== $result;
	}

	/**
	 * Delete an option from WordPress.
	 *
	 * @since 0.2.0
	 */
	protected function do_delete( string $key ): bool {
		return \delete_option( $key );
	}

	/**
	 * Check existence of an option in WordPress.
	 *
	 * @since 0.2.0
	 */
	protected function do_has( string $key ): bool {

		$sentinel = new \stdClass();
		$value    = \get_option( $key, $sentinel );

		return $value !== $sentinel;
	}

	protected function do_all( int $page, int $limit ): array {

		$db     = smliser_db();
		$offset = $db->calculate_query_offset( $page, $limit );
		$table  = $GLOBALS['wpdb']?->options;

		$sql = smliserQueryBuilder()
			->select( 'option_name', 'option_value' )
			->from( $table )
			->limit( $limit )
			->offset( $offset )
			->order_by( 'option_id', 'ASC' );

		$rows = $db->get_results(
			$sql->build(),
			$sql->get_bindings()
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$results = [];

		foreach ( $rows as $row ) {
			$results[ $row['option_name'] ] = Format::decode( $row['option_value'] );
		}

		return $results;
	}

	/**
	 * Search settings in WordPress options table.
	 *
	 * @since 0.2.0
	 */
	protected function do_search( string $query, int $page, int $limit ): array {

		$db     = smliser_db();
		$offset = $db->calculate_query_offset( $page, $limit );
		$table  = $GLOBALS['wpdb']?->options;

		$sql	= smliserQueryBuilder()
			->select( 'option_name', 'option_value' )
			->from( $table )
			->where_contains( 'option_name', $query )
			->limit( $limit )
			->offset( $offset )
			->order_by( 'option_id', 'ASC' );

		$rows = $db->get_results(
			$sql->build(),
			$sql->get_bindings()
		);

		if ( empty( $rows ) ) {
			return [];
		}

		$results = [];

		foreach ( $rows as $row ) {
			$results[ $row['option_name'] ] = Format::decode( $row['option_value'] );
		}

		return $results;
	}
}