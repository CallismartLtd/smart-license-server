<?php
/**
 * Release signer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Signs a release file with Ed25519, writing <file>.sig beside it.
 *
 * The packager signs the .sha256 checksums file, which holds the hash of
 * every artifact, so the signature covers the zip, the tarball and the
 * single-file installer. The updater checks the signature on the checksums,
 * then the downloaded zip against them, before opening anything.
 *
 * <file>.sig holds the base64 detached signature of the file's exact bytes.
 * The signing key is a file holding the base64 Ed25519 secret key
 * (64 bytes), made once with `build.php --generate-key=<path>` and kept
 * outside the repository. Its public key goes into the application's
 * ReleaseSignature::PUBLIC_KEYS.
 */
final class ReleaseSigner {

	/**
	 * Suffix of a signature file, appended to the signed file's name.
	 */
	public const SUFFIX = '.sig';

	/**
	 * Constructor.
	 *
	 * @param BuildContext $context Build context.
	 * @param BuildConsole $console Console.
	 */
	public function __construct(
		private readonly BuildContext $context,
		private readonly BuildConsole $console
	) {}

	/**
	 * Sign a file, or warn that the release is unsigned when no key was given.
	 *
	 * @param string $path File to sign; the signature is written to $path . SUFFIX.
	 * @return void
	 * @throws BuildException When the key cannot be used, or the file cannot be read or the signature written.
	 */
	public function sign( string $path ): void {
		if ( null === $this->context->sign_key ) {
			$this->console->warn( 'Unsigned build (--allow-unsigned): installations will refuse it. Do not publish it.' );
			return;
		}

		self::require_sodium();

		$secret   = self::read_key( $this->context->sign_key );
		$contents = file_get_contents( $path );

		if ( false === $contents ) {
			sodium_memzero( $secret );
			throw new BuildException( "Could not read {$path} to sign it." );
		}

		$signature = sodium_crypto_sign_detached( $contents, $secret );
		$public    = base64_encode( sodium_crypto_sign_publickey_from_secretkey( $secret ) );

		sodium_memzero( $secret );

		if ( false === file_put_contents( $path . self::SUFFIX, base64_encode( $signature ) . "\n" ) ) {
			throw new BuildException( 'Could not write ' . $path . self::SUFFIX . '.' );
		}

		$this->console->success( sprintf( '%s (public key %s)', basename( $path . self::SUFFIX ), $public ) );
	}

	/**
	 * The base64 public key of a signing key file.
	 *
	 * @param string $path Key file.
	 * @return string
	 * @throws BuildException When the file is missing or does not hold a key.
	 */
	public static function public_key( string $path ): string {
		self::require_sodium();

		$secret = self::read_key( $path );
		$public = base64_encode( sodium_crypto_sign_publickey_from_secretkey( $secret ) );

		sodium_memzero( $secret );

		return $public;
	}

	/**
	 * Create a new signing key file.
	 *
	 * @param string $path Where to write the secret key; must not exist.
	 * @return string The base64 public key, for ReleaseSignature::PUBLIC_KEYS.
	 * @throws BuildException When the file exists or cannot be written.
	 */
	public static function generate_key( string $path ): string {
		self::require_sodium();

		if ( file_exists( $path ) ) {
			throw new BuildException( "{$path} already exists; refusing to replace a signing key." );
		}

		$pair   = sodium_crypto_sign_keypair();
		$secret = sodium_crypto_sign_secretkey( $pair );
		$public = base64_encode( sodium_crypto_sign_publickey( $pair ) );

		$written = file_put_contents( $path, base64_encode( $secret ) . "\n" );

		sodium_memzero( $secret );
		sodium_memzero( $pair );

		if ( false === $written ) {
			throw new BuildException( "Could not write {$path}." );
		}

		chmod( $path, 0600 );

		return $public;
	}

	/**
	 * Read a secret key file.
	 *
	 * @param string $path Key file.
	 * @return string The 64-byte secret key.
	 * @throws BuildException When the file is missing or does not hold a key.
	 */
	private static function read_key( string $path ): string {
		$contents = is_file( $path ) ? file_get_contents( $path ) : false;

		if ( false === $contents ) {
			throw new BuildException( "Signing key {$path} not found or not readable." );
		}

		$secret = base64_decode( trim( $contents ), true );

		if ( false === $secret || SODIUM_CRYPTO_SIGN_SECRETKEYBYTES !== strlen( $secret ) ) {
			throw new BuildException( "{$path} is not an Ed25519 signing key." );
		}

		return $secret;
	}

	/**
	 * Fail clearly when PHP was built without sodium.
	 *
	 * @return void
	 * @throws BuildException When sodium is unavailable.
	 */
	private static function require_sodium(): void {
		if ( ! function_exists( 'sodium_crypto_sign_detached' ) ) {
			throw new BuildException( 'Signing needs the sodium extension.' );
		}
	}
}