<?php
/**
 * Repository for official Smliser extensions.
 */

declare( strict_types=1 );

namespace Callismart\SmliserOfficialMarketplace\Repositories;

use SmartLicenseServer\FileSystem\SoftwareRepository;

/** Stores and validates Smliser extension packages using software rules. */
final class SmliserExtensionRepository extends SoftwareRepository {

	public function app_type() : string {
		return 'smliser-extension';
	}
}
