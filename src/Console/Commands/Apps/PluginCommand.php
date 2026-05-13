<?php
/**
 * Plugin command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

/**
 * Plugin command
 */
class PluginCommand extends AbstractHostedAppCommand {
    protected static function get_type() : string {
        return 'plugin';
    }
}