<?php
/**
 * General asset manager for CSS/JS.
 *
 * Provides the canonical asset registry, dependency resolution,
 * asset grouping, category-scoped registration, global JS constants,
 * and HTML rendering for registered assets.
 *
 * This class is a singleton: default CSS/JS assets are registered
 * exactly once, at construction time, the first time instance() is
 * called.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */


namespace SmartLicenseServer\Assets;


use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Security\Permission\Capability;
use SmartLicenseServer\Security\Permission\Role;
use SmartLicenseServer\Utils\Format;

final class AssetsManager {


	/**
	 * Email editor asset group name.
	 */
	const GROUP_EMAIL_EDITOR = 'email_editor';


	/**
	 * Client dashboard asset group name.
	 */
	const GROUP_CLIENT_DASHBOARD = 'client_dashboard';


	/**
	 * Admin dashboard asset group name.
	 */
	const GROUP_ADMIN_DASHBOARD = 'admin_dashboard';


	/**
	 * Global asset category name.
	 *
	 * The default category for any asset registered without an
	 * explicit category. Global assets are meant to be loaded on
	 * every screen (admin dashboard, client dashboard, etc.).
	 */
	const CATEGORY_GLOBAL = 'global';


	/**
	 * The singleton instance.
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;


	/**
	 * Registered CSS assets.
	 *
	 * @var array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     media-type: string,
	 *     category: string
	 * }>
	 */
	private array $styles = [];


	/**
	 * Registered JavaScript assets.
	 *
	 * @var array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     footer: bool,
	 *     category: string
	 * }>
	 */
	private array $scripts = [];


	/**
	 * Registered global JS constants (translations, server-side data,
	 * etc.) to be exposed to the front end as `const NAME = ...;`
	 * statements.
	 *
	 * @var array<string, mixed>
	 */
	private array $js_constants = [];


	/**
	 * Construct the manager and register the default assets.
	 *
	 * Private: use instance() to obtain the singleton.
	 */
	private function __construct() {
        $this->register_default_js_constants();
		$this->register_default_styles();
		$this->register_default_scripts();
	}


	/**
	 * Prevent cloning of the singleton.
	 */
	private function __clone() {}


	/**
	 * Prevent unserialization from creating a second instance.
	 *
	 * @throws \LogicException Always.
	 */
	public function __wakeup() : void {
		throw new \LogicException( 'Cannot unserialize a singleton ' . self::class . '.' );
	}


	/**
	 * Get the singleton instance.
	 *
	 * On first call this constructs the manager and registers the
	 * default CSS/JS assets exactly once. Subsequent calls return the
	 * same instance without re-registering anything.
	 *
	 * @return self
	 */
	public static function instance() : self {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}


	/**
	 * Reset the singleton.
	 *
	 * Intended for tests: the next call to instance() will construct a
	 * fresh manager (with defaults registered again). Not part of the
	 * normal runtime flow.
	 *
	 * @return void
	 */
	public static function reset_instance() : void {
		self::$instance = null;
	}


	/**
	 * Get script suffix depending on debug mode.
	 *
	 * @return string
	 */
	public static function script_suffix() : string {
		return $_ENV['SCRIPT_SUFFIX'] ?? '';
	}


	/**
	 * Get the valid asset categories.
	 *
	 * @return string[]
	 */
	public function valid_categories() : array {
		return [
			self::CATEGORY_GLOBAL,
			self::GROUP_ADMIN_DASHBOARD,
			self::GROUP_CLIENT_DASHBOARD,
		];
	}


	/**
	 * Register a CSS asset.
	 *
	 * @param string $handle
	 * @param URL $url
	 * @param string[] $dependencies
	 * @param string $version
	 * @param string $media_type
	 * @param string $category One of the valid_categories() values. Defaults to CATEGORY_GLOBAL.
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the asset definition is invalid.
	 * @throws \LogicException If the handle is already registered.
	 */
	public function register_style(
		string $handle,
		URL $url,
		array $dependencies = [],
		string $version = '',
		string $media_type = 'all',
		string $category = self::CATEGORY_GLOBAL
	) : void {

		$this->validate_handle( $handle );
		$this->validate_dependencies( $handle, $dependencies );
		$this->validate_category( $category );

		if ( isset( $this->styles[ $handle ] ) ) {
			throw new \LogicException(
				sprintf( 'CSS asset "%s" is already registered.', $handle )
			);
		}

		if ( '' === trim( $media_type ) ) {
			throw new \InvalidArgumentException(
				'CSS asset media type cannot be empty.'
			);
		}

		$this->styles[ $handle ] = [
			'url'          => $url,
			'dependencies' => array_values( $dependencies ),
			'version'      => $version,
			'media-type'   => $media_type,
			'category'     => $category,
		];
	}


	/**
	 * Register a JavaScript asset.
	 *
	 * @param string $handle
	 * @param URL $url
	 * @param string[] $dependencies
	 * @param string $version
	 * @param bool $footer
	 * @param string $category One of the valid_categories() values. Defaults to CATEGORY_GLOBAL.
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the asset definition is invalid.
	 * @throws \LogicException If the handle is already registered.
	 */
	public function register_script(
		string $handle,
		URL $url,
		array $dependencies = [],
		string $version = '',
		bool $footer = true,
		string $category = self::CATEGORY_GLOBAL
	) : void {

		$this->validate_handle( $handle );
		$this->validate_dependencies( $handle, $dependencies );
		$this->validate_category( $category );

		if ( isset( $this->scripts[ $handle ] ) ) {
			throw new \LogicException(
				sprintf( 'JavaScript asset "%s" is already registered.', $handle )
			);
		}

		$this->scripts[ $handle ] = [
			'url'          => $url,
			'dependencies' => array_values( $dependencies ),
			'version'      => $version,
			'footer'       => $footer,
			'category'     => $category,
		];
	}


	/**
	 * Unregister a CSS asset.
	 *
	 * @param string $handle
	 * @return bool True if the asset was registered and removed.
	 */
	public function unregister_style( string $handle ) : bool {
		if ( ! isset( $this->styles[ $handle ] ) ) {
			return false;
		}

		unset( $this->styles[ $handle ] );

		return true;
	}


	/**
	 * Unregister a JavaScript asset.
	 *
	 * @param string $handle
	 * @return bool True if the asset was registered and removed.
	 */
	public function unregister_script( string $handle ) : bool {
		if ( ! isset( $this->scripts[ $handle ] ) ) {
			return false;
		}

		unset( $this->scripts[ $handle ] );

		return true;
	}


	/**
	 * Determine whether a CSS asset is registered.
	 *
	 * @param string $handle
	 * @return bool
	 */
	public function has_style( string $handle ) : bool {
		return isset( $this->styles[ $handle ] );
	}


	/**
	 * Determine whether a JavaScript asset is registered.
	 *
	 * @param string $handle
	 * @return bool
	 */
	public function has_script( string $handle ) : bool {
		return isset( $this->scripts[ $handle ] );
	}


	/**
	 * Get all registered CSS assets.
	 *
	 * @return array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     media-type: string,
	 *     category: string
	 * }>
	 */
	public function styles() : array {
		return $this->styles;
	}


	/**
	 * Get all registered JavaScript assets.
	 *
	 * @return array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     footer: bool,
	 *     category: string
	 * }>
	 */
	public function scripts() : array {
		return $this->scripts;
	}


	/**
	 * Get all registered CSS assets belonging to a category.
	 *
	 * @param string $category
	 * @return array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     media-type: string,
	 *     category: string
	 * }>
	 *
	 * @throws \InvalidArgumentException If the category is invalid.
	 */
	public function get_styles_by_category( string $category ) : array {

		$this->validate_category( $category );

		return array_filter(
			$this->styles,
			fn( array $style ) : bool => $style['category'] === $category
		);
	}


	/**
	 * Get all registered JavaScript assets belonging to a category.
	 *
	 * @param string $category
	 * @return array<string, array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     footer: bool,
	 *     category: string
	 * }>
	 *
	 * @throws \InvalidArgumentException If the category is invalid.
	 */
	public function get_scripts_by_category( string $category ) : array {

		$this->validate_category( $category );

		return array_filter(
			$this->scripts,
			fn( array $script ) : bool => $script['category'] === $category
		);
	}


	/**
	 * Get a registered CSS asset.
	 *
	 * @param string $handle
	 * @return array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     media-type: string,
	 *     category: string
	 * }|null
	 */
	public function get_style( string $handle ) : ?array {
		return $this->styles[ $handle ] ?? null;
	}


	/**
	 * Get a registered JavaScript asset.
	 *
	 * @param string $handle
	 * @return array{
	 *     url: URL,
	 *     dependencies: string[],
	 *     version: string,
	 *     footer: bool,
	 *     category: string
	 * }|null
	 */
	public function get_script( string $handle ) : ?array {
		return $this->scripts[ $handle ] ?? null;
	}


	/**
	 * Get a registered CSS asset URL.
	 *
	 * @param string $handle
	 * @return URL|null
	 */
	public function get_style_url( string $handle ) : ?URL {
		$style = $this->get_style( $handle );

		return $style['url'] ?? null;
	}


	/**
	 * Get a registered JavaScript asset URL.
	 *
	 * @param string $handle
	 * @return URL|null
	 */
	public function get_script_url( string $handle ) : ?URL {
		$script = $this->get_script( $handle );

		return $script['url'] ?? null;
	}


	/**
	 * Resolve CSS dependencies.
	 *
	 * Dependencies are returned before the assets that depend on them.
	 *
	 * @param string[] $handles
	 * @return string[]
	 *
	 * @throws \LogicException If an unknown dependency or circular dependency
	 *                         is encountered.
	 */
	public function resolve_styles( array $handles ) : array {
		return $this->resolve_dependencies( $handles, $this->styles, 'CSS' );
	}


	/**
	 * Resolve JavaScript dependencies.
	 *
	 * Dependencies are returned before the assets that depend on them.
	 *
	 * @param string[] $handles
	 * @return string[]
	 *
	 * @throws \LogicException If an unknown dependency or circular dependency
	 *                         is encountered.
	 */
	public function resolve_scripts( array $handles ) : array {
		return $this->resolve_dependencies( $handles, $this->scripts, 'JavaScript' );
	}


	/**
	 * Print a single CSS asset.
	 *
	 * The registered version is appended to the URL as the "version"
	 * query parameter before the URL is converted to a string.
	 *
	 * @param string $handle
	 * @param bool $echo
	 * @return string|null
	 */
	public function print_style( string $handle, bool $echo = true ) : ?string {

		$style = $this->get_style( $handle );

		if ( null === $style ) {
			return null;
		}

		$url = $style['url']->add_query_param( 'version', $style['version'] );

		$link_tag = sprintf(
			'<link rel="stylesheet" id="%s-css" href="%s" media="%s" />',
			escAttr( $handle ),
			escUrl( $url->url() ),
			escAttr( $style['media-type'] )
		);

		if ( $echo ) {
			echo $link_tag . PHP_EOL; // phpcs:ignore

			return null;
		}

		return $link_tag;
	}


	/**
	 * Print a single JavaScript asset.
	 *
	 * The registered version is appended to the URL as the "version"
	 * query parameter before the URL is converted to a string.
	 *
	 * @param string $handle
	 * @param bool $echo
	 * @return string|null
	 */
	public function print_script( string $handle, bool $echo = true ) : ?string {

		$script = $this->get_script( $handle );

		if ( null === $script ) {
			return null;
		}

		$url = $script['url']->add_query_param( 'version', $script['version'] );

		$html = sprintf(
			'<script id="%s-js" src="%s"></script>',
			escAttr( $handle ),
			escUrl( $url->url() )
		);

		if ( $echo ) {
			echo $html . PHP_EOL; // phpcs:ignore

			return null;
		}

		return $html;
	}


	/**
	 * Print multiple CSS assets with dependency resolution.
	 *
	 * @param string ...$handles
	 * @return void
	 */
	public function print_styles( string ...$handles ) : void {

		foreach ( $this->resolve_styles( $handles ) as $handle ) {
			$this->print_style( $handle );
		}
	}


	/**
	 * Print multiple JavaScript assets with dependency resolution.
	 *
	 * @param string ...$handles
	 * @return void
	 */
	public function print_scripts( string ...$handles ) : void {

		foreach ( $this->resolve_scripts( $handles ) as $handle ) {
			$this->print_script( $handle );
		}
	}


	/**
	 * Print every registered CSS asset belonging to a category,
	 * with dependency resolution.
	 *
	 * @param string $category
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the category is invalid.
	 */
	public function print_category_styles( string $category ) : void {

		$handles = array_keys( $this->get_styles_by_category( $category ) );

		if ( empty( $handles ) ) {
			return;
		}

		$this->print_styles( ...$handles );
	}


	/**
	 * Print every registered JavaScript asset belonging to a category,
	 * with dependency resolution.
	 *
	 * @param string $category
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the category is invalid.
	 */
	public function print_category_scripts( string $category ) : void {

		$handles = array_keys( $this->get_scripts_by_category( $category ) );

		if ( empty( $handles ) ) {
			return;
		}

		$this->print_scripts( ...$handles );
	}


	/**
	 * Print every registered CSS and JavaScript asset belonging to a
	 * category, with dependency resolution.
	 *
	 * @param string $category
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the category is invalid.
	 */
	public function print_category( string $category ) : void {
		$this->print_category_styles( $category );
		$this->print_category_scripts( $category );
	}


	/**
	 * Print every registered global CSS asset (assets registered
	 * without an explicit category, or explicitly under CATEGORY_GLOBAL).
	 *
	 * @return void
	 */
	public function print_global_styles() : void {
		$this->print_category_styles( self::CATEGORY_GLOBAL );
	}


	/**
	 * Print every registered global JavaScript asset (assets registered
	 * without an explicit category, or explicitly under CATEGORY_GLOBAL).
	 *
	 * @return void
	 */
	public function print_global_scripts() : void {
		$this->print_category_scripts( self::CATEGORY_GLOBAL );
	}


	/**
	 * Print every registered global CSS and JavaScript asset.
	 *
	 * @return void
	 */
	public function print_global() : void {
		$this->print_category( self::CATEGORY_GLOBAL );
	}


	/**
	 * Register a global JS constant (translations, server-side data,
	 * feature flags, etc.) to be exposed to the front end.
	 *
	 * @param string $name A valid JavaScript identifier, e.g. "smliserL10n".
	 * @param array $data
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the name is not a valid JS identifier.
	 * @throws \LogicException If the name is already registered.
	 */
	public function register_js_constant( string $name, array $data ) : void {

		$this->validate_js_constant_name( $name );

		if ( isset( $this->js_constants[ $name ] ) ) {
			throw new \LogicException(
				sprintf( 'JS constant "%s" is already registered.', $name )
			);
		}

		$this->js_constants[ $name ] = $data;
	}


	/**
	 * Determine whether a JS constant is registered.
	 *
	 * @param string $name
	 * @return bool
	 */
	public function has_js_constant( string $name ) : bool {
		return isset( $this->js_constants[ $name ] );
	}


	/**
	 * Get a registered JS constant's data.
	 *
	 * @param string $name
	 * @return array|null
	 */
	public function get_js_constant( string $name ) : ?array {
		return $this->js_constants[ $name ] ?? null;
	}


	/**
	 * Get all registered JS constants.
	 *
	 * @return array<string, array>
	 */
	public function get_js_constants() : array {
		return $this->js_constants;
	}


	/**
	 * Unregister a JS constant.
	 *
	 * @param string $name
	 * @return bool True if the constant was registered and removed.
	 */
	public function unregister_js_constant( string $name ) : bool {
		if ( ! isset( $this->js_constants[ $name ] ) ) {
			return false;
		}

		unset( $this->js_constants[ $name ] );

		return true;
	}


	/**
	 * Print all registered JS constants as a single inline <script> tag
	 * containing one `const NAME = ...;` statement per constant.
	 *
	 * Data is JSON-encoded with JSON_HEX_* flags so it is safe to embed
	 * directly inside a <script> tag (no closing-tag or quote breakout).
	 *
	 * Call this before print_global_scripts()/print_category_scripts()
	 * so consuming scripts can rely on the constants already existing.
	 *
	 * @param bool $echo
	 * @return string|null
	 *
	 * @throws \RuntimeException If a constant fails to JSON-encode.
	 */
	public function print_js_constants( bool $echo = true ) : ?string {

		if ( empty( $this->js_constants ) ) {
			return $echo ? null : '';
		}

		$statements = [];

		foreach ( $this->js_constants as $name => $data ) {

			$encoded = json_encode(
				$data,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
			);

			if ( false === $encoded ) {
				throw new \RuntimeException(
					sprintf(
						'Failed to encode JS constant "%s": %s',
						$name,
						json_last_error_msg()
					)
				);
			}

			$statements[] = sprintf( 'const %s = %s;', $name, $encoded );
		}

		$html = sprintf(
			'<script id="smliser-js-constants">%s</script>',
			implode( "\n\r", $statements )
		);

		if ( $echo ) {
			echo $html . PHP_EOL; // phpcs:ignore

			return null;
		}

		return $html;
	}


	/**
	 * Get the registered asset groups.
	 *
	 * Groups contain asset handles only. Asset definitions are resolved
	 * from the canonical registries.
	 *
	 * @return array<string, array{
	 *     styles: string[],
	 *     scripts: string[]
	 * }>
	 */
	public function groups() : array {
		return [
			self::GROUP_ADMIN_DASHBOARD => [
				'styles' => [
					'smliser-admin-styles',
					'smliser-tabler-icons',
					'smliser-styles',
					'smliser-form-styles',
					'smliser-modal',
					'smliser-datetime-picker',
					'select2',
                    'smliser-cache-stats'
				],
				'scripts' => [
					'smliser-admin-scripts',
					'smliser-jquery',
					'select2',
					'smliser-datetime-picker',
					'smliser-script',
					'smliser-modal',
					'smliser-tinymce',
					

				],
			],

			self::GROUP_CLIENT_DASHBOARD => [
				'styles' => [
					'smliser-tabler-icons',
					'smliser-styles',
					'smliser-form-styles',
					'smliser-modal',
					'smliser-client-dashboard',
					'smliser-datetime-picker',
					'select2',
				],
				'scripts' => [
					'smliser-jquery',
					'select2',
					'smliser-datetime-picker',
					'smliser-script',
					'smliser-modal',
					'smliser-client-dashboard',
				],
			],

			self::GROUP_EMAIL_EDITOR => [
				'styles' => [
					'smliser-tabler-icons',
					'smliser-styles',
					'smliser-form-styles',
					'smliser-modal',
					'smliser-datetime-picker',
					'smliser-email-editor',
				],
				'scripts' => [
					'smliser-jquery',
					'select2',
					'smliser-datetime-picker',
					'smliser-script',
					'smliser-modal',
					'smliser-email-editor',
				],
			],
		];
	}

	/**
	 * Return the asset definitions required by the standalone email editor page.
	 *
	 * Scripts are ordered so that dependencies come before dependants:
	 *   jquery → smliser-script → smliser-modal → smliser-email-editor
	 *
	 * @return array<string, array<int, array<string, string>>>
	 */
	public function get_email_editor_assets() : array {
		$all_css = CSS::all( self::script_suffix() );
		$all_js  = JS::all( self::script_suffix() );

		$styles = [
			'smliser-tabler-icons',
			'smliser-styles',
			'smliser-form-styles',
			'smliser-modal',
			'smliser-datetime-picker',
			'smliser-email-editor',
		];

		$scripts = [
			'smliser-jquery',
			'select2',
			'smliser-datetime-picker',
			'smliser-script',
			'smliser-modal',
			'smliser-email-editor',
		];

		return [
			'styles'  => array_map(
				fn( $handle ) => [
					'handle' => $handle,
					'url'    => $all_css[ $handle ]['url'],
				],
				$styles
			),
			'scripts' => array_map(
				fn( $handle ) => [
					'handle' => $handle,
					'url'    => $all_js[ $handle ]['url'],
				],
				$scripts
			),
		];
	}

	/**
	 * Get an asset group with resolved dependencies.
	 *
	 * @param string $group
	 * @return array{
	 *     styles: string[],
	 *     scripts: string[]
	 * }
	 */
	public function get_group( string $group ) : array {

		$groups = $this->groups();

		if ( ! isset( $groups[ $group ] ) ) {
			return [
				'styles'  => [],
				'scripts' => [],
			];
		}

		return [
			'styles'  => $this->resolve_styles( $groups[ $group ]['styles'] ),
			'scripts' => $this->resolve_scripts( $groups[ $group ]['scripts'] ),
		];
	}


	/**
	 * Print an asset group.
	 *
	 * @param string $group
	 * @return void
	 */
	public function print_group( string $group ) : void {

		$assets = $this->get_group( $group );

		if ( ! empty( $assets['styles'] ) ) {
			$this->print_styles( ...$assets['styles'] );
		}

		if ( ! empty( $assets['scripts'] ) ) {
			$this->print_scripts( ...$assets['scripts'] );
		}
	}


	/**
	 * Register the default CSS assets.
	 *
	 * Called once, from the constructor. Not intended to be called
	 * again on the same instance: the default handles will already be
	 * registered and register_style() will throw \LogicException on
	 * the duplicate handle.
	 *
	 * @return void
	 */
	private function register_default_styles() : void {
		foreach ( CSS::all( self::script_suffix() ) as $handle => $style ) {
			$this->register_style(
				$handle,
				$style['url'],
				$style['dependencies'],
				$style['version'],
				$style['media-type']
			);
		}
	}

	/**
	 * Register the default JS constants.
	 *
	 * @return void
	 */
	private function register_default_js_constants() : void {
		$this->register_js_constant( 'smliser_var', [
            'ajaxURL'           => adminUrl()->url(),
            'csrf_token'        => '',
            'spinner_gif'       => \assetsUrl( 'images/spinner.gif' )->url(),
            'spinner_gif_2x'    => \assetsUrl( 'images/spinner-2x.gif' )->url(),
            'app_search_api'    => \restAPIUrl( '/repository/' ),
            'default_roles'     => [
                'roles'         => Role::all( true ),
                'capabilities'  => Capability::get_caps()
            ],
            'uploads'            => [
                'max_upload_size'           => smliser_max_upload_size(),
                'max_upload_size_readable'  => Format::bytes( smliser_max_upload_size() ) 
            ]
        ]);
	}


	/**
	 * Register the default JavaScript assets.
	 *
	 * Called once, from the constructor. Not intended to be called
	 * again on the same instance: the default handles will already be
	 * registered and register_script() will throw \LogicException on
	 * the duplicate handle.
	 *
	 * @return void
	 */
	private function register_default_scripts() : void {
		foreach ( JS::all( self::script_suffix() ) as $handle => $script ) {
			$this->register_script(
				$handle,
				$script['url'],
				$script['dependencies'],
				$script['version'],
				$script['footer']
			);
		}
	}


	/**
	 * Validate an asset handle.
	 *
	 * @param string $handle
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	private function validate_handle( string $handle ) : void {

		if ( '' === trim( $handle ) ) {
			throw new \InvalidArgumentException(
				'Asset handle cannot be empty.'
			);
		}

		if ( ! preg_match( '/^[a-z0-9_-]+$/', $handle ) ) {
			throw new \InvalidArgumentException(
				sprintf( 'Invalid asset handle "%s".', $handle )
			);
		}
	}


	/**
	 * Validate an asset category.
	 *
	 * @param string $category
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	private function validate_category( string $category ) : void {

		if ( ! in_array( $category, $this->valid_categories(), true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid asset category "%s". Expected one of: %s.',
					$category,
					implode( ', ', $this->valid_categories() )
				)
			);
		}
	}


	/**
	 * Validate a JS constant name.
	 *
	 * @param string $name
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	private function validate_js_constant_name( string $name ) : void {

		if ( '' === trim( $name ) ) {
			throw new \InvalidArgumentException(
				'JS constant name cannot be empty.'
			);
		}

		if ( ! preg_match( '/^[A-Za-z_$][A-Za-z0-9_$]*$/', $name ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Invalid JS constant name "%s". Must be a valid JavaScript identifier.',
					$name
				)
			);
		}
	}


	/**
	 * Validate asset dependencies.
	 *
	 * Dependency existence is intentionally not checked here so assets may
	 * be registered in any order.
	 *
	 * @param string $handle
	 * @param string[] $dependencies
	 * @return void
	 *
	 * @throws \InvalidArgumentException
	 */
	private function validate_dependencies(
		string $handle,
		array $dependencies
	) : void {

		foreach ( $dependencies as $dependency ) {

			if ( ! is_string( $dependency ) || '' === trim( $dependency ) ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Invalid dependency for asset "%s".',
						$handle
					)
				);
			}

			$this->validate_handle( $dependency );

			if ( $dependency === $handle ) {
				throw new \InvalidArgumentException(
					sprintf(
						'Asset "%s" cannot depend on itself.',
						$handle
					)
				);
			}
		}
	}


	/**
	 * Resolve asset dependencies.
	 *
	 * @param string[] $handles
	 * @param array<string, array<string, mixed>> $registry
	 * @param string $type
	 * @return string[]
	 *
	 * @throws \LogicException
	 */
	private function resolve_dependencies(
		array $handles,
		array $registry,
		string $type
	) : array {

		$resolved = [];
		$stack    = [];

		$resolve = function ( string $handle ) use (
			&$resolve,
			&$resolved,
			&$stack,
			$registry,
			$type
		) : void {

			if ( isset( $resolved[ $handle ] ) ) {
				return;
			}

			if ( isset( $stack[ $handle ] ) ) {
				throw new \LogicException(
					sprintf(
						'Circular %s asset dependency detected involving "%s".',
						$type,
						$handle
					)
				);
			}

			if ( ! isset( $registry[ $handle ] ) ) {
				throw new \LogicException(
					sprintf(
						'Unknown %s asset "%s".',
						$type,
						$handle
					)
				);
			}

			$stack[ $handle ] = true;

			foreach ( $registry[ $handle ]['dependencies'] as $dependency ) {
				$resolve( $dependency );
			}

			$resolved[ $handle ] = true;

			unset( $stack[ $handle ] );
		};

		foreach ( $handles as $handle ) {
			$resolve( $handle );
		}

		return array_keys( $resolved );
	}
}