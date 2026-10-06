<?php
/**
 * Project info class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Facts about the project being built, read from the repository.
 *
 * Nothing here is typed on the command line, so every build of the same
 * source names and describes its artifacts the same way:
 *  - name: the package part of composer.json "name" (e.g. smart-license-server);
 *  - version: the SMLISER_VER constant defined under src/;
 *  - requirements: "php" and "ext-*" from composer.json "require".
 */
final class ProjectInfo {

	/**
	 * Constructor.
	 *
	 * @param string   $name       Package name, used in artifact names.
	 * @param string   $version    Application version.
	 * @param string   $php        PHP version constraint, e.g. ">=8.4".
	 * @param string[] $extensions Required PHP extensions, without the "ext-" prefix.
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $version,
		public readonly string $php,
		public readonly array $extensions
	) {}

	/**
	 * Read the project info from a repository.
	 *
	 * @param string $repo_root Repository root.
	 * @return self
	 * @throws BuildException When composer.json or the version constant is missing or invalid.
	 */
	public static function from_repo( string $repo_root ): self {
		$file = $repo_root . '/composer.json';

		try {
			$composer = json_decode( (string) @file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			throw new BuildException( "composer.json is missing or not valid JSON: {$e->getMessage()}" );
		}

		$package = (string) ( $composer['name'] ?? '' );
		$name    = substr( $package, (int) strrpos( '/' . $package, '/' ) );

		if ( 1 !== preg_match( '/^[a-z0-9][a-z0-9._-]*$/', $name ) ) {
			throw new BuildException( 'composer.json "name" must be set, e.g. "callismart/smart-license-server".' );
		}

		$require    = (array) ( $composer['require'] ?? array() );
		$extensions = array();

		foreach ( array_keys( $require ) as $package_name ) {
			if ( str_starts_with( (string) $package_name, 'ext-' ) ) {
				$extensions[] = substr( (string) $package_name, 4 );
			}
		}

		sort( $extensions );

		return new self( $name, self::read_version( $repo_root . '/src' ), (string) ( $require['php'] ?? '' ), $extensions );
	}

	/**
	 * Readable name, e.g. "Smart License Server" for "smart-license-server".
	 *
	 * @return string
	 */
	public function title(): string {
		return ucwords( str_replace( array( '-', '_', '.' ), ' ', $this->name ) );
	}

	/**
	 * The minimum PHP version as "major.minor", e.g. "8.4", or null when the constraint has none.
	 *
	 * @return string|null
	 */
	public function php_minor(): ?string {
		return 1 === preg_match( '/(\d+)\.(\d+)/', $this->php, $m ) ? "{$m[1]}.{$m[2]}" : null;
	}

	/**
	 * Find the single define( 'SMLISER_VER', '…' ) under src/.
	 *
	 * @param string $src Source directory.
	 * @return string
	 * @throws BuildException When it is missing, defined more than once, or not a version.
	 */
	private static function read_version( string $src ): string {
		$found    = array();
		$iterator = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $src, \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( 'php' !== $file->getExtension() ) {
				continue;
			}

			$code = (string) file_get_contents( $file->getPathname() );

			if ( preg_match_all( '/\bdefine\(\s*[\'"]SMLISER_VER[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $code, $m ) ) {
				foreach ( $m[1] as $version ) {
					$found[] = array( $version, substr( $file->getPathname(), strlen( $src ) - 3 ) );
				}
			}
		}

		if ( array() === $found ) {
			throw new BuildException( "No define( 'SMLISER_VER', '…' ) found under src/; the build names its artifacts with it." );
		}

		if ( count( $found ) > 1 ) {
			throw new BuildException( 'SMLISER_VER is defined more than once: ' . implode( ', ', array_column( $found, 1 ) ) );
		}

		$version = $found[0][0];

		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/', $version ) ) {
			throw new BuildException( "SMLISER_VER \"{$version}\" in {$found[0][1]} is not a version like 1.2.3." );
		}

		return $version;
	}
}