<?php
/**
 * Hosted Smliser theme class file.
 *
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

/**
 * Represents a theme for a Smliser application.
 */
class SmliserTheme extends Software {

	/**
	 * Get the application type.
	 */
	public function get_type() : string {
		return 'smliser-theme';
	}
}
