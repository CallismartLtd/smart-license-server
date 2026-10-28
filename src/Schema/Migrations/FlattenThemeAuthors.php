<?php
/**
 * Flatten theme authors migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use SmartLicenseServer\Schema\TableName;

/**
 * Store theme authors the way every app type does.
 *
 * Until schema 0.2.1, a theme's "author" column held a serialized PHP array
 * shaped like the WordPress.org themes API author object. From 0.2.1 every
 * app type stores the author's name in "author" and the profile URL in
 * "author_profile"; the WordPress.org shape is built only in the theme's
 * REST response.
 *
 * For each theme whose author is serialized: "author" becomes the array's
 * "author" (or "display_name" when that is empty), and "author_profile"
 * takes the array's "author_url" unless it already holds a URL.
 *
 * Safe to run again: a flattened author is no longer serialized.
 */
final class FlattenThemeAuthors extends AbstractMigration {

	public static function get_description() : string {
		return 'Store theme authors as a name and a profile URL';
	}

	public function up() : void {
		$table  = TableName::APPS->table();
		$select = $this->query()
			->select( 'id', 'author', 'author_profile' )
			->from( $table )
			->where( 'app_type', '=', 'wptheme' )
			->where_starts_with( 'author', 'a:' );

		$rows = $this->database()->get_results( $select->build(), $select->get_bindings() ) ?: [];

		foreach ( $rows as $row ) {
			$author = @\unserialize( (string) $row['author'], [ 'allowed_classes' => false ] );

			if ( ! is_array( $author ) ) {
				continue;
			}

			$name    = trim( (string) ( $author['author'] ?? '' ) ) ?: trim( (string) ( $author['display_name'] ?? '' ) );
			$profile = trim( (string) ( $row['author_profile'] ?? '' ) ) ?: trim( (string) ( $author['author_url'] ?? '' ) );

			$update = $this->query()
				->update( $table )
				->set( [
					'author'         => '' !== $name ? $name : null,
					'author_profile' => '' !== $profile ? $profile : null,
				] )
				->where( 'id', '=', (int) $row['id'] );

			$this->database()->execute( $update->build(), $update->get_bindings() );
		}
	}
}