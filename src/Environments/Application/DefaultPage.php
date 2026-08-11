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


use SmartLicenseServer\Core\Response;


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

					@media (max-width: 640px) {
						nav {
							align-items: flex-start;
							flex-direction: column;
							gap: 1rem;
						}

						.nav-links {
							gap: 1rem;
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
	public static function not_found(): Response {
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
	 * Render Documentation page.
	 * 
	 * @return Response
	 */
	public static function doc_page() : Response {
		ob_start();
		\smliser_rest_documentation();

		$content	= \ob_get_clean();

		return ( new static(
			200,
			'Documentation',
			'Documentation',
			'Rest API Documentation.',
			$content
		) )->render();
	}
}