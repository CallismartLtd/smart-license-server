<?php
/**
 * Theme renderer class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Theming
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Theming;

use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\Security\Context\Guard;
use Throwable;

/**
 * Turns a View into the Response the router sends.
 *
 * The active theme renders the page. When it cannot (a missing template,
 * a template that throws, a broken parent), the error is logged and the
 * page is rendered again with the default theme alone, so a broken theme
 * never leaves a visitor with a blank page. Only a failure of the default
 * theme itself propagates. The AssetsManager's page state (what is
 * enqueued and printed) is rolled back before the second render, so the
 * default theme prints the page's assets, and none of the failed theme's.
 *
 * Built only when a page is answered; requests for anything else never
 * construct it.
 */
final class ThemeRenderer {

	/**
	 * Constructor.
	 *
	 * @param ThemeManager  $themes Finds the active theme and its chain.
	 * @param URLManager    $urls   URL API.
	 * @param Guard         $guard  The signed-in principal, if any.
	 * @param AssetsManager $assets Registered styles and scripts.
	 * @param FileSystem    $fs     Filesystem API.
	 */
	public function __construct(
		private ThemeManager $themes,
		private URLManager $urls,
		private Guard $guard,
		private AssetsManager $assets,
		private FileSystem $fs
	) {}

	/**
	 * Render a View with the active theme.
	 *
	 * @param View    $view
	 * @param Request $request
	 * @return Response An HTML response with the View's status code and headers.
	 * @throws Throwable When the default theme cannot render the page.
	 */
	public function render( View $view, Request $request ) : Response {
		$theme      = $this->themes->active();
		$checkpoint = $this->assets->checkpoint();

		try {
			$html = $this->render_with( $this->themes->chain( $theme ), $view, $request );
		} catch ( Throwable $e ) {
			if ( ThemeManager::DEFAULT_SLUG === $theme->slug ) {
				throw $e;
			}

			\smliser_log_error( sprintf( '[Theme] The theme "%s" could not render the page "%s"; used the default theme: %s', $theme->slug, $view->get_template(), $e->getMessage() ) );

			// The failed render's output is thrown away: so is what it enqueued and printed.
			$this->assets->rollback( $checkpoint );

			$html = $this->render_with( [ $this->themes->default_theme() ], $view, $request );
		}

		$response = Response::make( $html, $view->get_status_code() )
			->set_header( 'Content-Type', 'text/html; charset=UTF-8' );

		foreach ( $view->get_headers() as $name => $value ) {
			$response->set_header( $name, $value );
		}

		return $response;
	}

	/**
	 * Render a View with a theme chain.
	 *
	 * @param Theme[] $chain
	 * @param View    $view
	 * @param Request $request
	 * @return string
	 */
	private function render_with( array $chain, View $view, Request $request ) : string {
		return ( new ThemeContext( $chain, $view, $request, $this->urls, $this->guard, $this->assets, $this->fs ) )->render_document();
	}
}