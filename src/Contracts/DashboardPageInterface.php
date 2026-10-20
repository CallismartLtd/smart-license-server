<?php
/**
 * Dashboard page interface file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Contracts
 */

namespace SmartLicenseServer\Contracts;

use SmartLicenseServer\Core\Request;

/**
 * A dashboard page: what a dashboard registry consumes to build its menu.
 *
 * The registry reads the menu key, the menu data and the submenus from the
 * page and registers them; the dashboard is then built from the registry,
 * never from the page. Each dashboard narrows this contract with its own
 * rule for what the page callbacks do (AdminPageInterface: they print the
 * admin content; ClientPageInterface: they return a View for the theme).
 */
interface DashboardPageInterface {
	/**
	 * Get the main page callback.
	 *
	 * The callback accepts the request object as its first argument.
	 *
	 * @return callable(Request): mixed
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
	 *  callback: callable(Request $request): mixed,
	 *  visibility: bool|callable():bool
	 * }[]
	 */
	public function get_submenu() : array;
}