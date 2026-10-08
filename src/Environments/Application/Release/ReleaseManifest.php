<?php
/**
 * ReleaseManifest class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Release
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Release;

use RuntimeException;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * The manifest.json a release build ships at the application root.
 *
 * Written by the build tool (tools/build, ReleaseManifest::write()); this
 * class reads it and checks the installed files against it:
 *
 *     {
 *         "name": "smart-license-server",
 *         "version": "1.2.0",
 *         "target": "standalone",
 *         "built_at": "2026-10-06T09:30:00Z",
 *         "requires": { "php": ">=8.4", "extensions": [ "zip" ] },
 *         "files": { "bootstrap.php": "<sha256>", … }
 *     }
 *
 * Only the files listed are checked. Files the release does not contain
 * (.env, storage, uploads, published assets) are not its concern.
 */
final class ReleaseManifest {

	/**
	 * Manifest file name, at the application root.
	 *
	 * @var string
	 */
	public const FILE = 'manifest.json';

	/**
	 * @param string                $name     Package name.
	 * @param string                $version  Release version.
	 * @param string                $target   Build target, e.g. "standalone".
	 * @param string                $built_at Build time, ISO 8601 UTC.
	 * @param array<string, mixed>  $requires Requirements: "php" constraint and "extensions".
	 * @param array<string, string> $files    Relative path => SHA-256.
	 */
	private function __construct(
		public readonly string $name,
		public readonly string $version,
		public readonly string $target,
		public readonly string $built_at,
		public readonly array $requires,
		public readonly array $files
	) {}

	/**
	 * Read the manifest of an installation.
	 *
	 * @param FileSystem $fs   Filesystem API.
	 * @param string     $root Application root.
	 * @return self|null Null when there is no manifest (a source checkout rather than a release).
	 *
	 * @throws RuntimeException When the manifest exists but cannot be read or is malformed.
	 */
	public static function load( FileSystem $fs, string $root ): ?self {
		$path = rtrim( $root, '/\\' ) . '/' . self::FILE;

		if ( ! $fs->is_file( $path ) ) {
			return null;
		}

		$json = $fs->get_contents( $path );
		$data = is_string( $json ) ? json_decode( $json, true ) : null;

		if ( ! is_array( $data ) || ! is_array( $data['files'] ?? null ) || ! is_string( $data['version'] ?? null ) ) {
			throw new RuntimeException( sprintf( '%s is damaged or not a release manifest.', $path ) );
		}

		$files = array();

		foreach ( $data['files'] as $relative => $hash ) {
			if ( ! is_string( $relative ) || ! self::is_safe_path( $relative ) || ! is_string( $hash ) || 1 !== preg_match( '/^[a-f0-9]{64}$/', $hash ) ) {
				throw new RuntimeException( sprintf( '%s lists an invalid entry: %s', $path, (string) $relative ) );
			}

			$files[ $relative ] = $hash;
		}

		return new self(
			(string) ( $data['name'] ?? '' ),
			$data['version'],
			(string) ( $data['target'] ?? '' ),
			(string) ( $data['built_at'] ?? '' ),
			is_array( $data['requires'] ?? null ) ? $data['requires'] : array(),
			$files
		);
	}

	/**
	 * Compare the installed files with the manifest.
	 *
	 * Every listed file must exist and match its hash. .htaccess files are
	 * only required to exist: the single-file installer merges the host's
	 * own lines into them, so their content legitimately differs.
	 *
	 * @param FileSystem    $fs       Filesystem API.
	 * @param string        $root     Application root.
	 * @param callable|null $progress Optional fn( int $done, int $total ), called every 250 files and at the end.
	 * @return ReleaseCheck
	 */
	public function verify( FileSystem $fs, string $root, ?callable $progress = null ): ReleaseCheck {
		$root     = rtrim( $root, '/\\' ) . '/';
		$missing  = array();
		$modified = array();
		$done     = 0;
		$total    = count( $this->files );

		foreach ( $this->files as $relative => $hash ) {
			$path = $root . $relative;

			if ( ! $fs->is_file( $path ) ) {
				$missing[] = $relative;
			} elseif ( '.htaccess' !== basename( $relative ) ) {
				// Hashed in streaming mode, so large files are never loaded into memory.
				$actual = hash_file( 'sha256', $path );

				if ( false === $actual || ! hash_equals( $hash, $actual ) ) {
					$modified[] = $relative;
				}
			}

			++$done;

			if ( null !== $progress && ( 0 === $done % 250 || $done === $total ) ) {
				$progress( $done, $total );
			}
		}

		return new ReleaseCheck( $missing, $modified, $total );
	}

	/**
	 * Whether a manifest path stays inside the application root.
	 *
	 * @param string $relative Path from the manifest.
	 * @return bool
	 */
	private static function is_safe_path( string $relative ): bool {
		return '' !== $relative
			&& '/' !== $relative[0]
			&& ! str_contains( $relative, '\\' )
			&& ! str_contains( $relative, ':' )
			&& ! in_array( '..', explode( '/', $relative ), true );
	}
}