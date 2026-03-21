<?php
/**
 * Smart License Server CLI bootstrap file.
 *
 * Entry point for running Smart License Server in a command-line
 * environment — queue workers, scheduled tasks, migrations, and
 * any other background or maintenance operations.
 *
 * Usage:
 *   php cli.php [command] [options]
 *
 * Available commands:
 *   work              — Process queued jobs until the queue is empty.
 *   schedule          — Run due scheduled tasks.
 *   work:schedule     — Process jobs AND run scheduled tasks in one pass.
 *   migrate           — Create missing database tables.
 *   install:roles     — Install default roles.
 *
 * Environment variables (or .env file):
 *   SMLISER_DB_HOST       Database host.          Default: 127.0.0.1
 *   SMLISER_DB_PORT       Database port.          Default: 3306
 *   SMLISER_DB_NAME       Database name.          Required.
 *   SMLISER_DB_USER       Database username.      Required.
 *   SMLISER_DB_PASSWORD   Database password.      Default: (empty)
 *   SMLISER_DB_CHARSET    Database charset.       Default: utf8mb4
 *   SMLISER_DB_PREFIX     Table prefix.           Default: (empty)
 *   SMLISER_APP_URL       Application base URL.   Required.
 *   SMLISER_REPO_PATH     Repository root path.   Default: dirname(__DIR__)
 *   SMLISER_UPLOADS_DIR   Uploads directory.      Default: dirname(__DIR__)/uploads
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\CLI
 * @since   0.2.0
 */

// Guard — must be run from CLI only.
if ( PHP_SAPI !== 'cli' ) {
    http_response_code( 403 );
    exit( 'This script can only be run from the command line.' );
}

// Resolve the application root (one level up from this file).
$app_root = '/var/www/html/apiv1.callismart.local';

// Core constants — mirror the WordPress bootstrap.
define( 'SMLISER_ABSPATH',  $app_root . '/' );
define( 'SMLISER_PATH',     $app_root . '/wp-content/plugins/smart-license-server/' );
define( 'SMLISER_FILE',     __FILE__ );
define( 'SMLISER_VER',      '0.2.0' );
define( 'SMLISER_DB_VER',   '0.2.0' );
define( 'SMLISER_URL',      '' ); // No URL context in CLI.
define( 'SMLISER_APP_NAME', 'Smart License Server' );

// Register the autoloader.
require_once SMLISER_PATH . 'includes/class-Autoloader.php';

// Load optional .env file from the application root.
$env_file = $app_root . '/.env';
if ( file_exists( $env_file ) ) {
    $lines = file( $env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    foreach ( $lines as $line ) {
        if ( str_starts_with( trim( $line ), '#' ) ) {
            continue; // Skip comments.
        }
        if ( str_contains( $line, '=' ) ) {
            [ $key, $value ] = explode( '=', $line, 2 );
            $_ENV[ trim( $key ) ] = trim( $value, " \t\n\r\0\x0B\"'" );
        }
    }
}

// Boot the CLI environment.
use SmartLicenseServer\Environments\CLI\SetUp as CLISetUp;

$cli = CLISetUp::instance();

// ── Command dispatcher ─────────────────────────────────────────────────────

$command = $argv[1] ?? 'help';

switch ( $command ) {

    case 'work':
        echo 'Processing queue...' . PHP_EOL;
        $processed = smliser_queue_worker()->process_within_time_budget();
        echo sprintf( 'Done. %d job(s) processed.' . PHP_EOL, $processed );
        break;

    case 'schedule':
        echo 'Running due scheduled tasks...' . PHP_EOL;
        $results = smliser_scheduler()->run_due_tasks();
        $total   = count( $results );
        $failed  = count( array_filter( $results, fn( $r ) => $r === false ) );
        echo sprintf( 'Done. %d task(s) ran, %d failed.' . PHP_EOL, $total, $failed );
        break;

    case 'work:schedule':
        echo 'Processing queue and running scheduled tasks...' . PHP_EOL;
        $processed = smliser_queue_worker()->process_within_time_budget();
        $results   = smliser_scheduler()->run_due_tasks();
        $total     = count( $results );
        $failed    = count( array_filter( $results, fn( $r ) => $r === false ) );
        echo sprintf( 'Queue: %d job(s) processed. Scheduler: %d task(s) ran, %d failed.' . PHP_EOL, $processed, $total, $failed );
        break;

    case 'migrate':
        echo 'Running database migrations...' . PHP_EOL;
        $cli->install_tables();
        echo 'Done.' . PHP_EOL;
        break;

    case 'install:roles':
        echo 'Installing default roles...' . PHP_EOL;
        $cli->install_default_roles();
        echo 'Done.' . PHP_EOL;
        break;

    case 'help':
    default:
        echo <<<HELP
Smart License Server CLI

Usage:
  php cli.php [command]

Commands:
  work              Process background jobs until the queue is empty.
  schedule          Run all due scheduled tasks.
  work:schedule     Process jobs and run scheduled tasks in one pass.
  migrate           Create any missing database tables.
  install:roles     Install default permission roles.
  help              Show this help message.

HELP;
        break;
}