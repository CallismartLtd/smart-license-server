<?php
/*
 * Plugin Name:         Smart license Server
 * Plugin URI:          https://callismart.com.ng/smart-license-server
 * Description:         Private plugin, themes and software repository with licensing and monetization feature.
 * Author:              Callistus Nwachukwu
 * Author URI:          https://callismart.com.ng/callistus
 * Version:             0.2.0
 * Requires at least:   6.8
 * Requires PHP:        8.4
 */

use SmartLicenseServer\Environments\WordPress\SetUp;

defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_ABSPATH' ) ) {
    return;
} 

defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_ABSPATH' ) ) {
    return;
} 

/**
 * Absolute path to the WordPress installation root.
 *
 * Mirrors WordPress ABSPATH to provide a consistent base reference
 * for resolving global paths within Smart License Server.
 *
 * @var string
 */
define( 'SMLISER_ABSPATH', \ABSPATH );

/**
 * Absolute path to the Smart License Server plugin directory.
 *
 * Used as the primary base path for all plugin-level file operations.
 *
 * @var string
 */
define( 'SMLISER_PATH', __DIR__ . '/' );

/**
 * Absolute path to the Smart License Server source directory.
 *
 * Serves as the root for class autoloading and internal code structure.
 *
 * @var string
 */
define( 'SMLISER_SRC_DIR', __DIR__ . '/src/' );

/**
 * Absolute path to the main plugin file.
 *
 * Used by WordPress for plugin identification and by the system
 * for resolving plugin-specific metadata or hooks.
 *
 * @var string
 */
define( 'SMLISER_FILE', __FILE__ );

/**
 * Current application version.
 *
 * Used for cache busting, compatibility checks, and general version tracking.
 *
 * @var string
 */
define( 'SMLISER_VER', '0.2.0' );

/**
 * Current database schema version.
 *
 * Used to manage and track database migrations and upgrades.
 *
 * @var string
 */
define( 'SMLISER_DB_VER', '0.2.0' );

/**
 * Base URL to the Smart License Server plugin directory.
 *
 * Used for loading public assets such as scripts, styles, and downloadable files.
 *
 * @var string
 */
define( 'SMLISER_URL', plugin_dir_url( __FILE__ ) );

/**
 * Human-readable application name.
 *
 * Used in UI labels, logs, CLI output, and general system identification.
 *
 * @var string
 */
define( 'SMLISER_APP_NAME', 'Smart License Server' );

require_once SMLISER_SRC_DIR . 'class-Autoloader.php';

SetUp::instance();