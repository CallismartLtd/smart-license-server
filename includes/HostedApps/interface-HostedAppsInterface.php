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

use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Owner;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Hosted Applications interface.
 * This interface defines the contract all the application types hosted in this repository must follow.
 */
interface HostedAppsInterface {

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
     * Set app ID
     * 
     * @param int $id
     */
    public function set_owner_id( $id );

    /**
     * Set app name
     * 
     * @param string $name
     */
    public function set_name( $name );

    /**
     * Set app author
     * 
     * @param string|array $value
     */
    public function set_author( $value );

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
    public function set_download_url( string $url );

    /**
     * Set the absolute path to the applications zip file or an uploaded file.
     * 
     * @param string|UploadedFile $file
     */
    public function set_file( string|UploadedFile $file );

    /**
     * The the value of the software license.
     * 
     * - `Software License` in this context is the license under which the hosted application
     * is distributed
     * 
     * @param array $license The required values can be {
     *      license     => GPLv3,
     *      license_uri => https://www.gnu.org/licenses/gpl-3.0.en.html
     * }
     */
    public function set_license( array $license );

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
     * Get the application unique identifier.
     * 
     * @return int
     */
    public function get_owner_id();

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

    /**
     * Get the license under which this hosted application is distributed.
     * 
     * @return array
     */
    public function get_license() : array;

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
     * Get the application app manifest.
     * 
     * The app manifest is used to build the app.json file store in the repository.
     * 
     * @return array
     */
    public function get_manifest() : array;

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