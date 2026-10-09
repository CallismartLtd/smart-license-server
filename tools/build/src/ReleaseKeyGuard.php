<?php
/**
 * Release key guard class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Refuses to build a release that installations could not verify updates with.
 *
 * Installations only accept packages signed by a key in the
 * ReleaseSignature::PUBLIC_KEYS they run. A release that ships an empty or
 * wrong list cannot verify any later release, so every site running it is
 * stuck until an administrator repairs it by hand. This guard makes that
 * impossible to publish:
 *
 *  - PUBLIC_KEYS must hold at least one valid Ed25519 public key;
 *  - the public key of the signing key (--sign-key) must be one of them,
 *    so the next release, signed with the same key, will be accepted;
 *  - a build without a signing key is refused unless --allow-unsigned is
 *    given (for local testing only; such a build is never to be published).
 *
 * It runs twice: on the sources before anything is copied (fail fast), and
 * on the built tree before the manifest is written (what actually ships).
 *
 * PUBLIC_KEYS is read with PHP's tokenizer; no application code is run.
 */
final class ReleaseKeyGuard {

	/**
	 * File holding the public keys.
	 */
	private const FILE = 'ReleaseSignature.php';

	/**
	 * Class declaring the public keys.
	 */
	private const CLASS_NAME = 'ReleaseSignature';

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
	 * Check the public keys under a directory against the signing key.
	 *
	 * @param string $dir   Directory to search for ReleaseSignature.php (the sources or the built tree).
	 * @param string $label What is checked, for messages ("sources", "build").
	 * @return void
	 * @throws BuildException When the release would leave installations unable to verify updates.
	 */
	public function check( string $dir, string $label ): void {
		$file = $this->find( $dir );
		$name = $this->relative( $file );
		$keys = self::read_keys( $file, $name );

		if ( array() === $keys ) {
			throw new BuildException(
				sprintf(
					'%s has no public keys in PUBLIC_KEYS. Installations running this release could never verify an update. Add your release (and recovery) public keys before building.',
					$name
				)
			);
		}

		if ( null === $this->context->sign_key ) {
			if ( ! $this->context->allow_unsigned ) {
				throw new BuildException( 'No signing key given (--sign-key or SMLISER_SIGNING_KEY). Release builds must be signed; use --allow-unsigned only for a local test build that will never be published.' );
			}

			$this->console->warn( sprintf( '%s: %d public key(s); unsigned build (--allow-unsigned), so the signing key cannot be checked against them. Do not publish this build.', $label, count( $keys ) ) );
			return;
		}

		$public = ReleaseSigner::public_key( $this->context->sign_key );
		$match  = array_search( $public, $keys, true );

		if ( false === $match ) {
			throw new BuildException(
				sprintf(
					'The signing key\'s public key %s is not in PUBLIC_KEYS of %s (%s). Installations would accept this release but refuse the next one signed with this key. Add it, or sign with a key that is listed.',
					$public,
					$name,
					implode( ', ', $keys )
				)
			);
		}

		$this->console->success(
			sprintf(
				'%s: signing key is in PUBLIC_KEYS as %s (%d key(s) listed)',
				$label,
				is_string( $match ) ? sprintf( '"%s"', $match ) : $public,
				count( $keys )
			)
		);
	}

	/**
	 * The public keys declared in a ReleaseSignature.php.
	 *
	 * Accepts array() or [] with plain or labelled entries:
	 * array( 'release' => '<base64>', '<base64>' ). Anything else inside the
	 * constant (concatenation, other constants, an invalid key) is refused,
	 * since the build could not be sure what the application will read.
	 *
	 * @param string      $file Path to ReleaseSignature.php.
	 * @param string|null $name Name of the file in messages; defaults to $file.
	 * @return array<string|int, string> Base64 public keys, keyed by label when labelled.
	 * @throws BuildException When the file cannot be read or the constant is not a plain list of keys.
	 */
	public static function read_keys( string $file, ?string $name = null ): array {
		$code = is_file( $file ) ? file_get_contents( $file ) : false;
		$name = $name ?? $file;

		if ( false === $code ) {
			throw new BuildException( "Could not read {$name}." );
		}

		$tokens = array_values(
			array_filter(
				token_get_all( $code ),
				static fn ( $token ): bool => ! is_array( $token ) || ! in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true )
			)
		);

		$start = null;

		foreach ( $tokens as $i => $token ) {
			if ( is_array( $token ) && T_STRING === $token[0] && 'PUBLIC_KEYS' === $token[1] && is_array( $tokens[ $i - 1 ] ?? null ) && T_CONST === $tokens[ $i - 1 ][0] ) {
				$start = $i + 1;
				break;
			}
		}

		if ( null === $start || '=' !== ( $tokens[ $start ] ?? null ) ) {
			throw new BuildException( "{$name} does not declare const PUBLIC_KEYS." );
		}

		$keys  = array();
		$label = null;

		for ( $i = $start + 1, $count = count( $tokens ); $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ';' === $token ) {
				return $keys;
			}

			if ( in_array( $token, array( '[', ']', '(', ')', ',' ), true ) || ( is_array( $token ) && in_array( $token[0], array( T_ARRAY, T_DOUBLE_ARROW ), true ) ) ) {
				continue;
			}

			if ( ! is_array( $token ) || T_CONSTANT_ENCAPSED_STRING !== $token[0] ) {
				throw new BuildException( sprintf( '%s: PUBLIC_KEYS must be a plain list of quoted keys; found "%s".', $name, is_array( $token ) ? $token[1] : $token ) );
			}

			$value = stripcslashes( substr( $token[1], 1, -1 ) );
			$next  = $tokens[ $i + 1 ] ?? null;

			if ( is_array( $next ) && T_DOUBLE_ARROW === $next[0] ) {
				$label = $value;
				continue;
			}

			$decoded = base64_decode( $value, true );

			if ( false === $decoded || SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES !== strlen( $decoded ) ) {
				throw new BuildException( sprintf( '%s: "%s" in PUBLIC_KEYS is not a base64 Ed25519 public key (keys must be whole quoted strings).', $name, $value ) );
			}

			if ( null === $label ) {
				$keys[] = $value;
			} else {
				$keys[ $label ] = $value;
				$label          = null;
			}
		}

		throw new BuildException( "{$name}: PUBLIC_KEYS is not terminated." );
	}

	/**
	 * Find the one ReleaseSignature.php under a directory.
	 *
	 * @param string $dir Directory.
	 * @return string Its path.
	 * @throws BuildException When there is none, or more than one.
	 */
	private function find( string $dir ): string {
		$found = array();
		$files = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
				static fn ( \SplFileInfo $item ): bool => ! $item->isDir() || ! in_array( $item->getFilename(), array( 'vendor', 'node_modules', '.git' ), true )
			)
		);

		foreach ( $files as $file ) {
			if ( self::FILE === $file->getFilename() && 1 === preg_match( '/\bclass\s+' . self::CLASS_NAME . '\b/', (string) file_get_contents( $file->getPathname() ) ) ) {
				$found[] = $file->getPathname();
			}
		}

		if ( 1 !== count( $found ) ) {
			throw new BuildException(
				0 === count( $found )
					? sprintf( 'No %s declaring class %s was found under %s.', self::FILE, self::CLASS_NAME, $dir )
					: sprintf( 'More than one %s was found: %s.', self::FILE, implode( ', ', $found ) )
			);
		}

		return $found[0];
	}

	/**
	 * A path relative to the repository or build directory, for messages.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function relative( string $path ): string {
		foreach ( array( $this->context->out_dir, $this->context->repo_root ) as $root ) {
			if ( str_starts_with( $path, $root . '/' ) ) {
				return substr( $path, strlen( $root ) + 1 );
			}
		}

		return $path;
	}
}