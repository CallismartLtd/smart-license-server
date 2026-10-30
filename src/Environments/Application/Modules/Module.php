<?php
/**
 * Module interface definition file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Modules
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Modules;

/**
 * An installed module: a folder in the installation's storage with a
 * manifest, installed from a package. Themes and extensions are modules.
 *
 * This is what the installer needs to know about a module, whatever its
 * type: who it is, which app version it needs, where it lives, and where
 * its public assets come from and go.
 */
interface Module {

	/**
	 * Slug: lowercase letters, digits and hyphens; equals the module's folder name.
	 */
	public string $slug { get; }

	/**
	 * Display name.
	 */
	public string $name { get; }

	/**
	 * Version.
	 */
	public string $version { get; }

	/**
	 * Lowest app version supported; null for any.
	 */
	public ?string $requires { get; }

	/**
	 * Absolute path of the module's folder, without a trailing slash.
	 */
	public string $path { get; }

	/**
	 * Whether the module supports an app version.
	 *
	 * @param string $app_version
	 * @return bool
	 */
	public function supports( string $app_version ) : bool;

	/**
	 * Absolute path of the module's assets folder (it may not exist).
	 *
	 * @return string
	 */
	public function assets_dir() : string;

	/**
	 * The name of the module's assets inside public/ (PublicAssets).
	 *
	 * @return string
	 */
	public function public_name() : string;
}