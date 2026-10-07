<?php
/**
 * UserSessionCutoff class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication\Session
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\UserSettings;

/**
 * Per-user session cut-off: "Sign out everywhere" without a growing store.
 *
 * Each user may have one timestamp in their options. Every session that
 * signed in at or before it is rejected, on every device. Storage is one
 * value per user that has ever used it, so it never grows with sessions.
 *
 * A normal logout does not use this; it only removes that browser's cookie.
 */
final class UserSessionCutoff implements SessionRevocationCheck {

	/**
	 * Whether the session's user still exists and signed in after their cut-off.
	 *
	 * Sessions whose principal is not a numeric user id are left to other checks.
	 *
	 * @param Session $session A session that decrypted and validated successfully.
	 * @return bool
	 */
	public function revoked( Session $session ): bool {
		$id = $session->principal_id;

		if ( ! is_int( $id ) && ! ctype_digit( $id ) ) {
			return false;
		}

		$user = User::get_by_id( (int) $id );

		// A deleted user's sessions end with the account.
		if ( ! $user instanceof User ) {
			return true;
		}

		$cutoff = (int) UserSettings::for( $user )->get( UserSettings::SESSIONS_VALID_AFTER, 0 );

		return $session->authenticated_at <= $cutoff;
	}

	/**
	 * Sign the user out on every device, this one included.
	 *
	 * The caller should also remove the current browser's cookie
	 * (SessionManager::invalidate()) and send the user to the login page.
	 *
	 * @param User $user The user.
	 * @return bool Whether the cut-off was saved.
	 */
	public function revoke_all( User $user ): bool {
		return UserSettings::for( $user )->set( UserSettings::SESSIONS_VALID_AFTER, time() );
	}
}