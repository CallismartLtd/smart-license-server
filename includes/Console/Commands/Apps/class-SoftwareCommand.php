<?php
/**
 * Software command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Software command
 */
class SoftwareCommand extends AbstractHostedAppCommand {
    protected static function get_type() : string {
        return 'software';
    }
}