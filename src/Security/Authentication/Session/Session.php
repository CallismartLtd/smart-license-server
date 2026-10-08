<?php
/**
 * Session class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication\Session
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

/**
 * An authenticated browser session, as carried by the session cookie.
 */
final readonly class Session {

	/**
	 * When the principal signed in.
	 *
	 * Unlike $issued_at, this survives cookie renewals, so it is what
	 * revocation cut-offs and the maximum session lifetime are measured
	 * against.
	 *
	 * @var int
	 */
	public int $authenticated_at;

	/**
	 * @param string              $id               Random session identifier; kept across renewals.
	 * @param string|int          $principal_id     Principal represented by the session.
	 * @param int                 $issued_at        When this cookie was issued.
	 * @param int                 $expires_at       When this cookie expires.
	 * @param array<string,mixed> $claims           Additional claims.
	 * @param int                 $authenticated_at When the principal signed in; 0 means $issued_at.
	 * @param bool                $persistent       Whether the user chose "remember me": the cookie
	 *                                              outlives the browser and the session lasts the
	 *                                              remembered lifetime instead of sliding.
	 */
	public function __construct(
		public string $id,
		public string|int $principal_id,
		public int $issued_at,
		public int $expires_at,
		public array $claims = [],
		int $authenticated_at = 0,
		public bool $persistent = false
	) {
		$this->authenticated_at = 0 === $authenticated_at ? $issued_at : $authenticated_at;
	}

	/**
	 * Determine whether the session is expired.
	 *
	 * @return bool
	 */
	public function expired(): bool {
		return $this->expires_at <= time();
	}

	/**
	 * Get a session claim.
	 *
	 * @param string $name    Claim name.
	 * @param mixed  $default Default value.
	 *
	 * @return mixed
	 */
	public function claim(
		string $name,
		mixed $default = null
	): mixed {
		return $this->claims[ $name ] ?? $default;
	}
}