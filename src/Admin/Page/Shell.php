<?php
/**
 * The admin dashboard shell.
 *
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Admin\Page;

use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use Throwable;

use function ob_end_clean;
use function ob_get_clean;
use function ob_start;
use function sprintf;

/**
 * The admin dashboard orchestrator.
 *
 * Resolves the active top-level menu and submenu from the request, dispatches
 * the matching handler, and assembles the left menu, top menu, and content
 * area into a single rendered string.
 *
 * NOTE: Submenu `visibility` (see AdminPageInterface::get_submenu()) is not
 * enforced here. AdminDashboardRegistry::add_submenu() currently discards
 * that field when a submenu is registered, so there is nothing for the Shell
 * to read at render time. Every registered submenu is treated as visible and
 * reachable until the registry itself is updated to retain it.
 */
class Shell {

	/**
	 * Query var used to resolve the active top-level menu.
	 *
	 * @var string
	 */
	protected string $page_query_var;

	/**
	 * Query var used to resolve the active sub-tab / submenu page.
	 *
	 * @var string
	 */
	protected string $tab_query_var;

	/**
	 * Constructor.
	 *
	 * @param AdminDashboardRegistry $registry       The booted menu registry.
	 * @param string                 $page_query_var Query var holding the active menu slug.
	 * @param string                 $tab_query_var  Query var holding the active tab slug.
	 */
	private function __construct( 
		protected AdminDashboardRegistry $registry, 
		string $page_query_var	= 'page',
		string $tab_query_var	= 'tab'
	) {
		$this->page_query_var = $page_query_var;
		$this->tab_query_var  = $tab_query_var;
	}

	/**
	 * Render the full admin shell for the given request.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public static function render( Request $request ) : Response {
		ob_start();
		$shell		= static::make();
		$menu_slug	= (string) $request->query( $shell->page_query_var, '' );

		$shell->render_head();

		$shell->render_structures();

		$shell->render_footer();

		return Response::make( ob_get_clean(), 200 )
			->set_header( 'Content-Type', 'text/html; charset="UTF-8"' );
	}

	/**
	 * Render admin head section.
	 * 
	 * Prints admin styles, scripts and global variables that are registered with
	 * in AssetsManager.
	 */
	protected function render_head() : void {

	}

	/**
	 * Render the body structures of the admin page.
	 */
	protected function render_structures() : void {

	}

	/**
	 * Render the admin footer and scripts.
	 * 
	 * @return void
	 */
	protected function render_footer() : void {

	}

	/**
	 * Dispatch the matching registered callback and capture its buffer / return.
	 *
	 * @param string  $menu_slug
	 * @param string  $tab_slug
	 * @param Request $request
	 * @return string
	 */
	protected function dispatch_callback( string $menu_slug, string $tab_slug, Request $request ) : string {
		$callback = $this->registry->get_menu_callback( $menu_slug, $tab_slug );

		if ( ! is_callable( $callback ) ) {
			return sprintf(
				'<div class="smliser-alert smliser-alert-error"><p>%s</p></div>',
				\escHtml( 'No valid handler callback was registered for this action.' )
			);
		}

		ob_start();

		try {
			$result = $callback( $request );
			$output = ob_get_clean();

			// If callback returned string instead of echoing, prefer return value
			if ( is_string( $result ) && ! empty( $result ) ) {
				return $result;
			}

			return (string) $output;
		} catch ( Throwable $e ) {
			ob_end_clean();

			\smliser_log_error( sprintf(
				'[Shell] Error executing admin callback for "%s/%s": %s',
				$menu_slug,
				$tab_slug,
				$e->getMessage()
			) );

			return sprintf(
				'<div class="smliser-alert smliser-alert-danger"><h4>%s</h4><p>%s</p></div>',
				\escHtml( 'Execution Error' ),
				\escHtml( $e->getMessage() )
			);
		}
	}

	/**
	 * Assemble sidebar navigation, top header bar, and content stage into final layout shell.
	 *
	 * @param string $active_menu
	 * @param string $active_tab
	 * @param string $content_html
	 * @return string
	 */
	protected function assemble_layout( string $active_menu, string $active_tab, string $content_html ) : string {
		$menus    = $this->registry->all();
		$submenus = $this->registry->get_submenus();

		ob_start();
		?>
        <style>
            /**
 * Smart License Server - Admin Shell Stylesheet
 *
 * @package SmartLicenseServer\Admin
 */

:root {
    --smliser-primary: #2271b1;
    --smliser-primary-hover: #135e96;
    --smliser-bg-main: #f0f0f1;
    --smliser-bg-sidebar: #1d2327;
    --smliser-bg-card: #ffffff;
    --smliser-text-main: #2c3338;
    --smliser-text-muted: #646970;
    --smliser-text-sidebar: #f0f0f1;
    --smliser-text-sidebar-hover: #72aee6;
    --smliser-border-color: #dcdcde;
    --smliser-radius: 6px;
    --smliser-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    
    /* System Alerts */
    --smliser-danger: #d63638;
    --smliser-danger-bg: #fcf0f1;
    --smliser-warning: #dba617;
    --smliser-warning-bg: #fcf8e3;
}

/* Base Wrapper & Layout Setup */
.smliser-admin-shell-wrapper {
    display: flex;
    min-height: calc(100vh - 32px);
    background-color: var(--smliser-bg-main);
    color: var(--smliser-text-main);
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
    font-size: 14px;
    line-height: 1.5;
    box-sizing: border-box;
}

.smliser-admin-shell-wrapper *,
.smliser-admin-shell-wrapper *::before,
.smliser-admin-shell-wrapper *::after {
    box-sizing: inherit;
}

/* -----------------------------------------------------------------
# Sidebar Navigation
----------------------------------------------------------------- */
.smliser-admin-sidebar {
    width: 240px;
    flex-shrink: 0;
    background-color: var(--smliser-bg-sidebar);
    color: var(--smliser-text-sidebar);
    display: flex;
    flex-direction: column;
}

.smliser-brand-header {
    padding: 20px 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.smliser-brand-header h2 {
    color: #ffffff;
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    padding: 0;
}

.smliser-admin-nav {
    padding: 12px 0;
    flex-grow: 1;
}

.smliser-menu-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.smliser-menu-item {
    margin: 2px 0;
}

.smliser-menu-item a {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    color: var(--smliser-text-sidebar);
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: background-color 0.15s ease, color 0.15s ease;
}

.smliser-menu-item a:hover {
    background-color: rgba(255, 255, 255, 0.05);
    color: var(--smliser-text-sidebar-hover);
}

.smliser-menu-item.is-active a {
    background-color: var(--smliser-primary);
    color: #ffffff;
    font-weight: 600;
}

.smliser-menu-icon {
    margin-right: 10px;
    font-size: 18px;
    width: 20px;
    text-align: center;
}

/* -----------------------------------------------------------------
# Main Content Stage & Top Bar
----------------------------------------------------------------- */
.smliser-admin-main {
    flex-grow: 1;
    display: flex;
    flex-direction: column;
    min-width: 0; /* Prevents flex items from overflowing horizontal boundaries */
}

.smliser-top-header {
    background-color: var(--smliser-bg-card);
    border-bottom: 1px solid var(--smliser-border-color);
    padding: 0 24px;
    display: flex;
    align-items: center;
    min-height: 52px;
}

/* Submenu / Tab Navigation */
.smliser-tab-nav {
    display: flex;
    gap: 8px;
    margin-bottom: -1px; /* Align border with header bottom border */
}

.smliser-tab-item {
    display: inline-block;
    padding: 14px 16px;
    text-decoration: none;
    color: var(--smliser-text-muted);
    font-weight: 500;
    font-size: 14px;
    border-bottom: 3px solid transparent;
    transition: color 0.15s ease, border-color 0.15s ease;
}

.smliser-tab-item:hover {
    color: var(--smliser-primary);
}

.smliser-tab-item.is-active {
    color: var(--smliser-primary);
    border-bottom-color: var(--smliser-primary);
    font-weight: 600;
}

/* Main Content Area */
.smliser-admin-content {
    padding: 24px;
    flex-grow: 1;
}

/* -----------------------------------------------------------------
# Alert Components & System Messages
----------------------------------------------------------------- */
.smliser-alert {
    padding: 16px 20px;
    border-radius: var(--smliser-radius);
    border-left: 4px solid var(--smliser-primary);
    background-color: var(--smliser-bg-card);
    box-shadow: var(--smliser-shadow);
    margin-bottom: 20px;
}

.smliser-alert h4 {
    margin: 0 0 6px 0;
    font-size: 15px;
}

.smliser-alert p {
    margin: 0;
}

.smliser-alert-danger,
.smliser-alert-error {
    border-left-color: var(--smliser-danger);
    background-color: var(--smliser-danger-bg);
    color: #501314;
}

/* -----------------------------------------------------------------
# Not Found (404) Stage Layout
----------------------------------------------------------------- */
.smliser-not-found-container {
    background-color: var(--smliser-bg-card);
    border: 1px solid var(--smliser-border-color);
    border-radius: var(--smliser-radius);
    padding: 48px 32px;
    text-align: center;
    max-width: 600px;
    margin: 40px auto;
    box-shadow: var(--smliser-shadow);
}

.smliser-not-found-container h2 {
    font-size: 22px;
    margin: 0 0 12px 0;
    color: var(--smliser-text-main);
}

.smliser-not-found-container p {
    color: var(--smliser-text-muted);
    font-size: 15px;
    margin: 0;
}

/* Responsive Collapse for Small Screens */
@media screen and (max-width: 782px) {
    .smliser-admin-shell-wrapper {
        flex-direction: column;
    }

    .smliser-admin-sidebar {
        width: 100%;
    }

    .smliser-top-header {
        overflow-x: auto;
    }
}
        </style>
		<div class="smliser-admin-shell-wrapper">
			<aside class="smliser-admin-sidebar">
				<div class="smliser-brand-header">
					<h2><?php echo \escHtml( 'Smart License' ); ?></h2>
				</div>
				<nav class="smliser-admin-nav">
					<ul class="smliser-menu-list">
						<?php foreach ( $menus as $slug => $menu ) : ?>
							<?php $is_active = ( $slug === $active_menu ); ?>
							<li class="smliser-menu-item <?php echo $is_active ? 'is-active' : ''; ?>">
								<a href="<?php echo \escUrl( \smliser_get_current_url()->add_query_param( $this->page_query_var, $slug )->url() ); ?>">
									<?php if ( ! empty( $menu['icon'] ) ) : ?>
										<span class="smliser-menu-icon dashicons <?php echo \escAttr( $menu['icon'] ); ?>"></span>
									<?php endif; ?>
									<span class="smliser-menu-label"><?php echo \escHtml( $menu['title'] ); ?></span>
								</a>
							</li>
						<?php endforeach; ?>
					</ul>
				</nav>
			</aside>

			<main class="smliser-admin-main">
				<header class="smliser-top-header">
					<?php if ( ! empty( $submenus ) ) : ?>
						<nav class="smliser-tab-nav">
							<?php foreach ( $submenus as $sub_slug => $submenu ) : ?>
								<?php $is_tab_active = $sub_slug === $active_tab; ?>
								<a href="<?php echo \escUrl( \smliser_get_current_url()->add_query_params( [$this->page_query_var => $active_menu, $this->tab_query_var => $sub_slug ] )->url() ); ?>" 
								   class="smliser-tab-item <?php echo $is_tab_active ? 'is-active' : ''; ?>">
									<?php echo \escHtml( $submenu['title'] ); ?>
								</a>
							<?php endforeach; ?>
						</nav>
					<?php endif; ?>
				</header>

				<section class="smliser-admin-content">
					<?php echo $content_html; ?>
				</section>
			</main>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a standard 404 stage layout for unhandled/unregistered pages.
	 *
	 * @param string $requested_slug
	 * @return string
	 */
	protected function render_not_found_page( string $requested_slug ) : string {
		$error_content = sprintf(
			'<div class="smliser-not-found-container">
				<h2>%s</h2>
				<p>%s</p>
			</div>',
			\escHtml( '404 - Page Not Found' ),
			sprintf( \escHtml( 'The admin page "%s" you requested does not exist or has been moved.' ), \escHtml( $requested_slug ) )
		);

		return $this->assemble_layout( $requested_slug, '', $error_content );
	}

	/**
	 * Get an instance of this class
	 * 
	 * @return static
	 */
	public static function make() : static {
		return new static(
			\smliser_envProvider()->adminDashboardRegistry(),
		);
	}
}