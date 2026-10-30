<?php
/**
 * Extension interface definition file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

use SmartLicenseServer\Core\Container\Container;

/**
 * The entry point of an extension: the class named by "entry" in its
 * extension.json.
 *
 * Philosophy:
 *
 *  - An extension adds to the app; it never edits it. Everything it adds
 *    goes through the container and the registries the app already has.
 *  - Two phases, so extensions can depend on each other safely: every
 *    enabled extension is registered before any extension is booted.
 *  - Extensions load in dependency order (an extension's "depends" load
 *    first), then in the order they were enabled.
 *
 * The entry class is built by the container, so its constructor may
 * type-hint services. It is built during the register phase, which runs
 * before the database, cache and models are ready: the constructor and
 * register() must not read or write them. Do that work in boot().
 */
interface ExtensionInterface {

	/**
	 * Bind this extension's services into the container.
	 *
	 * Runs before the app is booted. Bind only: do not resolve database,
	 * cache or model services, and do not rely on another extension's
	 * services (they may not be bound yet).
	 *
	 * @param Container $container The dependency injection container.
	 * @return void
	 */
	public function register( Container $container ) : void;

	/**
	 * Start this extension.
	 *
	 * Runs after every enabled extension is registered and after the app's
	 * own boot: core routes, registries, the database and the cache are
	 * ready, and the services of the extensions this one depends on exist.
	 *
	 * @param Container $container The dependency injection container.
	 * @return void
	 */
	public function boot( Container $container ) : void;
}