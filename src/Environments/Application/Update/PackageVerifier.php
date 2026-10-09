<?php
/**
 * PackageVerifier class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use SmartLicenseServer\Environments\Application\Release\ReleaseSignature;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Checks a release zip against its published checksums and their signature,
 * before anything in the zip is opened.
 *
 * A release publishes three artifacts side by side:
 *
 *     <base>.zip          the package
 *     <base>.sha256       "<sha256>  <file name>" for each artifact
 *     <base>.sha256.sig   Ed25519 signature of <base>.sha256
 *
 * A valid signature on the checksums and a matching hash of the zip prove
 * the package came from the holder of the signing key, unchanged.
 */
final class PackageVerifier {

	use UpdateProgressTrait;

	/**
	 * Constructor.
	 *
	 * @param FileSystem       $fs        Filesystem API.
	 * @param ReleaseSignature $signature Release signature checker.
	 */
	public function __construct(
		private FileSystem $fs,
		private ReleaseSignature $signature
	) {}

	/**
	 * Verify a zip against checksums and, unless trusted, their signature.
	 *
	 * @param string        $zip            Path to the zip.
	 * @param string|null   $checksums      Contents of the .sha256 file; null when there is none.
	 * @param string|null   $signature      Contents of the .sha256.sig file; null when there is none.
	 * @param bool          $trust_unsigned Accept a package without a valid signature (or checksums).
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{warnings: string[], signed_by: ?string, sha256: string}
	 *         Warnings about checks skipped because of $trust_unsigned; the label of the key that
	 *         signed the checksums (null when unsigned); the zip's SHA-256.
	 *
	 * @throws UpdateException When a check fails.
	 */
	public function verify( string $zip, ?string $checksums, ?string $signature, bool $trust_unsigned = false, ?callable $progress = null ) : array {
		$name   = basename( $zip );
		$actual = $this->fs->is_file( $zip ) ? hash_file( 'sha256', $zip ) : false;

		if ( false === $actual ) {
			throw new UpdateException( sprintf( '%s could not be read for verification.', $name ) );
		}

		$result = array( 'warnings' => array(), 'signed_by' => null, 'sha256' => $actual );

		if ( null === $checksums ) {
			if ( ! $trust_unsigned ) {
				throw new UpdateException( sprintf( 'No checksums file was found for %s, so the package cannot be verified.', $name ) );
			}

			$result['warnings'][] = 'The package has no checksums file; only the file list inside it was checked.';
			$this->report( $progress, 'verify', self::STATUS_WARNING, sprintf( '%s has no checksums file (accepted: --trust-unsigned). SHA-256 %s.', $name, $actual ) );

			return $result;
		}

		$signer = null === $signature || ! $this->signature->configured() ? null : $this->signature->signer( $checksums, $signature );

		if ( null === $signer ) {
			if ( ! $trust_unsigned ) {
				throw new UpdateException(
					$this->signature->configured()
						? 'The package signature is missing or invalid; it may not come from the publisher. Nothing was changed.'
						: 'This installation has no release signing keys, so it cannot verify any package.'
				);
			}

			$result['warnings'][] = 'The package signature was not verified (--trust-unsigned).';
			$this->report( $progress, 'verify', self::STATUS_WARNING, 'The checksums are not signed by a release key (accepted: --trust-unsigned).' );
		} else {
			$result['signed_by'] = $signer;
			$this->report( $progress, 'verify', self::STATUS_OK, sprintf( 'Checksums signature is valid (signed by %s).', $this->describe_key( $signer ) ) );
		}

		$expected = self::parse( $checksums )[ $name ] ?? null;

		if ( null === $expected ) {
			throw new UpdateException( sprintf( 'The checksums file does not list %s.', $name ) );
		}

		if ( ! hash_equals( $expected, $actual ) ) {
			throw new UpdateException( sprintf( '%s does not match its published checksum; the download may be damaged. Nothing was changed.', $name ) );
		}

		$this->report( $progress, 'verify', self::STATUS_OK, sprintf( '%s matches its published SHA-256 %s.', $name, $actual ) );

		return $result;
	}

	/**
	 * How to name a signing key in a message.
	 *
	 * @param string $signer Key label, or the base64 key when unlabelled.
	 * @return string
	 */
	private function describe_key( string $signer ) : string {
		return 44 === strlen( $signer ) && str_ends_with( $signer, '=' ) ? sprintf( 'key %s', $signer ) : sprintf( 'the "%s" key', $signer );
	}

	/**
	 * Parse a sha256sum-style checksums file.
	 *
	 * @param string $contents Lines of "<sha256>  <file name>" (a "*" before the name is allowed).
	 * @return array<string, string> File name => lowercase SHA-256.
	 */
	public static function parse( string $contents ) : array {
		$hashes = array();

		foreach ( preg_split( '/\R/', $contents ) ?: array() as $line ) {
			if ( 1 === preg_match( '/^([a-fA-F0-9]{64})\s+\*?(\S.*)$/', trim( $line ), $match ) ) {
				$hashes[ trim( $match[2] ) ] = strtolower( $match[1] );
			}
		}

		return $hashes;
	}
}