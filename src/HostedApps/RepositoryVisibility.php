<?php
/**
 * Repository visibility class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Security\Context\Guard;

/**
 * Which hosted apps the current principal may read from the public repository.
 *
 * The rule: active apps are public; apps in any other status (deactivated,
 * suspended, trash) are read only by a system admin. To everyone else they
 * do not exist: lookups answer "not found", listings never include them.
 *
 * Read endpoints that cache per request arguments add audience() to their
 * cache key, so an admin's answer is never served to anyone else.
 */
final class RepositoryVisibility {

	/**
	 * Role that reads apps in every status.
	 */
	public const ADMIN_ROLE = 'system_admin';

	/**
	 * Constructor.
	 *
	 * @param Guard $guard The current principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * Whether the principal reads apps in every status.
	 *
	 * @return bool
	 */
	public function sees_every_status() : bool {
		return (bool) $this->guard->get_principal()?->is( self::ADMIN_ROLE );
	}

	/**
	 * Whether the principal may read apps in a status.
	 *
	 * @param string $status An AbstractHostedApp::STATUS_* value.
	 * @return bool
	 */
	public function can_view_status( string $status ) : bool {
		return AbstractHostedApp::STATUS_ACTIVE === $status || $this->sees_every_status();
	}

	/**
	 * Whether the principal may read an app.
	 *
	 * @param HostedAppsInterface $app
	 * @return bool
	 */
	public function can_view( HostedAppsInterface $app ) : bool {
		return $this->can_view_status( $app->get_status() );
	}

	/**
	 * The audience the principal belongs to, for cache keys: 'admin' or 'public'.
	 *
	 * @return string
	 */
	public function audience() : string {
		return $this->sees_every_status() ? 'admin' : 'public';
	}
}
