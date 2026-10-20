<?php
/**
 * The admin appearance page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Admin\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Admin\ContentHandlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\Theme;
use SmartLicenseServer\Environments\Application\Theming\ThemeException;
use SmartLicenseServer\Environments\Application\Theming\ThemeInstaller;
use SmartLicenseServer\Environments\Application\Theming\ThemeManager;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The admin Appearance menu: the site themes of the public pages.
 *
 * The Themes page lists the installed themes; themes.js installs (from the
 * marketplace or an uploaded package), activates, updates and removes them
 * through admin/json/themes/* (ThemeManagement). The admin itself is never
 * themed.
 */
class AppearancePage implements AdminPageInterface {

	/**
	 * Script handle of the Themes page (JS.php).
	 */
	public const SCRIPT = 'themes';

	/**
	 * Constructor.
	 *
	 * @param TemplateLocator $locator        Admin templates.
	 * @param URLManager      $urlmanager     URL API.
	 * @param AssetsManager   $assets_manager Admin scripts.
	 * @param ThemeManager    $themes         The installed themes and the active one.
	 * @param ThemeInstaller  $installer      Where each theme came from.
	 */
	public function __construct(
		protected TemplateLocator $locator,
		protected URLManager $urlmanager,
		protected AssetsManager $assets_manager,
		protected ThemeManager $themes,
		protected ThemeInstaller $installer
	) {
		$this->assets_manager->set_script_category( self::SCRIPT, AssetsManager::CATEGORY_ADMIN_DASHBOARD );
	}

	/*
	|---------------
	| PAGE HANDLERS
	|---------------
	*/

	/**
	 * Render the Themes page.
	 *
	 * Template 'admin.contents.appearance.themes'. Variables:
	 *   list<array> $themes  Every theme, the default first (see theme_rows()).
	 *   string      $active  Slug of the theme rendering the pages.
	 *   string      $recorded Slug recorded as active (differs from $active when that theme cannot be used).
	 *
	 * @param Request $request
	 * @return void
	 */
	public function themes_page( Request $request ) : void {
		$active       = $this->themes->active()->slug;
		$recorded     = $this->themes->active_slug();
		$themes       = $this->theme_rows( $active );
		$page_handler = $this;

		$vars = \compact( 'themes', 'active', 'recorded', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.appearance.themes', $vars );
	}

	/**
	 * The rows of the Themes page.
	 *
	 * @param string $active Slug of the theme rendering the pages.
	 * @return list<array{slug: string, name: string, version: string, description: string, author: string, parent: ?string,
	 *     source: string, marketplace: bool, signed_by: ?string, active: bool, builtin: bool, children: string[],
	 *     supported: bool, requires: ?string, error: ?string}>
	 */
	public function theme_rows( string $active ) : array {
		$rows = [ $this->row( $this->themes->default_theme(), $active, 'Built in', null ) ];

		foreach ( $this->themes->installed() as $slug => $theme ) {
			$record = $this->installer->record( $slug );

			if ( $theme instanceof ThemeException ) {
				$rows[] = [
					'slug'        => $slug,
					'name'        => $slug,
					'version'     => (string) ( $record['version'] ?? '' ),
					'description' => '',
					'author'      => '',
					'parent'      => null,
					'source'      => ThemeInstaller::describe_source( $record ),
					'marketplace' => ThemeInstaller::SOURCE_MARKETPLACE === ( $record['source'] ?? null ),
					'signed_by'   => $record['signed_by'] ?? null,
					'active'      => false,
					'builtin'     => false,
					'children'    => [],
					'supported'   => false,
					'requires'    => null,
					'error'       => $theme->getMessage(),
				];
				continue;
			}

			$rows[] = $this->row( $theme, $active, ThemeInstaller::describe_source( $record ), $record );
		}

		return $rows;
	}

	/*
	|-------------------
	| MENU REGISTRATION
	|-------------------
	*/

	/**
	 * {@inheritdoc}
	 */
	public function get_menu_key() : string {
		return 'appearance';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_menu_data() : array {
		return [
			'title'      => 'Appearance',
			'icon'       => 'ti ti-palette',
			'handler'    => $this,
			'slug'       => 'appearance',
			'visibility' => true,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_submenu() : array {
		return [
			[
				'title'      => 'Themes',
				'slug'       => 'themes',
				'callback'   => [ $this, 'themes_page' ],
				'visibility' => true,
			],
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function index_page_handler() : callable {
		return [ $this, 'themes_page' ];
	}

	/**
	 * The content header of the Themes page.
	 *
	 * @param Request $request
	 * @return array<string, mixed>
	 */
	public function get_top_menu_args( Request $request ) : array {
		return [
			'breadcrumbs' => [
				[
					'label' => 'Appearance',
					'url'   => $this->urlmanager->admin_url( 'appearance' ),
					'icon'  => 'ti ti-palette',
				],
				[
					'label' => 'Themes',
				],
			],
			'actions' => [],
		];
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * A row of the Themes page.
	 *
	 * @param Theme                     $theme
	 * @param string                    $active Slug of the theme rendering the pages.
	 * @param string                    $source How it was installed.
	 * @param array<string, mixed>|null $record Its package record.
	 * @return array<string, mixed>
	 */
	private function row( Theme $theme, string $active, string $source, ?array $record = null ) : array {
		$builtin = ThemeManager::DEFAULT_SLUG === $theme->slug;

		return [
			'slug'        => $theme->slug,
			'name'        => $theme->name,
			'version'     => $theme->version,
			'description' => $theme->description,
			'author'      => $theme->author,
			'parent'      => null !== $theme->parent && ThemeManager::DEFAULT_SLUG !== $theme->parent ? $theme->parent : null,
			'source'      => $source,
			'marketplace' => ThemeInstaller::SOURCE_MARKETPLACE === ( $record['source'] ?? null ),
			'signed_by'   => $record['signed_by'] ?? null,
			'active'      => $theme->slug === $active,
			'builtin'     => $builtin,
			'children'    => $builtin ? [] : $this->themes->children( $theme->slug ),
			'supported'   => $theme->supports( \SMLISER_VER ),
			'requires'    => $theme->requires,
			'error'       => null,
		];
	}
}