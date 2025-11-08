<?php
/*
 * Plugin Name: Smart license Server
 * Plugin URI: https://callismart.com.ng/smart-license-server
 * Description: Private plugin, themes and software repository with licensing and monetization feature.
 * Author: Callistus Nwachukwu
 * Author URI: https://callismart.com.ng/callistus
 * Version: 0.1.0
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_PATH' ) ) {
    return;
} 

define( 'SMLISER_PATH', __DIR__ . '/' );
define( 'SMLISER_FILE', __FILE__ );
define( 'SMLISER_VER', '0.1.0' );
define( 'SMLISER_DB_VER', '0.0.9' );
define( 'SMLISER_URL', plugin_dir_url( __FILE__ ) );
define( 'SMLISER_APP_NAME', 'Smart License Server' );

require_once SMLISER_PATH . 'includes/class-smliser-config.php';
require_once SMLISER_PATH . 'includes/class-install.php';
SmartLicense_config::instance();