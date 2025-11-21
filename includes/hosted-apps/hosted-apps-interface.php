<?php
/**
 * Hosted Apps interface file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage HostedApps
 * @since 0.0.6
 */

namespace SmartLicenseServer\HostedApps;
use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Hosted Applications interface.
 * This interface defines the contract all the application types hosted in this repository must follow.
 */
interface Hosted_Apps_Interface {

    /*
    |----------
    | SETTERS
    |----------
    */

    /**
     * Set app ID
     * 
     * @param int $id
     */
    public function set_id( $id );

    /**
     * Set app name
     * 
     * @param string $name
     */
    public function set_name( $name );

    /**
     * Set app author name
     * 
     * @param string $name
     */
    public function set_author( $name );

    /**
     * Set app homepage URL
     * 
     * @param string $url
     */
    public function set_homepage( $url );

    /**
     * Set app name author profile url
     * 
     * @param string $url
     */
    public function set_author_profile( $url );

    /**
     * Set app version
     * 
     * @param string $version
     */
    public function set_version( $version );

    /**
     * Set app download url
     * 
     * @param string $url
     */
    public function set_download_url( $url );

    /**
     * Set the absolute path to the applications zip file.
     * 
     * @param string $path
     */
    public function set_file( $path );

    /**
    |----------------
    | GETTERS
    |----------------
     */

    /**
     * Get the application type (e.g., 'plugin', 'theme', 'library').
     * 
     * @return string
     */
    public function get_type();

    /**
     * Get the application unique identifier.
     * 
     * @return int
     */
    public function get_id();

    /**
     * Get the application name.
     * 
     * @return string
     */
    public function get_name();

    /**
     * Get the application version.
     * 
     * @return string
     */
    public function get_version();

    /**
     * Get the download URL for the application.
     * 
     * @return string
     */
    public function get_download_url();

    /**
     * Get the application author.
     * 
     * @return string
     */
    public function get_author();

    /**
     * get author profile
     * 
     * @return string
     */
    public function get_author_profile();

    /**
     * Get the application slug (URL-friendly name).
     * 
     * @return string
     */
    public function get_slug();

    /**
     * Get the application homepage URL.
     * 
     * @return string
     */
    public function get_homepage();

    /**
     * Get the application description.
     * 
     * @return string
     */
    public function get_description();

    /**
     * Get application short description.
     */
    public function get_short_description();

    /**
     * Get the application changelog.
     * 
     * @return string
     */
    public function get_changelog();

    /**
     * Get application support URL.
     */
    public function get_support_url();

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Save or update the application data.
     * 
     * @return true|Exception True on success, WordPress Error on failure
     */
    public function save();

    /**
     * Get the application by slug
     * 
     * @param string $slug
     */
    public static function get_by_slug( $slug );

    /**
     * Delete the application from the repository.
     * 
     * @return bool
     */
    public function delete();

    /*
    |------------------
    | UTILITY METHODS
    |------------------
    */

    /**
     * Get the application REST API response data.
     * 
     * @return array
     */
    public function get_rest_response() : array;

    /**
     * Check whether the app is monetized
     * 
     * @return bool True when it is monetized, false otherwise.
     */
    public function is_monetized() : bool;

    /*
    |----------------------------------
    | Repository FileSystem operations
    |----------------------------------
    */
    
    /**
     * Get the absolute path to the application zip file.
     * 
     * @return string
     */
    public function get_zip_file();
}