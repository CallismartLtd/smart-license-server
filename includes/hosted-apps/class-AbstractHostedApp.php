<?php
/**
 * AbstractHostedApp class file
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 * @package SmartLicenseServer\HostedApps
 */
namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Core\URL;

defined( 'ABSPATH' ) || exit;

/**
 * Abstract Hosted Application Base Class
 * Provides shared implementation for Hosted_Apps_Interface.
 * 
 * @package SmartLicenseServer\HostedApps
 */
abstract class AbstractHostedApp implements Hosted_Apps_Interface {

    /**
     * App database ID
     * 
     * @var int $id The app ID.
     */
    protected $id = 0;

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
    protected $status = 'active';

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
     * @var string $last_updated The last time the app was updated
     */
    protected $last_updated = '';

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
        'changelog'     => '',
        'screenshots'   => '',
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
     * @var string|array $file The absolute path to the app zip file or an array of uploaded file data.
     */
    protected $file;

    /**
     * The app tags.
     * 
     * @var array $tags
     */
    protected $tags = [];

    /**
     * App meta data
     * 
     * @var array $meta_data
     */
    protected $meta_data = [];

    /**
     * App trash status
     * 
     * @var string
     */
    const STATUS_TRASH = 'trash';

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
     * Set the app name
     * 
     * @param string $name
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( unslash( $name ) );
    }

    /**
     * Set the slug
     * 
     * @param string $slug
     */
    public function set_slug( $slug ) {
        $this->slug = sanitize_text_field( unslash( $slug ) );
    }

    /**
     * Set the app status
     * 
     * @param string $status
     */
    public function set_status( $status ) {
        $this->status = \sanitize_text_field( \unslash( $status ) );
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
        $this->homepage = sanitize_url( $url, array( 'https' ) );
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
        $this->author_profile = sanitize_url( $url );
    }

    /**
     * Set app version
     * 
     * @param string $version
     */
    public function set_version( $version ) {
        $this->version = sanitize_text_field( unslash( $version ) );
    }
    /**
     * Set Download url.
     * 
     * @param string $link The download url.
     */
    public function set_download_url( $link = '' ) {
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

            $this->download_link = sanitize_url( $download_link, array( 'http', 'https' ) );            
        }
    }

    /**
     * Set last updated
     * 
     * @param $date
     */
    public function set_last_updated( $date ) {
        $this->last_updated = sanitize_text_field( $date );
    }

    /**
     * Set When created
     * 
     * @param $date
     */
    public function set_created_at( $date ) {
        $this->created_at = sanitize_text_field( $date );
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
    }

    /**
     *  Set the file
     * 
     * @param array|string $file
     */
    public function set_file( $file ) {
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
        $this->tags = array_map( 'sanitize_text_field', $tags );
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
     * Get the application homepage URL.
     * 
     * @return string
     */
    public function get_homepage() {
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
     * @return array $screenshots
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
     * Get the file
     */
    public function get_file() {
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
    public function get_last_updated() {
        return $this->last_updated;
    }
    /**
     * Get when updated
     */
    public function get_date_created() {
        return $this->created_at;
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

    /**
     * Get an instance of this class from an array
     * @param array $data
     */
    abstract public static function from_array( $data ) : static;

    /**
    |---------------------
    | SHARED CRUD METHODS
    |---------------------
    */

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
                $key   = sanitize_key( $row['meta_key'] );
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
    public function update_meta( $key, $value ) {
        $app_id = absint( $this->get_id() );

        if ( ! $app_id ) {
            return false;
        }

        $db         = smliser_dbclass();
        $table      = static::get_db_meta_table();
        $fk_column  = $this->get_meta_foreign_key();

        $key   = sanitize_key( $key );
        $store = maybe_serialize( $value );

        // Look for existing meta row
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
        $meta_key = sanitize_text_field( $meta_key );

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
        $meta_key   = sanitize_key( $meta_key );

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
        $this->set_status( self::STATUS_TRASH );

        return false === \is_smliser_error( $this->save() );
    }

    /**
     * Check if this app is monetized.
     * 
     * @return bool true if monetized, false otherwise.
     */
    public function is_monetized() : bool {
        $db         = smliser_dbclass();
        $table_name = SMLISER_MONETIZATION_TABLE;
        $query      = "SELECT COUNT(*) FROM {$table_name} WHERE `item_type` = ? AND `item_id` = ? AND `enabled` = ?";
        $params     = [$this->get_type(), absint( $this->id ), '1'];
        return $db->get_var( $query, $params ) > 0;
    }

    /**
     * Get a sample of download URL for licensed app
     */
    public function monetized_url_sample() {
        return sprintf( '%s?download_token={token}', $this->get_download_url() );
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
        if ( ! is_array( $file ) ) {
            return $file;
        }

        if ( is_array( $file ) && isset( $file['tempname'] ) ) {
            return $file['tempname'];
        }

        return new Exception( 'file_not_found', \sprintf( '%s zip file not found', $this->get_type() ), array( 'status' => 404 ) );
    }

}