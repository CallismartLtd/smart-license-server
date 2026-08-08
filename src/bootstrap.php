<?php
/**
 * Core environment bootstrap file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments
 * @since   0.2.0
 * @var array $config Array of runtime configuration data @see RuntimeConfig::class for structure.
 */

use SmartLicenseServer\RuntimeConfig;
use SmartLicenseServer\Exceptions\GlobalErrorHandler;

require_once 'Autoloader.php';

$smliser_runtime   = RuntimeConfig::defaults();

GlobalErrorHandler::instance()->enableProduction()->registerHandlers();

try {
    $smliser_runtime->merge( $config ?? [] );
} catch ( \Throwable $e ) {
    GlobalErrorHandler::instance()
        ->abort( $e, 'Configuration Error.' );
} finally {
    unset( $config );
    GlobalErrorHandler::reset();
}

GlobalErrorHandler::instance()->bootstrap([
    'debug'             => $smliser_runtime->debug_mode,
    'display_errors'    => $smliser_runtime->display_errors,
    'log_errors'        => $smliser_runtime->log_errors,
    'log_path'          => $smliser_runtime->error_log_path,
])->registerHandlers();

require_once 'constants.php';

unset( $smliser_runtime );