<?php

use SmartLicenseServer\Environments\Tests\TestEnvironment;

if ( 'cli' !== PHP_SAPI ) {
	function_exists( 'http_response_code' ) && http_response_code( 403 );
	exit( 'This script can only be run from the command line.' );
}


$config = [
	'app_root'      => '/var/www/html/apiv1.callismart.local/',
	'base_dir'      => '/var/www/html/apiv1.callismart.local/wp-content/plugins/smart-license-server/',
	'src_dir'       => '/var/www/html/apiv1.callismart.local/wp-content/plugins/smart-license-server/src/',
	'index_file'    => __FILE__,
    'base_dir_url'  => 'https://localhost'
];

require_once $config['src_dir'] . 'Environments/bootstrap.php';

TestEnvironment::boot();