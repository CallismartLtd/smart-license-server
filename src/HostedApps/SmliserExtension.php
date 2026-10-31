<?php
/**
 * Hosted Smliser extension class file.
 *
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

/**
 * Represents an extension for a Smliser application.
 */
class SmliserExtension extends Software {

	/**
	 * Get the application type.
	 */
	public function get_type() : string {
		return 'smliser-extension';
	}
}
