<?php
/**
 * Client dashboard overview page class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\ContentHandlers
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;

/**
 * The client dashboard's first page: who is signed in.
 */
final class OverviewPage implements ClientPageInterface {

	/**
	 * Constructor.
	 *
	 * @param Guard $guard The signed-in principal.
	 */
	public function __construct( private Guard $guard ) {}

	/**
	 * {@inheritdoc}
	 */
	public function get_menu_key() : string {
		return 'overview';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_menu_data() : array {
		return [
			'title'      => 'Overview',
			'slug'       => 'overview',
			'handler'    => $this,
			'icon'       => 'ti ti-home',
			'visibility' => true,
		];
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_submenu() : array {
		return [];
	}

	/**
	 * {@inheritdoc}
	 */
	public function index_page_handler() : callable {
		return [ $this, 'overview' ];
	}

	/**
	 * GET <client dashboard prefix>/ and /overview/.
	 *
	 * Template 'client.overview'. Data:
	 *   string $name  The signed-in user's display name.
	 *   string $email The signed-in user's email address.
	 *   string $role  The user's role label.
	 *
	 * @param Request $request
	 * @return View
	 */
	public function overview( Request $request ) : View {
		$principal = $this->guard->principal();
		$actor     = $principal->get_actor();

		return View::make(
			'client.overview',
			[
				'name'  => (string) $principal->get_display_name(),
				'email' => $actor instanceof User ? $actor->get_email() : '',
				'role'  => (string) $principal->get_role()->get_label(),
			]
		);
	}
}