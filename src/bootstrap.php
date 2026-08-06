<?php
/**
 * Core environment bootstrap file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments
 * @since   0.2.0
 */

$defaults   = [
    'app_root'      => $_SERVER['DOCUMENT_ROOT'] ?? '',
    'storage_dir'   => '',
    'runtime_dir'   => '',
    'src_dir'       => dirname( __FILE__ ),
    'index_file'    => __FILE__,
    
    'debug_mode'        => false,
    'display_errors'    => false,
    'log_errors'        => false,
    
    'base_dir_url'  => '',
    
];

$config = array_intersect_key( array_merge( $defaults, $config ?? [] ), $defaults );

require_once 'constants.php';

require_once 'Autoloader.php';

\SmartLicenseServer\Exceptions\GlobalErrorHandler::instance()
    ->bootstrap([
        'debug'             => $config['debug_mode'],
        'display_errors'    => $config['display_errors'],
        'log_errors'        => $config['log_errors'],
        'log_path'          => \SMLISER_ROOT . 'error.log',
    ])
->registerHandlers();