<?php
/**
 * Standalone build target class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Build\Targets
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Build\Targets;

use SmartLicenseServer\Build\BuildContext;
use SmartLicenseServer\Build\BuildException;

/**
 * Builds Smart License Server as a standalone PHP application.
 *
 * Layout:
 *   bootstrap.php, smliser, public/index.php  (generated)
 *   .htaccess                                 (generated) Keeps everything but public/ unreachable
 *                                             where the document root is this folder (Apache).
 *   server/nginx.conf, server/Caddyfile, server/apache.conf, server/server.php  (generated)
 *   manifest.json                             (written last, by the builder)
 *   system/smliser/, system/templates/, system/assets/, system/vendor/
 *
 * storage/, .env, public/.htaccess and the public/assets symlink are
 * created by the application at install, update or upgrade time.
 */
class StandaloneTarget extends AbstractTarget {

	/**
	 * {@inheritdoc}
	 */
	public function name(): string {
		return 'standalone';
	}

	/**
	 * {@inheritdoc}
	 */
	public function environment(): string {
		return 'Application';
	}

	/**
	 * {@inheritdoc}
	 */
	public function runtime_path(): string {
		return 'system';
	}

	/**
	 * {@inheritdoc}
	 */
	public function root_files(): array {
		return array_merge(
			parent::root_files(),
			array(
				'readme.md'         => 'readme.md',
				'.env.example'      => '.env.example',
				'.htaccess.example' => '.htaccess.example',
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function generated_files( BuildContext $context ): array {
		$core = '/' . $this->core_path();

		return array(
			'bootstrap.php'    => array(
				'contents' => strtr( self::BOOTSTRAP, array( '{{CORE}}' => $core, '{{RUNTIME}}' => '/' . $this->runtime_path() . '/' ) ),
				'mode'     => 0644,
			),
			'smliser'          => array(
				'contents' => self::CLI_ENTRY,
				'mode'     => 0755,
			),
			'public/index.php' => array(
				'contents' => self::WEB_ENTRY,
				'mode'     => 0644,
			),
			'.htaccess'        => array(
				'contents' => $this->stub( 'root.htaccess' ),
				'mode'     => 0644,
			),
		) + $this->server_configs( $context );
	}

	/**
	 * Contents of a template in tools/build/stubs/standalone/.
	 *
	 * @param string $name Template path, relative to that folder.
	 * @return string
	 * @throws BuildException When the template is missing.
	 */
	private function stub( string $name ): string {
		$path = dirname( __DIR__, 2 ) . '/stubs/standalone/' . $name;

		if ( ! is_file( $path ) ) {
			throw new BuildException( "Template {$name} not found in tools/build/stubs/standalone/." );
		}

		return (string) file_get_contents( $path );
	}

	/**
	 * Web server configurations, written to server/ in the build.
	 *
	 * nginx, Caddy and an Apache virtual host for production, and server.php,
	 * a router for PHP's built-in server for local development. The PHP-FPM
	 * socket and the PHP requirement are filled in from composer.json, so they
	 * match the release.
	 *
	 * @param BuildContext $context Build context.
	 * @return array<string, array{contents: string, mode: int}>
	 * @throws BuildException When a configuration template is missing.
	 */
	private function server_configs( BuildContext $context ): array {
		$minor = $context->project->php_minor();
		$vars  = array(
			'{{PHP_FPM_SOCKET}}'  => null === $minor ? '/run/php/php-fpm.sock' : "/run/php/php{$minor}-fpm.sock",
			'{{PHP_REQUIREMENT}}' => null === $minor ? 'a supported PHP version' : "PHP {$minor} or newer",
		);
		$files = array();

		foreach ( array( 'nginx.conf', 'Caddyfile', 'apache.conf', 'server.php' ) as $name ) {
			$files[ 'server/' . $name ] = array(
				'contents' => strtr( $this->stub( 'server/' . $name ), $vars ),
				'mode'     => 0644,
			);
		}

		return $files;
	}

	/*
	|----------------
	| Entry templates
	|----------------
	*/

	/**
	 * Platform bootstrap. {{CORE}} and {{RUNTIME}} are build-relative paths with a leading slash.
	 */
	private const BOOTSTRAP = <<<'PHP'
<?php
/**
 * Smart License Server - platform bootstrap file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

use SmartLicenseServer\Exceptions\GlobalErrorHandler;

/*
|---------------------------------------------------------
| Register the autoloader.
|---------------------------------------------------------
| The autoloader automatically loads the required
| classes and loads the registered functions. It is
| the first thing to be loaded in the bootstrap process.
|
*/
require_once __DIR__ . '{{CORE}}/Autoloader.php';

/*
|----------------------------------------
| Load the environment variables.
|----------------------------------------
*/
( new \SmartLicenseServer\Core\DotEnv( __DIR__ ) )->load( '.env' );

/*
|----------------------------------------
| Prepare the runtime configuration which
| will overide the default configuration
| values in the core bootstrap file.
|----------------------------------------
*/
$config = [
	'app_root'			=> __DIR__,
	'runtime_dir'		=> __DIR__ . '{{RUNTIME}}',
	'storage_dir'		=> __DIR__ . '/storage/',
	'index_file'		=> __DIR__ . '/public/index.php',
	'debug_mode'		=> (bool) ( $_ENV[ 'SMLISER_DEBUG' ] ?? false ),
	'display_errors'	=> (bool) ( $_ENV[ 'SMLISER_DISPLAY_ERRORS' ] ?? false ),
	'log_errors'		=> true,
	'secret'			=> $_ENV[ 'SMLISER_SECRET' ] ?? '',
	'salt'				=> $_ENV[ 'SMLISER_SALT' ] ?? '',
	'db_table_prefix'	=> $_ENV[ 'SMLISER_DB_PREFIX' ] ?? 'smliser_',
	'error_log_path'	=> ( $_ENV[ 'SMLISER_ERROR_LOG_PATH' ] ?? '' ) ?: __DIR__ . '/storage/logs/error.log',
];

/*
|-----------------------------------------
| Set the global error handling policy.
|-----------------------------------------
| Every error is reported and logged, in
| production too. SMLISER_DEBUG and
| SMLISER_DISPLAY_ERRORS only control what
| is shown to visitors.
*/
GlobalErrorHandler::instance()->bootstrap([
	'debug'             => $config['debug_mode'],
	'display_errors'    => $config['display_errors'],
	'log_errors'        => $config['log_errors'],
	'log_path'          => $config['error_log_path'],
	'error_reporting'   => E_ALL,
])->registerHandlers();

/*
|---------------------------
| Load the core bootstrap file.
|---------------------------
*/
require_once __DIR__ . '{{CORE}}/bootstrap.php';

/*
|------------------------------------------------
| Create and return the application environment.
|------------------------------------------------
*/
return \SmartLicenseServer\Environments\Application\ApplicationEnvironment::create( $smliser_runtime );

PHP;

	/**
	 * CLI entry point.
	 */
	private const CLI_ENTRY = <<<'PHP'
#!/usr/bin/env php
<?php
/**
 * Smart License Server - CLI bootstrap file.
 *
 * @author Callistus Nwachukwu
 */

( require __DIR__ . '/bootstrap.php' )->container()

/*
| -----------------------------------
| Create the Console kernel instance.
| -----------------------------------
*/
->get( \SmartLicenseServer\Environments\Application\Kernel\ConsoleKernel::class )

/*
| -----------------------------------
| Bootstrap the application
| -----------------------------------
*/
->boot()

/*
| -----------------------------------
| Run the application lifecycle
| -----------------------------------
*/
->run()

/*
| -----------------------------------
| Terminate the request
| -----------------------------------
*/
->terminate();

PHP;

	/**
	 * HTTP entry point.
	 */
	private const WEB_ENTRY = <<<'PHP'
<?php
/**
 * Smart License Server - HTTP bootstrap file.
 *
 * @author Callistus Nwachukwu
 */

( require_once dirname( __DIR__ ) . '/bootstrap.php' )->container()

/*
| -----------------------------------
| Create the HTTP kernel instance.
| -----------------------------------
*/
->get( \SmartLicenseServer\Environments\Application\Kernel\HTTPKernel::class )

/*
| -----------------------------------
| Bootstrap the application.
| -----------------------------------
*/
->boot()

/*
| -----------------------------------
| Run the application lifecycle.
| -----------------------------------
*/
->run()

/*
| -----------------------------------
| Terminate the request.
| -----------------------------------
*/
->terminate();

PHP;
}