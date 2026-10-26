<?php
/**
 * Client dashboard dispatcher class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\Page
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\Page;

use SmartLicenseServer\ClientDashboard\ClientDashboardRegistry;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\CorePages;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\ContextServiceProvider;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The client dashboard's page handler, built from the registry.
 *
 * The client dashboard counterpart of Admin\Page\Dispatcher and the admin
 * shell: it finds the current menu and submenu in ClientDashboardRegistry
 * from the {page} and {tab} route parameters, runs their callback, which
 * returns the content View, and hands the theme the dashboard to draw
 * around it.
 *
 * The content View gets:
 *   - the frame 'client-dashboard' (templates/frames/client-dashboard.php);
 *   - the Tabler icons style, because menu icons are Tabler classes;
 *   - the page forms script (View::FORMS_SCRIPT), so a page's forms post
 *     to the client dashboard's JSON actions with the CSRF token;
 *   - the core modal (MODAL_ASSET), so forms with data-confirm ask in a
 *     SmliserModal dialog;
 *   - the data $dashboard, built from the registry, visible items only:
 *     array{
 *         menu: list<array{key: string, title: string, icon: string, url: string, current: bool,
 *             submenu: list<array{slug: string, title: string, url: string, current: bool}>}>,
 *         top_menu: list<array{key: string, title: string, icon: string,
 *             icons: list<array{class: string, attributes: array<string, string>}>,
 *             type: string, href: string, attributes: array<string, string>}>,
 *         current: array{menu: string, submenu: ?string},
 *         context: array{
 *             action: string,
 *             current: string,
 *             options: list<array{value: string, label: string, role: string, type: string, current: bool}>
 *         },
 *         account: array{name: string, email: string, verified: bool, resend_url: string}
 *     }
 *     context is who the user can act for (ContextServiceProvider::get_acting_contexts()),
 *     the user themselves first; a theme shows a switcher posting "context" to
 *     context.action when there is more than one option. account.verified is
 *     false until the user verifies their email; a theme shows a notice with
 *     a button posting to account.resend_url.
 *   - the title of the current submenu or menu, unless the page set one.
 *
 * An unknown or hidden page or tab is the theme's 404 page.
 */
final class Dispatcher {

	/**
	 * Frame the theme renders around client dashboard pages.
	 */
	public const FRAME = 'client-dashboard';

	/**
	 * Core style of the icon classes menus use (AssetsManager handle).
	 */
	public const ICON_STYLE = 'tabler-icons';

	/**
	 * Core modal (AssetsManager handle, script and style): SmliserModal, which
	 * page-forms.js uses for the data-confirm questions of dashboard forms.
	 */
	public const MODAL_ASSET = 'modal';

	/**
	 * Constructor.
	 *
	 * @param ClientDashboardRegistry $registry   The client dashboard's menus.
	 * @param URLManager              $urlmanager URL API.
	 * @param CorePages               $core_pages The 404 page.
	 * @param Guard                   $guard      The signed-in principal.
	 */
	public function __construct(
		private ClientDashboardRegistry $registry,
		private URLManager $urlmanager,
		private CorePages $core_pages,
		private Guard $guard
	) {}

	/**
	 * GET <client dashboard prefix>/, /{page}/ and /{page}/{tab}/.
	 *
	 * @param Request $request
	 * @return View
	 * @throws \LogicException When a page callback does not return a View.
	 */
	public function render_client_dashboard( Request $request ) : View {
		$key = $this->menu_key( (string) $request->route_param( 'page', '' ) );

		if ( null === $key ) {
			return $this->core_pages->not_found( $request );
		}

		$menu    = (array) $this->registry->get( $key );
		$tab     = (string) $request->route_param( 'tab', '' );
		$submenu = null;

		if ( '' !== $tab ) {
			$submenu = $this->registry->get_submenu_by_slug( $key, $tab );

			if ( null === $submenu || ! $this->visible( $submenu['visibility'] ) ) {
				return $this->core_pages->not_found( $request );
			}
		}

		$callback = null !== $submenu ? $submenu['callback'] : $menu['handler']->index_page_handler();
		$view     = $callback( $request );

		if ( ! $view instanceof View ) {
			throw new \LogicException( sprintf( 'The client dashboard page "%s" must return a %s; it returned %s.', null !== $submenu ? "{$key}/{$tab}" : $key, View::class, get_debug_type( $view ) ) );
		}

		if ( '' === $view->get_title() ) {
			$view->set_title( null !== $submenu ? $submenu['title'] : $menu['title'] );
		}

		return $view
			->set_frame( self::FRAME )
			->add_style( self::ICON_STYLE )
			->add_style( self::MODAL_ASSET )
			->add_script( self::MODAL_ASSET )
			->add_script( View::FORMS_SCRIPT )
			->set( 'dashboard', $this->dashboard( $key, null !== $submenu ? $submenu['slug'] : null ) );
	}

	/**
	 * The visible menus, submenus and top menu items, with their URLs.
	 *
	 * @param string      $current_key     Current menu key.
	 * @param string|null $current_submenu Current submenu slug; null for the menu's page.
	 * @return array<string, mixed> See the class description.
	 */
	public function dashboard( string $current_key, ?string $current_submenu ) : array {
		$menu = [];

		foreach ( $this->registry->all() as $key => $item ) {
			if ( ! $this->visible( $item['visibility'] ) ) {
				continue;
			}

			$submenu = [];

			foreach ( $this->registry->get_submenu( $key ) ?? [] as $sub ) {
				if ( ! $this->visible( $sub['visibility'] ) ) {
					continue;
				}

				$submenu[] = [
					'slug'    => $sub['slug'],
					'title'   => $sub['title'],
					'url'     => $this->urlmanager->client_dashboard_url( $item['slug'] . '/' . $sub['slug'] )->url(),
					'current' => $key === $current_key && $sub['slug'] === $current_submenu,
				];
			}

			$menu[] = [
				'key'     => (string) $key,
				'title'   => $item['title'],
				'icon'    => $item['icon'],
				'url'     => $this->urlmanager->client_dashboard_url( $this->registry->is_root_menu( $key ) ? '' : $item['slug'] )->url(),
				'current' => $key === $current_key,
				'submenu' => $submenu,
			];
		}

		$top_menu = [];

		foreach ( $this->registry->get_top_menus() as $key => $item ) {
			if ( ! $this->visible( $item['visibility'] ) ) {
				continue;
			}

			$top_menu[] = [
				'key'        => (string) $key,
				'title'      => $item['title'],
				'icon'       => $item['icon'],
				'icons'      => $item['icons'],
				'type'       => $item['type'],
				'href'       => $item['href'],
				'attributes' => $item['attributes'],
			];
		}

		return [
			'menu'     => $menu,
			'top_menu' => $top_menu,
			'current'  => [ 'menu' => $current_key, 'submenu' => $current_submenu ],
			'context'  => $this->context(),
			'account'  => $this->account(),
		];
	}

	/**
	 * Who the signed-in user can act for, and who they act for now.
	 *
	 * @return array{action: string, current: string, options: list<array{value: string, label: string, role: string, type: string, current: bool}>}
	 */
	private function context() : array {
		$principal = $this->guard->get_principal();
		$user      = $principal?->get_actor();
		$owner     = $principal?->get_owner();
		$current   = $owner && $owner->is_organization()
			? sprintf( '%s:%d', $owner->get_type(), $owner->get_subject_id() )
			: sprintf( '%s:%d', (string) $user?->get_type(), (int) $user?->get_id() );
		$options   = [];

		if ( $user instanceof User ) {
			foreach ( ContextServiceProvider::get_acting_contexts( $user ) as $context ) {
				$value     = sprintf( '%s:%d', $context['subject']->get_type(), $context['subject']->get_id() );
				$options[] = [
					'value'   => $value,
					'label'   => $context['subject'] instanceof User ? sprintf( '%s (you)', $context['subject']->get_display_name() ) : $context['subject']->get_display_name(),
					'role'    => $context['role']->get_label(),
					'type'    => $context['subject']->get_type(),
					'current' => $value === $current,
				];
			}
		}

		return [
			'action'  => $this->urlmanager->client_dashboard_url( 'json/context' )->url(),
			'current' => $current,
			'options' => $options,
		];
	}

	/**
	 * The signed-in user's account, for notices such as email verification.
	 *
	 * @return array{name: string, email: string, verified: bool, resend_url: string}
	 */
	private function account() : array {
		$user = $this->guard->get_principal()?->get_actor();

		return [
			'name'       => $user instanceof User ? $user->get_display_name() : '',
			'email'      => $user instanceof User ? $user->get_email() : '',
			'verified'   => ! $user instanceof User || $user->is_email_verified(),
			'resend_url' => $this->urlmanager->login_url( 'form/resend-verification' )->url(),
		];
	}

	/**
	 * The key of the visible menu a {page} slug names; the root menu for none.
	 *
	 * @param string $slug
	 * @return string|null
	 */
	private function menu_key( string $slug ) : ?string {
		foreach ( $this->registry->all() as $key => $item ) {
			$matches = '' === $slug ? $this->registry->is_root_menu( $key ) : $item['slug'] === $slug;

			if ( $matches ) {
				return $this->visible( $item['visibility'] ) ? (string) $key : null;
			}
		}

		return null;
	}

	/**
	 * Evaluate a registry visibility: a bool, or a callable returning one.
	 *
	 * @param bool|callable $visibility
	 * @return bool
	 */
	private function visible( bool|callable $visibility ) : bool {
		return is_callable( $visibility ) ? (bool) $visibility() : $visibility;
	}
}