<?php
/**
 * ReleaseSignature class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Release
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Release;

/**
 * Checks the Ed25519 signatures a release build writes beside its files.
 *
 * tools/build signs the release's .sha256 checksums file and publishes the
 * base64 signature as <name>-<version>-<target>.sha256.sig. A valid
 * signature on the checksums, followed by a matching hash of the downloaded
 * zip, proves the package came from the holder of the signing key and
 * arrived unchanged, even if the server it was downloaded from was not.
 */
final class ReleaseSignature {

	/**
	 * Suffix of a signature file, appended to the signed file's name.
	 *
	 * @var string
	 */
	public const SUFFIX = '.sig';

	/**
	 * Base64 Ed25519 public keys releases may be signed with.
	 *
	 * Printed by `php tools/build/build.php --generate-key=<path>`. To rotate
	 * a key, add the new one, ship a release signed with the old key, then
	 * sign with the new one and remove the old key in a later release.
	 *
	 * Keys may be labelled, so update logs name the key that signed a
	 * package: array( 'release' => '...', 'recovery' => '...' ). Unlabelled
	 * keys are reported by their base64 value.
	 *
	 * @var array<string|int, string>
	 */
	public const PUBLIC_KEYS = array(
		'release'  => 'gWdFBlGnnabdKn1QY0KswecDiLaKFPvQ31j9624B2eI=',
		'recovery' => 'ckTouIv9PH6UNasGWnxiI2kK61tKF3+t+dBfQOwuEEc=',
	);

	/**
	 * Decoded public keys, keyed by label (or the base64 key when unlabelled).
	 *
	 * @var array<string, string>
	 */
	private array $keys = array();

	/**
	 * Constructor.
	 *
	 * @param array<string|int, string> $public_keys Base64 public keys, optionally labelled; defaults to PUBLIC_KEYS.
	 *
	 * @throws \InvalidArgumentException When a key is not a base64 Ed25519 public key.
	 */
	public function __construct( array $public_keys = self::PUBLIC_KEYS ) {
		foreach ( $public_keys as $label => $encoded ) {
			$key = is_string( $encoded ) ? base64_decode( $encoded, true ) : false;

			if ( false === $key || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $key ) ) {
				throw new \InvalidArgumentException( 'Invalid release public key.' );
			}

			$this->keys[ is_string( $label ) ? $label : $encoded ] = $key;
		}
	}

	/**
	 * Whether any public key is configured, so signed releases can be checked.
	 *
	 * @return bool
	 */
	public function configured(): bool {
		return ! empty( $this->keys );
	}

	/**
	 * Whether a signature matches the data under one of the public keys.
	 *
	 * @param string $data      The signed file's exact contents.
	 * @param string $signature Contents of its .sig file (base64; surrounding whitespace ignored).
	 * @return bool
	 */
	public function verify( string $data, string $signature ): bool {
		return null !== $this->signer( $data, $signature );
	}

	/**
	 * The key a signature was made with, among the configured ones.
	 *
	 * @param string $data      The signed file's exact contents.
	 * @param string $signature Contents of its .sig file (base64; surrounding whitespace ignored).
	 * @return string|null The key's label (or base64 value when unlabelled); null when no key matches.
	 */
	public function signer( string $data, string $signature ): ?string {
		$signature = base64_decode( trim( $signature ), true );

		if ( false === $signature || SODIUM_CRYPTO_SIGN_BYTES !== strlen( $signature ) ) {
			return null;
		}

		foreach ( $this->keys as $label => $key ) {
			if ( sodium_crypto_sign_verify_detached( $signature, $data, $key ) ) {
				return (string) $label;
			}
		}

		return null;
	}
}