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

/**
 * Builds Smart License Server as a standalone PHP application.
 *
 * Layout:
 *   bootstrap.php, smliser, public/index.php  (generated)
 *   system/<core>/, system/templates/, system/assets/, system/vendor/
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
		$core = '/' . $this->core_path( $context );

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
		);
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
	'debug_mode'		=> $_ENV[ 'SMLISER_DEBUG' ] ?? false,
	'display_errors'	=> $_ENV[ 'SMLISER_DISPLAY_ERRORS' ] ?? false,
	'log_errors'		=> $_ENV[ 'SMLISER_DEBUG' ] ?? false,
	'secret'			=> $_ENV[ 'SMLISER_SECRET' ] ?? '',
	'salt'				=> $_ENV[ 'SMLISER_SALT' ] ?? '',
	'db_table_prefix'	=> $_ENV[ 'SMLISER_DB_PREFIX' ] ?? 'smliser_',
	'error_log_path'	=> __DIR__ . '/storage/logs/error.log',
];

/*
|-----------------------------------------
| Set the global error handling policy.
|-----------------------------------------
*/
GlobalErrorHandler::instance()->bootstrap([
    'debug'             => $config['debug_mode'],
    'display_errors'    => $config['display_errors'],
    'log_errors'        => $config['log_errors'],
    'log_path'          => $config['error_log_path'],
    'error_reporting'   => $config['debug_mode'] ? E_ALL : 0
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