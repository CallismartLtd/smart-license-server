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

define( 'SMLISER_ABSPATH', \ABSPATH );
define( 'SMLISER_PATH', __DIR__ . '/' );
define( 'SMLISER_SRC_DIR', SMLISER_PATH . 'src/' );
define( 'SMLISER_FILE', __FILE__ );
define( 'SMLISER_VER', '0.2.0' );
define( 'SMLISER_DB_VER', '0.2.0' );
define( 'SMLISER_URL', plugin_dir_url( __FILE__ ) );
define( 'SMLISER_APP_NAME', 'Smart License Server' );

require_once SMLISER_SRC_DIR . 'class-Autoloader.php';

SetUp::instance();