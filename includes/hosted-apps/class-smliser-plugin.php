<?php
/**
 * The Smart License Product Class file.
 * 
 * @package Smliser\classes
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\HostedApps\Hosted_Apps_Interface;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\PluginRepository;
use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Represents a typical plugin hosted in this repository.
 */
class Smliser_Plugin implements Hosted_Apps_Interface {
    /**
     * A singleton instance of this class
     */
    protected static $instance = null;

    /**
     * Item ID.
     * @var int $item_id The plugin ID.
     */
    protected $item_id = 0;

    /**
     * The name of the plugin..
     * 
     * @var string $name The plugin's name.
     */
    protected $name = '';
        
    /**
     * License key for the plugin.
     * 
     * @var string $license_key
     */
    protected $license_key = '';

    /**
     * Plugin slug.
     * 
     * @var string $slug Plugin slug.
     */
    protected $slug = '';

    /**
     * Plugin Version.
     * 
     * @var string $version The current version of the plugin.
     */
    protected $version = '';

    /**
     * Author .
     * 
     * @var string $author The author of the plugin
     */
    protected $author = '';

    /**
     * Author profile.
     * 
     * @var string $author_profile The author's profile link or information.
     */
    protected $author_profile = '';

    /**
     * WordPress Version requirement.
     * 
     * @var string $requires The minimum WordPress version required to run the plugin.
     */
    protected $requires = '';

    /**
     * Version Tested up to.
     * 
     * @var string $tested The latest WordPress version the plugin has been tested with.
     */
    protected $tested = '';

    /**
     * PHP version requirement.
     * 
     * @var string $requires_php The minimum PHP version required to run the plugin.
     */
    protected $requires_php = '';

    /**
     * Update time.
     * 
     * @var string $last_updated The last time the plugin was updated
     */
    protected $last_updated = '';

    /**
     * Date created
     * 
     * @var string $created_at When plugin was created.
     */
    protected $created_at;

    /**
     * An array of different sections of plugin information (e.g., description, installation, FAQ).
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
     * Short description.
     * 
     * @var string $short_description A brief description of the plugin.
     */
    protected $short_description = '';

    /**
     * An array of different plugin screenshots.
     * 
     * @var array $screenshots
     */
    protected $screenshots = array();

    /**
     * An array of of plugin icons.
     * 
     * @var array $screenshots
     */
    protected $icons = array();

    /**
     * An array of banner images for the plugin..
     * 
     * @var array $banners
     */
    protected $banners = array(
        'high'    => '',
        'low'    => '',
    );

    /**
     *  An array of plugin ratings.
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
     * The plugin homepage URL
     * 
     * @var string $homepage
     */
    protected $homepage = '#';

    /**
     * Plugin zip file.
     * 
     * @var string|array $file The absolute path to the plugin zip file or an array of uploaded file data.
     */
    protected $file;

    /**
     * Class constructor.
     */
    public function __construct() {}

    /**
     * Instanciate a single instance of this class
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get application type
     * @return string
     */
    public function get_type() {
        return 'plugin';
    }

    /**
     * Get the application REST API response data.
     * 
     * @return array
     */
    public function get_rest_response() : array {
        return $this->formalize_response();
    }

    /**
     * Get short description
     * 
     * @return string
     */
    public function get_short_description() {
        return $this->short_description;
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
     * Get the absolute path to the plugin zip file
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

        return new Exception( 'file_not_found', __( 'Plugin file not found.', 'smliser' ), array( 'status' => 404 ) );
    }

    /*
    |----------
    | SETTERS
    |----------
    */

    /**
     * Set Item ID
     * 
     * @param int $id The database id.
     */
    public function set_id( $id  ) {
        $this->item_id = absint( $id );
    }

    /**
     * Set Item ID
     * 
     * @param int $item_id The database id.
     */
    public function set_item_id( $item_id  ) {
        $this->set_id( $item_id );
    }

    /**
     * Set plugin name
     * 
     * @param string $name The plugin name
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( $name );
    }

    /**
     * Set license key.
     * 
     * @param string $license_key
     */
    public function set_license_key( $license_key ) {
        $this->license_key = sanitize_text_field( $license_key );
    }

    /**
     * Set Slug.
     * 
     * @param string $slug Plugin slug from repository.
     */
    public function set_slug( $slug ) {
        $this->slug = sanitize_text_field( $slug );
    }

    /**
     * Set version
     * 
     * @param string $version Plugin version.
     */
    public function set_version( $version ) {
        $this->version = sanitize_text_field( $version );
    }

    /**
     * Set Author
     * 
     * @param string $author The author name.
     */
    public function set_author( $author ) {
        $this->author = sanitize_text_field( $author );
    }

    /**
     * Set the author profile.
     * 
     * @param string $url   URL for the profile
     */
    public function set_author_profile( $url ) {
        $this->author_profile = sanitize_text_field( $url );
    }

    /**
     * Set WordPress required version for plugin.
     * 
     * @param string $wp_version.
     */
    public function set_required( $wp_version ) {
        $this->requires = sanitize_text_field( $wp_version );
    }

    /**
     * Set WordPress version tested up to.
     * 
     * @param string $version
     */
    public function set_tested( $version ) {
        $this->tested = sanitize_text_field( $version );
    }

    /**
     * Set the required PHP version.
     * 
     * @param string $version
     */
    public function set_required_php( $version ) {
        $this->requires_php = sanitize_text_field( $version );
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
     * Set download url
     * 
     * @param string $url
     */
    public function set_download_url( $url = '' ) {
        $this->set_download_link( $url );
    }

    /**
     * Set Download link.
     * 
     * @param string $link The download link.
     */
    public function set_download_link( $link = '' ) {
        if ( ! empty( $link ) && '#' !== $link ) {
            $this->download_link = sanitize_url( $link, array( 'http', 'https' ) );
           
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
     *  Set the file
     * 
     * @param array|string $file
     */
    public function set_file( $file ) {
        $this->file = $file;
    }

    /**
     * Set Section
     * 
     * @param array $section_data An associative array containing each section information.
     * @see Smliser_Plugin::$sections.
     */
    public function set_section( $section_data ) {
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
     * Set app homepage.
     * 
     * @param string $url
     */
    public function set_homepage( $url ) {
        $this->homepage = sanitize_url( $url, array( 'https', 'http' ) );
    }
    
    /**
     * Set Banners
     * 
     * @param array $banners
     */
    public function set_banners( $banners ) {
        $this->banners['low']   = $banners['low'] ?? '';
        $this->banners['high']  = $banners['high'] ?? '';
    }

    /**
     * Set plugin screenshots
     * 
     * @param array $screenshots Array of screenshots in the form:
     * [
     *   '1' => [
     *      'src'     => 'https://example.com/screenshot-1.png',
     *      'caption' => 'Screenshot caption'
     *   ],
     *   ...
     * ]
     */
    public function set_screenshots( array $screenshots ) {
        foreach ( $screenshots as $key => &$screenshot ) {
            if ( isset( $screenshot['src'] ) ) {
                $screenshot['src'] = esc_url_raw( $screenshot['src'] );
            }

            if ( isset( $screenshot['caption'] ) ) {
                $screenshot['caption'] = sanitize_text_field( $screenshot['caption'] );
            }
        }

        $this->screenshots = $screenshots;
    }

    /**
     * Set plugin icons
     * 
     * @param array $icons
     */
    public function set_icons( array $icons ) {
        $this->icons    = array_map( 'sanitize_url', unslash( $icons ) );

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

    /*
    |----------
    | GETTERS.
    |----------
    */

    /**
     * Get the application unique identifier.
     * 
     * @return int
     */
    public function get_id() {
        return $this->item_id;
    }

    /**
     * Get Item ID
     */
    public function get_item_id() {
        return $this->get_id();
    }

    /**
     * Get plugin name
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get Slug.
     */
    public function get_slug() {
        return $this->slug;
    }

    /**
     * Get version
     */
    public function get_version() {
        return $this->version;
    }

    /**
     * Get Author
     */
    public function get_author() {
        return $this->author;
    }

    /**
     * Get the author profile.
     */
    public function get_author_profile() {
        return $this->author_profile;
    }

    /**
     * Get WordPress required version for plugin.
     */
    public function get_required() {
        return $this->requires;
    }

    /**
     * Get WordPress version tested up to.
     */
    public function get_tested() {
        return $this->tested ;
    }

    /**
     * Get the required PHP version.
     */
    public function get_required_php() {
        return $this->requires_php;
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
     * Get Download URL.
     */
    public function get_download_url() {
        return $this->download_link;
    }

    /**
     * Get Download link.
     */
    public function get_download_link() {
        return $this->get_download_url();
    }

    /**
     * Get Banners
     * 
     * @return array $banners
     */
    public function get_banners() {
        return $this->banners;
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
     * Get license key.
     * 
     * @return string
     */
    public function get_license_key() {
        return $this->license_key;
    }

    /**
     * Get the URL to view the plugin.
     */
    public function get_url() {
        $slug       = $this->get_slug();
        $slug_parts = explode( '/', $slug );
        $url        = site_url( 'plugins/'. $slug_parts[0] );
        return esc_url_raw( $url );

    }

    /**
     * Get a plugin section information
     * 
     * @param string $name  Section name.
     * @return string The particular section name
     */
    public function get_section( $name ) {
        return isset($this->sections[$name] ) ? $this->sections[$name] : '';
    }

    /**
     * Get plugin descripion
     * 
     * @return string a parsed HTML string from the readme file.
     */
    public function get_description() {
        return $this->get_section( 'description' );
    }

    /**
     * Get the plugin installation text.
     * 
     * @return string a parsed HTML string from the readme file.
     */
    public function get_installation() {
        return $this->get_section( 'installation' );
    }

    /**
     * Get the plugin changelog from the readme.txt file.
     * 
     * @return string a parsed HTML string from the readme file.
     */
    public function get_changelog() {
        return $this->get_section( 'changelog' );
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
     * Get support url
     */
    public function get_support_url() {
        return $this->get_meta( 'support_url', '' );
    }

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Get a plugin by slug.
     * 
     * @param string $value The  plugin slug.
     * @return self|null    The plugin object or null if not found.
     */

    public static function get_by_slug( $value ) {
        global $wpdb;

        /**
         * @var wpdb $wpdb
         */

        $value = basename( $value, '.zip' );

        if ( ! is_string( $value ) || empty( $value ) ) {
            return null;
        }        
    
        // phpcs:disable 
        $query      = $wpdb->prepare( "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE . " WHERE `slug` = %s", $value );
        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable

        if ( ! empty( $result ) ){
            return self::from_array( $result );
        }

        return null;
    }

    /**
     * Get a plugin by it's ID.
     * 
     * @param int $id The plugin ID.
     * @return self|null
     */
    public static function get_plugin( $id = 0 ) {
        $id = absint( $id );
        if ( empty( $id ) ) {
            return null;
        }

        // phpcs:disable
        global $wpdb;
        $query  = $wpdb->prepare( "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE . " WHERE `id` = %d", $id );
        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable
        if ( $result ) {
            return self::from_array( $result );
        }

        return null;
    }

    /**
     * Get all plugins.
     * 
     * @param array $args Optional arguments for querying plugins.
     * @return self[] An array of Smliser_Plugin objects.
     */
    public static function get_plugins( $args = array() ) {
        global $wpdb;

        $default_args = array(
            'page'      => 1,
            'limit'     => 25,
        );

        $parsed_args = parse_args( $args, $default_args );

        $offset     = ( absint( $parsed_args['page'] ) - 1 ) * absint( $parsed_args['limit'] );
        $table_name = SMLISER_PLUGIN_ITEM_TABLE;

        $query      = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id ASC LIMIT %d OFFSET %d", absint( $parsed_args['limit'] ), absint( $offset ) );
        $results    = $wpdb->get_results( $query, ARRAY_A );
        $plugins    = array();

        if ( $results ) {

            foreach ( $results as $result ) {
                $plugins[] = self::from_array( $result );
            }
        }

        return $plugins;
    }

    /**
     * Create new plugin or update the existing one.
     * 
     * @return true|Exception True on success, false on failure.
     */
    public function save() {
        global $wpdb;

        $file           = $this->file;
        $filename       = strtolower( str_replace( ' ', '-', $this->get_name() ) );
        $repo_class     = new PluginRepository();

        $plugin_data = array(
            'name'          => $this->get_name(),
            'version'       => $this->get_version(),
            'author'        => $this->get_author(),
            'author_profile'=> $this->get_author_profile(),
            'requires'      => $this->get_required(),
            'tested'        => $this->get_tested(),
            'requires_php'  => $this->get_required_php(),
            'download_link' => $this->get_download_link(),
        );

        $data_formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

        if ( $this->get_id() ) {
            if ( is_array( $this->file ) ) {
                $slug = $repo_class->upload_zip( $file, $this->get_slug(), true );
                if ( is_smliser_error( $slug ) ) {
                    return $slug;
                }

                if ( $slug !== $this->get_slug() ) {
                    $plugin_data['slug'] = $slug;
                    $data_formats[]      = '%s';
                    $this->set_slug( $slug );
                }
            }

            $plugin_data['last_updated']    = current_time( 'mysql' );
            $data_formats[]                 = '%s';
            
            $result = $wpdb->update(
                SMLISER_PLUGIN_ITEM_TABLE,
                $plugin_data,
                array( 'id' => absint( $this->get_id() ) ),
                $data_formats,
                array( '%d' )
            );

        } else {
            if ( ! is_array( $file ) ) {
                return new Exception( 'no_file_provided', __( 'No plugin file provided for upload.', 'smliser' ), array( 'status' => 400 ) );
            }

            $slug = $repo_class->upload_zip( $file, $filename );

            if ( is_smliser_error( $slug ) ) {
                return $slug;
            }

            $this->set_slug( $slug );

            $plugin_data['slug']        = $this->get_slug();
            $plugin_data['created_at']  = current_time( 'mysql' );

            $data_formats = $data_formats + array( '%s', '%s' );

            $result = $wpdb->insert( 
                SMLISER_PLUGIN_ITEM_TABLE, 
                $plugin_data, 
                $data_formats
            );

            $this->set_id( $wpdb->insert_id );
        }


        if ( false !== $result ) {
            do_action( 'smliser_plugin_saved', $this );
            delete_transient( 'smliser_total_plugins' );
            return true;
        }

        return new Exception( 'db_insert_error', $wpdb->last_error );
    }

    /**
     * Delete a plugin.
     */
    public function delete() {
        global $wpdb;

        if ( empty( $this->item_id ) ) {
            return false; // A valid plugin should have an ID.
        }
    
        $repo_class = new PluginRepository();
        
        $item_id        = $this->get_item_id();
        $file_delete    = $repo_class->delete_from_repo( $this->get_slug() );

        if ( is_smliser_error( $file_delete ) ) {
            return $file_delete;
        }

        $plugin_deletion    = $wpdb->delete( SMLISER_PLUGIN_ITEM_TABLE, array( 'id' => $item_id ), array( '%d' ) );
        $meta_deletion      = $wpdb->delete( SMLISER_PLUGIN_META_TABLE, array( 'plugin_id' => $item_id ), array( '%d' ) );

        return $plugin_deletion && $meta_deletion !== false;
    }

    /**
     * Add a new metadata.
     * 
     * @param mixed $key Meta Key.
     * @param mixed $value Meta value.
     * @return bool True on success, false on failure.
     */
    public function add_meta( $key, $value ) {
        global $wpdb;

        // Sanitize inputs
        $item_id    = absint( $this->get_item_id() );
        $meta_key   = sanitize_text_field( $key );
        $meta_value = sanitize_text_field( is_array( $value ) ? maybe_serialize( $value ) : $value );

        // Prepare data for insertion
        $data = array(
            'plugin_id'    => $item_id,
            'meta_key'      => $meta_key,
            'meta_value'    => $meta_value,
        );

        $data_format = array( '%d', '%s', '%s' );

        $result = $wpdb->insert( SMLISER_PLUGIN_META_TABLE, $data, $data_format );
        return $result !== false;

        return false;
    }

    /**
     * Update existing metadata
     * 
     * @param mixed $key Meta key.
     * @param mixed $value New value.
     * @return bool True on success, false on failure.
     */
    public function update_meta( $key, $value ) {
        if ( ! $this->get_item_id() ) {
            return false;
        }
        global $wpdb;

        $table_name = SMLISER_PLUGIN_META_TABLE;
        $key        = sanitize_text_field( $key );
        $value      = sanitize_text_field( is_array( $value ) ? maybe_serialize( $value ) : $value );

        // Prepare data for insertion/update.
        $data = array(
            'plugin_id'     => absint( $this->get_item_id() ),
            'meta_key'      => $key,
            'meta_value'    => $value,
        );

        $data_format = array( '%d', '%s', '%s' );

        // Check if the meta_key already exists for the given plugin ID
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT 1 FROM {$table_name} WHERE plugin_id = %d AND meta_key = %s",
            absint( $this->get_item_id() ),
            $key
        ) );

        if ( ! $exists ) {
            // Insert new record if it doesn't exist
            $inserted = $wpdb->insert( $table_name, $data, $data_format );

            return $inserted !== false;
        } else {
            // Update existing record
            $updated = $wpdb->update(
                $table_name,
                array( 'meta_value' => $value ),
                array(
                    'plugin_id' => absint( $this->get_item_id() ),
                    'meta_key'   => $key,
                ),
                array( '%s' ),
                array( '%d', '%s' )
            );

            return $updated !== false;
        }
    }

    /**
     * Get the value of a metadata
     * 
     * @param $meta_key The meta key.
     * @param $default_to What to return when nothing is found.
     * @return mixed|null $value The value.
     */
    public function get_meta( $meta_key, $default_to = null ) {
        global $wpdb;

        $query  = $wpdb->prepare( 
            "SELECT `meta_value` FROM " . SMLISER_PLUGIN_META_TABLE . " WHERE `plugin_id` = %d AND `meta_key` = %s", 
            absint( $this->get_item_id() ), 
            sanitize_text_field( $meta_key )
        );

        $result = $wpdb->get_var( $query );

        if ( is_null( $result ) ) {
            return $default_to;
        }
        return is_serialized( $result ) ? unserialize( $result ) : $result;
    }

    /**
     * Delete a metadata.
     * 
     * @param string $meta_key The meta key.
     * @return bool True on success, false on failure.
     */
    public function delete_meta( $meta_key ) {
        global $wpdb;

        $item_id    = absint( $this->get_item_id() );
        $meta_key   = sanitize_text_field( $meta_key );
        $where      = array(
            'plugin_id' => $item_id,
            'meta_key'  => $meta_key
        );

        $where_format = array( '%d', '%s' );

        // Execute the delete query
        $deleted = $wpdb->delete( SMLISER_PLUGIN_META_TABLE, $where, $where_format );

        // Return true on success, false on failure
        return $deleted !== false;
    }

    /*
    |---------------
    |UTILITY METHODS
    |---------------
    */

    /**
     * Converts associative array to object of this class.
     */
    public static function from_array( $result ) {
        $self = new self();
        $self->set_item_id( $result['id'] ?? 0 );
        $self->set_name( $result['name'] ?? '' );
        $self->set_slug( $result['slug'] ?? '' );
        $self->set_version( $result['version'] ?? '' );
        $self->set_author( $result['author'] ?? '' );
        $self->set_author_profile( $result['author_profile'] ?? '' );
        $self->set_required( $result['requires'] ?? '' );
        $self->set_tested( $result['tested'] ?? '' );
        $self->set_required_php( $result['requires_php'] ?? '' );
        $self->set_download_link( $result['download_link'] ?? '' );
        $self->set_created_at( $result['created_at'] ?? '' );
        $self->set_last_updated( $result['last_updated'] ?? '' );
        $self->set_homepage( $self->get_meta( 'homepage_url', '#' ) );
        
        /** 
         * Set file information
         * 
         * @var SmartLicenseServer\PluginRepository $repo_class
         */
        $repo_class         = Smliser_Software_Collection::get_app_repository_class( $self->get_type() );
        $plugin_file_path   = $repo_class->locate( $self->get_slug() );
        if ( ! is_smliser_error( $plugin_file_path ) ) {
            $self->set_file( $plugin_file_path );
        } else {
            $self->set_file( null );
        }

        /** 
         * Section informations 
         */
        $sections = array(
            'description'   => $repo_class->get_description( $self->get_slug() ),
            'changelog'     => $repo_class->get_changelog( $self->get_slug() ),
            'installation'  => $repo_class->get_installation( $self->get_slug() ),
            'screenshots'   => $repo_class->get_screenshot_html( $self->get_slug() ),
        );
        $self->set_section( $sections );

        /**
         * Icons
         */
        $self->set_icons( $repo_class->get_assets( $self->get_slug(), 'icons' ) );

        /**
         * Screenshots
         */
        $self->set_screenshots( $repo_class->get_screenshots( $self->get_slug() ) );

        /**
         *  Banners.
         */
        $self->set_banners( $repo_class->get_assets( $self->get_slug(), 'banners' ) );

        /**
         * Set short description
         */
        $self->short_description = $repo_class->get_short_description( $self->get_slug() );

        $self->set_ratings( $self->get_meta( 'ratings', 
            array(
                '5' => 0,
                '4' => 0,
                '3' => 0,
                '2' => 0,
                '1' => 0,
            )
        ));

        $self->set_num_ratings( $self->get_meta( 'num_ratings', 0 ) );
        $self->set_active_installs( $self->get_meta( 'active_installs', 0 ) );

        return $self;
    }

    /**
     * Format response for plugin REST API response.
     * 
     * @return array
     */
    public function formalize_response() {
        $pseudo_slug    = explode( '/', $this->get_slug() )[0];
        $data = array(
            'name'              => $this->get_name(),
            'type'              => $this->get_type(),
            'slug'              => $pseudo_slug ,
            'version'           => $this->get_version(),
            'author'            => '<a href="' . esc_url( $this->get_author_profile() ) . '">' . $this->get_author() . '</a>',
            'author_profile'    => $this->get_author_profile(),
            'homepage'          => $this->get_homepage(),
            'package'           => $this->get_download_link(),
            'banners'           => $this->get_banners(),
            'screenshots'       => $this->get_screenshots(),
            'icons'             => $this->get_icons(),
            'requires'          => $this->get_required(),
            'tested'            => $this->get_tested(),
            'requires_php'      => $this->get_required_php(),
            'requires_plugins'  => [],
            'added'             => $this->get_date_created(),
            'last_updated'      => $this->get_last_updated(),
            'short_description' => $this->get_short_description(),
            'sections'          => $this->get_sections(),
            'num_ratings'       => $this->get_num_ratings(),
            'rating'            => $this->get_average_rating(),
            'ratings'           => $this->get_ratings(),
            'support_url'       => $this->get_support_url(),
            'active_installs'   => $this->get_active_installs(),
            'is_monetized'       => $this->is_monetized(),
            'monetization'      => [],
        );

        if ( $this->is_monetized() ) {
            $monetization = Monetization::get_by_item( $this->get_type(), $this->get_id() );

            $data['monetization'] = $monetization->to_array() ?: [];
        }

        return $data;
    }

    /**
     * Check if this plugin is monetized.
     * 
     * @return bool true if monetized, false otherwise.
     */
    public function is_monetized() : bool {
        global $wpdb;
        $table_name = SMLISER_MONETIZATION_TABLE;
        $query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE `item_type` = %s AND `item_id` = %d AND `enabled` = %s", $this->get_type(), absint( $this->item_id ), '1' );

        return $wpdb->get_var( $query ) > 0; // phpcs:disable
    }

    /**
     * Get a sample of download URL for licensed plugin
     */
    public function monetized_url_sample() {
        return sprintf( '%s?download_token={token}', $this->get_download_url() );
    }

}