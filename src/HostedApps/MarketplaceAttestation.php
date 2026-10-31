<?php
/**
 * Marketplace artifact attestation.
 */

declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

use RuntimeException;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\HostedApps\Storage\AppStorage;

/**
 * Creates marketplace checksums and detached signatures for hosted artifacts.
 *
 * The private key is configured on the hosting service as
 * SMLISER_MARKETPLACE_SIGNING_KEY (base64 Ed25519 secret key). It must never be
 * shipped with hosted apps. Clients pin PUBLIC_KEY in their marketplace
 * verifier.
 */
final class MarketplaceAttestation {

	/** Hosted types whose releases are controlled by the official marketplace. */
	public const array REQUIRED_APP_TYPES = [ 'smliser-theme', 'smliser-extension' ];

	/** The marketplace signing key's public half, shipped in client apps. */
	public const PUBLIC_KEY = 'rs9am8LBRxNSqxIxhDo40OyFucyjxxER2NFpTwfhtvY=';

	/** Fail before persisting a package when the service key is unavailable. */
	public function assert_configured() : void {
		$this->secret_key();
	}

	/**
	 * Attest a stored ZIP artifact and write its .sha256 and .sha256.sig files.
	 *
	 * The signed checksum includes the hosted app type, slug and artifact name,
	 * so the signature cannot be moved to a different hosted app identity.
	 *
	 * @return array{filename: string, sha256: string, signature: string}
	 */
	public function attest( AppStorage $storage, string $type, string $slug, string $filename ) : array {
		if ( ! str_ends_with( strtolower( $filename ), '.zip' ) ) {
			throw new RuntimeException( 'Only ZIP artifacts can receive marketplace attestations.' );
		}

		$file = AppFile::artifact( $type, $slug, $filename );
		$stream = $storage->read_stream( $file );

		if ( ! is_resource( $stream ) ) {
			throw new RuntimeException( 'The artifact could not be opened for attestation.' );
		}

		$context = hash_init( 'sha256' );
		hash_update_stream( $context, $stream );
		fclose( $stream );
		$sha256 = hash_final( $context );
		$base = substr( $filename, 0, -4 );
		$checksums = sprintf(
			"# smliser-marketplace-attestation-v1\n# type: %s\n# slug: %s\n# artifact: %s\n%s  %s\n",
			$type,
			$slug,
			$filename,
			$sha256,
			$filename
		);
		$signature = base64_encode( sodium_crypto_sign_detached( $checksums, $this->secret_key() ) );

		$this->store_text_artifact( $storage, $type, $slug, $base . '.sha256', $checksums );
		$this->store_text_artifact( $storage, $type, $slug, $base . '.sha256.sig', $signature . "\n" );

		return [ 'filename' => $filename, 'sha256' => $sha256, 'signature' => $signature ];
	}

	/**
	 * Write a text sidecar through the generic hosted-app storage API.
	 */
	private function store_text_artifact( AppStorage $storage, string $type, string $slug, string $name, string $contents ) : void {
		$path = tempnam( sys_get_temp_dir(), 'smliser-attestation-' );

		if ( false === $path ) {
			throw new RuntimeException( 'Could not create a temporary attestation file.' );
		}

		try {
			if ( false === file_put_contents( $path, $contents, LOCK_EX ) ) {
				throw new RuntimeException( 'Could not write an attestation file.' );
			}

			$existing = array_column( $storage->list_artifacts( $type, $slug ), 'name' );
			$storage->put_artifact( $type, $slug, $name, $path, in_array( $name, $existing, true ) );
		} finally {
			@unlink( $path );
		}
	}

	/**
	 * Get and validate the configured marketplace private key.
	 */
	private function secret_key() : string {
		$encoded = $_ENV['SMLISER_MARKETPLACE_SIGNING_KEY'] ?? getenv( 'SMLISER_MARKETPLACE_SIGNING_KEY' );
		$key = is_string( $encoded ) ? base64_decode( trim( $encoded ), true ) : false;

		if ( false === $key || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $key ) ) {
			throw new RuntimeException( 'Marketplace attestation is not configured. Set SMLISER_MARKETPLACE_SIGNING_KEY on the hosting service.' );
		}

		$public = sodium_crypto_sign_publickey_from_secretkey( $key );
		if ( ! hash_equals( base64_decode( self::PUBLIC_KEY, true ), $public ) ) {
			throw new RuntimeException( 'The marketplace signing key does not match the public key pinned by client apps.' );
		}

		return $key;
	}
}
