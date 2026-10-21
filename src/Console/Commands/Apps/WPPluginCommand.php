<?php
/**
 * WordPress plugin command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

/**
 * Manage the hosted WordPress plugins (`wpplugin`).
 */
class WPPluginCommand extends AbstractHostedAppCommand {
	protected static function get_type() : string {
		return 'wpplugin';
	}
}
