<?php
/**
 * Asset minifier class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build;

/**
 * Writes minified copies of the JavaScript and CSS files in a build.
 *
 * Every `name.js` / `name.css` gets a `name.min.js` / `name.min.css` next to
 * it; the original is kept. Files already named `*.min.*` are skipped.
 *
 * Minification uses esbuild, pinned in tools/build/package.json. It is a
 * build-time tool only and never ships with the application. esbuild is run
 * through its Node API (require('esbuild')), which finds its own platform
 * binary, so it works whether or not npm created node_modules/.bin links
 * (npm's bin-links setting, or filesystems without symlinks). Each file is
 * minified on its own, never bundled or turned into a module, so plain
 * scripts and jQuery code behave as before: only names local to functions
 * are shortened, top-level names and globals are kept.
 */
final class AssetMinifier {

	/**
	 * JavaScript output target.
	 *
	 * Without a target esbuild may shorten code into newer syntax (e.g.
	 * `a || (a = b)` into `a ||= b`). Newer syntax in the sources, such as
	 * private class fields, is lowered to ES2018.
	 *
	 * Deliberately not a browser list: esbuild treats destructuring as
	 * unsupported before Safari 14.1 and cannot lower it, so a "safari12"
	 * entry makes every file that destructures fail to build.
	 */
	public const JS_TARGET = array( 'es2018' );

	/**
	 * CSS output target: the browsers of the ES2018 era.
	 *
	 * CSS ignores "es" targets, so the browsers are listed. With them, esbuild
	 * avoids newer CSS (e.g. it expands `inset` for Safari 12).
	 */
	public const CSS_TARGET = array( 'chrome64', 'edge79', 'firefox62', 'safari12' );

	/**
	 * Extensions to minify.
	 */
	private const EXTENSIONS = array( 'js', 'css' );

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
	 * Minify every JavaScript and CSS file under a directory of the build.
	 *
	 * @param string $dir Absolute directory in the build output.
	 * @throws BuildException When esbuild is unavailable or fails.
	 */
	public function run( string $dir ): void {
		$this->console->step( 'Minifying assets' );

		$files = is_dir( $dir ) ? $this->find_files( $dir ) : array();

		if ( array() === $files ) {
			$this->console->info( 'No JavaScript or CSS files to minify.' );
			return;
		}

		$this->ensure_esbuild();

		$builds = array();

		foreach ( array( 'js' => self::JS_TARGET, 'css' => self::CSS_TARGET ) as $extension => $target ) {
			$entries = array_values( array_filter( $files, static fn ( string $file ): bool => str_ends_with( strtolower( $file ), '.' . $extension ) ) );

			if ( array() === $entries ) {
				continue;
			}

			$builds[] = array(
				'entryPoints'   => $entries,
				'absWorkingDir' => $dir,
				'outdir'        => $dir,
				'outbase'       => $dir,
				'outExtension'  => array( '.' . $extension => '.min.' . $extension ),
				'minify'        => true,
				'target'        => $target,
				'charset'       => 'utf8',
				'legalComments' => 'inline',
				'logLevel'      => 'warning',
			);
		}

		// Runs from tools/build so require() resolves its node_modules.
		// esbuild prints build errors itself; anything else is printed here.
		$this->execute(
			array(
				$this->context->node,
				'-e',
				"try{const b=require('esbuild');for(const o of JSON.parse(process.argv[1])){b.buildSync(o)}}catch(e){if(!e.errors){console.error(e.message)}process.exit(1)}",
				json_encode( $builds, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ),
			),
			$this->tool_dir(),
			'esbuild',
			'Pass --node=<path> if Node.js is not on your PATH, or build with --no-minify.',
			'Fix the errors reported above, or build with --no-minify.'
		);

		$before = 0;
		$after  = 0;

		foreach ( $files as $file ) {
			$minified = preg_replace( '/\.(js|css)$/', '.min.$1', $file );
			$original = (int) filesize( $dir . '/' . $file );

			if ( ! is_file( $dir . '/' . $minified ) ) {
				throw new BuildException( "esbuild did not produce {$minified}." );
			}

			$size    = (int) filesize( $dir . '/' . $minified );
			$before += $original;
			$after  += $size;

			$this->console->success(
				sprintf( '%s  %s → %s%s', $minified, $this->bytes( $original ), $this->bytes( $size ), $this->saving( $original, $size ) )
			);
		}

		$this->console->info(
			sprintf( '%d file(s): %s → %s%s', count( $files ), $this->bytes( $before ), $this->bytes( $after ), $this->saving( $before, $after ) )
		);
	}

	/*
	|----------
	| esbuild
	|----------
	*/

	/**
	 * Make sure the pinned esbuild package is installed, installing it on first use.
	 *
	 * Checks the package itself, not node_modules/.bin/esbuild: npm does not
	 * create .bin links when bin-links is off or symlinks are unavailable.
	 *
	 * @return void
	 * @throws BuildException When it cannot be installed.
	 */
	private function ensure_esbuild(): void {
		$package = $this->tool_dir() . '/node_modules/esbuild/package.json';

		if ( is_file( $package ) ) {
			return;
		}

		$this->console->info( 'esbuild is not installed yet; running npm ci in tools/build (one time).' );

		$this->execute(
			array( $this->context->npm, 'ci', '--no-audit', '--no-fund', '--loglevel=error' ),
			$this->tool_dir(),
			'npm',
			'Install Node.js and npm, pass --npm=<path>, or build with --no-minify.',
			'See the npm output above, or build with --no-minify.'
		);

		if ( ! is_file( $package ) ) {
			throw new BuildException( 'npm ci finished, but esbuild was not installed in tools/build/node_modules.' );
		}
	}

	/**
	 * Directory of the build tool (holds package.json and node_modules).
	 *
	 * @return string
	 */
	private function tool_dir(): string {
		return dirname( __DIR__ );
	}

	/**
	 * Run a command, streaming its output.
	 *
	 * @param string[] $command Command and arguments.
	 * @param string   $cwd     Working directory.
	 * @param string   $label      Name used in errors.
	 * @param string   $start_hint Advice when the command cannot start.
	 * @param string   $exit_hint  Advice when the command fails.
	 * @throws BuildException When it cannot start or exits non-zero.
	 */
	private function execute( array $command, string $cwd, string $label, string $start_hint = '', string $exit_hint = '' ): void {
		$process = @proc_open( $command, array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes, $cwd );

		if ( ! is_resource( $process ) ) {
			throw new BuildException( trim( "Could not start {$label} ({$command[0]}). {$start_hint}" ) );
		}

		$code = proc_close( $process );

		if ( 0 !== $code ) {
			throw new BuildException( trim( "{$label} exited with code {$code}. {$exit_hint}" ) );
		}
	}

	/*
	|----------
	| Helpers
	|----------
	*/

	/**
	 * JavaScript and CSS files under a directory, relative to it, excluding *.min.*.
	 *
	 * @param string $dir Directory.
	 * @return string[] Sorted relative paths with forward slashes.
	 */
	private function find_files( string $dir ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			/** @var \SplFileInfo $file */
			$name = $file->getFilename();

			if ( ! $file->isFile()
				|| ! in_array( strtolower( $file->getExtension() ), self::EXTENSIONS, true )
				|| 1 === preg_match( '/\.min\.[^.]+$/i', $name )
			) {
				continue;
			}

			$files[] = str_replace( '\\', '/', substr( $file->getPathname(), strlen( $dir ) + 1 ) );
		}

		sort( $files );

		return $files;
	}

	/**
	 * Human-readable size.
	 *
	 * @param int $bytes Size in bytes.
	 * @return string
	 */
	private function bytes( int $bytes ): string {
		return $bytes >= 1024 ? sprintf( '%.1f KB', $bytes / 1024 ) : $bytes . ' B';
	}

	/**
	 * Size change, e.g. " (-62%)", or " (+50%)" for tiny files that grow.
	 *
	 * @param int $before Original size.
	 * @param int $after  Minified size.
	 * @return string
	 */
	private function saving( int $before, int $after ): string {
		if ( 0 === $before ) {
			return '';
		}

		$change = (int) round( ( $after / $before - 1 ) * 100 );

		return sprintf( ' (%s%d%%)', $change > 0 ? '+' : '', $change );
	}
}