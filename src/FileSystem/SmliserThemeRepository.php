<?php
/**
 * Repository for Smliser themes.
 *
 * @package SmartLicenseServer\FileSystem
 */

declare( strict_types=1 );

namespace SmartLicenseServer\FileSystem;

/**
 * Stores and validates Smliser theme packages using software package rules.
 */
class SmliserThemeRepository extends SoftwareRepository {

	/**
	 * Get the app type served by this repository.
	 */
	public function app_type() : string {
		return 'smliser-theme';
	}
}
