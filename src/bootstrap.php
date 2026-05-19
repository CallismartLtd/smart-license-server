<?php
/**
 * Core environment bootstrap file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments
 * @since   0.2.0
 */

/**
 * Human-readable application name.
 *
 * @var string
 */
define( 'SMLISER_APP_NAME', 'Smart License Server' );

/**
 * Current application version.
 *
 * Used for version tracking, compatibility checks, and CLI output.
 *
 * @var string
 */
define( 'SMLISER_VER', '0.2.0' );

/**
 * Current database schema version.
 *
 * Used to determine whether database migrations or updates are required.
 *
 * @var string
 */
define( 'SMLISER_DB_VER', '0.2.0' );

/*
|---------------------------------------
| CORE APPLICATION BOOTSTRAP CONSTANTS
|---------------------------------------
*/

$defaults   = [
    'app_root'      => dirname( __DIR__ ),
    'base_dir'      => dirname( __DIR__ ),
    'base_dir_url'  => '',
    'src_dir'       => dirname( __FILE__ ),
    'index_file'    => __FILE__
];

$config = (array) ( isset( $config ) ? $config : [] );

$consts = array_merge( $defaults, $config );

/**
 * Absolute path to the Smart License Server application root directory.
 *
 * @var string
 */
define( 'SMLISER_ROOT',  rtrim( $consts['app_root'], '/' ) . '/' );

/**
 * Absolute path Smart License Server base directory.
 *
 * Points to the directory where core files and resources reside.
 *
 * @var string
 */
define( 'SMLISER_PATH', rtrim( $consts['base_dir'], '/' ) . '/' );

/**
 * Absolute path to the application entry point file.
 *
 * @var string
 */
define( 'SMLISER_FILE', rtrim( $consts['index_file'], '/' ) . '/' );

/**
 * Absolute path to the Smart License Server source code directory.
 * 
 * Points to the `src` directory where all source codes reside.
 *
 * @var string
 */
define( 'SMLISER_SRC_DIR', rtrim( $consts['src_dir'], '/' ) . '/' );

/**
 * The base directory URL.
 * 
 * Used to locate core assets and files within the base directory.
 *
 * @var string
 */
define( 'SMLISER_URL', $consts['base_dir_url'] );

// Register the autoloader.
require_once SMLISER_SRC_DIR . 'Autoloader.php';
