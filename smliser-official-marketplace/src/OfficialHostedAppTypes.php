<?php
/**
 * Entry point for the official marketplace hosted app types.
 */

declare( strict_types=1 );

namespace Callismart\SmliserOfficialMarketplace;

use Callismart\SmliserOfficialMarketplace\HostedApps\SmliserExtension;
use Callismart\SmliserOfficialMarketplace\HostedApps\SmliserTheme;
use Callismart\SmliserOfficialMarketplace\Repositories\SmliserExtensionRepository;
use Callismart\SmliserOfficialMarketplace\Repositories\SmliserThemeRepository;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Environments\Application\Extensions\ExtensionInterface;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;

/**
 * Registers official Smliser products with the hosted app service.
 */
final class OfficialHostedAppTypes implements ExtensionInterface {

	public function register( Container $container ) : void {
		$registry = HostedAppsRegistry::instance( $container );

		$registry->register(
			'smliser-theme',
			[
				'class'                   => SmliserTheme::class,
				'directory_class'         => SmliserThemeRepository::class,
				'labels'                  => [ 'singular' => 'Smliser Theme', 'plural' => 'Smliser Themes' ],
				'description'             => 'Upload themes for Smliser applications.',
				'icon'                    => 'ti ti-palette',
				'storage_dir'             => 'smliser-theme',
				'marketplace_attestation' => true,
			]
		);

		$registry->register(
			'smliser-extension',
			[
				'class'                   => SmliserExtension::class,
				'directory_class'         => SmliserExtensionRepository::class,
				'labels'                  => [ 'singular' => 'Smliser Extension', 'plural' => 'Smliser Extensions' ],
				'description'             => 'Upload extensions for Smliser applications.',
				'icon'                    => 'ti ti-puzzle',
				'storage_dir'             => 'smliser-extension',
				'marketplace_attestation' => true,
			]
		);
	}

	public function boot( Container $container ) : void {}
}
