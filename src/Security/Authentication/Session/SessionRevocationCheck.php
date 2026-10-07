<?php
/**
 * SessionRevocationCheck interface file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication\Session
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

/**
 * Decides whether a cryptographically valid session has been revoked.
 *
 * SessionManager is stateless and cannot know this on its own; the
 * application supplies an implementation backed by whatever bounded state
 * it keeps (for example a per-principal cut-off time).
 */
interface SessionRevocationCheck {

	/**
	 * Whether the session must no longer be accepted.
	 *
	 * @param Session $session A session that decrypted and validated successfully.
	 * @return bool
	 */
	public function revoked( Session $session ): bool;
}