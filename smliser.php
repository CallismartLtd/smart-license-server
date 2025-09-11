<?php
/*
 * Plugin Name: Smart license Server
 * Plugin URI: https://callismart.com.ng/smart-license-server
 * Description: license REST API server for WordPress premium plugins.
 * Author: Callistus Nwachukwu
 * Author URI: https://callismart.com.ng/callistus
 * Version: 0.0.7
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_PATH' ) ) {
    return;
} 

define( 'SMLISER_PATH', __DIR__ . '/' );
define( 'SMLISER_FILE', __FILE__ );
define( 'SMLISER_VER', '0.0.7' );
define( 'SMLISER_DB_VER', '0.0.7' );
define( 'SMLISER_URL', plugin_dir_url( __FILE__) );

require_once SMLISER_PATH . 'includes/class-smliser-config.php';
require_once SMLISER_PATH . 'includes/class-install.php';
SmartLicense_config::instance();