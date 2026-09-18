<?php
/**
 * Core environment runtime bootstrap file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments
 * @since   0.2.0
 * @var array $config Array of runtime configuration data @see RuntimeConfig::class for structure.
 */

use SmartLicenseServer\RuntimeConfig;

// Register the autoloader if it hasn't been registered yet.
require_once 'Autoloader.php';

// Merge the runtime configuration with the default configuration values.
$smliser_runtime   = RuntimeConfig::defaults()->merge( $config ?? [] );

// Define the global constants used in the application.
require_once 'constants.php';

// Destroy the default config variable to avoid global scope pollution.
unset( $config );

// Return the initialized runtime configuration.
return $smliser_runtime;