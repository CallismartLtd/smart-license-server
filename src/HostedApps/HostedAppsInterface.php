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

use DateTimeImmutable;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;

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
     * @param int|string $id
     */
    public function set_id( int|string $id ) : static;

    /**
     * Set app ID
     * 
     * @param int|string $id
     */
    public function set_owner_id( int|string $id ) : static;

    /**
     * Set app name
     * 
     * @param string $name
     */
    public function set_name( ?string $name ) : static;

    /**
     * Set app slug
     * 
     * @param string $slug
     */
    public function set_slug( string $slug ) : static;

    /**
     * Set app status
     * 
     * @param string $status
     */
    public function set_status( string $status ) : static;
    /**
     * Set app author
     * 
     * @param string|array $value
     */
    public function set_author( string|array $value ) : static;

    /**
     * Set app homepage URL
     * 
     * @param string $url
     */
    public function set_homepage( ?string $url ) : static;

    /**
     * Set app screenshots
     * 
     * @param array $screenshots
     */
    public function set_screenshots( array $screenshots ) : static;

    /**
     * Set App icons
     * 
     * @param array $icons
     */
    public function set_icons( array $icons ) : static;

    /**
     * Set app name author profile url
     * 
     * @param string $url
     */
    public function set_author_profile( ?string $url ) : static;

    /**
     * Set app version
     * 
     * @param string $version
     */
    public function set_version( ?string $version ) : static;

    /**
     * Set app download url
     * 
     * @param string|URL $url
     */
    public function set_download_url( string|URL $url ) : static;

    /**
     * Set the absolute path to the applications zip file or an uploaded file.
     * 
     * @param string|UploadedFile $file
     */
    public function set_file( string|UploadedFile $file ) : static;

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
    public function set_license( array $license ) : static;

    /**
     * Set last updated
     * 
     * @param mixed $date
     */
    public function set_updated_at( mixed $date ) : static;

    /**
     * Set When created
     * 
     * @param mixed $date
     */
    public function set_created_at( mixed $date ) : static;

    /**
     * Set Section
     * 
     * @param array $section_data An associative array containing each section information.
     */
    public function set_section( array $section_data ) : static;

    /**
     * Set ratings
     * 
     * @param array{5: int, 4: int, 3: int, 2: int, 1: int} $ratings
     */
    public function set_ratings( $ratings ) : static;

    /**
     * Get number of rating
     * 
     * @param int $value
     */
    public function set_num_ratings( int $value ) : static ;

    /**
     * The number of active installations
     * @param int $value
     */
    public function set_active_installs( mixed $value ) : static;

    /**
     * Set the app support URL
     *
     * @param string|URL $url The support URL
     * @return static
     */
    public function set_support_url( string|URL $url ) : static;

    /**
     * Set the APP tags.
     * 
     * @param array $tags
     */
    public function set_tags( array $tags ) : static;

    /**
     * Set the manifest property.
     * 
     * @param array $data
     * 
     */
    public function set_manifest( array $data ) : static;

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
    public function get_type() : string;

    /**
     * Get the application unique identifier.
     * 
     * @return int
     */
    public function get_id() : int;

    /**
     * Get the application unique identifier.
     * 
     * @return int
     */
    public function get_owner_id() : int;

    /**
     * Get the application name.
     * 
     * @return string
     */
    public function get_name() : string;

    /**
     * Get the app status
     * 
     * @return string
     */
    public function get_status() : string;

    /**
     * Get the application version.
     * 
     * @return string
     */
    public function get_version() : string;

    /**
     * Get the download URL for the application.
     * 
     * @return URL
     */
    public function get_download_url() : URL;

    /**
     * Get the application author.
     * 
     * @return string|array
     */
    public function get_author() : string|array;

    /**
     * get author profile
     * 
     * @return URL|null
     */
    public function get_author_profile() : ?URL;

    /**
     * Get the application slug (URL-friendly name).
     * 
     * @return string
     */
    public function get_slug() : string;

    /**
     * Get the application homepage URL.
     * 
     * @return URL
     */
    public function get_homepage() : URL;

    /**
     * Get the application description.
     * 
     * @return string
     */
    public function get_description() : string;

    /**
     * Get application short description.
     */
    public function get_short_description() : string;

    /**
     * Get the application changelog.
     * 
     * @return string
     */
    public function get_changelog() : string;

    /**
     * Get application support URL.
     */
    public function get_support_url() : ?URL;

    /**
     * Get the license under which this hosted application is distributed.
     * 
     * @return array
     */
    public function get_license() : array;

    /**
     * Get app screenshots
     * 
     * @return array $screenshots
     */
    public function get_screenshots() : array;

    /**
     * Get plugin icons
     * 
     * @return array
     */
    public function get_icons() : array;

    /**
     * Get the application's installation guide.
     * 
     * @return string a parsed HTML string from the readme file.
     */
    public function get_installation() : string;

    /**
     * Get the value of a specific section property.
     * 
     * @param string $name  Section name.
     * @return string The particular section name
     */
    public function get_section( $name ) : string;

    /**
     * Get ratings
     * 
     * @return array $ratings
     */
    public function get_ratings() : array;

    /**
     * Get number of rating
     * 
     * @return int
     */
    public function get_num_ratings() : int;

    /**
     * Get average rating
     * 
     * @return float
     */
    public function get_average_rating() : float ;

    /**
     * The number of active installations
     */
    public function get_active_installs() : int;

    /**
     * Get the file.
     * 
     * @return string|UploadedFile
     */
    public function get_file() : string|UploadedFile;

    /**
     * Get Sections
     * 
     * @return array $sections.
     */
    public function get_sections() : array;

    /**
     * Get last updated
     */
    public function get_updated_at() : DateTimeImmutable;

    /**
     * Get when created.
     */
    public function get_created_at() : DateTimeImmutable;

    /**
     * Get the app tags.
     * 
     * @return array $tags
     */
    public function get_tags() : array;

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
    public static function get_by_slug( string $slug ) : ?static;

    /**
     * Delete the application from the repository.
     * 
     * @return bool
     */
    public function delete() : bool;

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

    /**
     * Tells whether an app exists.
     * 
     * @return bool
     */
    public function exists() : bool;

    /**
     * Get the value of all metadata in key => value format.
     */
    public function get_meta_data() : array;

    /**
     * Get the value of a specific metadata key.
     * 
     * @param string $key The metadata key.
     * @param mixed $default The default value to return if the key does not exist.
     * @return mixed The value of the metadata key or the default value if the key does not exist.
     */
    public function get_meta( string $key, mixed $default = null ) : mixed;


    /**
     * Hydrate object from DB row with only core fields.
     * Does NOT load meta, assets, sections, or files.
     *
     * @param array $row Associative array from DB.
     * @return static
     */
    public static function from_array_minimal( array $row ): static ;

    /**
     * Get an instance of this class from an array
     * 
     * @param array $data
     */
    public static function from_array( $data ) : static;
    /*
    |----------------------------------
    | Repository FileSystem operations
    |----------------------------------
    */
    
    /**
     * Get the absolute path to the application zip file.
     * 
     * @return string|Exception
     */
    public function get_zip_file() : string|Exception;
}