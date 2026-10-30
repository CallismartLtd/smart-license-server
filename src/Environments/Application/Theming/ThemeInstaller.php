<?php
/**
 * Theme installer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use RuntimeException;
use SmartLicenseServer\Environments\Application\Assets\PublicAssets;
use SmartLicenseServer\Environments\Application\Modules\Module;
use SmartLicenseServer\Environments\Application\Modules\ModuleInstaller;
use SmartLicenseServer\Environments\Application\Update\PackageVerifier;
use SmartLicenseServer\Environments\Application\Update\UpdateServer;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;

/**
 * Installs, updates and removes the site themes of this installation.
 *
 * The install, update, marketplace and removal pipeline is ModuleInstaller's;
 * see it for how packages are verified, checked and swapped in. What is
 * specific to themes:
 *
 *  - A theme package holds one folder named after the theme slug
 *    (theme.json, templates/, assets/), installed in storage/themes/<slug>.
 *  - A theme "<slug>" is published on the marketplace as the hosted app
 *    "smliser-theme-<slug>".
 *  - PHP runs only from templates/. A package with PHP anywhere else is
 *    refused.
 *  - A theme that names a parent needs it installed first, and only one
 *    level of parents is supported.
 *  - A theme that is active, or the parent of another theme, is not removed.
 *
 * The default theme ships with the app and is never installed, replaced or
 * removed here. Which theme is active is ThemeManager's record.
 */
final class ThemeInstaller extends ModuleInstaller {

	public const NOUN               = 'theme';
	public const RESULT_KEY         = 'theme';
	public const LIST_KEY           = 'themes';
	public const MARKETPLACE_PREFIX = 'smliser-theme-';

	/**
	 * Constructor.
	 *
	 * @param FileSystem      $fs       Filesystem API.
	 * @param ThemeManager    $themes   The installed themes and the active one.
	 * @param PublicAssets    $assets   Publishes each theme's assets in public/.
	 * @param PackageVerifier $verifier Checks a package against its checksums and signature.
	 * @param UpdateServer    $server   The marketplace.
	 */
	public function __construct(
		FileSystem $fs,
		private ThemeManager $themes,
		PublicAssets $assets,
		PackageVerifier $verifier,
		UpdateServer $server
	) {
		parent::__construct( $fs, $themes, $assets, $verifier, $server );
	}

	/**
	 * Remove an installed theme and its published assets.
	 *
	 * @param string $slug Theme slug.
	 * @return string[] Warnings, e.g. assets left in public/ because they were not published by us.
	 * @throws ThemeException When the theme is the default, active or a parent of another theme, or cannot be removed.
	 */
	public function remove_theme( string $slug ) : array {
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
		return new ThemeException( $message, 0, $previous );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function slug_pattern() : string {
		return Theme::SLUG_PATTERN;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function load_staged( string $path ) : Module {
		return Theme::from_directory( $this->fs, $path );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function public_name_of( string $slug ) : string {
		return 'themes/' . $slug;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function publish_action() : string {
		return 'run `site-theme publish`';
	}

	/**
	 * {@inheritdoc}
	 */
	protected function reserved_message( string $slug ) : ?string {
		return ThemeManager::DEFAULT_SLUG === $slug
			? 'The default theme ships with the application; it is updated with the application, and cannot be installed, replaced or removed here.'
			: null;
	}

	/**
	 * {@inheritdoc}
	 */
	protected function check_staged_extra( Module $module ) : void {
		if ( ! $module instanceof Theme || null === $module->parent || ThemeManager::DEFAULT_SLUG === $module->parent ) {
			return;
		}

		if ( ! $this->themes->is_installed( $module->parent ) ) {
			throw new ThemeException( sprintf( 'The theme "%s" needs its parent theme "%s"; install the parent first.', $module->slug, $module->parent ) );
		}

		if ( null !== $this->themes->find( $module->parent )->parent ) {
			throw new ThemeException( sprintf( 'The parent "%s" of the theme "%s" has a parent itself; only one level is supported.', $module->parent, $module->slug ) );
		}
	}

	/**
	 * {@inheritdoc}
	 */
	protected function php_refusal( string $path ) : ?string {
		return str_starts_with( $path, 'templates/' )
			? null
			: sprintf( 'The package holds the PHP file %s outside templates/; PHP runs only from templates/.', $path );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function assert_removable( string $slug ) : void {
		if ( $this->themes->active_slug() === $slug ) {
			throw new ThemeException( sprintf( 'The theme "%s" is active; activate another theme before removing it.', $slug ) );
		}

		$children = $this->themes->children( $slug );

		if ( [] !== $children ) {
			throw new ThemeException( sprintf( 'The theme "%s" is the parent of %s; remove those first.', $slug, implode( ', ', $children ) ) );
		}
	}
}