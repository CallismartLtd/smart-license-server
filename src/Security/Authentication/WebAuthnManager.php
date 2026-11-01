<?php
/**
 * WebAuthn ceremonies for passkey enrollment and authentication.
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
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\AuthenticatorSelectionCriteria;

/** Wraps the maintained WebAuthn library and keeps ceremony validation centralized. */
final class WebAuthnManager {
	private \Symfony\Component\Serializer\SerializerInterface $serializer;
	private CeremonyStepManagerFactory $ceremonies;
	private string $origin;
	private string $rp_id;

	public function __construct(
		private AuthenticationFactorManager $factors,
		private URLManager $urls,
		#[\SensitiveParameter] private string $secret
	) {
		$origin = $urls->url()->get_origin();
		if ( ! is_string( $origin ) || '' === $origin ) {
			throw new \RuntimeException( 'WebAuthn requires a configured site origin.' );
		}
		$this->origin = $origin;
		$this->rp_id  = (string) parse_url( $origin, PHP_URL_HOST );
		if ( '' === $this->rp_id ) {
			throw new \RuntimeException( 'WebAuthn requires a valid relying party host.' );
		}
		$this->ceremonies = new CeremonyStepManagerFactory();
		$this->ceremonies->setAllowedOrigins( [ $origin ] );
		$support = new AttestationStatementSupportManager();
		$this->serializer = ( new WebauthnSerializerFactory( $support ) )->create();
	}

	/** Build registration options and return their token-bound challenge. */
	public function begin_registration( User $user, string $label ) : array {
		$challenge = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' );
		$exclude = [];
		foreach ( $this->factors->passkeys( (int) $user->get_id() ) as $passkey ) {
			$record = $this->serializer->deserialize( (string) $passkey['credential_data'], \Webauthn\CredentialRecord::class, 'json' );
			$exclude[] = $record->getPublicKeyCredentialDescriptor();
		}
		$options = PublicKeyCredentialCreationOptions::create(
			PublicKeyCredentialRpEntity::create( '', $this->rp_id ),
			PublicKeyCredentialUserEntity::create(
				$user->get_email(),
				$this->user_handle( (int) $user->get_id() ),
				$user->get_email()
			),
			$challenge,
			[
				PublicKeyCredentialParameters::create( PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -7 ),
				PublicKeyCredentialParameters::create( PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY, -257 ),
			],
			AuthenticatorSelectionCriteria::create( null, 'required', 'preferred' ),
			'none',
			$exclude,
			120000
		);
		$token = $this->factors->create_challenge( (int) $user->get_id(), 'passkey_enroll', [
			'options' => $this->serializer->serialize( $options, 'json' ),
			'label'   => $label,
		] );
		return [ 'challenge' => $token, 'options' => $this->serializer->serialize( $options, 'json' ) ];
	}

	/** Validate and persist a registration response. */
	public function finish_registration( User $user, string $token, string $credential_json ) : bool {
		$challenge = $this->factors->get_challenge( $token, 'passkey_enroll' );
		if ( ! $challenge || (int) $challenge['user_id'] !== (int) $user->get_id() ) {
			return false;
		}
		try {
			$options = $this->serializer->deserialize( $challenge['payload']['options'], PublicKeyCredentialCreationOptions::class, 'json' );
			$credential = $this->serializer->deserialize( $credential_json, PublicKeyCredential::class, 'json' );
			if ( ! $credential->response instanceof AuthenticatorAttestationResponse ) {
				return false;
			}
			$record = AuthenticatorAttestationResponseValidator::create( $this->ceremonies->creationCeremony() )
				->check( $credential->response, $options, $this->rp_id );
			if ( ! $this->factors->consume_challenge( $token ) ) {
				return false;
			}
			return $this->factors->save_passkey(
				(int) $user->get_id(),
				$record->publicKeyCredentialId,
				$this->serializer->serialize( $record, 'json' ),
				(string) ( $challenge['payload']['label'] ?? 'Passkey' )
			);
		} catch ( \Throwable ) {
			$this->factors->record_challenge_failure( $token );
			return false;
		}
	}

	/** Build an assertion request restricted to a user's registered passkeys. */
	public function begin_assertion( User $user, string $parent_challenge ) : array {
		$descriptors = [];
		foreach ( $this->factors->passkeys( (int) $user->get_id() ) as $passkey ) {
			$record = $this->serializer->deserialize( (string) $passkey['credential_data'], \Webauthn\CredentialRecord::class, 'json' );
			$descriptors[] = $record->getPublicKeyCredentialDescriptor();
		}
		if ( ! $descriptors ) {
			throw new \RuntimeException( 'No passkeys are registered.' );
		}
		$options = PublicKeyCredentialRequestOptions::create(
			rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ),
			$this->rp_id,
			$descriptors,
			'required',
			120000
		);
		$token = $this->factors->create_challenge( (int) $user->get_id(), 'login_passkey', [
			'options' => $this->serializer->serialize( $options, 'json' ),
			'parent'  => $parent_challenge,
		] );
		return [ 'challenge' => $token, 'options' => $this->serializer->serialize( $options, 'json' ) ];
	}

	/** Validate a passkey assertion and consume the parent login challenge. */
	public function finish_assertion( string $token, string $credential_json ) : ?array {
		$challenge = $this->factors->get_challenge( $token, 'login_passkey' );
		if ( ! $challenge ) {
			return null;
		}
		try {
			$parent_token = (string) ( $challenge['payload']['parent'] ?? '' );
			$parent = $this->factors->get_challenge( $parent_token, 'login' );
			if ( ! $parent || (int) $parent['user_id'] !== (int) $challenge['user_id'] ) {
				return null;
			}
			$options = $this->serializer->deserialize( $challenge['payload']['options'], PublicKeyCredentialRequestOptions::class, 'json' );
			$credential = $this->serializer->deserialize( $credential_json, PublicKeyCredential::class, 'json' );
			if ( ! $credential->response instanceof AuthenticatorAssertionResponse ) {
				return null;
			}
			$passkey = $this->factors->passkey_by_credential_id( $credential->rawId );
			if ( ! $passkey || (int) $passkey['user_id'] !== (int) $challenge['user_id'] ) {
				$this->factors->record_challenge_failure( $token );
				return null;
			}
			$record = $this->serializer->deserialize( (string) $passkey['credential_data'], \Webauthn\CredentialRecord::class, 'json' );
			$updated = AuthenticatorAssertionResponseValidator::create( $this->ceremonies->requestCeremony() )
				->check( $record, $credential->response, $options, $this->rp_id, $record->userHandle );
			if ( ! $this->factors->consume_challenge( $token ) ) {
				return null;
			}
			$this->factors->update_passkey( (int) $passkey['id'], $this->serializer->serialize( $updated, 'json' ) );
			return $this->factors->consume_challenge( $parent_token )
				? [ 'user_id' => (int) $challenge['user_id'], 'payload' => $parent['payload'] ]
				: null;
		} catch ( \Throwable ) {
			$this->factors->record_challenge_failure( $token );
			return null;
		}
	}

	private function user_handle( int $user_id ) : string {
		return hash_hmac( 'sha256', 'webauthn-user:' . $user_id, $this->secret, true );
	}
}
