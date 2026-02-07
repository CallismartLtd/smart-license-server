<?php
/*
 * Plugin Name: Smart license Server
 * Plugin URI: https://callismart.com.ng/smart-license-server
 * Description: Private plugin, themes and software repository with licensing and monetization feature.
 * Author: Callistus Nwachukwu
 * Author URI: https://callismart.com.ng/callistus
 * Version: 0.2.0
 */

namespace SmartLicenseServer;

use SmartLicenseServer\Environment\WordPress\SetUp;

defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_ABSPATH' ) ) {
    return;
} 

define( 'SMLISER_ABSPATH', \ABSPATH );
define( 'SMLISER_PATH', __DIR__ . '/' );
define( 'SMLISER_FILE', __FILE__ );
define( 'SMLISER_VER', '0.2.0' );
define( 'SMLISER_DB_VER', '0.2.0' );
define( 'SMLISER_URL', plugin_dir_url( __FILE__ ) );
define( 'SMLISER_APP_NAME', 'Smart License Server' );

require_once SMLISER_PATH . 'includes/class-Autoloader.php';

SetUp::init();