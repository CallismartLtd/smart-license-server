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
     * Returns a structured array of route configurations that can be consumed
     * and registered by any supported environment implementation
     * (WordPress, Laravel, custom PHP router, etc.).
     *
     * Each route definition contains the following keys:
     *
     * - namespace (string)
     *      The base namespace under which all routes are grouped.
     *      Example: "smart-license-server/v1"
     *
     * - routes (array)
     *      A list of individual route configuration arrays.
     *
     * Each route configuration supports the following keys:
     *
     * - route (string)
     *      The URI pattern relative to the namespace.
     *      Example: "/activate" or "/repository/{id}"
     *
     * - methods (string|array)
     *      Allowed HTTP methods for the route.
     *      Can be a string (e.g. "GET") or an array of methods
     *      (e.g. ["POST", "PUT"]).
     *
     * - handler (callable)
     *      The main route handler callback that processes the request
     *      and returns a Response object or compatible result.
     *      Receives an instance of SmartLicenseServer\Core\Request.
     *
     * - guard (callable)
     *      Authorization callback executed before the handler.
     *      Determines whether the request is permitted to proceed.
     *      Must return true to allow execution of the handler,
     *      or an error result to reject the request.
     *
     * - args (array)
     *      Definition of expected route parameters including type,
     *      validation rules, and sanitization callbacks.
     *      Used by environment implementations to validate input.
     *
     * - category (string)
     *      Logical grouping identifier for the route, such as
     *      "license", "repository", or "testing".
     *      Used for internal organization and documentation purposes.
     *
     * - name (string)
     *      Human-readable descriptive name for the route.
     *      Useful for logging, debugging, or UI display.
     *
     * @return array{
     *     namespace: string,
     *     routes: array<int, array{
     *         route: string,
     *         methods: string|array<int, string>,
     *         handler: callable,
     *         guard?: callable,
     *         args?: array<string, array{
     *             required?: bool,
     *             type?: string,
     *             description?: string,
     *             default?: mixed
     *         }>,
     *         category?: string,
     *         name?: string
     *     }>
     * }
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