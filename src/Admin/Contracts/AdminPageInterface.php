<?php
/**
 * Admin page interface file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\Contracts;

use SmartLicenseServer\Core\Request;

/**
 * Defines the contract all admin dashboard classes must implement.
 */
interface AdminPageInterface {
    /**
     * Get the main page callback.
     * 
     * @return callable(Request $request) The handler should accept the request object as 
     * its first argument.
     */
    public static function index_page_handler() : callable;

    /**
     * Get the submenu.
     * 
     * @return array<string, array{
     *  title: string,
     *  slug: string,
     *  handler: callable(Request $request)
     * }>
     */
    public static function get_submenus() : array;

    /**
     * Get the routing variable name for the submenu if applicable.
     * 
     * @return string|null
     */
    public static function routing_var() : ?string;
}