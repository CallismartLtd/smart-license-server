<?php
/**
 * Core environment bootstrap file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments
 * @since   0.2.0
 * @var array{
 *  app_root: string,
 *  base_dir: string,
 *  base_dir_url: string,
 *  src_dir: string,
 *  index_file: string} $config The required configuration to bootstrap the application environment.
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

$config = array_intersect_key( array_merge( $defaults, $config ?? [] ), $defaults );

/**
 * Absolute path to the root directory.
 *
 * @var string
 */
define( 'SMLISER_ROOT',  rtrim( $config['app_root'], '/' ) . '/' );

/**
 * Absolute path to the runtime directory.
 *
 * @var string
 */
define( 'SMLISER_RUNTIME_DIR', rtrim( $config['base_dir'], '/' ) . '/' );

/**
 * Absolute path to the application entry point file.
 *
 * @var string
 */
define( 'SMLISER_FILE', rtrim( $config['index_file'], '/' ) . '/' );

/**
 * Absolute path to the Smart License Server source code directory.
 * 
 * Points to the `src` directory where all source codes reside.
 *
 * @var string
 */
define( 'SMLISER_SRC_DIR', rtrim( $config['src_dir'], '/' ) . '/' );

/**
 * The base directory URL.
 * 
 * Used to locate core assets and files within the base directory.
 *
 * @var string
 */
define( 'SMLISER_URL', $config['base_dir_url'] );

// Register the autoloader.
require_once SMLISER_SRC_DIR . 'Autoloader.php';
