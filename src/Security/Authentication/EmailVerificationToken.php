<?php
/**
 * EmailVerificationToken class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security\Authentication
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

/**
 * Issues and verifies email verification tokens.
 *
 * A token is a signed payload: the user ID, the email address it was sent
 * to, and when it was issued. It proves the holder received mail at that
 * address, so it is valid only while the user's email is still that address
 * and for TTL_HOURS after it was issued. Nothing is stored: changing the
 * email address invalidates every link sent to the old one, and using a link
 * for an address already verified changes nothing.
 */
final class EmailVerificationToken {
	use TokenDeliveryTrait;

	/**
	 * How long a verification link stays valid, in hours.
	 *
	 * @var int
	 */
	public const TTL_HOURS = 48;

	/**
	 * @param string $secret Application secret.
	 * @param string $salt   Application salt.
	 */
	public function __construct(
		#[\SensitiveParameter] string $secret,
		#[\SensitiveParameter] string $salt
	) {
		$this->secret = $secret;
		$this->salt   = $salt;
	}

	/**
	 * Create a token for the user's current email address.
	 *
	 * @param User $user The user verifying their email address.
	 * @return string URL-safe token.
	 */
	public function issue( User $user ) : string {
		$payload = \smliser_safe_json_encode(
			[
				'id'        => $user->get_id(),
				'email'     => strtolower( $user->get_email() ),
				'timestamp' => time(),
			]
		);

		$signature = static::hmac_hash( $payload, $this->derive_key( 'email_verification' ), 'sha256' );

		return static::base64url_encode( sprintf( '%s.%s', $payload, $signature ) );
	}

	/**
	 * Check a token.
	 *
	 * @param string $token Token from the verification link.
	 * @return array{valid: bool, user?: User, reason?: string}
	 */
	public function verify( #[\SensitiveParameter] string $token ) : array {
		$decoded = static::base64url_decode( $token );

		if ( ! $decoded || ! str_contains( $decoded, '.' ) ) {
			return [ 'valid' => false, 'reason' => 'This verification link is not valid.' ];
		}

		// Split at the last dot: the email in the payload has dots, the hex signature none.
		$split     = (int) strrpos( $decoded, '.' );
		$payload   = substr( $decoded, 0, $split );
		$signature = substr( $decoded, $split + 1 );

		if ( ! hash_equals( static::hmac_hash( $payload, $this->derive_key( 'email_verification' ), 'sha256' ), $signature ) ) {
			return [ 'valid' => false, 'reason' => 'This verification link is not valid.' ];
		}

		$data = json_decode( $payload, true );

		if ( ! is_array( $data ) || empty( $data['id'] ) || empty( $data['email'] ) || ! isset( $data['timestamp'] ) ) {
			return [ 'valid' => false, 'reason' => 'This verification link is not valid.' ];
		}

		if ( (int) $data['timestamp'] + self::TTL_HOURS * 3600 < time() ) {
			return [ 'valid' => false, 'reason' => 'This verification link has expired. Ask for a new one.' ];
		}

		$user = User::get_by_id( (int) $data['id'] );

		if ( ! $user ) {
			return [ 'valid' => false, 'reason' => 'The account for this link no longer exists.' ];
		}

		if ( strtolower( $user->get_email() ) !== (string) $data['email'] ) {
			return [ 'valid' => false, 'reason' => 'This link was sent to an email address the account no longer uses.' ];
		}

		return [ 'valid' => true, 'user' => $user ];
	}
}
