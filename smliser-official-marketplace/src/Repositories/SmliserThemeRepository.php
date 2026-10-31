<?php
/**
 * Repository for official Smliser themes.
 */

declare( strict_types=1 );

namespace Callismart\SmliserOfficialMarketplace\Repositories;

use SmartLicenseServer\FileSystem\SoftwareRepository;

/** Stores and validates Smliser theme packages using software rules. */
final class SmliserThemeRepository extends SoftwareRepository {

	public function app_type() : string {
		return 'smliser-theme';
	}
}
