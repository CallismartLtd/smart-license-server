<?php
/**
 * Builder class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

use SmartLicenseServer\Build\Targets\AbstractTarget;

/**
 * Runs the steps shared by every build target.
 */
final class Builder {

	/**
	 * Repository directories every build copies.
	 */
	private const SOURCE_DIRS = array( 'src', 'templates', 'assets' );

	/**
	 * Number of files copied in this run.
	 *
	 * @var int
	 */
	private int $copied = 0;

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
	 * Build the given target.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException On any failure.
	 */
	public function run( AbstractTarget $target ): void {
		$started = microtime( true );

		$this->console->write(
			sprintf( 'Building %s (core: %s)', $target->name(), $target->core_path( $this->context ) )
		);

		$this->validate( $target );
		$this->prepare_output();
		$this->copy_sources( $target );
		$this->copy_root_files( $target );

		if ( $this->context->minify && $target->minify_assets() ) {
			( new AssetMinifier( $this->context, $this->console ) )->run( $this->context->target( $target->assets_path() ) );
		}

		if ( $target->installs_dependencies() ) {
			$this->install_dependencies( $target );
		}

		$this->write_generated_files( $target );
		$target->after_build( $this->context, $this->console );

		$this->console->write(
			sprintf( '%sDone in %.1fs → %s', PHP_EOL, microtime( true ) - $started, $this->context->out_dir )
		);
	}

	/*
	|-----------
	| Validation
	|-----------
	*/

	/**
	 * Check sources and the output path before touching anything.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a precondition fails.
	 */
	private function validate( AbstractTarget $target ): void {
		$this->console->step( 'Validating' );

		foreach ( self::SOURCE_DIRS as $dir ) {
			if ( ! is_dir( $this->context->source( $dir ) ) ) {
				throw new BuildException( "Source directory {$dir}/ not found in {$this->context->repo_root}." );
			}
		}

		$environment = 'src/Environments/' . $target->environment();
		if ( ! is_dir( $this->context->source( $environment ) ) ) {
			throw new BuildException( "Target environment {$environment}/ not found." );
		}

		foreach ( array_keys( $target->root_files() ) as $file ) {
			if ( ! is_file( $this->context->source( $file ) ) ) {
				throw new BuildException( "Root file {$file} not found." );
			}
		}

		if ( $target->installs_dependencies() && ! is_file( $this->context->source( 'composer.json' ) ) ) {
			throw new BuildException( 'composer.json not found.' );
		}

		$this->guard_output_path();

		$this->console->success( 'Sources and output path OK' );
	}

	/**
	 * Refuse output paths that would delete or nest inside the repository sources.
	 *
	 * @throws BuildException When the output path is unsafe.
	 */
	private function guard_output_path(): void {
		$out  = $this->normalize( $this->context->out_dir );
		$root = $this->normalize( $this->context->repo_root );

		if ( $out === $root || str_starts_with( $root . '/', $out . '/' ) ) {
			throw new BuildException( 'Output directory must not be the repository root or one of its parents.' );
		}

		foreach ( self::SOURCE_DIRS as $dir ) {
			$source = $root . '/' . $dir;
			if ( $out === $source || str_starts_with( $out . '/', $source . '/' ) ) {
				throw new BuildException( "Output directory must not be inside {$dir}/." );
			}
		}
	}

	/*
	|----------------
	| Copying sources
	|----------------
	*/

	/**
	 * Reset the output directory.
	 *
	 * @throws BuildException When the directory cannot be created.
	 */
	private function prepare_output(): void {
		$this->console->step( 'Preparing output directory' );

		if ( is_dir( $this->context->out_dir ) ) {
			$this->remove_tree( $this->context->out_dir );
		}

		$this->make_dir( $this->context->out_dir );
		$this->console->success( $this->context->out_dir );
	}

	/**
	 * Copy src/, templates/ and assets/ into the runtime directory.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a copy fails.
	 */
	private function copy_sources( AbstractTarget $target ): void {
		$this->console->step( 'Copying sources' );

		$destinations = array(
			'src'       => $target->core_path( $this->context ),
			'templates' => $target->templates_path(),
			'assets'    => $target->assets_path(),
		);

		foreach ( $destinations as $source => $destination ) {
			$before  = $this->copied;
			$exclude = 'src' === $source ? $this->excluded_environments( $target ) : array();

			$this->copy_tree( $this->context->source( $source ), $this->context->target( $destination ), $exclude );

			$this->console->success( sprintf( '%s/ → %s/ (%d files)', $source, $destination, $this->copied - $before ) );
		}

		$kept = 'Environments/' . $target->environment();
		$this->console->info( "Kept {$kept}/; excluded: " . ( implode( ', ', $this->excluded_environments( $target ) ) ?: 'none' ) );
	}

	/**
	 * src-relative Environments subdirectories that do not belong to the target.
	 *
	 * @param AbstractTarget $target Target.
	 * @return string[]
	 */
	private function excluded_environments( AbstractTarget $target ): array {
		$excluded = array();
		$entries  = scandir( $this->context->source( 'src/Environments' ) ) ?: array();

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry || $entry === $target->environment() ) {
				continue;
			}

			if ( is_dir( $this->context->source( 'src/Environments/' . $entry ) ) ) {
				$excluded[] = 'Environments/' . $entry;
			}
		}

		sort( $excluded );
		return $excluded;
	}

	/**
	 * Copy the target's root files.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a copy fails.
	 */
	private function copy_root_files( AbstractTarget $target ): void {
		$this->console->step( 'Copying root files' );

		foreach ( $target->root_files() as $source => $destination ) {
			$this->copy_file( $this->context->source( $source ), $this->context->target( $destination ) );
			$this->console->success( $destination );
		}
	}

	/*
	|----------------------
	| Composer dependencies
	|----------------------
	*/

	/**
	 * Write the rewritten composer.json, copy composer.lock and run composer install.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When Composer fails.
	 */
	private function install_dependencies( AbstractTarget $target ): void {
		$this->console->step( 'Installing dependencies' );

		$composer_dir = $this->context->target( $target->composer_path() );
		$this->make_dir( $composer_dir );

		file_put_contents( $composer_dir . '/composer.json', $this->composer_json() );
		$this->console->success( BuildContext::join( $target->composer_path(), 'composer.json' ) );

		$lock = $this->context->source( 'composer.lock' );
		if ( is_file( $lock ) ) {
			$this->copy_file( $lock, $composer_dir . '/composer.lock' );
			$this->console->success( BuildContext::join( $target->composer_path(), 'composer.lock' ) );
		} else {
			$this->console->warn( 'composer.lock not found; Composer will resolve versions fresh.' );
		}

		$command = array(
			$this->context->composer,
			'install',
			'--no-dev',
			'--optimize-autoloader',
			'--no-interaction',
			'--no-progress',
			'--working-dir=' . $composer_dir,
		);

		$process = @proc_open( $command, array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes, $composer_dir );
		if ( ! is_resource( $process ) ) {
			throw new BuildException( "Could not start Composer ({$this->context->composer}). Pass --composer=<path>." );
		}

		$code = proc_close( $process );
		if ( 0 !== $code ) {
			throw new BuildException( "composer install exited with code {$code}." );
		}

		$this->console->success( BuildContext::join( $target->composer_path(), 'vendor' ) . '/ installed' );
	}

	/**
	 * The repository composer.json with psr-4 paths under src/ pointed at the core directory.
	 *
	 * Decoded as objects so empty JSON objects round-trip as {} rather than [].
	 * Only `autoload` changes, which is not part of the lock file's content-hash.
	 *
	 * @return string
	 * @throws BuildException When composer.json is invalid.
	 */
	private function composer_json(): string {
		try {
			$data = json_decode( (string) file_get_contents( $this->context->source( 'composer.json' ) ), false, 512, JSON_THROW_ON_ERROR );
		} catch ( \JsonException $e ) {
			throw new BuildException( 'composer.json is not valid JSON: ' . $e->getMessage() );
		}

		$core = $this->context->core_dir;

		if ( 'src' !== $core && isset( $data->autoload->{'psr-4'} ) ) {
			$remap = static fn ( string $path ): string => ( 'src' === rtrim( $path, '/' ) || str_starts_with( $path, 'src/' ) )
				? $core . '/' . ltrim( substr( $path, 3 ), '/' )
				: $path;

			foreach ( get_object_vars( $data->autoload->{'psr-4'} ) as $namespace => $paths ) {
				$data->autoload->{'psr-4'}->{$namespace} = is_array( $paths ) ? array_map( $remap, $paths ) : $remap( $paths );
				$this->console->info( sprintf( 'psr-4 %s → %s', $namespace, json_encode( $data->autoload->{'psr-4'}->{$namespace}, JSON_UNESCAPED_SLASHES ) ) );
			}
		}

		return json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n";
	}

	/*
	|----------------
	| Generated files
	|----------------
	*/

	/**
	 * Write the target's generated entry files.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a file cannot be written.
	 */
	private function write_generated_files( AbstractTarget $target ): void {
		$this->console->step( 'Generating entry files' );

		foreach ( $target->generated_files( $this->context ) as $relative => $file ) {
			$path = $this->context->target( $relative );
			$this->make_dir( dirname( $path ) );

			if ( false === file_put_contents( $path, $file['contents'] ) ) {
				throw new BuildException( "Could not write {$relative}." );
			}

			chmod( $path, $file['mode'] );
			$this->console->success( sprintf( '%s (%o)', $relative, $file['mode'] ) );
		}
	}

	/*
	|-------------------
	| Filesystem helpers
	|-------------------
	*/

	/**
	 * Recursively copy a directory.
	 *
	 * @param string   $source      Source directory.
	 * @param string   $destination Destination directory.
	 * @param string[] $exclude     Source-relative directories to skip.
	 * @throws BuildException When a copy fails.
	 */
	private function copy_tree( string $source, string $destination, array $exclude = array() ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveCallbackFilterIterator(
				new \RecursiveDirectoryIterator( $source, \FilesystemIterator::SKIP_DOTS ),
				static function ( \SplFileInfo $item ) use ( $source, $exclude ): bool {
					$relative = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source ) + 1 ) );
					return ! in_array( $relative, $exclude, true );
				}
			),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		$this->make_dir( $destination );

		foreach ( $iterator as $item ) {
			$target = $destination . '/' . str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source ) + 1 ) );

			if ( $item->isDir() ) {
				$this->make_dir( $target );
				continue;
			}

			$this->copy_file( $item->getPathname(), $target );
		}
	}

	/**
	 * Copy one file, preserving its permission bits.
	 *
	 * @param string $source      Source file.
	 * @param string $destination Destination file.
	 * @throws BuildException When the copy fails.
	 */
	private function copy_file( string $source, string $destination ): void {
		$this->make_dir( dirname( $destination ) );

		if ( ! copy( $source, $destination ) ) {
			throw new BuildException( "Could not copy {$source}." );
		}

		@chmod( $destination, fileperms( $source ) & 0777 );
		++$this->copied;
	}

	/**
	 * Create a directory recursively.
	 *
	 * @param string $dir Directory.
	 * @throws BuildException When creation fails.
	 */
	private function make_dir( string $dir ): void {
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new BuildException( "Could not create {$dir}." );
		}
	}

	/**
	 * Remove a directory tree without following symlinks.
	 *
	 * @param string $dir Directory.
	 */
	private function remove_tree( string $dir ): void {
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $iterator as $item ) {
			( $item->isDir() && ! $item->isLink() ) ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $dir );
	}

	/**
	 * Normalize a path for comparison.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize( string $path ): string {
		$real = realpath( $path );
		return rtrim( str_replace( '\\', '/', false !== $real ? $real : $path ), '/' );
	}
}