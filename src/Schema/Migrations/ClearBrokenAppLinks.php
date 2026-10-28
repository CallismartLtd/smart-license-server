<?php
/**
 * Clear broken app links migration class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Schema\Migrations
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Schema\Migrations;

use Callismart\DBPrism\Migrations\AbstractMigration;
use SmartLicenseServer\Schema\TableName;

/**
 * Clear the app links that were saved broken.
 *
 * Until schema 0.2.1, saving an app stored:
 *  - as a new app's download link, this site's download URL read before the
 *    app had a slug: ".../downloads/{type}/.zip", which answers 404;
 *  - as the author profile of an app without one, "/" (what an empty URL
 *    builds).
 *
 * From 0.2.1 an app stores only a download link elsewhere (an empty one
 * means this site serves the download, see AbstractHostedApp::$download_link)
 * and only a valid author profile URL, so both are emptied.
 *
 * Safe to run again: a cleared link no longer matches.
 */
final class ClearBrokenAppLinks extends AbstractMigration {

	public static function get_description() : string {
		return 'Clear app download links saved without the app slug, and empty author profile URLs';
	}

	public function up() : void {
		$table = TableName::APPS->table();

		$links = $this->query()
			->update( $table )
			->set( [ 'download_link' => null ] )
			->where_ends_with( 'download_link', '/.zip' );

		$this->database()->execute( $links->build(), $links->get_bindings() );

		$profiles = $this->query()
			->update( $table )
			->set( [ 'author_profile' => null ] )
			->where( 'author_profile', '=', '/' );

		$this->database()->execute( $profiles->build(), $profiles->get_bindings() );
	}
}