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
     * Get top menu key
     * 
     * @return string
     */
    public static function get_menu_key() : string;

    /**
     * Get top menu data
     * 
     * @return array{
     *     title: string,
     *     slug: string,
     *     handler: class-string<static>,
     *     icon: string,
     *     visibility: bool|callable():bool
     * }
     */
    public static function get_menu_data() : array;

    /**
     * Get the submenu.
     * 
     * @return array{
     *  title: string,
     *  slug: string,
     *  callback: callable(Request $request): void,
     *  visibility: bool|callable():bool
     * }[]
     */
    public static function get_submenu() : array;
}