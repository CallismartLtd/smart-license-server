<?php
/**
 * Theme command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Theme command
 */
class ThemeCommand extends AbstractHostedAppCommand {
    protected static function get_type() : string {
        return 'theme';
    }
}