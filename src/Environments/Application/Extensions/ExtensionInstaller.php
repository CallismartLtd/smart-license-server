<?php
/**
 * Extension installer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Extensions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Extensions;

use RuntimeException;
use SmartLicenseServer\Environments\Application\Assets\PublicAssets;
use SmartLicenseServer\Environments\Application\Modules\Module;
use SmartLicenseServer\Environments\Application\Modules\ModuleInstaller;
use SmartLicenseServer\Environments\Application\Update\PackageVerifier;
use SmartLicenseServer\Environments\Application\Update\UpdateServer;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;

/**
 * Installs, updates and removes the extensions of this installation.
 *
 * The install, update, marketplace and removal pipeline is ModuleInstaller's;
 * see it for how packages are verified, checked and swapped in. What is
 * specific to extensions:
 *
 *  - An extension package holds one folder named after the extension slug
 *    (extension.json, src/, optional assets/), installed in
 *    storage/extensions/<slug>.
 *  - An extension "<slug>" is published on the marketplace as a hosted app
 *    of the app type "smliser-extension", with the extension slug as its
 *    app slug.
 *  - Installing does not enable. Whether an extension runs is
 *    ExtensionManager's record, changed with enable() and disable().
 *  - Replacing an enabled extension is allowed: the new files load from the
 *    next request on. An enabled extension is not removed; disable it first.
 */
final class ExtensionInstaller extends ModuleInstaller {

	public const NOUN               = 'extension';
	public const RESULT_KEY         = 'extension';
	public const LIST_KEY           = 'extensions';
	public const MARKETPLACE_TYPE	= 'smliser-extension';

	/**
	 * Constructor.
	 *
	 * @param FileSystem       $fs         Filesystem API.
	 * @param ExtensionManager $extensions The installed extensions and the enabled ones.
	 * @param PublicAssets     $assets     Publishes each extension's assets in public/.
	 * @param PackageVerifier  $verifier   Checks a package against its checksums and signature.
	 * @param UpdateServer     $server     The marketplace.
	 */
	public function __construct(
		FileSystem $fs,
		private ExtensionManager $extensions,
		PublicAssets $assets,
		PackageVerifier $verifier,
		UpdateServer $server
	) {
		parent::__construct( $fs, $extensions, $assets, $verifier, $server );
	}

	/**
	 * Remove an installed extension and its published assets.
	 *
	 * @param string $slug Extension slug.
	 * @return string[] Warnings, e.g. assets left in public/ because they were not published by us.
	 * @throws ExtensionException When the extension is enabled, or cannot be removed.
	 */
	public function remove_extension( string $slug ) : array {
		return $this->uninstall( $slug );
	}

	/*
	|-----------------------
	| ModuleInstaller HOOKS
	|-----------------------
	*/

	/**
	 * {@inheritdoc}
	 */
	protected function fail( string $message, ?Throwable $previous = null ) : RuntimeException {
		return new ExtensionException( $message, 0, $previous );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function slug_pattern() : string {
		return Extension::SLUG_PATTERN;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function load_staged( string $path ) : Module {
		return Extension::from_directory( $this->fs, $path );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function public_name_of( string $slug ) : string {
		return 'extensions/' . $slug;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function publish_action() : string {
		return 'publish the assets again';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function assert_removable( string $slug ) : void {
		if ( $this->extensions->is_enabled( $slug ) ) {
			throw new ExtensionException( sprintf( 'The extension "%s" is enabled; disable it before removing it.', $slug ) );
		}
	}
}