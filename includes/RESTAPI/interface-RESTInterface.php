<?php
/**
 * The rest API interface file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\RESTAPI;

/**
 * Defines all methods that REST API versions must implement.
 */
interface RESTInterface {
    /**
     * Get the api name space
     * 
     * @return string
     */
    public static function get_namespace() : string;

    /**
     * Get all route definitions for the API.
     * 
     * @return array
     */
    public static function get_routes() : array;

    /**
     * Get REST API categories
     * 
     * @return array
     */
    public static function get_categories() : array;

    /**
     * Describe routes by category or all routes.
     */
    public static function describe_routes( ?string $category = null ) : array;
}