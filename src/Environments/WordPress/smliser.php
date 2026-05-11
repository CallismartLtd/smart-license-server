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

use SmartLicenseServer\Environments\WordPress\WordPressEnvironment;

defined( 'ABSPATH' ) || exit;
if ( defined( 'SMLISER_ROOT' ) ) return;

$config = [
    'app_root'      => ABSPATH,
    'base_dir'      => __DIR__,
    'base_dir_url'  => plugin_dir_url( __FILE__ ) ,
    'src_dir'       => __DIR__ . '/src/',
    'index_file'    => __FILE__
];

require_once $config['src_dir'] . 'Environments/bootstrap.php';

WordPressEnvironment::boot();