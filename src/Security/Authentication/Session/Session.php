<?php

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication\Session;

final readonly class Session {

	/**
	 * @param string $id
	 * @param string|int $principal_id
	 * @param int $issued_at
	 * @param int $expires_at
	 * @param array<string,mixed> $claims
	 */
	public function __construct(
		public string $id,
		public string|int $principal_id,
		public int $issued_at,
		public int $expires_at,
		public array $claims = []
	) {}

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
	 * @param string $name Claim name.
	 * @param mixed $default Default value.
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