<?php
/**
 * Theme context class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\Context\Principal;

/**
 * $this inside every theme template: renders one View with one theme chain.
 *
 * The template contract (every theme may provide, the default theme always does):
 *
 *   templates/layout.php        The document: <html> to </html>.
 *                               Variables: $content (the page's HTML), $view.
 *                               Must call $this->head() inside <head> and
 *                               $this->footer() before </body>.
 *   templates/pages/<slug>.php  One per page template (View::get_template()).
 *                               Variables: the View's data, and $view.
 *   templates/frames/<slug>.php When the View names a frame (View::set_frame()):
 *                               a wrapper around the page, inside the layout.
 *                               Variables: $content (the page's HTML), the
 *                               View's data, and $view.
 *   templates/partials/<name>.php
 *                               Pieces included with $this->partial().
 *                               Variables: the data passed, and $view.
 *
 * A template, partial or asset is taken from the first theme in the chain
 * that has it (the theme, its parent, the default theme), so a theme only
 * ships what it changes.
 *
 * Templates print; the output is captured and becomes the Response body.
 * Escape with escHtml(), escAttr() and escUrl().
 */
final class ThemeContext {

	/**
	 * A partial name: lowercase dot-separated segments.
	 */
	private const PARTIAL_PATTERN = '/^[a-z0-9][a-z0-9_-]*(?:\.[a-z0-9][a-z0-9_-]*)*$/';

	/**
	 * Constructor.
	 *
	 * @param Theme[]       $chain   The theme chain, most specific first.
	 * @param View          $view    The page to render.
	 * @param Request       $request The current request.
	 * @param URLManager    $urls    URL API.
	 * @param Guard         $guard   The signed-in principal, if any.
	 * @param AssetsManager $assets  Registered styles and scripts.
	 * @param FileSystem    $fs      Filesystem API.
	 */
	public function __construct(
		private array $chain,
		private View $view,
		private Request $request,
		private URLManager $urls,
		private Guard $guard,
		private AssetsManager $assets,
		private FileSystem $fs
	) {
		if ( [] === $chain ) {
			throw new \InvalidArgumentException( 'A theme chain needs at least one theme.' );
		}
	}

	/*
	|-----------
	| RENDERING
	|-----------
	*/

	/**
	 * Render the page (into its frame, if any) into the layout and return the document.
	 *
	 * Called once by ThemeRenderer; not for templates.
	 *
	 * @return string
	 * @throws ThemeException When the page template, the frame or the layout is missing.
	 */
	public function render_document() : string {
		$page    = $this->locate( 'pages/' . str_replace( '.', '/', $this->view->get_template() ) . '.php' );
		$content = $this->capture( $page, $this->view->get_data() );

		if ( '' !== $this->view->get_frame() ) {
			$frame   = $this->locate( 'frames/' . str_replace( '.', '/', $this->view->get_frame() ) . '.php' );
			$content = $this->capture( $frame, [ 'content' => $content ] + $this->view->get_data() );
		}

		return $this->capture( $this->locate( 'layout.php' ), [ 'content' => $content ] );
	}

	/**
	 * Print a partial (templates/partials/<name>.php; dots for subfolders).
	 *
	 * @param string               $name e.g. 'header' or 'docs.route-card'.
	 * @param array<string, mixed> $data Variables for the partial.
	 * @return void
	 * @throws ThemeException When no theme in the chain has the partial.
	 */
	public function partial( string $name, array $data = [] ) : void {
		if ( 1 !== preg_match( self::PARTIAL_PATTERN, $name ) ) {
			throw new ThemeException( sprintf( '"%s" is not a valid partial name.', $name ) );
		}

		echo $this->capture( $this->locate( 'partials/' . str_replace( '.', '/', $name ) . '.php' ), $data ); // phpcs:ignore -- template output.
	}

	/**
	 * Print what core puts in <head>: title, description, the page's styles.
	 *
	 * @return void
	 */
	public function head() : void {
		printf( "<title>%s</title>\n", \escHtml( $this->title() ) );

		if ( '' !== $this->view->get_description() ) {
			printf( "<meta name=\"description\" content=\"%s\">\n", \escAttr( $this->view->get_description() ) );
		}

		if ( [] !== $this->view->get_styles() ) {
			$this->assets->print_styles( ...$this->view->get_styles() );
		}
	}

	/**
	 * Print what core puts before </body>: the page's scripts.
	 *
	 * The registered JS constants (smliser_var: CSRF token and header,
	 * URLs) are printed first, because core scripts read them.
	 *
	 * @return void
	 */
	public function footer() : void {
		if ( [] === $this->view->get_scripts() ) {
			return;
		}

		$this->assets->print_js_constants();
		$this->assets->print_scripts( ...$this->view->get_scripts() );
	}

	/*
	|-------------------
	| TEMPLATE HELPERS
	|-------------------
	*/

	/**
	 * The page being rendered.
	 *
	 * @return View
	 */
	public function view() : View {
		return $this->view;
	}

	/**
	 * The theme rendering the page (the first in the chain).
	 *
	 * @return Theme
	 */
	public function theme() : Theme {
		return $this->chain[0];
	}

	/**
	 * The current request.
	 *
	 * @return Request
	 */
	public function request() : Request {
		return $this->request;
	}

	/**
	 * The document title: "<page title> — <site name>", or the site name.
	 *
	 * @return string
	 */
	public function title() : string {
		$title = $this->view->get_title();

		return '' === $title ? \SMLISER_APP_NAME : sprintf( '%s — %s', $title, \SMLISER_APP_NAME );
	}

	/**
	 * A URL of this site.
	 *
	 * @param string               $path
	 * @param array<string, mixed> $query
	 * @return URL
	 */
	public function url( string $path = '', array $query = [] ) : URL {
		return $this->urls->url( $path, $query );
	}

	/**
	 * The URL API, for the site's other URLs (sign-in, client dashboard...).
	 *
	 * @return URLManager
	 */
	public function urls() : URLManager {
		return $this->urls;
	}

	/**
	 * The signed-in principal; null for a visitor.
	 *
	 * @return Principal|null
	 */
	public function principal() : ?Principal {
		return $this->guard->get_principal();
	}

	/**
	 * The URL of a theme asset (assets/<path>), versioned for caching.
	 *
	 * Taken from the first theme in the chain that has the file, and served
	 * from public/themes/<slug>/ by the web server.
	 *
	 * @param string $path Path inside assets/, e.g. 'css/theme.css'.
	 * @return URL
	 * @throws ThemeException When the path is invalid or no theme in the chain has the file.
	 */
	public function asset_url( string $path ) : URL {
		$path = ltrim( $path, '/' );

		if ( '' === $path || str_contains( $path, '\\' ) || in_array( '..', explode( '/', $path ), true ) ) {
			throw new ThemeException( sprintf( '"%s" is not a valid theme asset path.', $path ) );
		}

		foreach ( $this->chain as $theme ) {
			if ( $this->fs->is_file( $theme->assets_dir() . '/' . $path ) ) {
				return $this->urls->themes_url( $theme->slug . '/' . $path, [ 'version' => $theme->version ] );
			}
		}

		throw new ThemeException( sprintf( 'No theme in the chain of "%s" has the asset "%s".', $this->theme()->slug, $path ) );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * The file of a template, from the first theme in the chain that has it.
	 *
	 * @param string $relative Path inside templates/.
	 * @return string
	 * @throws ThemeException
	 */
	private function locate( string $relative ) : string {
		foreach ( $this->chain as $theme ) {
			$file = $theme->templates_dir() . '/' . $relative;

			if ( $this->fs->is_file( $file ) ) {
				return $file;
			}
		}

		throw new ThemeException( sprintf( 'No theme in the chain of "%s" has the template "%s".', $this->theme()->slug, $relative ) );
	}

	/**
	 * Run a template and return what it printed.
	 *
	 * Output buffers the template opens and leaves open are closed, so a
	 * broken template cannot leak output into the response.
	 *
	 * @param string               $file
	 * @param array<string, mixed> $data
	 * @return string
	 */
	private function capture( string $file, array $data ) : string {
		$level = ob_get_level();
		ob_start();

		try {
			$this->load( $file, $data + [ 'view' => $this->view ] );
		} finally {
			$output = '';

			while ( ob_get_level() > $level ) {
				$output = (string) ob_get_clean() . $output;
			}
		}

		return $output;
	}

	/**
	 * Include a template with its variables; $this is this context.
	 *
	 * @param string               $__file
	 * @param array<string, mixed> $__data
	 * @return void
	 */
	private function load( string $__file, array $__data ) : void {
		extract( $__data, EXTR_SKIP ); // phpcs:ignore
		require $__file;
	}
}