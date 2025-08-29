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

defined( 'ABSPATH' ) || exit;

/**
 * Hosted Applications interface.
 * This interface defines the contract all the application types hosted in this repository must follow.
 */
interface Smliser_Hosted_Apps_Interface {
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
     * Get the application REST API response data.
     * 
     * @return array
     */
    public function get_rest_response();

    /**
     * Save or update the application data.
     * 
     * @return bool
     */
    public function save();

    /**
     * Delete the application from the repository.
     * 
     * @return bool
     */
    public function delete();

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
     * Get application support URL.
     */
    public function get_support_url();

}