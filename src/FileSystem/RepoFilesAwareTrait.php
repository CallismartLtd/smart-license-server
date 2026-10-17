<?php
/**
 * Repository files aware trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

/**
 * Provides unified methods to get known and shared repository files like 
 * - readme.md
 * - installation.md
 * - changelog.md
 *
 * Each is read with Repository::read_document(), which falls back to the
 * copy inside the app's package.
 *
 * @property \SmartLicenseServer\Utils\MDParser $mdparser
 */
trait RepoFilesAwareTrait {
	/**
	 * Get the changelog.
	 *
	 * @param string $slug The app slug.
	 * @param string $suffix The file extension, without the dot.
	 * @return string The changelog in HTML format.
	 */
	public function get_changelog( string $slug, string $suffix = 'md' ) : string {
		return $this->mdparser->parse( $this->read_document( $slug, sprintf( 'changelog.%s', $suffix ) ) );
	}

	/**
	 * Get the installation guide.
	 *
	 * @param string $slug The app slug.
	 * @param string $suffix The file extension, without the dot.
	 * @return string The installation guide in HTML format.
	 */
	public function get_installation( string $slug, string $suffix = 'md' ) : string {
		return $this->mdparser->parse( $this->read_document( $slug, sprintf( 'installation.%s', $suffix ) ) );
	}

	/**
	 * Get the readme.
	 *
	 * @param string $slug The app slug.
	 * @param string $suffix The file extension, without the dot.
	 * @return string The readme in HTML format.
	 */
	public function get_readme( string $slug, string $suffix = 'md' ) : string {
		return $this->mdparser->parse( $this->read_document( $slug, sprintf( 'readme.%s', $suffix ) ) );
	}
}
