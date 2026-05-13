<?php
/**
 * Theme command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

/**
 * Theme command
 */
class ThemeCommand extends AbstractHostedAppCommand {
    protected static function get_type() : string {
        return 'theme';
    }
}