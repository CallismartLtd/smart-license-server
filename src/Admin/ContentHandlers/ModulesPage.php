<?php
/**
 * The admin modules page class file.
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
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The admin Modules menu: what this installation adds to itself.
 *
 * Submenus:
 *   Themes       The installed site themes of the public pages.
 *   Marketplace  What the update server publishes, read from its repository
 *                REST endpoints, ready to install.
 * Extensions will be a submenu of their own.
 *
 * Within a submenu, pages are chosen with the "section" query variable
 * (SECTION_VAR), e.g. modules/themes?section=upload; each submenu lists its
 * sections as header links. Every installed or published module is drawn as
 * a module card (templates/admin/contents/modules/card.php), the same
 * markup for themes, extensions and the marketplace; modules.js posts a
 * card's actions to admin/json/<kind>s/<action>.
 *
 * The admin itself is never themed.
 */
class ModulesPage implements AdminPageInterface {

	/**
	 * Style and script handles of the Modules pages (CSS.php, JS.php).
	 */
	public const ASSETS = 'modules';

	/**
	 * Query variable naming a section of a submenu page.
	 */
	public const SECTION_VAR = 'section';

	/**
	 * Sections of the Themes page: section => header label. The first is the default.
	 */
	public const THEME_SECTIONS = [
		'installed' => 'Installed',
		'upload'    => 'Upload',
	];

	/**
	 * Sections of the Marketplace page: section => header label. The first is the default.
	 */
	public const MARKETPLACE_SECTIONS = [
		'themes' => 'Themes',
	];

	/**
	 * Constructor.
	 *
	 * @param TemplateLocator $locator        Admin templates.
	 * @param URLManager      $urlmanager     URL API.
	 * @param AssetsManager   $assets_manager Admin styles and scripts.
	 * @param ThemeManager    $themes         The installed themes and the active one.
	 * @param ThemeInstaller  $installer      Package records and the marketplace.
	 * @param FileSystem      $fs             Finds a theme's screenshot.
	 */
	public function __construct(
		protected TemplateLocator $locator,
		protected URLManager $urlmanager,
		protected AssetsManager $assets_manager,
		protected ThemeManager $themes,
		protected ThemeInstaller $installer,
		protected FileSystem $fs
	) {
		$this->assets_manager->set_style_category( self::ASSETS, AssetsManager::CATEGORY_ADMIN_DASHBOARD );
		$this->assets_manager->set_script_category( self::ASSETS, AssetsManager::CATEGORY_ADMIN_DASHBOARD );
	}

	/*
	|---------------
	| PAGE HANDLERS
	|---------------
	*/

	/**
	 * Modules > Themes: the installed themes, or the upload form (?section=upload).
	 *
	 * Template 'admin.contents.modules.themes'. Variables:
	 *   string      $section  The section shown (a THEME_SECTIONS key).
	 *   list<array> $cards    Every theme, the default first, as module cards (see card()).
	 *   string      $active   Slug of the theme rendering the pages.
	 *   string      $recorded Slug recorded as active (differs from $active when that theme cannot be used).
	 *
	 * @param Request $request
	 * @return void
	 */
	public function themes_page( Request $request ) : void {
		$section      = $this->section( $request, self::THEME_SECTIONS );
		$active       = $this->themes->active()->slug;
		$recorded     = $this->themes->active_slug();
		$cards        = 'installed' === $section ? $this->theme_cards( $active ) : [];
		$menu_args    = $this->header( $request, 'themes', 'Themes', self::THEME_SECTIONS, $section );
		$page_handler = $this;

		$vars = \compact( 'section', 'cards', 'active', 'recorded', 'menu_args', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.modules.themes', $vars );
	}

	/**
	 * Modules > Marketplace: what the update server publishes (?section=themes).
	 *
	 * Template 'admin.contents.modules.marketplace'. Variables:
	 *   string      $section    The section shown (a MARKETPLACE_SECTIONS key).
	 *   string      $search     The search term (?search=).
	 *   list<array> $cards      The page of published modules, as module cards.
	 *   array|null  $pagination {total, page, limit, total_pages}; null when the marketplace could not be read.
	 *   string|null $error      Why the marketplace could not be read.
	 *   string      $page_url   This section's URL, for the search form and the pagination.
	 *
	 * @param Request $request
	 * @return void
	 */
	public function marketplace_page( Request $request ) : void {
		$section    = $this->section( $request, self::MARKETPLACE_SECTIONS );
		$search     = \trim( (string) $request->query( 'search', '' ) );
		$page       = \max( 1, (int) $request->query( 'paged', 1 ) );
		$cards      = [];
		$pagination = null;
		$error      = null;

		try {
			$listing    = $this->installer->marketplace( $search, $page );
			$pagination = $listing['pagination'];

			foreach ( $listing['themes'] as $theme ) {
				$cards[] = $this->marketplace_theme_card( $theme );
			}
		} catch ( ThemeException $e ) {
			$error = $e->getMessage();
		}

		$page_url     = $this->urlmanager->admin_url( 'modules', 'marketplace', [ self::SECTION_VAR => $section ] )->url();
		$menu_args    = $this->header( $request, 'marketplace', 'Marketplace', self::MARKETPLACE_SECTIONS, $section );
		$page_handler = $this;

		$vars = \compact( 'section', 'search', 'cards', 'pagination', 'error', 'page_url', 'menu_args', 'page_handler', 'request' );
		$this->locator->render( 'admin.contents.modules.marketplace', $vars );
	}

	/*
	|-------
	| CARDS
	|-------
	*/

	/**
	 * Every theme as a module card: the default theme first, then the installed ones.
	 *
	 * @param string $active Slug of the theme rendering the pages.
	 * @return list<array<string, mixed>> See card().
	 */
	public function theme_cards( string $active ) : array {
		$cards = [ $this->installed_theme_card( $this->themes->default_theme(), $active, null ) ];

		foreach ( $this->themes->installed() as $slug => $theme ) {
			$record = $this->installer->record( $slug );

			if ( $theme instanceof ThemeException ) {
				$cards[] = $this->card(
					slug: $slug,
					name: $slug,
					description: $theme->getMessage(),
					badges: [ [ 'Broken', 'danger' ] ],
					meta: $this->record_meta( $record ),
					actions: [ $this->action( 'remove', 'Remove', 'ti ti-trash', \sprintf( 'Remove the broken theme "%s" and its files?', $slug ), true ) ]
				);
				continue;
			}

			$cards[] = $this->installed_theme_card( $theme, $active, $record );
		}

		return $cards;
	}

	/**
	 * A module card: the data card.php draws, the same for every kind of module.
	 *
	 * @param string                                      $slug        Module slug; the actions' target.
	 * @param string                                      $name        Display name.
	 * @param string                                      $kind        Module kind: "theme" (actions post to admin/json/themes/*).
	 * @param string                                      $version     Version; empty when unknown.
	 * @param string                                      $description One or two sentences.
	 * @param string                                      $image       Picture URL; empty for the name's initial.
	 * @param list<array{0: string, 1: string}>           $badges      [label, tone]; tone: active, info, warning, danger, muted.
	 * @param list<array{0: string, 1: string}>           $meta        [icon class, text] lines.
	 * @param list<array<string, mixed>>                  $actions     See action().
	 * @param list<array{0: string, 1: string}>           $links       [label, URL] external links.
	 * @param bool                                        $highlight   Draw the card as the current one (e.g. the active theme).
	 * @return array<string, mixed>
	 */
	public function card(
		string $slug,
		string $name,
		string $kind = 'theme',
		string $version = '',
		string $description = '',
		string $image = '',
		array $badges = [],
		array $meta = [],
		array $actions = [],
		array $links = [],
		bool $highlight = false
	) : array {
		return \compact( 'slug', 'name', 'kind', 'version', 'description', 'image', 'badges', 'meta', 'actions', 'links', 'highlight' );
	}

	/**
	 * A card action: a button posting to admin/json/<kind>s/<action> with the card's slug.
	 *
	 * @param string $action  Endpoint action, e.g. "activate".
	 * @param string $label   Button text.
	 * @param string $icon    Tabler icon class.
	 * @param string $confirm Confirmation question; empty for none.
	 * @param bool   $danger  Draw the confirmation as destructive.
	 * @return array{action: string, label: string, icon: string, confirm: string, danger: bool}
	 */
	public function action( string $action, string $label, string $icon, string $confirm = '', bool $danger = false ) : array {
		return \compact( 'action', 'label', 'icon', 'confirm', 'danger' );
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
		return 'modules';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_menu_data() : array {
		return [
			'title'      => 'Modules',
			'icon'       => 'ti ti-puzzle',
			'handler'    => $this,
			'slug'       => 'modules',
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
			[
				'title'      => 'Marketplace',
				'slug'       => 'marketplace',
				'callback'   => [ $this, 'marketplace_page' ],
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

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * An installed theme's card.
	 *
	 * @param Theme                     $theme
	 * @param string                    $active Slug of the theme rendering the pages.
	 * @param array<string, mixed>|null $record Its package record; null for the default theme or a copied-in theme.
	 * @return array<string, mixed>
	 */
	private function installed_theme_card( Theme $theme, string $active, ?array $record ) : array {
		$builtin  = ThemeManager::DEFAULT_SLUG === $theme->slug;
		$children = $builtin ? [] : $this->themes->children( $theme->slug );
		$parent   = null !== $theme->parent && ThemeManager::DEFAULT_SLUG !== $theme->parent ? $theme->parent : null;
		$is_on    = $theme->slug === $active;
		$badges   = [];
		$actions  = [];

		if ( $is_on ) {
			$badges[] = [ 'Active', 'active' ];
		}

		if ( null !== $parent ) {
			$badges[] = [ \sprintf( 'Child of %s', $parent ), 'info' ];
		}

		if ( [] !== $children ) {
			$badges[] = [ \sprintf( 'Parent of %s', \implode( ', ', $children ) ), 'info' ];
		}

		if ( ! $theme->supports( \SMLISER_VER ) ) {
			$badges[] = [ \sprintf( 'Needs app %s', $theme->requires ), 'warning' ];
		}

		$badges[] = [ $builtin ? 'Built in' : ThemeInstaller::describe_source( $record ), 'muted' ];

		if ( ! $is_on && $theme->supports( \SMLISER_VER ) ) {
			$actions[] = $this->action( 'activate', 'Activate', 'ti ti-player-play' );
		}

		if ( ThemeInstaller::SOURCE_MARKETPLACE === ( $record['source'] ?? null ) ) {
			$actions[] = $this->action( 'update', 'Update', 'ti ti-download', \sprintf( 'Update %s from the marketplace? Its files are replaced with the latest version.', $theme->name ) );
		}

		if ( ! $builtin && ! $is_on && [] === $children ) {
			$actions[] = $this->action( 'remove', 'Remove', 'ti ti-trash', \sprintf( 'Remove %s and its files?', $theme->name ), true );
		}

		return $this->card(
			slug: $theme->slug,
			name: $theme->name,
			version: $theme->version,
			description: $theme->description,
			image: $this->screenshot( $theme ),
			badges: $badges,
			meta: \array_merge(
				'' !== $theme->author ? [ [ 'ti ti-user', $theme->author ] ] : [],
				$this->record_meta( $record )
			),
			actions: $actions,
			highlight: $is_on
		);
	}

	/**
	 * A marketplace theme's card.
	 *
	 * @param array{slug: string, name: string, version: string, summary: string, author: string, icon: string, homepage: string, installed: ?string, update: bool} $theme
	 * @return array<string, mixed>
	 */
	private function marketplace_theme_card( array $theme ) : array {
		$badges  = [ [ 'Theme', 'muted' ] ];
		$actions = [];

		if ( null === $theme['installed'] ) {
			$actions[] = $this->action( 'install', 'Install', 'ti ti-download' );
		} elseif ( $theme['update'] ) {
			$badges[]  = [ \sprintf( 'Installed %s', $theme['installed'] ), 'info' ];
			$actions[] = $this->action( 'install', \sprintf( 'Update to %s', $theme['version'] ), 'ti ti-download', \sprintf( 'Update %s from %s to %s? Its files are replaced.', $theme['name'], $theme['installed'], $theme['version'] ) );
		} else {
			$badges[] = [ 'Installed', 'active' ];
		}

		return $this->card(
			slug: $theme['slug'],
			name: $theme['name'],
			version: $theme['version'],
			description: $theme['summary'],
			image: $theme['icon'],
			badges: $badges,
			meta: '' !== $theme['author'] ? [ [ 'ti ti-user', $theme['author'] ] ] : [],
			actions: $actions,
			links: '' !== $theme['homepage'] ? [ [ 'Details', $theme['homepage'] ] ] : []
		);
	}

	/**
	 * The meta lines of a package record.
	 *
	 * @param array<string, mixed>|null $record
	 * @return list<array{0: string, 1: string}>
	 */
	private function record_meta( ?array $record ) : array {
		if ( null === $record ) {
			return [];
		}

		$meta = [];

		if ( \is_string( $record['signed_by'] ?? null ) ) {
			$meta[] = [ 'ti ti-shield-check', \sprintf( 'Signed by the "%s" key', $record['signed_by'] ) ];
		} elseif ( true === ( $record['trusted'] ?? false ) ) {
			$meta[] = [ 'ti ti-shield-x', 'Unsigned; installed as trusted' ];
		}

		if ( \is_string( $record['installed_at'] ?? null ) && false !== \strtotime( $record['installed_at'] ) ) {
			$meta[] = [ 'ti ti-clock', \sprintf( 'Installed %s', \gmdate( 'Y-m-d', (int) \strtotime( $record['installed_at'] ) ) ) ];
		}

		return $meta;
	}

	/**
	 * A theme's card picture: its assets/screenshot.png, when it ships one.
	 *
	 * @param Theme $theme
	 * @return string URL; empty for none.
	 */
	private function screenshot( Theme $theme ) : string {
		foreach ( [ 'screenshot.png', 'screenshot.jpg', 'screenshot.webp' ] as $file ) {
			if ( $this->fs->is_file( $theme->assets_dir() . '/' . $file ) ) {
				return $this->urlmanager->themes_url( $theme->slug . '/' . $file, [ 'version' => $theme->version ] )->url();
			}
		}

		return '';
	}

	/**
	 * The requested section of a submenu page; the first section for none or an unknown one.
	 *
	 * @param Request               $request
	 * @param array<string, string> $sections Section => label.
	 * @return string
	 */
	private function section( Request $request, array $sections ) : string {
		$section = (string) $request->query( self::SECTION_VAR, '' );

		return isset( $sections[ $section ] ) ? $section : (string) \array_key_first( $sections );
	}

	/**
	 * The content header of a submenu page: breadcrumbs, and its sections as links.
	 *
	 * @param Request               $request
	 * @param string                $tab      Submenu slug.
	 * @param string                $title    Submenu title.
	 * @param array<string, string> $sections Section => label.
	 * @param string                $current  The section shown.
	 * @return array<string, mixed>
	 */
	private function header( Request $request, string $tab, string $title, array $sections, string $current ) : array {
		$actions = [];

		foreach ( $sections as $section => $label ) {
			$actions[] = [
				'label'  => $label,
				'url'    => $this->urlmanager->admin_url( 'modules', $tab, $section === \array_key_first( $sections ) ? [] : [ self::SECTION_VAR => $section ] )->url(),
				'active' => $section === $current,
			];
		}

		return [
			'breadcrumbs' => [
				[
					'label' => 'Modules',
					'url'   => $this->urlmanager->admin_url( 'modules' )->url(),
					'icon'  => 'ti ti-puzzle',
				],
				[
					'label' => $title,
				],
			],
			'actions'     => $actions,
		];
	}
}
