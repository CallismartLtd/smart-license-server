<?php
/**
 * Client Dashboard Registry
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\ClientDashboard\ContentHandlers\LicensesPage;
use SmartLicenseServer\ClientDashboard\ContentHandlers\OverviewPage;
use SmartLicenseServer\ClientDashboard\ContentHandlers\PurchasesPage;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Contracts\AbstractDashboardRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The client dashboard's menus, built like the admin dashboard's.
 *
 * Every page implements ClientPageInterface; the registry consumes the
 * pages, and the client dashboard is built from the registry: the active
 * theme draws the side menu, the submenus and the top menu from it (see
 * ClientDashboard\Page\Dispatcher). Extensions and themes add, move,
 * hide or remove menus, submenus and top menu items through the
 * AbstractDashboardRegistry API.
 *
 * @example Register a page:
 * ```php
 * $container->get( ClientDashboardRegistry::class )
 *     ->register( $page->get_menu_key(), $page->get_menu_data() );
 * ```
 */
final class ClientDashboardRegistry extends AbstractDashboardRegistry {

	/**
	 * Constructor.
	 *
	 * @param URLManager $urlmanager URL API, for the top menu links.
	 * @param Container  $container  Builds the core pages.
	 * @param Guard      $guard      The signed-in principal, for visibility.
	 */
	public function __construct(
		protected URLManager $urlmanager,
		protected Container $container,
		protected Guard $guard
	) {}

	/**
	 * Register the core pages and top menu items.
	 *
	 * @return void
	 */
	protected function boot() : void {
		if ( $this->booted ) {
			return;
		}

		$this->booted = true;

		$core_menu = [
			OverviewPage::class,
			LicensesPage::class,
			PurchasesPage::class,
		];

		foreach ( $core_menu as $id ) {
			/** @var ClientPageInterface $page */
			$page = $this->container->get( $id );
			$this->register( $page->get_menu_key(), $page->get_menu_data() );

			foreach ( $page->get_submenu() as $submenu ) {
				$this->add_submenu( $page->get_menu_key(), $submenu );
			}
		}

		$this->add_top_menu( 'home_url', [
			'title'      => 'Home',
			'type'       => 'link',
			'href'       => $this->urlmanager->url( '/' ),
			'icon'       => 'ti ti-home',
			'visibility' => true,
		] );

		$this->add_top_menu( 'admin_dashboard', [
			'title'      => 'Admin',
			'type'       => 'link',
			'href'       => $this->urlmanager->admin_url(),
			'icon'       => 'ti ti-layout-dashboard',
			'visibility' => fn () : bool => (bool) $this->guard->get_principal()?->is( 'system_admin' ),
		] );

		$this->add_top_menu( 'logout_button', [
			'title'      => 'Sign out',
			'type'       => 'link',
			'href'       => $this->urlmanager->logout_url(),
			'icon'       => 'ti ti-logout',
			'visibility' => true,
		] );
	}

	/**
	 * {@inheritdoc}
	 */
	protected function page_contract() : string {
		return ClientPageInterface::class;
	}

	/**
	 * Determine whether a key represents the root/overview menu item.
	 *
	 * @param string $key
	 * @return bool
	 */
	public function is_root_menu( string $key ) : bool {
		return 'overview' === $this->canonical_key( $key );
	}
}