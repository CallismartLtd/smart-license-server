<?php
/**
 * Hosted Smliser theme class.
 */

declare( strict_types=1 );

namespace Callismart\SmliserOfficialMarketplace\HostedApps;

use SmartLicenseServer\HostedApps\Software;

/** Represents a theme for a Smliser application. */
final class SmliserTheme extends Software {

	public function get_type() : string {
		return 'smliser-theme';
	}
}
