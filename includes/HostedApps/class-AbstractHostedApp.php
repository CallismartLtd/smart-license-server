<?php
/**
 * AbstractHostedApp class file
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 * @package SmartLicenseServer\HostedApps
 */
namespace SmartLicenseServer\HostedApps;

use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\UploadedFile;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

use function smliser_dbclass;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Abstract Hosted Application Base Class
 * Provides shared implementation for HostedAppsInterface.
 * 
 * @package SmartLicenseServer\HostedApps
 */
abstract class AbstractHostedApp implements HostedAppsInterface {
    use SanitizeAwareTrait;

    /**
     * App database ID
     * 
     * @var int $id The app ID.
     */
    protected $id = 0;

    /**
     * App owner ID
     * 
     * @var int $owner_id
     */
    protected $owner_id = 0;

    /**
     * The name of the app..
     * 
     * @var string $name The app's name.
     */
    protected $name = '';

    /**
     * The app status
     * 
     * @var string $status
     */
    protected $status = self::STATUS_ACTIVE;

    /**
     * App slug.
     * 
     * @var string $slug app slug.
     */
    protected $slug = '';

    /**
     * App Version.
     * 
     * @var string $version The current version of the app.
     */
    protected $version = '';

    /**
     * Author .
     * 
     * @var string|array $author The author of the app
     */
    protected $author;

    /**
     * Author profile.
     * 
     * @var string $author_profile The author's app link or information.
     */
    protected $author_profile = '';

    /**
     * Update time.
     * 
     * @var string $updated_at The last time the app was updated
     */
    protected $updated_at = '';

    /**
     * Date created
     * 
     * @var string $created_at When app was created.
     */
    protected $created_at;

    /**
     * An array of app sections (e.g., description, installation, FAQ).
     * 
     * @var array $sections
     */
    protected $sections = array(
        'description'   => '',
        'installation'  => '',
        'faq'           => '',
        'screenshots'   => '',
        'changelog'     => '',
        'reviews'       => '',

    );

    /**
     * An array of of app icons.
     * 
     * @var array $icons
     */
    protected $icons = array();

    /**
     * Short description.
     * 
     * @var string $short_description A brief description of the app.
     */
    protected $short_description = '';

    /**
     * An array of app screenshots.
     * 
     * @var array $screenshots
     */
    protected $screenshots = array();

    /**
     *  An array of app ratings.
     * 
     * @var array $ratings
     */
    protected $ratings = array(
        '5' => 0,
        '4' => 0,
        '3' => 0,
        '2' => 0,
        '1' => 0,
    );

    /**
     * Number of ratings
     * 
     * @var int $num_ratings
     */
    protected $num_ratings = 0;

    /**
     * The number of active installations
     * 
     * @var int $active_installs
     */
    protected $active_installs = 0;

    /**
     * Download link.
     * 
     * @var string $download_link The file url.
     */
    protected $download_link = '#';

    /**
     * The app support URL
     * 
     * @var string $support_url
     */
    protected $support_url = '';

    /**
     * The app homepage URL
     * 
     * @var string $homepage
     */
    protected $homepage = '#';

    /**
     * Absolute path to the application's zip file.
     * 
     * @var string|UploadedFile $file The absolute path to the app zip file or an uploaded zip file.
     */
    protected string|UploadedFile $file;

    /**
     * The app tags.
     * 
     * @var array $tags
     */
    protected $tags = [];

    /**
     * App meta data stored in the database.
     * 
     * @var array $meta_data
     */
    protected $meta_data = [];

    /**
     * The applications manifest data used to build the app.json.
     * 
     * @var array $manifest
     */
    protected array $manifest = [
        'type'          => '',
        'platforms'     => [],
        'tech_stack'    => [],
        'dependencies'  => [],
    ];

    /**
     * The license underwhich this application is distributed
     * 
     * @var array $license
     */
    protected $license = [
        'license'       => '',
        'license_uri'   => ''
    ];

    /**
     * App trash status
     * 
     * @var string
     */
    const STATUS_TRASH = 'trash';

    /**
     * App active status.
     * 
     * @var string
     */
    const STATUS_ACTIVE = 'active';

    /**
     * App inactive status.
     * 
     * @var string
     */
    const STATUS_INACTIVE   = 'deactivated';

    /**
     * App suspended status
     * 
     * @var string
     */
    const STATUS_SUSPENDED  = 'suspended';

    /**
     * All status collections.
     * 
     * @var array
     */
    const STATUSES = [
        self::STATUS_ACTIVE     => self::STATUS_ACTIVE,
        self::STATUS_INACTIVE   => self::STATUS_INACTIVE,
        self::STATUS_SUSPENDED  => self::STATUS_SUSPENDED,
        self::STATUS_TRASH      => self::STATUS_TRASH,
    ];

    /**
    |----------------
    | SETTERS
    |----------------
    */

    /**
     * Set the database ID
     * 
     * @param int $id
     */
    public function set_id( $id ) {
        $this->id = absint( $id );
    }

    /**
     * Set the owner ID
     * 
     * @param int $owner_id
     */
    public function set_owner_id( $owner_id ) {
        $this->owner_id = self::sanitize_int( $owner_id );
    }

    /**
     * Set the app name
     * 
     * @param string $name
     */
    public function set_name( $name ) {
        $this->name = self::sanitize_text( $name );
    }

    /**
     * Set the slug
     * 
     * @param string $slug
     */
    public function set_slug( $slug ) {
        $this->slug = self::sanitize_text( $slug );
    }

    /**
     * Set the app status
     * 
     * @param string $status
     */
    public function set_status( $status ) {
        $this->status = self::sanitize_text( $status );
    }

    /**
     * Set app author property.
     * 
     * @param string|array $value
     */
    public function set_author( $value ) {
        if ( is_array( $value ) ) {
            $this->author = array_intersect_key( $value, $this->author );
        } else {
            $this->author = $value;
        }
        
    }

    /**
     * set app homepage URL
     * 
     * @param string $url
     */
    public function set_homepage( $url ) {
        $this->homepage = self::sanitize_url( $url, array( 'https' ) );
    }

    /**
     * Set app screenshots
     * 
     * @param array $screenshots
     */
    public function set_screenshots( array $screenshots ) {
        $this->screenshots = $screenshots;
    }

    /**
     * Set App icons
     * 
     * @param array $icons
     */
    public function set_icons( array $icons ) {
        $this->icons = $icons;

    }
    /**
     * Set the link to the author's profile
     * 
     * @param string $url
     */
    public function set_author_profile( $url ) {
        $this->author_profile = self::sanitize_url( $url );
    }

    /**
     * Set app version
     * 
     * @param string $version
     */
    public function set_version( $version ) {
        $this->version = self::sanitize_text( $version );
    }
    /**
     * Set Download url.
     * 
     * @param string $link The download url.
     */
    public function set_download_url( string $link = '' ) {
        $url            = new URL( $link );
        if ( $url->is_valid() ) {
            $this->download_link = $url->__toString();
        } else {
            $slug           = $this->get_slug();
            $download_slug  = smliser_get_download_slug();
            $type           = $this->get_type();
            $slug           = basename( $slug, '.zip' );

            $parts          = [ $download_slug, $type, $slug ];
            $path           = sprintf( '%s.zip', implode( '/', $parts ) );
            $download_link  = site_url( $path );

            $this->download_link = self::sanitize_url( $download_link, array( 'http', 'https' ) );            
        }
    }

    /**
     * Set last updated
     * 
     * @param $date
     */
    public function set_updated_at( $date ) {
        $this->updated_at = self::sanitize_text( $date );
    }

    /**
     * Set When created
     * 
     * @param $date
     */
    public function set_created_at( $date ) {
        $this->created_at = self::sanitize_text( $date );
    }

    /**
     * Set Section
     * 
     * @param array $section_data An associative array containing each section information.
     */
    public function set_section( array $section_data ) {
        if ( isset( $section_data['description'] ) ) {
            $this->sections['description'] = $section_data['description'];
        } 
        
        if ( isset( $section_data['installation'] ) ) {
            $this->sections['installation'] = $section_data['installation'];
        }
        
        if ( isset( $section_data['changelog'] ) ) {
            $this->sections['changelog'] = $section_data['changelog'];
        }

        if ( isset( $section_data['screenshots'] ) ) {
            $this->sections['screenshots'] = $section_data['screenshots'];
        }
        
        if ( isset( $section_data['faq'] ) ) {
            $this->sections['faq'] = $section_data['faq'];
        }

        if ( isset( $section_data['reviews'] ) ) {
            $this->sections['reviews'] = $section_data['reviews'];
        }
    }

    /**
     *  Set the file
     * 
     * @param string|UploadedFile $file
     */
    public function set_file( string|UploadedFile $file ) {
        $this->file = $file;
    }

    /**
     * Set ratings
     * 
     * @param array $ratings
     */
    public function set_ratings( $ratings ) {
        $this->ratings = array_intersect_key( $ratings, $this->ratings );
    }

    /**
     * Get number of rating
     * 
     * @param int $value
     */
    public function set_num_ratings( $value ) {
        $this->num_ratings  = absint( $value );
    }

    /**
     * The number of active installations
     * @param int $value
     */
    public function set_active_installs( $value ) {
        $this->active_installs = absint( $value );
    }

    /**
     * Set the app support URL
     *
     * @param string $url The support URL
     * @return void
     */
    public function set_support_url( $url ) {
        $this->support_url = $url;
    }

    /**
     * Set the APP tags.
     * 
     * @param array $tags
     */
    public function set_tags( $tags ) {
        $this->tags = array_map( [__CLASS__, 'sanitize_text'], (array) $tags );
    }

    /**
     * Set the manifest property.
     * 
     * @param array $data
     */
    public function set_manifest( array $data ) {
        $defaults   = [
            'type'          => '',
            'platforms'     => [],
            'tech_stack'    => [],
            'dependencies'  => [],
        ];
        
        $this->manifest = $data + $defaults;
    }

    /**
     * Set the software license underwhich this app is distributed
     * 
     * @param array $license
     */
    public function set_license( array $license ) {
        $this->license = $license + $this->license;
    }
    
    /**
    |------------
    | GETTERS
    |------------
    */

    /**
     * Get the app ID.
     * 
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the app owner ID
     * 
     * @return int
     */
    public function get_owner_id() {
        return $this->owner_id;
    }


    /**
     * Get the application name.
     * 
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get the app status
     * 
     * @return string
     */
    public function get_status() {
        return $this->status;
    }

    /**
     * Get the application version.
     * 
     * @return string
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get the download URL for the application.
     * 
     * @return string
     */
    public function get_download_url() {
        return $this->download_link;
    }

    /**
     * Get the application author.
     * 
     * @return string|array
     */
    public function get_author() {
        return $this->author;
    }

    /**
     * Get the application slug (URL-friendly name).
     * 
     * @return string
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Get the homepage URL
     * 
     * @return string
     */
    public function get_homepage() {
        if ( empty( $this->homepage ) || '#' === $this->homepage ) {
            return $this->get_url();
        }
        return $this->homepage;
    }
    
    /**
     * Get plugin screenshots
     * 
     * @return array $screenshots
     */
    public function get_screenshots() {
        return $this->screenshots;

    }

    /**
     * Get plugin icons
     * 
     * @return array
     */
    public function get_icons() {
        return $this->icons;

    }

    /**
     * Get the application description.
     * 
     * @return string
     */
    public function get_description() {
        return $this->sections['description'];
    }

    /**
     * Get application short description.
     */
    public function get_short_description() {
        return $this->short_description;
    }

    /**
     * Get the application changelog.
     * 
     * @return string
     */
    public function get_changelog() {
        return $this->get_section( 'changelog' );
    }
    /**
     * Get the application's installation guide.
     * 
     * @return string a parsed HTML string from the readme file.
     */
    public function get_installation() {
        return $this->get_section( 'installation' );
    }

    /**
     * Get application support URL.
     */
    public function get_support_url() {
        return $this->support_url;
    }

    /**
     * Get the value of a specific section property.
     * 
     * @param string $name  Section name.
     * @return string The particular section name
     */
    public function get_section( $name ) {
        return isset($this->sections[$name] ) ? $this->sections[$name] : '';
    }


    /**
     * Get ratings
     * 
     * @return array $ratings
     */
    public function get_ratings() {
        return $this->ratings;
    }

    /**
     * Get number of rating
     * 
     * @return int
     */
    public function get_num_ratings() {
        return $this->num_ratings;
    }

    /**
     * Get average rating
     * 
     * @return float
     */
    public function get_average_rating() {
        $total_ratings  = array_sum( $this->ratings );
        $num_ratings    = $this->get_num_ratings();

        if ( $num_ratings > 0 ) {
            return round( $total_ratings / $num_ratings, 2 );
        }

        return 0;
    }

    /**
     * The number of active installations
     */
    public function get_active_installs() {
        return $this->active_installs;
    }

    /**
     * Get the file.
     * 
     * @return string|UploadedFile
     */
    public function get_file() : string|UploadedFile {
        return $this->file;
    }

    /**
     * Get Sections
     * 
     * @return array $sections.
     */
    public function get_sections() {
        return $this->sections;
    }
    
    /**
     * Get last updated
     */
    public function get_updated_at() {
        return $this->updated_at;
    }
    
    /**
     * Get last updated
     */
    public function get_last_updated() {
        return $this->get_updated_at();
    }

    /**
     * Get when created.
     */
    public function get_created_at() {
        return $this->created_at;
    }
    /**
     * Get when created.
     */
    public function get_date_created() {
        return $this->get_created_at();
    }

    /**
     * Get the app tags.
     * 
     * @return array $tags
     */
    public function get_tags() {
        return $this->tags;
    }

    /**
     * Get the manifest property
     * 
     * @return array
     */
    public function get_manifest() : array {
        return $this->manifest;
    }

    /**
     * Get app destribution license
     * 
     * @return array
     */
    public function get_license() : array {
        return $this->license;
    }

    /**
    |-------------------------------------------
    | ABSTRACT METHODS THAT MUST BE IMPLEMENTED
    |-------------------------------------------
    */

    /**
     * get author profile
     * 
     * @return string
     */
    abstract public function get_author_profile();

    /**
     * Get the application type (e.g., 'plugin', 'theme', 'library').
     * 
     * @return string
     */
    abstract public function get_type() : string;

    /**
     * Get the app main icon
     * 
     * @return string
     */
    abstract function get_icon() : string;

    /**
     * Method to get the database table name.
     */
    abstract public static function get_db_table();

    /**
     * Method to get the database meta table name.
     */
    abstract public static function get_db_meta_table();

    /**
     * Get the foreign key column name for metadata table
     * 
     * @return string
     */
    abstract protected function get_meta_foreign_key() : string;
    
    // abstract public function save() : true|Exception;

    /**
     * Get an instance of this class from an array
     * @param array $data
     */
    abstract public static function from_array( $data ) : static;

    /**
     * Get database fields.
     * 
     * @return array<int, string>
     */
    abstract public function get_fillable() : array;

    /**
    |---------------------
    | SHARED CRUD METHODS
    |---------------------
    */

    /**
     * Save the app.
     * 
     * @return bool|Exception
     */
    final public function save() : bool|Exception {
        if ( ! $this->get_id() ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_table();
        $file       = $this->file;
        $repo_class = HostedApplicationService::get_app_repository_class( $this->get_type() );
        $db_fields  = $this->get_fillable();
        $now        = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

        $data       = [];

        foreach( $db_fields as $key ) {
            $method     = "get_{$key}";

            if ( ! \is_callable( [$this, $method] ) ) {
                continue;
            }

            $data[$key] = $this->$method();
        }

        $data['updated_at']  = $now->format( 'Y-m-d H:i:s' );

        if ( $this->get_id() ) {
            if ( ! is_string( $file ) ) {
                $slug = $repo_class->upload_zip( $file, $this->get_slug(), true );
                
                if ( is_smliser_error( $slug ) ) {
                    return $slug;
                }

                /** @var string $slug  */
                if ( $slug !== $this->get_slug() ) {
                    $data['slug'] = $slug;
                    $this->set_slug( $slug );
                }
            }

            $result = $db->update( $table, $data, array( 'id' => absint( $this->get_id() ) ) );

        } else {
            if ( is_string( $file ) ) {
                return new Exception( 'required_file', __( 'No plugin file provided for upload.', 'smliser' ), array( 'status' => 400 ) );
            }

            $filename   = $this->get_slug() ?: strtolower( str_replace( ' ', '-', $this->get_name() ) );
            $slug       = $repo_class->upload_zip( $file, $filename );

            if ( is_smliser_error( $slug ) ) {
                return $slug;
            }

            /** @var string $slug  */
            $this->set_slug( $slug );

            $data['slug']       = $this->get_slug();
            $data['created_at'] = $now->format( 'Y-m-d H:i:s' );
            $result             = $db->insert( $table, $data );

            $this->set_id( $db->get_insert_id() );
            $this->set_created_at( $now->format( 'Y-m-d H:i:s' ) );
        }

        $this->set_updated_at( $now->format( 'Y-m-d H:i:s' ) );
        $this->set_file( $repo_class->locate( $this->get_slug() ) );
        $repo_class->regenerate_app_dot_json( $this );

        return ( false !== $result ) ? true : new Exception( 'db_insert_error', $db->get_last_error() );


    }

    /**
     * Get a app by it's slug.
     * 
     * @param string $slug The app slug.
     * @return self|null   The app object or null if not found.
     */
    public static function get_by_slug( $slug ) : static|null {
        $db     = smliser_dbclass();
        $table  = static::get_db_table();
        $slug   = basename( $slug, '.zip' );

        if ( ! is_string( $slug ) || empty( $slug ) ) {
            return null;
        }        
    
        $sql    = "SELECT * FROM {$table} WHERE `slug` = ?";
        $result = $db->get_row( $sql, [$slug] );

        if ( ! empty( $result ) ){
            return static::from_array( $result );
        }

        return null;
    }

    /**
     * Load all metadata for this app into internal cache.
     *
     * @return array Loaded metadata in key => value format.
     */
    public function load_meta() : array {
        if ( ! $this->get_id() ) {
            return [];
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_meta_table();
        $app_id     = absint( $this->get_id() );
        $fk_column  = $this->get_meta_foreign_key();

        $sql        = "SELECT `meta_key`, `meta_value` FROM {$table} WHERE `{$fk_column}` = ? ORDER BY `id` ASC";
        $results    = $db->get_results( $sql, [ $app_id ] );

        $meta = [];

        if ( ! empty( $results ) ) {
            foreach ( $results as $row ) {
                $key   = self::sanitize_key( $row['meta_key'] );
                $value = $row['meta_value'];

                if ( is_serialized( $value ) ) {
                    $value = unserialize( $value );
                }

                $meta[ $key ] = $value;
            }
        }

        $this->meta_data = $meta;

        return $meta;
    }

    /**
     * Update existing metadata.
     *
     * @param mixed $key   Meta key.
     * @param mixed $value New value.
     * @return bool True on success, false on failure.
     */
    public function update_meta( $key, $value ) : bool {
        $app_id = absint( $this->get_id() );

        if ( ! $app_id ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_meta_table();
        $fk_column  = $this->get_meta_foreign_key();

        $key   = self::sanitize_key( $key );
        $store = maybe_serialize( $value );

        // Look for existing meta row.
        $meta_id = $db->get_var(
            "SELECT `id` FROM {$table} WHERE `{$fk_column}` = ? AND `meta_key` = ?",
            [ $app_id, $key ]
        );

        if ( empty( $meta_id ) ) {
            // INSERT
            $inserted = $db->insert(
                $table,
                [
                    $fk_column   => $app_id,
                    'meta_key'   => $key,
                    'meta_value' => $store,
                ]
            );

            if ( $inserted !== false ) {
                $this->meta_data[ $key ] = $value;
                return true;
            }

            return false;

        } else {
            // UPDATE existing meta
            $updated = $db->update(
                $table,
                [ 'meta_value' => $store ],
                [
                    $fk_column  => $app_id,
                    'id'        => $meta_id,
                    'meta_key'  => $key,
                ]
            );

            if ( $updated !== false ) {
                $this->meta_data[ $key ] = $value;
                return true;
            }

            return false;
        }
    }

    /**
     * Get the value of a metadata.
     *
     * @param string $meta_key   The meta key.
     * @param mixed  $default_to The fallback value.
     * @return mixed|null
     */
    public function get_meta( $meta_key, $default_to = null ) {
        $meta_key = self::sanitize_text( $meta_key );

        if ( array_key_exists( $meta_key, $this->meta_data ) ) {
            return $this->meta_data[ $meta_key ];
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_meta_table();
        $fk_column  = $this->get_meta_foreign_key();

        $sql    = "SELECT `meta_value` FROM {$table} WHERE `{$fk_column}` = ? AND `meta_key` = ?";
        $params = [ absint( $this->get_id() ), $meta_key ];

        $result = $db->get_var( $sql, $params );

        if ( is_null( $result ) ) {
            $this->meta_data[ $meta_key ] = $default_to;
            return $default_to;
        }

        $value = is_serialized( $result ) ? unserialize( $result ) : $result;

        $this->meta_data[ $meta_key ] = $value;

        return $value;
    }

    /**
     * Delete a metadata.
     *
     * @param string $meta_key The meta key.
     * @return bool True on success, false on failure.
     */
    public function delete_meta( $meta_key ) {
        $app_id = absint( $this->get_id() );

        if ( ! $app_id ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_meta_table();
        $fk_column  = $this->get_meta_foreign_key();
        $meta_key   = self::sanitize_key( $meta_key );

        // Delete from database
        $deleted = $db->delete(
            $table,
            [
                $fk_column => $app_id,
                'meta_key' => $meta_key,
            ]
        );

        if ( $deleted !== false ) {
            // Remove from instance cache if it exists
            if ( isset( $this->meta_data[ $meta_key ] ) ) {
                unset( $this->meta_data[ $meta_key ] );
            }

            return true;
        }

        return false;
    }

    /**
     * Move this app to trash
     * 
     * @return bool
     */
    public function trash() {
        $this->set_status( static::STATUS_TRASH );

        return false === \is_smliser_error( $this->save() );
    }

    /**
     * Get a sample of download URL for licensed app
     */
    public function monetized_url_sample() {
        return sprintf( '%s?download_token=[TOKEN]', $this->get_download_url() );
    }

    /*
    |------------------------
    | SHARED UTILITY METHODS
    |------------------------
    */
        
    /**
     * Get the URL to view the app.
     * 
     * @return string
     */
    public function get_url() {
        $slug   = basename( $this->get_slug(), '.zip' );
        $type   = $this->get_type();
        $url    = new URL( site_url() );
        $url->set_path( $type . '/' . $slug );

        return $url->__toString();
    }

    /**
     * Get the absolute path to the app zip file
     * 
     * @return string|Exception The file path or Exception on failure.
     */
    public function get_zip_file() {
        $file = $this->get_file();
        
        if ( is_array( $file ) && isset( $file['tmp_name'] ) ) {
            return $file['tmp_name'];
        }

        return $file;
    }

    /**
     * Get all available statuses with their labels.
     * 
     * @return array
     */
    public static function get_statuses() {
        return [
            static::STATUS_ACTIVE     => 'Active',
            static::STATUS_INACTIVE   => 'Inactive',
            static::STATUS_SUSPENDED  => 'Suspended',
            static::STATUS_TRASH      => 'Trash',
        ];
    }

    /**
     * The app owner instance
     * 
     * @return ?Owner
     */
    public function get_owner() : ?Owner {
        return Owner::get_by_id( $this->get_owner_id() );
    }

    /*
    |------------------------
    | SHARED CONDITIONAL METHODS
    |------------------------
    */
    /**
     * Check if app is in trash
     * 
     * @return bool
     */
    public function is_trashed() : bool {
        return static::STATUS_TRASH === $this->get_status();
    }

    /**
     * Check if app is active
     * 
     * @return bool
     */
    public function is_active() : bool {
        return static::STATUS_ACTIVE === $this->get_status();
    }


    /**
     * Check if app is inactive
     * 
     * @return bool
     */
    public function is_inactive() : bool {
        return static::STATUS_INACTIVE === $this->get_status();
    }

    /**
     * Check if app is suspended
     * 
     * @return bool
     */
    public function is_suspended() : bool {
        return static::STATUS_SUSPENDED === $this->get_status();
    }

    /**
     * Check if app can be activated
     * 
     * @return bool
     */
    public function can_be_activated() : bool {
        return ! $this->is_active() && ! $this->is_trashed();
    }

    /**
     * Check if app can be restored from trash.
     * 
     * @return bool
     */
    public function can_be_restored() : bool {
        return $this->is_trashed();
    }

    /**
     * Check if app can be trashed.
     * 
     * @return bool
     */
    public function can_be_trashed() : bool {
        return ! $this->is_trashed();
    }
    
    /**
     * Check if this app is monetized.
     * 
     * @return bool true if monetized, false otherwise.
     */
    public function is_monetized() : bool {
        $db         = smliser_dbclass();
        $table_name = SMLISER_MONETIZATION_TABLE;
        $query      = "SELECT COUNT(*) FROM {$table_name} WHERE `app_type` = ? AND `app_id` = ? AND `enabled` = ?";
        $params     = [$this->get_type(), absint( $this->id ), '1'];
        return $db->get_var( $query, $params ) > 0;
    }

    /**
     * Tells if the app exists.
     * 
     * @return bool
     */
    public function exists() : bool {
        return $this->get_id() > 0;
    }

    /**
     * Tells whether this app has an owner.
     * 
     * @return bool
     */
    public function has_owner() : bool {
        return ( $this->get_owner() instanceof Owner );
    }
}