<?php
/**
 * Repository for Smliser extensions.
 *
 * @package SmartLicenseServer\FileSystem
 */

declare( strict_types=1 );

namespace SmartLicenseServer\FileSystem;

/**
 * Stores and validates Smliser extension packages using software package rules.
 */
class SmliserExtensionRepository extends SoftwareRepository {

	/**
	 * Get the app type served by this repository.
	 */
	public function app_type() : string {
		return 'smliser-extension';
	}
}
