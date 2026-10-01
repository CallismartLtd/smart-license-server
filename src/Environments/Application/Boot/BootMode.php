<?php
/**
 * Boot mode enum file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Boot;

/**
 * The mode the application boots into for the current request or command.
 *
 * @package SmartLicenseServer\Environments\Application\Boot
 * @since 0.2.0
 */
enum BootMode : string {

	/**
	 * Installed and available.
	 */
	case Normal = 'normal';

	/**
	 * Not installed yet.
	 */
	case Installation = 'installation';

	/**
	 * Unavailable on the web: a maintenance flag is present (written by any
	 * maintenance, upgrade or installation run), or the state could not be
	 * read safely.
	 */
	case Maintenance = 'maintenance';

	/**
	 * Whether web access is refused in this mode.
	 *
	 * @return bool
	 */
	public function is_downtime() : bool {
		return self::Maintenance === $this;
	}

	/**
	 * Whether web requests are answered with the 503 handler instead of the application.
	 *
	 * Installation is included until the web installer exists; the CLI
	 * installer is the way to install meanwhile.
	 *
	 * @return bool
	 */
	public function blocks_web() : bool {
		return self::Normal !== $this;
	}
}