<?php
/**
 * WebAuthn ceremonies for passkey registration and passkey sign-in.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Actors\User;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Wraps the WebAuthn library and keeps passkey ceremony validation in one place.
 *
 * Philosophy: a passkey replaces the password, it does not add to it.
 *
 *  - Sign-in is usernameless. The browser offers the passkeys it holds for this
 *    site, the user picks one, and the credential ID tells us who they are.
 *    No email or password is needed or accepted on this path.
 *  - User verification (device PIN or biometric) is always required, on both
 *    registration and sign-in. The passkey therefore already proves possession
 *    and the user's presence, so a successful passkey sign-in is complete and
 *    never asks for a second step. Email codes, authenticator apps and recovery
 *    codes belong to the password path only.
 *  - Credentials are always discoverable (resident), otherwise they could not
 *    be used without first typing an identifier.
 *
 * Ceremony challenges are stored through {@see AuthenticationFactorManager}
 * as short-lived, single-use rows; credentials through {@see PasskeyStore}.
 */
final class WebAuthnManager {

	/**
	 * Challenge purpose: a signed-in user registering a passkey.
	 */
	public const PURPOSE_REGISTER = 'passkey_register';

	/**
	 * Challenge purpose: an anonymous visitor signing in with a passkey.
	 */
	public const PURPOSE_LOGIN = 'passkey_login';

	/**
	 * Lifetime of a sign-in challenge, in seconds. Slightly longer than the
	 * browser prompt timeout, so an unanswered challenge expires on its own.
	 */
	private const LOGIN_CHALLENGE_TTL = 180;

	/**
	 * Browser prompt timeout, in milliseconds.
	 */
	private const CEREMONY_TIMEOUT_MS = 120000;

	private \Symfony\Component\Serializer\SerializerInterface $serializer;
	private CeremonyStepManagerFactory $ceremonies;
	private string $origin;
	private string $rp_id;

	/**
	 * Constructor.
	 *
	 * @param PasskeyStore                $passkeys   Credential storage.
	 * @param AuthenticationFactorManager $challenges Ceremony challenge storage.
	 * @param URLManager                  $urls       URL API.
	 * @param string                      $secret     Application secret.
	 */
	public function __construct(
		private PasskeyStore $passkeys,
		private AuthenticationFactorManager $challenges,
		private URLManager $urls,
		#[\SensitiveParameter] private string $secret
	) {
		$origin = $urls->url()->get_origin();
		if ( ! is_string( $origin ) || '' === $origin ) {
			throw new \RuntimeException( 'WebAuthn requires a configured site origin.' );
		}
		$this->origin = $origin;
		$this->rp_id  = (string) $urls->url()->get_host();
		if ( '' === $this->rp_id ) {
			throw new \RuntimeException( 'WebAuthn requires a valid relying party host.' );
		}
		$this->ceremonies = new CeremonyStepManagerFactory();
		$this->ceremonies->setAllowedOrigins( [ $origin ] );
		$support          = new AttestationStatementSupportManager();
		$this->serializer = ( new WebauthnSerializerFactory( $support ) )->create();
	}

	/*
	|------------
	|Registration
	|------------
	*/

	/**
	 * Build registration options and return their token-bound challenge.
	 *
	 * @param User   $user  The signed-in user adding a passkey.
	 * @param string $label Name the user gave the passkey.
	 * @return array{challenge:string,options:string} Challenge token and JSON options.
	 */
	public function begin_registration( User $user, string $label ) : array {
		$exclude = [];
		foreach ( $this->passkeys->for_user( (int) $user->get_id() ) as $passkey ) {
			$exclude[] = $this->record( $passkey )->getPublicKeyCredentialDescriptor();
		}

		$options = PublicKeyCredentialCreationOptions::create(
			PublicKeyCredentialRpEntity::create( '', $this->rp_id ),
			PublicKeyCredentialUserEntity::create(
				$user->get_email(),
				$this->user_handle( (int) $user->get_id() ),
				$user->get_email()
			),
			$this->new_challenge(),
			[
				PublicKeyCredentialParameters::create( PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -7 ),
				PublicKeyCredentialParameters::create( PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -257 ),
			],
			AuthenticatorSelectionCriteria::create(
				AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_NO_PREFERENCE,
				AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
				AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED
			),
			PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
			$exclude,
			self::CEREMONY_TIMEOUT_MS
		);

		$json  = $this->serializer->serialize( $options, 'json' );
		$token = $this->challenges->create_challenge( (int) $user->get_id(), self::PURPOSE_REGISTER, [
			'options' => $json,
			'label'   => $label,
		] );

		return [ 'challenge' => $token, 'options' => $json ];
	}

	/**
	 * Validate a registration response and store the new passkey.
	 *
	 * @param User   $user            The signed-in user adding a passkey.
	 * @param string $token           Challenge token from begin_registration().
	 * @param string $credential_json The browser's credential, as JSON.
	 * @return bool Whether the passkey was stored.
	 */
	public function finish_registration( User $user, string $token, string $credential_json ) : bool {
		$challenge = $this->challenges->get_challenge( $token, self::PURPOSE_REGISTER );
		if ( ! $challenge || (int) $challenge['user_id'] !== (int) $user->get_id() ) {
			return false;
		}

		try {
			$options    = $this->serializer->deserialize( $challenge['payload']['options'], PublicKeyCredentialCreationOptions::class, 'json' );
			$credential = $this->serializer->deserialize( $credential_json, PublicKeyCredential::class, 'json' );
			if ( ! $credential->response instanceof AuthenticatorAttestationResponse ) {
				return false;
			}
			$record = AuthenticatorAttestationResponseValidator::create( $this->ceremonies->creationCeremony() )
				->check( $credential->response, $options, $this->rp_id );
			if ( ! $this->challenges->consume_challenge( $token ) ) {
				return false;
			}
			return $this->passkeys->add(
				(int) $user->get_id(),
				$record->publicKeyCredentialId,
				$this->serializer->serialize( $record, 'json' ),
				(string) ( $challenge['payload']['label'] ?? 'Passkey' )
			);
		} catch ( \Throwable ) {
			$this->challenges->record_challenge_failure( $token );
			return false;
		}
	}

	/*
	|-------------
	|Passkey login
	|-------------
	*/

	/**
	 * Build sign-in options for an anonymous visitor.
	 *
	 * No user is identified yet, so no credentials are listed: the browser
	 * offers whichever discoverable passkeys it holds for this site.
	 *
	 * @param array<string,mixed> $context Values to carry to finish_authentication(),
	 *                                     such as "remember" and the redirect URL. Must be JSON-safe.
	 * @return array{challenge:string,options:string} Challenge token and JSON options.
	 */
	public function begin_authentication( array $context = [] ) : array {
		// The sign-in endpoint is anonymous, so stale challenges are cleared on every use.
		$this->challenges->prune_challenges();

		$options = PublicKeyCredentialRequestOptions::create(
			$this->new_challenge(),
			$this->rp_id,
			[],
			PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
			self::CEREMONY_TIMEOUT_MS
		);

		$json  = $this->serializer->serialize( $options, 'json' );
		$token = $this->challenges->create_challenge( null, self::PURPOSE_LOGIN, [
			'options' => $json,
			'context' => $context,
		], self::LOGIN_CHALLENGE_TTL );

		return [ 'challenge' => $token, 'options' => $json ];
	}

	/**
	 * Validate a sign-in assertion and identify the user it belongs to.
	 *
	 * Succeeding means the user has signed in. The caller still decides whether
	 * the account may sign in and issues the session.
	 *
	 * @param string $token           Challenge token from begin_authentication().
	 * @param string $credential_json The browser's assertion, as JSON.
	 * @return array{user_id:int,context:array<string,mixed>}|null Null when the assertion is not valid.
	 */
	public function finish_authentication( string $token, string $credential_json ) : ?array {
		$challenge = $this->challenges->get_challenge( $token, self::PURPOSE_LOGIN );
		if ( ! $challenge ) {
			return null;
		}

		try {
			$options    = $this->serializer->deserialize( $challenge['payload']['options'], PublicKeyCredentialRequestOptions::class, 'json' );
			$credential = $this->serializer->deserialize( $credential_json, PublicKeyCredential::class, 'json' );
			if ( ! $credential->response instanceof AuthenticatorAssertionResponse ) {
				$this->challenges->record_challenge_failure( $token );
				return null;
			}

			$passkey = $this->passkeys->find_by_credential_id( $credential->rawId );
			if ( ! $passkey ) {
				$this->challenges->record_challenge_failure( $token );
				return null;
			}

			$user_id = (int) $passkey['user_id'];
			$record  = $this->record( $passkey );

			// The handle stored with the credential must be the one we derive for the row's owner.
			if ( ! hash_equals( $this->user_handle( $user_id ), $record->userHandle ) ) {
				$this->challenges->record_challenge_failure( $token );
				return null;
			}

			// A null user handle makes the library require the one the authenticator returned,
			// and that it matches this credential: nobody was identified before the ceremony.
			$updated = AuthenticatorAssertionResponseValidator::create( $this->ceremonies->requestCeremony() )
				->check( $record, $credential->response, $options, $this->rp_id, null );

			if ( ! $this->challenges->consume_challenge( $token ) ) {
				return null;
			}

			$this->passkeys->record_use( (int) $passkey['id'], $this->serializer->serialize( $updated, 'json' ) );

			return [
				'user_id' => $user_id,
				'context' => is_array( $challenge['payload']['context'] ?? null ) ? $challenge['payload']['context'] : [],
			];
		} catch ( \Throwable ) {
			$this->challenges->record_challenge_failure( $token );
			return null;
		}
	}

	/*
	|-------
	|Helpers
	|-------
	*/

	/**
	 * Rebuild the library's credential record from a stored row.
	 *
	 * @param array<string,mixed> $passkey Row from the passkey store.
	 * @return CredentialRecord
	 */
	private function record( array $passkey ) : CredentialRecord {
		return $this->serializer->deserialize( (string) $passkey['credential_data'], CredentialRecord::class, 'json' );
	}

	/**
	 * A new random WebAuthn challenge.
	 *
	 * @return string
	 */
	private function new_challenge() : string {
		return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
	}

	/**
	 * The opaque, stable user handle of a user.
	 *
	 * Derived from the application secret so it reveals nothing about the user ID.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	private function user_handle( int $user_id ) : string {
		return hash_hmac( 'sha256', 'webauthn-user:' . $user_id, $this->secret, true );
	}
}
