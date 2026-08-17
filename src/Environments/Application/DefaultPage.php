<?php
/**
 * Default Application Page Renderer file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application
 * @since   0.2.0
 */


declare( strict_types = 1 );


namespace SmartLicenseServer\Environments\Application;


use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\RESTAPI\RouteCatalog;


/**
 * Builds and renders the application's default HTML pages.
 */
final class DefaultPage {


	/**
	 * HTTP response status code.
	 *
	 * @var int
	 */
	private int $status;


	/**
	 * Page title.
	 *
	 * @var string
	 */
	private string $title;


	/**
	 * Page heading.
	 *
	 * @var string
	 */
	private string $heading;


	/**
	 * Page description.
	 *
	 * @var string
	 */
	private string $description;


	/**
	 * Page content.
	 *
	 * @var string
	 */
	private string $content;


	/**
	 * Constructor.
	 *
	 * @param int    $status      HTTP response status code.
	 * @param string $title       HTML document title.
	 * @param string $heading     Main page heading.
	 * @param string $description Page description.
	 * @param string $content     Main page content.
	 */
	private function __construct(
		int $status,
		string $title,
		string $heading,
		string $description,
		string $content
	) {
		$this->status      = $status;
		$this->title       = $title;
		$this->heading     = $heading;
		$this->description = $description;
		$this->content     = $content;
	}


	/**
	 * Render and return the complete HTTP Response object.
	 *
	 * @return Response
	 */
	public function render(): Response {
		$html = sprintf(
			'<!DOCTYPE html><html lang="en">%s%s</html>',
			$this->render_head(),
			$this->render_body()
		);


		return Response::make( $html, $this->status )
			->set_header( 'Content-Type', 'text/html; charset=UTF-8' );
	}


	/**
	 * Render HTML head with embedded styles.
	 *
	 * @return string
	 */
	private function render_head(): string {
		return sprintf(
			'
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<title>%s</title>

				<style>
					:root {
						--bg: #090d16;
						--card-bg: rgba(15, 23, 42, 0.75);
						--border: rgba(255, 255, 255, 0.08);
						--text-main: #f8fafc;
						--text-muted: #94a3b8;
						--accent: #6366f1;
						--accent-glow: rgba(99, 102, 241, 0.18);
						--get: #10b981;
						--post: #6366f1;
						--put: #f59e0b;
						--patch: #f59e0b;
						--delete: #ef4444;
					}

					* {
						box-sizing: border-box;
						margin: 0;
						padding: 0;
					}

					body {
						font-family:
							-apple-system,
							BlinkMacSystemFont,
							"Segoe UI",
							Roboto,
							Oxygen,
							Ubuntu,
							Cantarell,
							sans-serif;
						background-color: var(--bg);
						color: var(--text-main);
						min-height: 100vh;
						display: flex;
						flex-direction: column;
						justify-content: space-between;
						line-height: 1.6;
						overflow-x: hidden;
					}

					.glow-bg {
						position: absolute;
						top: -180px;
						left: 50%%;
						transform: translateX(-50%%);
						width: 700px;
						height: 700px;
						background: radial-gradient(
							circle,
							var(--accent-glow) 0%%,
							transparent 70%%
						);
						pointer-events: none;
						z-index: 0;
					}

					.container {
						width: 100%%;
						max-width: 1150px;
						margin: 0 auto;
						padding: 2rem 1.5rem 3.5rem;
						z-index: 1;
					}

					nav {
						display: flex;
						align-items: center;
						justify-content: space-between;
						gap: 2rem;
						padding: 0.75rem 0;
						margin-bottom: 4rem;
						border-bottom: 1px solid var(--border);
					}

					.brand {
						color: var(--text-main);
						text-decoration: none;
						font-weight: 700;
						letter-spacing: -0.02em;
					}

					.nav-links {
						display: flex;
						align-items: center;
						gap: 1.5rem;
					}

					.nav-links a {
						color: var(--text-muted);
						text-decoration: none;
						font-size: 0.9rem;
						transition: color 0.2s ease;
					}

					.nav-links a:hover {
						color: var(--text-main);
					}

					header {
						text-align: center;
						margin-bottom: 4rem;
					}

					.badge {
						display: inline-flex;
						align-items: center;
						gap: 0.5rem;
						padding: 0.4rem 0.9rem;
						background: rgba(99, 102, 241, 0.12);
						border: 1px solid rgba(99, 102, 241, 0.3);
						border-radius: 9999px;
						color: #a5b4fc;
						font-size: 0.85rem;
						font-weight: 600;
						margin-bottom: 1.5rem;
					}

					.status-dot {
						width: 8px;
						height: 8px;
						background-color: #10b981;
						border-radius: 50%%;
						box-shadow: 0 0 10px #10b981;
					}

					h1 {
						font-size: clamp(2.5rem, 5vw, 4rem);
						font-weight: 800;
						letter-spacing: -0.025em;
						line-height: 1.1;
						margin-bottom: 1.25rem;
						background: linear-gradient(
							180deg,
							#ffffff 0%%,
							#cbd5e1 100%%
						);
						-webkit-background-clip: text;
						-webkit-text-fill-color: transparent;
					}

					header p {
						font-size: 1.2rem;
						color: var(--text-muted);
						max-width: 720px;
						margin: 0 auto;
					}

					.content {
						width: 100%%;
					}

					.grid {
						display: grid;
						grid-template-columns:
							repeat(auto-fit, minmax(320px, 1fr));
						gap: 1.5rem;
					}

					.card {
						background: var(--card-bg);
						border: 1px solid var(--border);
						border-radius: 12px;
						padding: 2rem;
						backdrop-filter: blur(12px);
						transition: all 0.2s ease-in-out;
					}

					.card:hover {
						border-color: rgba(99, 102, 241, 0.4);
						transform: translateY(-2px);
						box-shadow:
							0 12px 30px -10px rgba(0, 0, 0, 0.6);
					}

					.card h3 {
						font-size: 1.25rem;
						font-weight: 600;
						margin-bottom: 0.75rem;
						color: #fff;
					}

					.card p {
						color: var(--text-muted);
						font-size: 0.95rem;
					}

					.code-block {
						margin-top: 1.5rem;
						background: #030712;
						border: 1px solid var(--border);
						border-radius: 6px;
						padding: 0.65rem 0.85rem;
						font-family:
							ui-monospace,
							SFMono-Regular,
							Menlo,
							Monaco,
							Consolas,
							monospace;
						font-size: 0.85rem;
						color: #38bdf8;
					}

					.message {
						max-width: 700px;
						margin: 0 auto;
						text-align: center;
					}

					footer {
						text-align: center;
						padding: 2rem;
						color: var(--text-muted);
						font-size: 0.875rem;
						border-top: 1px solid var(--border);
						width: 100%%;
						z-index: 1;
					}

					footer strong {
						color: var(--text-main);
					}

					footer a {
						color: #818cf8;
						text-decoration: none;
					}

					footer a:hover {
						text-decoration: underline;
					}

					/* Documentation page */

					.api-breadcrumb {
						display: flex;
						align-items: center;
						gap: 0.5rem;
						color: var(--text-muted);
						font-size: 0.85rem;
						margin-bottom: 1.25rem;
					}

					.api-breadcrumb a {
						color: var(--text-muted);
						text-decoration: none;
					}

					.api-breadcrumb a:hover {
						color: var(--text-main);
					}

					.api-breadcrumb-sep {
						color: var(--border);
					}

					.api-category-nav {
						display: flex;
						flex-wrap: wrap;
						gap: 0.5rem;
						margin-bottom: 2.5rem;
						padding-bottom: 1.5rem;
						border-bottom: 1px solid var(--border);
					}

					.api-category-nav a {
						color: var(--text-muted);
						text-decoration: none;
						font-size: 0.85rem;
						padding: 0.35rem 0.85rem;
						border-radius: 9999px;
						border: 1px solid var(--border);
						transition: all 0.2s ease;
					}

					.api-category-nav a:hover {
						color: var(--text-main);
						border-color: rgba(99, 102, 241, 0.4);
					}

					.api-category-nav a.active {
						color: #a5b4fc;
						background: rgba(99, 102, 241, 0.12);
						border-color: rgba(99, 102, 241, 0.3);
					}

					.api-version-section {
						margin-bottom: 3.5rem;
					}

					.api-version-section > h2 {
						font-size: 1.5rem;
						margin-bottom: 1.5rem;
						padding-bottom: 0.75rem;
						border-bottom: 1px solid var(--border);
					}

					.api-category-section {
						margin-bottom: 2.5rem;
					}

					.api-category-section > h3 {
						font-size: 1.05rem;
						color: var(--text-muted);
						text-transform: uppercase;
						letter-spacing: 0.05em;
						margin-bottom: 1rem;
					}

					.api-route-card {
						grid-column: 1 / -1;
					}

					.api-route-path-container {
						display: flex;
						align-items: center;
						justify-content: space-between;
						gap: 1rem;
						margin-bottom: 1rem;
					}

					.api-route-path {
						font-family:
							ui-monospace,
							SFMono-Regular,
							Menlo,
							Monaco,
							Consolas,
							monospace;
						font-size: 0.95rem;
						color: #38bdf8;
						word-break: break-all;
					}

					.api-copy-btn {
						flex-shrink: 0;
						background: transparent;
						border: 1px solid var(--border);
						color: var(--text-muted);
						border-radius: 6px;
						padding: 0.3rem 0.7rem;
						font-size: 0.8rem;
						cursor: pointer;
						transition: all 0.2s ease;
					}

					.api-copy-btn:hover {
						border-color: var(--accent);
						color: var(--text-main);
					}

					.api-method-block {
						padding: 1rem 0;
						border-top: 1px solid var(--border);
					}

					.api-method-block:first-of-type {
						border-top: none;
						padding-top: 0;
					}

					.api-method-block h4 {
						display: flex;
						align-items: center;
						gap: 0.6rem;
						font-size: 1rem;
						font-weight: 600;
						margin-bottom: 0.75rem;
					}

					.api-method-badge {
						display: inline-block;
						font-size: 0.7rem;
						font-weight: 700;
						letter-spacing: 0.03em;
						padding: 0.2rem 0.55rem;
						border-radius: 4px;
						color: #030712;
					}

					.api-method-get { background: var(--get); }
					.api-method-post { background: var(--post); color: #fff; }
					.api-method-put,
					.api-method-patch { background: var(--put); }
					.api-method-delete { background: var(--delete); color: #fff; }

					.api-no-args {
						color: var(--text-muted);
						font-size: 0.85rem;
						font-style: italic;
					}

					.api-argument {
						padding: 0.75rem 0;
						border-top: 1px solid var(--border);
					}

					.api-argument:first-child {
						border-top: none;
						padding-top: 0;
					}

					.api-argument-header {
						display: flex;
						flex-wrap: wrap;
						align-items: center;
						gap: 0.5rem;
						margin-bottom: 0.35rem;
					}

					.api-argument-name {
						font-family:
							ui-monospace,
							SFMono-Regular,
							Menlo,
							Monaco,
							Consolas,
							monospace;
						font-weight: 600;
					}

					.api-argument-type,
					.api-argument-required,
					.api-argument-optional,
					.api-argument-default {
						font-size: 0.75rem;
						padding: 0.15rem 0.5rem;
						border-radius: 4px;
						background: rgba(255, 255, 255, 0.06);
						color: var(--text-muted);
					}

					.api-argument-required {
						background: rgba(239, 68, 68, 0.15);
						color: #fca5a5;
					}

					.api-argument-optional {
						background: rgba(16, 185, 129, 0.12);
						color: #6ee7b7;
					}

					.api-argument-description {
						color: var(--text-muted);
						font-size: 0.88rem;
					}

					.api-empty-state {
						color: var(--text-muted);
						text-align: center;
						padding: 2rem;
					}

					@media (max-width: 640px) {
						nav {
							align-items: flex-start;
							flex-direction: column;
							gap: 1rem;
						}

						.nav-links {
							gap: 1rem;
						}

						.api-route-path-container {
							flex-direction: column;
							align-items: flex-start;
						}
					}
				</style>
			</head>',
			htmlspecialchars( $this->title, ENT_QUOTES, 'UTF-8' )
		);
	}


	/**
	 * Render HTML body section.
	 *
	 * @return string
	 */
	private function render_body(): string {
		return sprintf(
			'<body>
				<div class="glow-bg"></div>

				<main class="container">
					%s
					%s
					%s
				</main>

				%s
			</body>',
			$this->render_navigation(),
			$this->render_header(),
			$this->render_content(),
			$this->render_footer()
		);
	}


	/**
	 * Render navigation.
	 *
	 * @return string
	 */
	private function render_navigation(): string {
		return '
		<nav>
			<a class="brand" href="/">Smart License Server</a>

			<div class="nav-links">
				<a href="/">Home</a>
				<a href="/documentation/">Documentation</a>
			</div>
		</nav>';
	}


	/**
	 * Render main page header.
	 *
	 * @return string
	 */
	private function render_header(): string {
		return sprintf(
			'
			<header>
				<div class="badge">
					<span class="status-dot"></span>
					Smart License Server Engine Active
				</div>

				<h1>%s</h1>

				<p>%s</p>
			</header>',
			htmlspecialchars( $this->heading, ENT_QUOTES, 'UTF-8' ),
			htmlspecialchars( $this->description, ENT_QUOTES, 'UTF-8' )
		);
	}


	/**
	 * Render page content.
	 *
	 * @return string
	 */
	private function render_content(): string {
		return sprintf(
			'<section class="content">%s</section>',
			$this->content
		);
	}


	/**
	 * Render footer element.
	 *
	 * @return string
	 */
	private function render_footer(): string {
		return sprintf(
			'<footer>
				Smart License Server &copy; %s —
				Developed by <strong>Callistus Nwachukwu</strong>
				(<a href="mailto:admin@callismart.com.ng">
					admin@callismart.com.ng
				</a>)
			</footer>',
			date( 'Y' )
		);
	}


	/**
	 * Render the application home page.
	 *
	 * @return Response
	 */
	public static function home(): Response {
		$php_version = \PHP_VERSION;


		$content = sprintf(
			'
			<div class="grid">
				<div class="card">
					<h3>CLI Control Engine</h3>

					<p>
						Manage applications, licenses, and environment setup
						directly from your terminal using the global runner.
					</p>

					<div class="code-block">
						<code>smliser status</code>
					</div>
				</div>

				<div class="card">
					<h3>DBPrism Abstraction</h3>

					<p>
						Multi-adapter database abstraction layer supporting
						MySQL, MariaDB, PostgreSQL, and SQLite seamlessly.
					</p>

					<div class="code-block">
						<code>callismart/dbprism v0.2.0</code>
					</div>
				</div>

				<div class="card">
					<h3>Runtime Environment</h3>

					<p>
						Strict PHP 8.4+ execution engine utilizing Callismart
						HTTP/DTO and CommonMark Markdown parsing.
					</p>

					<div class="code-block">
						<code>PHP v%s</code>
					</div>
				</div>
			</div>',
			htmlspecialchars( $php_version, ENT_QUOTES, 'UTF-8' )
		);


		return ( new static(
			200,
			'Smart License Server — Operational',
			'Smart License Server',
			'A database-agnostic application for privately hosting, licensing, updating, and distributing custom software, WordPress plugins, and themes.',
			$content
		) )->render();
	}


	/**
	 * Render the HTTP 404 Not Found page.
	 *
	 * @return Response
	 */
	public static function not_found( Request $request ): Response {
		if ( $request->wantsJson() ) {
			$message	= 'The requested resource could not be found.';
			smliser_send_json(
				[
					'error'	=> 'Not found',
					'message'	=> $message,
					'data'	=> [
						'message'	=> $message
					]
				],
				404
			);
		}

		$content = '
			<div class="message">
				<div class="card">
					<h3>Page Not Found</h3>

					<p>
						The requested resource could not be found.
						Check the URL and try again.
					</p>
				</div>
			</div>';


		return ( new static(
			404,
			'404 — Page Not Found',
			'Page Not Found',
			'The resource you requested does not exist.',
			$content
		) )->render();
	}


	/**
	 * Render the HTTP 405 Method Not Allowed page.
	 *
	 * @return Response
	 */
	public static function method_not_allowed(): Response {
		$content = '
			<div class="message">
				<div class="card">
					<h3>Method Not Allowed</h3>

					<p>
						The HTTP method used for this resource is not supported.
					</p>
				</div>
			</div>';


		return ( new static(
			405,
			'405 — Method Not Allowed',
			'Method Not Allowed',
			'The requested resource does not support this HTTP method.',
			$content
		) )->render();
	}


	/**
	 * Render the REST API documentation page.
	 *
	 * The router calls this with only the current Request — no catalog is
	 * handed in, so every registered API version is resolved here via the
	 * environment provider, and one RouteCatalog is built per version on
	 * the fly. The optional `category` route param (e.g. a route registered
	 * as `documentation/{category}`, matching URLs like
	 * `/documentation/repository/`) narrows the page down to just that
	 * category, across every version, instead of listing everything.
	 *
	 * @param Request $request Current request, used to read the optional
	 *                         `category` route param.
	 * @return Response
	 */
	public static function doc_page( Request $request ): Response {
		$versions = \smliser_envProvider()->restProvider()->version_instances();

		$catalogs = [];
		foreach ( $versions as $version ) {
			$catalogs[ $version->namespace() ] = new RouteCatalog( $version );
		}

		$category = $request->route_param( 'category' );
		$category = is_string( $category ) && '' !== $category ? $category : null;

		$content = self::render_breadcrumb( $category )
			. self::render_category_nav( $catalogs, $category )
			. self::render_catalogs( $catalogs, $category );

		$heading     = 'API Documentation';
		$description = 'Available REST API routes, grouped by version and category.';

		if ( null !== $category ) {
			$label       = self::humanize_category( $category );
			$heading     = sprintf( 'API Documentation — %s', $label );
			$description = sprintf( 'Routes in the "%s" category.', $label );
		}

		return ( new static(
			200,
			'Documentation',
			$heading,
			$description,
			$content
		) )->render();
	}


	/**
	 * Render the "Documentation / Category" breadcrumb trail.
	 *
	 * @param string|null $category Active category key, or null on the unfiltered index.
	 * @return string
	 */
	private static function render_breadcrumb( ?string $category ): string {
		if ( null === $category ) {
			return '<nav class="api-breadcrumb"><span>Documentation</span></nav>';
		}

		return sprintf(
			'<nav class="api-breadcrumb"><a href="/documentation/">Documentation</a><span class="api-breadcrumb-sep">/</span><span>%s</span></nav>',
			htmlspecialchars( self::humanize_category( $category ), ENT_QUOTES, 'UTF-8' )
		);
	}


	/**
	 * Render the category navigation strip: an "All" link back to the
	 * unfiltered index plus one link per distinct category found across
	 * every version's catalog, so a reader can jump straight to a category
	 * without knowing its URL in advance.
	 *
	 * @param array<string, RouteCatalog> $catalogs Route catalogs keyed by version namespace.
	 * @param string|null                 $active   Currently active category key, if any.
	 * @return string
	 */
	private static function render_category_nav( array $catalogs, ?string $active ): string {
		$categories = [];

		foreach ( $catalogs as $catalog ) {
			foreach ( $catalog->categories() as $category ) {
				$categories[ $category ] = true;
			}
		}

		$categories = array_keys( $categories );

		if ( empty( $categories ) ) {
			return '';
		}

		sort( $categories );

		$links = sprintf(
			'<a href="/documentation/"%s>All</a>',
			null === $active ? ' class="active"' : ''
		);

		foreach ( $categories as $category ) {
			$links .= sprintf(
				'<a href="/documentation/%s/"%s>%s</a>',
				rawurlencode( $category ),
				$category === $active ? ' class="active"' : '',
				htmlspecialchars( self::humanize_category( $category ), ENT_QUOTES, 'UTF-8' )
			);
		}

		return sprintf( '<nav class="api-category-nav">%s</nav>', $links );
	}


	/**
	 * Render every version's section, optionally narrowed to a single category.
	 *
	 * @param array<string, RouteCatalog> $catalogs Route catalogs keyed by version namespace.
	 * @param string|null                 $category Category key to filter by, or null for all.
	 * @return string
	 */
	private static function render_catalogs( array $catalogs, ?string $category ): string {
		$sections = '';

		foreach ( $catalogs as $namespace => $catalog ) {
			$sections .= self::render_version_section( (string) $namespace, $catalog, $category );
		}

		$sections = trim( $sections );

		if ( '' !== $sections ) {
			return $sections;
		}

		return null !== $category
			? sprintf(
				'<div class="api-empty-state">No routes found in category "%s".</div>',
				htmlspecialchars( self::humanize_category( $category ), ENT_QUOTES, 'UTF-8' )
			)
			: '<div class="api-empty-state">No routes are currently registered.</div>';
	}


	/**
	 * Render one version's section: its category groups, each holding
	 * that category's route cards.
	 *
	 * @param string       $namespace Version namespace, used as the section heading.
	 * @param RouteCatalog $catalog   Catalog describing that version's routes.
	 * @param string|null  $category  When given, render only this category
	 *                                (and only if the version has routes in it)
	 *                                instead of every category the version has.
	 * @return string
	 */
	private static function render_version_section( string $namespace, RouteCatalog $catalog, ?string $category ): string {
		$categories = null !== $category ? [ $category ] : $catalog->categories();

		$category_sections = '';

		foreach ( $categories as $cat ) {
			$routes = $catalog->list_by_category( $cat );

			if ( empty( $routes ) ) {
				continue;
			}

			$category_sections .= self::render_category_section( $cat, $routes );
		}

		if ( '' === $category_sections ) {
			return '';
		}

		return sprintf(
			'<section class="api-version-section">
				<h2>%s</h2>
				%s
			</section>',
			htmlspecialchars( strtoupper( $namespace ), ENT_QUOTES, 'UTF-8' ),
			$category_sections
		);
	}


	/**
	 * Render one category's heading and the grid of route cards under it.
	 *
	 * @param string $category Category key (e.g. "license", "repository").
	 * @param array  $routes   Route descriptors from RouteCatalog::list_by_category().
	 * @return string
	 */
	private static function render_category_section( string $category, array $routes ): string {
		$route_cards = implode( '', array_map( [ static::class, 'render_route_card' ], $routes ) );

		return sprintf(
			'<div class="api-category-section">
				<h3>%s</h3>
				<div class="grid">%s</div>
			</div>',
			htmlspecialchars( self::humanize_category( $category ), ENT_QUOTES, 'UTF-8' ),
			$route_cards
		);
	}


	/**
	 * Render a single route's card: its path plus one method block per HTTP
	 * method the route supports.
	 *
	 * @param array $route Route descriptor from RouteCatalog.
	 * @return string
	 */
	private static function render_route_card( array $route ): string {
		$method_blocks = implode( '', array_map(
			static fn( string $method ) => self::render_method_block( $method, $route['methods'][ $method ] ),
			array_keys( $route['methods'] )
		) );

		return sprintf(
			'<div class="card api-route-card">
				<div class="api-route-path-container">
					<div class="api-route-path">%s</div>
					<button class="api-copy-btn" onclick="navigator.clipboard.writeText(\'%s\'); this.textContent=\'Copied!\'; setTimeout(() => this.textContent=\'Copy\', 2000);">Copy</button>
				</div>
				%s
			</div>',
			htmlspecialchars( $route['humanized_route'], ENT_QUOTES, 'UTF-8' ),
			self::esc_js( $route['humanized_route'] ),
			$method_blocks
		);
	}


	/**
	 * Render one HTTP method's block within a route card: the method badge,
	 * route name, and its argument list (or a "no parameters" note).
	 *
	 * @param string $method      Uppercase HTTP method, e.g. "GET".
	 * @param array  $method_data Method descriptor: ['name' => ..., 'args' => ...].
	 * @return string
	 */
	private static function render_method_block( string $method, array $method_data ): string {
		$args = $method_data['args'];

		$args_html = empty( $args )
			? '<p class="api-no-args">No parameters required.</p>'
			: implode( '', array_map( [ static::class, 'render_argument' ], $args ) );

		return sprintf(
			'<div class="api-method-block">
				<h4>
					<span class="api-method-badge api-method-%s">%s</span>
					%s
				</h4>
				%s
			</div>',
			strtolower( $method ),
			htmlspecialchars( $method, ENT_QUOTES, 'UTF-8' ),
			htmlspecialchars( $method_data['name'], ENT_QUOTES, 'UTF-8' ),
			$args_html
		);
	}


	/**
	 * Render a single route argument's row.
	 *
	 * @param array{name: string, type: string, required: bool, description: string, default: mixed} $arg
	 * @return string
	 */
	private static function render_argument( array $arg ): string {
		$required_badge = $arg['required']
			? '<span class="api-argument-required">Required</span>'
			: '<span class="api-argument-optional">Optional</span>';

		$default_badge = null !== $arg['default']
			? sprintf(
				'<span class="api-argument-default">Default: %s</span>',
				htmlspecialchars( (string) $arg['default'], ENT_QUOTES, 'UTF-8' )
			)
			: '';

		return sprintf(
			'<div class="api-argument">
				<div class="api-argument-header">
					<span class="api-argument-name">%s</span>
					<span class="api-argument-type">%s</span>
					%s
					%s
				</div>
				<p class="api-argument-description">%s</p>
			</div>',
			htmlspecialchars( $arg['name'], ENT_QUOTES, 'UTF-8' ),
			htmlspecialchars( $arg['type'], ENT_QUOTES, 'UTF-8' ),
			$required_badge,
			$default_badge,
			htmlspecialchars( $arg['description'], ENT_QUOTES, 'UTF-8' )
		);
	}


	/**
	 * Turn a category key like "bulk-messages" into a display label like
	 * "Bulk Messages". Derived from the key itself rather than a lookup
	 * table, so an unrecognized or future category still renders sensibly
	 * instead of falling back to a raw key or a missing-label gap.
	 *
	 * @param string $category
	 * @return string
	 */
	private static function humanize_category( string $category ): string {
		return ucwords( str_replace( [ '-', '_' ], ' ', $category ) );
	}


	/**
	 * Escape a string for safe interpolation inside a single-quoted JS
	 * string literal in inline event-handler attributes.
	 *
	 * @param string $text
	 * @return string
	 */
	private static function esc_js( string $text ): string {
		return addslashes( $text );
	}
}