<?php
/**
 * Module manager interface definition file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Modules
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Modules;

/**
 * Finds the installed modules of one type (themes, extensions).
 *
 * What a ModuleInstaller needs from the manager of the type it installs.
 * Each manager throws its own exception (a RuntimeException) when a module
 * cannot be found or is invalid.
 */
interface ModuleManager {

	/**
	 * An installed module by slug.
	 *
	 * @param string $slug
	 * @return Module
	 * @throws \RuntimeException When the module is not installed or is invalid.
	 */
	public function find( string $slug ) : Module;

	/**
	 * Every installed module folder, valid or not. Scans the folder: for admin lists, never for boot.
	 *
	 * @return array<string, Module|\RuntimeException> Folder name => the module, or why it cannot be loaded.
	 */
	public function installed() : array;

	/**
	 * Whether a module folder is installed (valid or not).
	 *
	 * @param string $slug
	 * @return bool
	 */
	public function is_installed( string $slug ) : bool;

	/**
	 * Forget a loaded module, after its folder was replaced or removed.
	 *
	 * @param string $slug
	 * @return void
	 */
	public function forget( string $slug ) : void;

	/**
	 * Absolute path of the folder the modules are installed in, without a trailing slash.
	 *
	 * @return string
	 */
	public function installed_dir() : string;
}