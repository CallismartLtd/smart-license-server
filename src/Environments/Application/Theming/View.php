<?php
/**
 * View class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

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
 */
final class View {

	/**
	 * A template slug: lowercase dot-separated segments.
	 */
	private const TEMPLATE_PATTERN = '/^[a-z0-9][a-z0-9-]*(?:\.[a-z0-9][a-z0-9-]*)*$/';

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
	 * Registered style handles the page needs (AssetsManager).
	 *
	 * @var string[]
	 */
	private array $styles = [];

	/**
	 * Registered script handles the page needs (AssetsManager).
	 *
	 * @var string[]
	 */
	private array $scripts = [];

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

	/**
	 * Ask for registered styles; the theme prints them in the head.
	 *
	 * @param string ...$handles AssetsManager style handles.
	 * @return static
	 */
	public function add_style( string ...$handles ) : static {
		$this->styles = array_values( array_unique( [ ...$this->styles, ...$handles ] ) );

		return $this;
	}

	/**
	 * Ask for registered scripts; the theme prints them before </body>.
	 *
	 * @param string ...$handles AssetsManager script handles.
	 * @return static
	 */
	public function add_script( string ...$handles ) : static {
		$this->scripts = array_values( array_unique( [ ...$this->scripts, ...$handles ] ) );

		return $this;
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

	/**
	 * Style handles the page asked for.
	 *
	 * @return string[]
	 */
	public function get_styles() : array {
		return $this->styles;
	}

	/**
	 * Script handles the page asked for.
	 *
	 * @return string[]
	 */
	public function get_scripts() : array {
		return $this->scripts;
	}
}