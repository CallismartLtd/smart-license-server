<?php
/**
 * Client page interface file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard\Contracts
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\Contracts;

use SmartLicenseServer\Contracts\DashboardPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Environments\Application\Theming\View;

/**
 * Defines the contract every client dashboard page implements.
 *
 * ClientDashboardRegistry consumes it to build the client dashboard menu,
 * as AdminDashboardRegistry consumes AdminPageInterface. The page and
 * submenu callbacks return a View: the active theme renders it inside the
 * client dashboard frame, which it builds from the registry.
 */
interface ClientPageInterface extends DashboardPageInterface {
	/**
	 * Get the main page callback.
	 *
	 * @return callable(Request $request): View
	 */
	public function index_page_handler() : callable;

	/**
	 * Get the menu key.
	 *
	 * @return string
	 */
	public function get_menu_key() : string;

	/**
	 * Get the menu data.
	 *
	 * @return array{
	 *     title: string,
	 *     slug: string,
	 *     handler: static,
	 *     icon: string,
	 *     visibility: bool|callable():bool
	 * }
	 */
	public function get_menu_data() : array;

	/**
	 * Get the submenu.
	 *
	 * @return array{
	 *  title: string,
	 *  slug: string,
	 *  callback: callable(Request $request): View,
	 *  visibility: bool|callable():bool
	 * }[]
	 */
	public function get_submenu() : array;
}
