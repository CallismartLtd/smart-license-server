<?php
/**
 * LinkedBuilder class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

use SmartLicenseServer\Build\Targets\AbstractTarget;

/**
 * Builds a run folder that executes the repository's own files.
 *
 * Philosophy: a development build is the release layout with the repository
 * standing in for the copy. It generates only what a source checkout lacks (the
 * entry points and server router, from the same templates a release uses) and
 * links the rest, so an edit is live on the next request and nothing is built
 * twice.
 *
 * What it does not do, by design: copy, minify, install production dependencies,
 * write a manifest, sign or package. Because nothing is wiped, running it again
 * only repairs links and regenerates entry points; .env, storage/ and the
 * installation survive, so the installer runs once per folder, not once per build.
 *
 * Use the normal build to test what ships. Linked folders have no manifest.json,
 * so the application treats them as source checkouts.
 */
final class LinkedBuilder {

	/**
	 * Repository directories linked into the runtime directory.
	 */
	private const SOURCE_DIRS = [ 'src', 'templates', 'assets', 'themes' ];

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
	 * Link the given target's layout to this checkout.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException On any failure.
	 */
	public function run( AbstractTarget $target ) : void {
		$started = microtime( true );

		$this->console->write(
			sprintf( 'Linking %s %s for %s to %s', $this->context->project->name, $this->context->project->version, $target->name(), $this->context->repo_root )
		);

		$this->validate( $target );
		$this->ensure_dependencies();
		$this->link_sources( $target );
		$this->write_generated_files( $target );

		$this->console->write(
			sprintf( '%sDone in %.1fs → %s', PHP_EOL, microtime( true ) - $started, $this->context->out_dir )
		);

		$this->next_steps();
	}

	/*
	|-----------
	| Validation
	|-----------
	*/

	/**
	 * Check the sources exist and the output path cannot damage them.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When something is missing or the output path is unsafe.
	 */
	private function validate( AbstractTarget $target ) : void {
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

		$out  = $this->normalize( $this->context->out_dir );
		$root = $this->normalize( $this->context->repo_root );

		if ( $out === $root || str_starts_with( $root . '/', $out . '/' ) ) {
			throw new BuildException( 'Output directory must not be the repository root or one of its parents.' );
		}

		foreach ( [ ...self::SOURCE_DIRS, 'vendor' ] as $dir ) {
			$source = $root . '/' . $dir;
			if ( $out === $source || str_starts_with( $out . '/', $source . '/' ) ) {
				throw new BuildException( "Output directory must not be inside {$dir}/." );
			}
		}

		$this->console->success( 'Sources and output path OK' );
	}

	/*
	|-------------
	| Dependencies
	|-------------
	*/

	/**
	 * Install the repository's dependencies when vendor/ is missing.
	 *
	 * Development dependencies are kept: the application's test command runs
	 * from vendor/bin. An existing vendor/ is never touched, so repeated runs
	 * need no network.
	 *
	 * @throws BuildException When Composer cannot run or fails.
	 */
	private function ensure_dependencies() : void {
		$this->console->step( 'Checking dependencies' );

		if ( is_file( $this->context->source( 'vendor/autoload.php' ) ) ) {
			$this->console->success( 'vendor/ present, left as it is (run composer install yourself to refresh it)' );
			return;
		}

		$command = [
			$this->context->composer,
			'install',
			'--no-interaction',
			'--no-progress',
			'--working-dir=' . $this->context->repo_root,
		];

		$process = @proc_open( $command, [ 0 => STDIN, 1 => STDOUT, 2 => STDERR ], $pipes, $this->context->repo_root );
		if ( ! is_resource( $process ) ) {
			throw new BuildException( "Could not start Composer ({$this->context->composer}). Pass --composer=<path>." );
		}

		$code = proc_close( $process );
		if ( 0 !== $code ) {
			throw new BuildException( "composer install exited with code {$code}." );
		}

		$this->console->success( 'vendor/ installed' );
	}

	/*
	|--------
	| Linking
	|--------
	*/

	/**
	 * Link the repository into the target's layout.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a link cannot be made.
	 */
	private function link_sources( AbstractTarget $target ) : void {
		$this->console->step( 'Linking sources' );

		$links = [
			$target->core_path()      => 'src',
			$target->templates_path() => 'templates',
			$target->assets_path()    => 'assets',
			$target->themes_path()    => 'themes',
			BuildContext::join( $target->composer_path(), 'vendor' ) => 'vendor',
		];

		// The installer looks for .env.example and .htaccess.example next to the application.
		foreach ( $target->root_files() as $source => $destination ) {
			$links[ $destination ] = $source;
		}

		foreach ( $links as $destination => $source ) {
			$this->link( $destination, $source );
		}
	}

	/**
	 * Make one link, or leave a correct one alone.
	 *
	 * A link to anything else is replaced. A real file or folder is never
	 * removed: it may be somebody's work.
	 *
	 * @param string $destination Path inside the output directory.
	 * @param string $source      Path inside the repository.
	 * @throws BuildException When the source is missing, the destination is not a link, or linking fails.
	 */
	private function link( string $destination, string $source ) : void {
		$target = realpath( $this->context->source( $source ) );
		$link   = $this->context->target( $destination );

		if ( false === $target ) {
			throw new BuildException( "{$source} not found in {$this->context->repo_root}." );
		}

		if ( is_link( $link ) ) {
			if ( realpath( $link ) === $target ) {
				$this->console->info( "{$destination} already links to {$source}" );
				return;
			}

			// A directory link needs rmdir() on Windows.
			if ( ! @unlink( $link ) && ! @rmdir( $link ) ) {
				throw new BuildException( "Could not replace the link {$destination}." );
			}
		} elseif ( file_exists( $link ) ) {
			throw new BuildException( "{$destination} exists and is not a link, so it was left alone. Remove it, or pass another --out." );
		}

		$this->make_dir( dirname( $link ) );

		if ( ! @symlink( $target, $link ) ) {
			throw new BuildException( "Could not link {$destination}. Symlinks must be allowed (on Windows, enable Developer Mode); otherwise use the normal build." );
		}

		$this->console->success( "{$destination} → {$source}" );
	}

	/*
	|-----------------
	| Generated files
	|-----------------
	*/

	/**
	 * Write the target's entry points and server configurations.
	 *
	 * These come from the target, so they are the files a release contains.
	 *
	 * @param AbstractTarget $target Target.
	 * @throws BuildException When a file cannot be written.
	 */
	private function write_generated_files( AbstractTarget $target ) : void {
		$this->console->step( 'Generating files' );

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
	|-----------
	| Next steps
	|-----------
	*/

	
	/**
	 * Tell the developer what to run next, from the state of the folder.
	 */
	private function next_steps() : void {
		$out = $this->context->out_dir;
		$env = $out . '/.env';

		if ( ! is_file( $env ) ) {
			$this->console->write( "First time in this folder: php {$out}/smliser installer run" );
		} else {
			$contents = (string) file_get_contents( $env );

			if ( false === preg_match( '/^SMLISER_SCRIPT_DEBUG\s*=\s*(true|1|yes|on)\s*$/mi', $contents ) ) {
				$contents = preg_replace(
					'/^SMLISER_SCRIPT_DEBUG\s*=.*$/mi',
					'SMLISER_SCRIPT_DEBUG=true',
					$contents,
					-1,
					$count
				);

				if ( 0 === $count ) {
					$contents = rtrim( $contents ) . "\nSMLISER_SCRIPT_DEBUG=true\n";
				}

				if ( false === file_put_contents( $env, $contents ) ) {
					$this->console->error( "Unable to enable SMLISER_SCRIPT_DEBUG in {$env}." );
					return;
				}

				$this->console->write( 'Enabled SMLISER_SCRIPT_DEBUG=true in .env.' );
			}
		}

		$this->console->write( "Run it: php {$out}/smliser server" );
	}


	/*
	|--------------------
	| Filesystem helpers
	|--------------------
	*/

	/**
	 * Create a directory recursively.
	 *
	 * @param string $dir Directory.
	 * @throws BuildException When creation fails.
	 */
	private function make_dir( string $dir ) : void {
		if ( ! is_dir( $dir ) && ! mkdir( $dir, 0755, true ) && ! is_dir( $dir ) ) {
			throw new BuildException( "Could not create {$dir}." );
		}
	}

	/**
	 * Normalize a path for comparison.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	private function normalize( string $path ) : string {
		$real = realpath( $path );
		return rtrim( str_replace( '\\', '/', false !== $real ? $real : $path ), '/' );
	}
}
