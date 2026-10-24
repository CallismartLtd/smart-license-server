<?php
/**
 * View class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\URL;

/**
 * What a page handler returns: which page to show, with what data.
 *
 * A page handler never prints and never builds HTML. It returns a View;
 * the active theme renders the View into the Response the router sends.
 * The View says what the page is (template, data, status, title); the
 * theme decides what it looks like.
 *
 * The template is a dot slug of a page template every theme can provide,
 * resolved under the theme's templates/pages/ folder:
 *   'home'       → templates/pages/home.php
 *   'errors.404' → templates/pages/errors/404.php
 *
 * The data is extracted into the page template's scope, and the View
 * itself is available there as $view.
 *
 * A View may name a frame: a wrapper the theme renders around the page,
 * inside the document layout, resolved under templates/frames/
 * ('client-dashboard' → templates/frames/client-dashboard.php). Areas
 * with their own chrome use it (the client dashboard's menus); their
 * pages stay plain content.
 *
 * Assets are not held by the View: add_style(), add_script() and
 * add_js_constant() register on the request's AssetsManager (bound once
 * by the web bootstrapper, see use_assets()) and enqueue there. The
 * AssetsManager prints them where they belong (styles, JS constants and
 * head scripts in <head>; the other scripts before </body>) when the
 * theme calls ThemeContext::head() and ThemeContext::footer().
 */
final class View {

	/**
	 * Core script that posts the forms of themed pages to their JSON actions
	 * (AssetsManager handle; see assets/js/page-forms.js for its markup contract).
	 */
	public const FORMS_SCRIPT = 'page-forms';

	/**
	 * Handle of the file uploader: its script and its style share it.
	 * A page with input[type="file"][data-smliser-uploader] adds both.
	 *
	 * @var string
	 */
	public const UPLOADER_ASSET = 'smliser-uploader';

	/**
	 * A template slug: lowercase dot-separated segments.
	 */
	private const TEMPLATE_PATTERN = '/^[a-z0-9][a-z0-9-]*(?:\.[a-z0-9][a-z0-9-]*)*$/';

	/**
	 * Frame slug; empty for none.
	 *
	 * @var string
	 */
	private string $frame = '';

	/**
	 * Page title, without the site name.
	 *
	 * @var string
	 */
	private string $title = '';

	/**
	 * Page description, for the meta description.
	 *
	 * @var string
	 */
	private string $description = '';

	/**
	 * Extra response headers.
	 *
	 * @var array<string, string>
	 */
	private array $headers = [];

	/**
	 * Gives the request's asset manager, where Views register and enqueue assets.
	 *
	 * @var (\Closure(): AssetsManager)|null
	 */
	private static ?\Closure $assets_resolver = null;

	/**
	 * The request's asset manager, once resolved.
	 *
	 * @var AssetsManager|null
	 */
	private static ?AssetsManager $assets = null;

	/**
	 * Constructor.
	 *
	 * @param string               $template    Page template slug.
	 * @param array<string, mixed> $data        Template data.
	 * @param int                  $status_code HTTP status code.
	 */
	private function __construct(
		private string $template,
		private array $data,
		private int $status_code
	) {}

	/**
	 * Make a View.
	 *
	 * @param string               $template    Page template slug, e.g. 'home' or 'errors.404'.
	 * @param array<string, mixed> $data        Variables for the page template.
	 * @param int                  $status_code HTTP status code.
	 * @return self
	 * @throws \InvalidArgumentException When the template slug or the status code is invalid.
	 */
	public static function make( string $template, array $data = [], int $status_code = 200 ) : self {
		if ( 1 !== preg_match( self::TEMPLATE_PATTERN, $template ) ) {
			throw new \InvalidArgumentException( sprintf( '"%s" is not a valid page template slug: use lowercase dot-separated segments, e.g. "errors.404".', $template ) );
		}

		return ( new self( $template, $data, 200 ) )->set_status_code( $status_code );
	}

	/*
	|---------
	| SETTERS
	|---------
	*/

	/**
	 * Set the HTTP status code.
	 *
	 * @param int $code
	 * @return static
	 * @throws \InvalidArgumentException When the code is not between 100 and 599.
	 */
	public function set_status_code( int $code ) : static {
		if ( $code < 100 || $code > 599 ) {
			throw new \InvalidArgumentException( sprintf( '%d is not an HTTP status code.', $code ) );
		}

		$this->status_code = $code;

		return $this;
	}

	/**
	 * Name the frame the theme renders around the page; empty for none.
	 *
	 * @param string $frame Frame slug, e.g. 'client-dashboard'.
	 * @return static
	 * @throws \InvalidArgumentException When the slug is invalid.
	 */
	public function set_frame( string $frame ) : static {
		if ( '' !== $frame && 1 !== preg_match( self::TEMPLATE_PATTERN, $frame ) ) {
			throw new \InvalidArgumentException( sprintf( '"%s" is not a valid frame slug: use lowercase dot-separated segments.', $frame ) );
		}

		$this->frame = $frame;

		return $this;
	}

	/**
	 * Set the page title, without the site name.
	 *
	 * @param string $title
	 * @return static
	 */
	public function set_title( string $title ) : static {
		$this->title = $title;

		return $this;
	}

	/**
	 * Set the page description.
	 *
	 * @param string $description
	 * @return static
	 */
	public function set_description( string $description ) : static {
		$this->description = $description;

		return $this;
	}

	/**
	 * Set a response header.
	 *
	 * Content-Type is set by the theme renderer and cannot be changed here.
	 *
	 * @param string $name
	 * @param string $value
	 * @return static
	 * @throws \InvalidArgumentException For Content-Type.
	 */
	public function set_header( string $name, string $value ) : static {
		if ( 0 === strcasecmp( $name, 'Content-Type' ) ) {
			throw new \InvalidArgumentException( 'A page is always HTML; Content-Type is set by the theme renderer.' );
		}

		$this->headers[ $name ] = $value;

		return $this;
	}

	/**
	 * Set a variable for the page template.
	 *
	 * @param string $key
	 * @param mixed  $value
	 * @return static
	 */
	public function set( string $key, mixed $value ) : static {
		$this->data[ $key ] = $value;

		return $this;
	}

	/*
	|--------
	| ASSETS
	|--------
	*/

	/**
	 * Bind the request's asset manager; called once by the web bootstrapper.
	 *
	 * The manager is resolved on the first asset a View puts on a page, so
	 * requests that never do (REST, files, admin actions) never build it.
	 *
	 * @param \Closure(): AssetsManager $resolver
	 * @return void
	 */
	public static function use_assets( \Closure $resolver ) : void {
		self::$assets_resolver = $resolver;
		self::$assets          = null;
	}

	/**
	 * Put a style on the page: enqueue a registered handle, or register a
	 * new one first when a URL is given.
	 *
	 * @param string   $handle       Style handle.
	 * @param URL|null $url          The stylesheet URL, to register a new handle; null for a registered one.
	 * @param string[] $dependencies Style handles it needs (new handle only).
	 * @param string   $version      Version, for caching (new handle only).
	 * @param string   $media        Media type (new handle only).
	 * @return static
	 * @throws \LogicException When no asset manager is bound, or the handle is unknown or already registered.
	 */
	public function add_style( string $handle, ?URL $url = null, array $dependencies = [], string $version = '', string $media = 'all' ) : static {
		$assets = self::assets();

		if ( null !== $url ) {
			$assets->register_style( $handle, $url, $dependencies, $version, $media );
		}

		$assets->enqueue_style( $handle );

		return $this;
	}

	/**
	 * Put a script on the page: enqueue a registered handle, or register a
	 * new one first when a URL is given.
	 *
	 * A script registered for the footer is printed before </body>; one
	 * registered for the head (footer: false) in <head>, after the JS
	 * constants.
	 *
	 * @param string   $handle       Script handle.
	 * @param URL|null $url          The script URL, to register a new handle; null for a registered one.
	 * @param string[] $dependencies Script handles it needs (new handle only).
	 * @param string   $version      Version, for caching (new handle only).
	 * @param bool     $footer       Print before </body> rather than in <head> (new handle only).
	 * @return static
	 * @throws \LogicException When no asset manager is bound, or the handle is unknown or already registered.
	 */
	public function add_script( string $handle, ?URL $url = null, array $dependencies = [], string $version = '', bool $footer = true ) : static {
		$assets = self::assets();

		if ( null !== $url ) {
			$assets->register_script( $handle, $url, $dependencies, $version, $footer );
		}

		$assets->enqueue_script( $handle );

		return $this;
	}

	/**
	 * Expose data to scripts as a global constant: `const NAME = {...};`,
	 * printed in <head> before every script.
	 *
	 * @param string               $name A valid JavaScript identifier.
	 * @param array<string, mixed> $data
	 * @return static
	 * @throws \LogicException When no asset manager is bound, or the name is already registered.
	 */
	public function add_js_constant( string $name, array $data ) : static {
		self::assets()->register_js_constant( $name, $data );

		return $this;
	}

	/**
	 * The bound asset manager.
	 *
	 * @return AssetsManager
	 * @throws \LogicException When none is bound.
	 */
	private static function assets() : AssetsManager {
		if ( null === self::$assets_resolver ) {
			throw new \LogicException( 'No asset manager is bound to views: View::use_assets() is called by the web bootstrapper.' );
		}

		return self::$assets ??= ( self::$assets_resolver )();
	}

	/*
	|---------
	| GETTERS
	|---------
	*/

	/**
	 * The page template slug.
	 *
	 * @return string
	 */
	public function get_template() : string {
		return $this->template;
	}

	/**
	 * Every template variable.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data() : array {
		return $this->data;
	}

	/**
	 * One template variable.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get( string $key, mixed $default = null ) : mixed {
		return array_key_exists( $key, $this->data ) ? $this->data[ $key ] : $default;
	}

	/**
	 * The HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code() : int {
		return $this->status_code;
	}

	/**
	 * The frame slug; empty for none.
	 *
	 * @return string
	 */
	public function get_frame() : string {
		return $this->frame;
	}

	/**
	 * The page title, without the site name; empty for none.
	 *
	 * @return string
	 */
	public function get_title() : string {
		return $this->title;
	}

	/**
	 * The page description; empty for none.
	 *
	 * @return string
	 */
	public function get_description() : string {
		return $this->description;
	}

	/**
	 * Extra response headers.
	 *
	 * @return array<string, string>
	 */
	public function get_headers() : array {
		return $this->headers;
	}
}
