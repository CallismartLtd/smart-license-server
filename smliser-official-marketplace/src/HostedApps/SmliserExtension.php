<?php
/**
 * Hosted Smliser extension class.
 */

declare( strict_types=1 );

namespace Callismart\SmliserOfficialMarketplace\HostedApps;

use SmartLicenseServer\HostedApps\Software;

/** Represents an extension for a Smliser application. */
final class SmliserExtension extends Software {

	public function get_type() : string {
		return 'smliser-extension';
	}
}
