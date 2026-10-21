<?php
/**
 * WordPress theme command file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
namespace SmartLicenseServer\Console\Commands\Apps;

/**
 * Manage the hosted WordPress themes (`wptheme`).
 */
class WPThemeCommand extends AbstractHostedAppCommand {
	protected static function get_type() : string {
		return 'wptheme';
	}
}
