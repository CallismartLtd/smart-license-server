<?php
/**
 * Release manifest class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

use SmartLicenseServer\Build\Targets\AbstractTarget;

/**
 * Writes manifest.json at the root of the build.
 *
 * The manifest describes the release and lists every file it contains with
 * its SHA-256 hash, so a download can be verified, an installation can check
 * its own files (missing, changed or left over from a broken upload), and an
 * updater can tell what a release adds, changes and removes:
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
 * The manifest does not list itself.
 */
final class ReleaseManifest {

	/**
	 * Manifest file name, at the build root.
	 */
	public const FILE = 'manifest.json';

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
	 * Hash every file in the build and write the manifest.
	 *
	 * @param AbstractTarget $target Target.
	 * @param int            $time   Build time (Unix timestamp).
	 * @return void
	 * @throws BuildException When a file cannot be read or the manifest cannot be written.
	 */
	public function write( AbstractTarget $target, int $time ): void {
		$this->console->step( 'Writing release manifest' );

		$files = array();

		foreach ( self::files( $this->context->out_dir ) as $relative ) {
			if ( self::FILE === $relative ) {
				continue;
			}

			$hash = hash_file( 'sha256', $this->context->target( $relative ) );

			if ( false === $hash ) {
				throw new BuildException( "Could not read {$relative} to hash it." );
			}

			$files[ $relative ] = $hash;
		}

		$project  = $this->context->project;
		$manifest = array(
			'name'     => $project->name,
			'version'  => $project->version,
			'target'   => $target->name(),
			'built_at' => gmdate( 'Y-m-d\TH:i:s\Z', $time ),
			'requires' => array(
				'php'        => $project->php,
				'extensions' => $project->extensions,
			),
			'files'    => $files,
		);

		$json = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR ) . "\n";

		if ( false === file_put_contents( $this->context->target( self::FILE ), $json ) ) {
			throw new BuildException( 'Could not write ' . self::FILE . '.' );
		}

		$this->console->success( sprintf( '%s (%s %s, %d files)', self::FILE, $project->name, $project->version, count( $files ) ) );
	}

	/**
	 * Every file under a directory, relative to it, sorted.
	 *
	 * @param string $dir Directory.
	 * @return string[] Relative paths with forward slashes.
	 */
	public static function files( string $dir ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			if ( $file->isFile() ) {
				$files[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
			}
		}

		sort( $files, SORT_STRING );

		return $files;
	}
}