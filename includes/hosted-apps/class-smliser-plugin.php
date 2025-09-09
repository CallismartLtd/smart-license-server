<?php
/**
 * The Smart License Product Class file.
 * 
 * @package Smliser\classes
 */

use SmartLicenseServer\HostedApps\Hosted_Apps_Interface,
SmartLicenseServer\Monetization\Monetization,
SmartLicenseServer\PluginRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Class representation of plugin meta data
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
     * @var array|null $file The plugin file.
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
     */
    public function get_rest_response() {
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
        if ( ! empty( $link ) ) {
            $this->download_link = sanitize_url( $link, array( 'http', 'https' ) );
           
        } else {
            $slug           = $this->get_slug();
            $download_slug  = smliser_get_download_slug();
            $download_link  = site_url( $download_slug  . '/plugins/' . basename( $slug ));

            $this->download_link = sanitize_url( $download_link, array( 'http', 'https' ) );            
        }
    }

    /**
     *  Set the file
     * 
     * @param array|null $file
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
     * @param array $screenshots
     */
    public function set_screenshots( array $screenshots ) {
        $values             = array_values( $screenshots );
        $this->screenshots  = array_map( 'sanitize_url', wp_unslash( $screenshots ) );

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
        return $this->get_section( 'changlog' );
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
     * Get one or more Plugins data by slug.
     * 
     * @param string|float|int $value   The value to search for in the given column.
     * @return self|false  A single instance false for invalid input.
     */

    public static function get_by_slug( $value ) {
        global $wpdb;

        $value = self::normalize_slug( $value );

        if ( ! is_string( $value ) || empty( $value ) ) {
            return false;
        }        
    
        // phpcs:disable 
        $query      = $wpdb->prepare( "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE . " WHERE `slug` = %s", $value );
        $result = $wpdb->get_row( $query, ARRAY_A );
        // phpcs:enable

        if ( ! empty( $result ) ){
            return self::convert_db_result( $result );
        }

        return false;
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
            return self::convert_db_result( $result );
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

        $parsed_args = wp_parse_args( $args, $default_args );

        $offset     = ( absint( $parsed_args['page'] ) - 1 ) * absint( $parsed_args['limit'] );
        $table_name = SMLISER_PLUGIN_ITEM_TABLE;

        $query      = $wpdb->prepare( "SELECT * FROM {$table_name} ORDER BY id ASC LIMIT %d OFFSET %d", absint( $parsed_args['limit'] ), absint( $offset ) );
        $results    = $wpdb->get_results( $query, ARRAY_A );
        $plugins    = array();

        if ( $results ) {

            foreach ( $results as $result ) {
                $plugins[] = self::convert_db_result( $result );
            }
        }

        return $plugins;
    }

    /**
     * Create new plugin or update the existing one.
     * 
     * @return true|WP_Error True on success, false on failure.
     */
    public function save() {
        global $wpdb;

        $file           = $this->file;
        $filename       = strtolower( str_replace( ' ', '-', $this->get_name() ) );
        $smliser_repo   = new PluginRepository();

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

        if ( $this->get_id() ) {
            if ( ! empty( $this->file ) ) {
                $slug = $smliser_repo->upload_zip( $file, $this->get_slug(), true );
                if ( is_wp_error( $slug ) ) {
                    return $slug;
                }
            }

            $plugin_data['last_updated'] = current_time( 'mysql' );
            
            $result = $wpdb->update(
                SMLISER_PLUGIN_ITEM_TABLE,
                $plugin_data,
                array( 'id' => absint( $this->get_id() ) ),
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
                array( '%d' )
            );

        } else {
            if ( empty( $file ) ) {
                return new WP_Error( 'missing_plugin', 'No plugin file found.' );
            }

            $slug = $smliser_repo->upload_zip( $file, $filename );

            if ( is_wp_error( $slug ) ) {
                return $slug;
            }

            $this->set_slug( $slug );

            $plugin_data['slug']        = $this->get_slug();
            $plugin_data['created_at']  = current_time( 'mysql' );
            $result = $wpdb->insert( 
                SMLISER_PLUGIN_ITEM_TABLE, 
                $plugin_data, 
                array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) 
            );

            $this->set_id( $wpdb->insert_id );
        }


        if ( false !== $result ) {
            do_action( 'smliser_plugin_saved', $this );
            delete_transient( 'smliser_total_plugins' );
            return true;
        }

        return new WP_Error( 'db_insert_error', $wpdb->last_error );
    }

    /**
     * Upload a plugin.
     */
    public function update() {
        global $smliser_repo, $wpdb;
        $file = $this->file;
    
        if ( ! empty( $this->file ) ) {
            $slug = $smliser_repo->update_plugin( $file, $this->get_slug() );
            if ( is_wp_error( $slug ) ) {
                return $slug;
            }
        }
    
        // Prepare plugin data.
        $plugin_data = array(
            'name'          => sanitize_text_field( $this->get_name() ),
            'slug'          => sanitize_text_field( $this->get_slug() ),
            'version'       => sanitize_text_field( $this->get_version() ),
            'author'        => sanitize_text_field( $this->get_author() ),
            'author_profile'=> sanitize_url( $this->get_author_profile(), array( 'http', 'https' ) ),
            'requires'      => sanitize_text_field( $this->get_required() ),
            'tested'        => sanitize_text_field( $this->get_tested() ),
            'requires_php'  => sanitize_text_field( $this->get_required_php() ),
            'download_link' => sanitize_url( $this->get_download_link(), array( 'http', 'https' ) ),
            'last_updated'  => current_time( 'mysql' ),
        );
    
        // Database update.
        $result = $wpdb->update(
            SMLISER_PLUGIN_ITEM_TABLE,
            $plugin_data,
            array( 'id' => absint( $this->get_item_id() ) ),
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ),
            array( '%d' )
        );
    
        if ( $result ) {
            do_action( 'smliser_plugin_saved', $this );
            return $this->get_item_id();
        }
        return $result;
    }
    
    /**
     * Delete a plugin.
     */
    public function delete() {
        global $wpdb;

        if ( empty( $this->item_id ) ) {
            return false; // A valid plugin should have an ID.
        }
    
        $smliser_repo = new PluginRepository();
        
        $item_id        = $this->get_item_id();
        $file_delete    = $smliser_repo->delete_from_repo( $this->get_slug() );

        if ( is_wp_error( $file_delete ) ) {
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
     * convert database result to Smliser_plugin
     */
    protected static function convert_db_result( $result ) {
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
         */
        global $smliser_repo;
        $repo_class         = Smliser_Software_Collection::get_app_repository_class( $self->get_type() );
        $plugin_file_path   = $smliser_repo->get_plugin( $self->get_slug() );
        if ( ! is_wp_error( $plugin_file_path ) ) {
            $self->set_file( $plugin_file_path );
        } else {
            $self->set_file( null );
        }

        /** 
         * Section informations 
         */
        $sections = array(
            'description'   => $smliser_repo->get_description( $self->get_slug() ),
            'changelog'     => $smliser_repo->get_changelog( $self->get_slug() ),
            'installation'  => $smliser_repo->get_installation_text( $self->get_slug() ),
        );
        $self->set_section( $sections );

        /**
         * Screenshots
         */
        $self->set_screenshots( $repo_class->get_assets( $self->get_slug(), 'screenshots' ) );

        /**
         *  Set other meta information.
         */
        $self->set_banners( $repo_class->get_assets( $self->get_slug(), 'banners' ) );

        /**
         * Set short description
         */
        $self->short_description = $smliser_repo->get_short_description( $self->get_slug() );

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
     * Determine and return the format of a data.
     * 
     * @param mixed $data Unknown data.
     * @return $format The correct data format for DB insertion or update.
     */
    protected function get_data_format( $data ) {
        if ( is_int( $data ) ) {
            return '%d';
        } elseif ( is_float( $data ) ) {
            return '%f';
        } else {
            return '%s';
        }
    }

    /**
     * Format response for plugin update.
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
            'homepage'          => $this->get_url(),
            'package'           => $this->get_download_link(),
            'banners'           => $this->get_banners(),
            'screenshots'       => $this->get_screenshots(),
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
    public function is_monetized() {
        global $wpdb;
        $table_name = SMLISER_MONETIZATION_TABLE;
        $query = $wpdb->prepare( "SELECT COUNT(*) FROM {$table_name} WHERE `item_type` = %s AND `item_id` = %d AND `enabled` = %s", 'plugin', absint( $this->item_id ), '1' );

        return $wpdb->get_var( $query ) > 0; // phpcs:disable
    }


    /**
     * Check if a given plugin is licensed.
     * 
     * @return bool true if plugin is licensed, false otherwise.
     */
    public function is_licensed() {
        if ( empty( $this->item_id ) ) {
            return false; // Plugin must exist.
        }
        
        global $wpdb;
        $table_name = SMLISER_LICENSE_TABLE;
        $query      = $wpdb->prepare( "SELECT `item_id` FROM {$table_name} WHERE `item_id` = %d", absint( $this->item_id ) );
        $result     = $wpdb->get_var( $query ); // phpcs:disable

        return ! empty( $result );
    }

    /**
     * Get a sample of download URL for licensed plugin
     */
    public function licensed_download_url() {
        $download_url   = add_query_arg( array( 'download_token' => '{token}' ), $this->get_download_url() );
        return $download_url;
    }

    /**
     * Normalize a plugin slug as plugin-slug/plugin-slug.
     * 
     * @param string $slug The slug.
     */
    public static function normalize_slug( $slug ) {
        if ( empty( $slug ) || ! is_string( $slug ) ) {
            return '';
        }

        // Check whether the slug contains forward slash
        $parts = explode( '/', $slug );

        if ( 1 === count( $parts ) ) {
            if ( str_contains( $parts[0], '.' ) ) {
                $slug = substr( $parts[0], 0, strpos( $parts[0], '.' ) );
            }

            $slug = trailingslashit( $slug ) . $slug;
        } elseif ( count( $parts ) >= 2) {
            if ( $parts[1] !== $parts[0] ) {
                // assumming the first string is actual slug.
                $slug = trailingslashit ( $parts[0] ) . $parts[0];
            }
        }
        
        if ( ! str_ends_with( $slug, '.zip' ) ) {
            if ( str_contains( $slug, '.' ) ) {
                $slug = substr( $slug, 0, strpos( $slug, '.' ) );
            }
            
            $slug = $slug . '.zip';
        }

        return sanitize_and_normalize_path( $slug );
    }

    /*
    |-------------------------------
    | ACTION HANDLERS / CONTROLLERS
    |-------------------------------
    */

    /**
     * Plugin ajax action handler
     */
    public static function action_handler() {
        if ( ! check_ajax_referer( 'smliser_nonce', 'security', false ) ) {
            wp_send_json_error( array( 'message' => 'This action failed basic security check' ), 401 );
        }

        if ( ! current_user_can( 'install_plugins' ) ) {
            wp_send_json_error( array( 'message' => 'You do not have the required permission to do this.' ), 403 );

        }
        
        $item_id = isset( $_GET['item_id'] ) ? absint( $_GET['item_id'] ) : 0;
        $action  = isset( $_GET['real_action'] ) ? sanitize_text_field( $_GET['real_action'] ) : '';

        if ( empty( $item_id ) ) {
            return;
        }

        $obj    = new self();
        $self   = $obj->get_plugin( absint( $item_id ) );
        
        if ( empty( $self ) ) {
            return;
        }

        switch ( $action ) {
            case 'delete':
                $result = $self->delete();
                $message = 'Plugin deleted';
                break;
        }

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array( 'message' => $message, 'redirect_url' => smliser_repo_page() ) );
       
    }

    /**
     * Form controller.
     */
    public static function plugin_upload_controller () {
        if ( isset( $_POST['smliser_plugin_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_plugin_form_nonce'] ) ), 'smliser_plugin_form_nonce' ) ) {
            $is_new     = isset( $_POST['smliser_plugin_upload_new'] );
            $is_update  = isset( $_POST['smliser_plugin_upload_update'] );

            if ( $is_new ) {
                $self = new self();
            } elseif ( $is_update ) {
                $id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
                $self = self::get_plugin( $id );
            }

            $file = isset( $_FILES['smliser_plugin_file'] ) && UPLOAD_ERR_OK === $_FILES['smliser_plugin_file']['error'] ? $_FILES['smliser_plugin_file'] : null;
            $self->set_file( $file );
            $self->set_name( isset( $_POST['smliser_plugin_name']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_name'] ) ) : '' );
            $self->set_author( isset( $_POST['smliser_plugin_author']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_author'] ) ) : '' );
            $self->set_author_profile( isset( $_POST['smliser_plugin_author_profile']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_author_profile'] ) ) : '' );
            $self->set_required( isset( $_POST['smliser_plugin_requires']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_requires'] ) ) : '' );
            $self->set_tested( isset( $_POST['smliser_plugin_tested']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_tested'] ) ) : '' );
            $self->set_required_php( isset( $_POST['smliser_plugin_requires_php']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_requires_php'] ) ) : '' );
            $self->set_version( isset( $_POST['smliser_plugin_version']  ) ? sanitize_text_field( wp_unslash( $_POST['smliser_plugin_version'] ) ) : '' );
            $self->set_download_link( isset( $_POST['smliser_plugin_download_link']  ) ? sanitize_url( wp_unslash( $_POST['smliser_plugin_download_link'] ), array( 'http', 'https' ) ) : '' );

            if ( $is_new ) {
                $item_id = $self->save();
                if ( is_wp_error( $item_id ) ) {
                    set_transient( 'smliser_form_validation_message', $item_id->get_error_message(), 15 );
                    wp_safe_redirect( smliser_admin_repo_tab() );
                    exit;
                }
                set_transient( 'smliser_form_success', true, 4 );
                wp_safe_redirect( smliser_admin_repo_tab( 'edit', $item_id ) );
                exit;
            }
            
            if ( $is_update ) {
                $self->update_meta( 'support_url', isset( $_POST['smliser_plugin_support_url'] ) ? sanitize_url( wp_unslash( $_POST['smliser_plugin_support_url'] ), array( 'http', 'https' ) ) : '' );
                $update = $self->save();
                if ( is_wp_error( $update ) ) {
                    set_transient( 'smliser_form_validation_message', $update->get_error_message(), 5 );
                } else {
                    set_transient( 'smliser_form_success', true, 4 );

                }
                
                wp_safe_redirect( smliser_admin_repo_tab( 'edit', $id ) );
                exit;
            }

        }
        wp_safe_redirect( smliser_admin_repo_tab() );
        exit;
    }

}