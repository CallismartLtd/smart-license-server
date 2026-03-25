<?php
/**
 * Theme command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

use SmartLicenseServer\Console\Commands\AbstractHostedAppCommand;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Theme command
 */
class ThemeCommand extends AbstractHostedAppCommand {
    protected static function get_type() : string {
        return 'theme';
    }
}