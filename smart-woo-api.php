<?php
/*
Plugin Name: Smart license Server
Description: license REST API server for WordPress premium plugins.
Version: 1.0
Author: Callistus Nwachukwu
*/
defined( 'ABSPATH' ) || exit;

if ( defined( 'SMLISER_PATH' ) ) {
    return;
} 

define( 'SMLISER_PATH', __DIR__ . '/' );
define( 'SMLISER_FILE', __FILE__ );
define( 'SMLISER_VER', '1.0.0' );
define( 'SMLISER_URL', plugin_dir_url( __FILE__) );

require_once SMLISER_PATH . 'includes/class-smliser-config.php';
require_once SMLISER_PATH . 'includes/class-install.php';
SmartLicense_config::instance();
