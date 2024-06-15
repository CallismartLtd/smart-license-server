<?php
/**
 * The Smart License Product Class file.
 * 
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class representation of plugin meta data
 */
class Smliser_Plugin {

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
        '2x'    => '',
        '1x'    => '',
    );

    /**
     * Icons
     */
    protected $icons = array(
        '2x'    => '',
        '1x'    => '',
    );

    /**
     * Download link.
     * 
     * @var $download_link The file url.
     */
    protected $download_link = '#';

    /**
     * Plugin zip file.
     * 
     * @var string $file The plugin file.
     */
    protected $file;

    /**
     * Class constructor.
     */
    public function __construct() {}

    /*
    |----------
    | SETTERS
    |----------
    */

    /**
     * Set Item ID
     * 
     * @param int $item_id The database id.
     */
    public function set_item_id( $item_id  ) {
        $this->item_id = absint( $item_id );
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
     * Set Download link.
     * 
     * @param string $slug the download query vars
     */
    public function set_download_link( $slug ) {
        $this->download_link = sanitize_text_field( $slug );
    }

    /**
     *  Set the file
     * 
     * @param array $file
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
            $this->sections['description'] = sanitize_text_field( $section_data['description'] );
        } 
        
        if ( isset( $section_data['installation'] ) ) {
            $this->sections['installation'] = sanitize_text_field( $section_data['installation'] );
        } 
        
        if ( isset( $section_data['changelog'] ) ) {
            $this->sections['changelog'] = sanitize_text_field( $section_data['changelog'] );
        }
    }

    /*
    |----------
    | GETTERS.
    |----------
    */

    /**
     * Get Item ID
     */
    public function get_item_id() {
        return $this->item_id;
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
        return $this->last_updated;
    }

    /**
     * Get Download link.
     */
    public function get_download_link() {
        return $this->download_link;
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
        return $this->section[$name];
    }

    /**
     * Get Sections
     * 
     * @return array $sections.
     */
    public function get_sections() {
        return $this->sections;
    }

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Get a Plugin data by a given column name
     */
    public function get_plugin_by( $column_name = '', $value = '' ) {

        $allowed_columns = array( 
            'name', 'license_key', 
            'slug', 'version', 'author', 
            'author_profile', 'requires', 
            'tested', 'requires_php', 
            'download_link', 'created_at', 'last_updated',
        );

        // Sanitize the column name.
        $column_name = sanitize_key( $column_name );

        // Validate the column name.
        if ( ! in_array( $column_name, $allowed_columns, true ) ) {
            return false; // Invalid column name.
        }

        // Sanitize the value.
        $value = sanitize_text_field( $value );

        // Check if the value is empty.
        if ( empty( $value ) ) {
            return false;
        }

        global $wpdb;
        // Prepare and execute the query.
        $query = $wpdb->prepare( "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE . " WHERE {$column_name} = %s", $value );
        $result = $wpdb->get_row( $query, ARRAY_A );
         if ( $result ){
            return self::convert_db_result( $result );

        }
        return false;
    }

    /**
     * Get a plugin by it's ID.
     * 
     * @param int $id The plugin ID
     */
    public function get_plugin( $id = 0 ) {
        $id = absint( $id );
        if ( empty( $id ) ) {
            return false;
        }

        // phpcs:disable
        global $wpdb;
        $query  = $wpdb->prepare( "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE . " WHERE `id` = %d", $id );
        $result = $wpdb->get_row( $query, ARRAY_A );
        
        if ( $result ) {
            return self::convert_db_result( $result );
        }

        return false;
    }

    /**
     * Get all plugins
     */
    public static function get_plugins() {
        global $wpdb;
        // phpcs:disable
        $query      = "SELECT * FROM " . SMLISER_PLUGIN_ITEM_TABLE;
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
     * Save a plugin.
     */
    public function save() {
        // Handle the plugin file first.
        $file = $this->file;

        if ( empty( $file ) ) {
            return new WP_Error( 'missing_plugin', 'No plugin uploaded' );
        }

        global $smliser_repo, $wpdb;
        $slug = $smliser_repo->upload_to_repository( $file );

        if ( is_wp_error( $slug ) ) {
            return $slug;
        }

        $this->set_slug( $slug );
        $this->set_download_link( site_url( '/plugin/'. $slug )  );


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
        );

        // Database insertion.
        $result = $wpdb->insert( 
            SMLISER_PLUGIN_ITEM_TABLE, 
            $plugin_data, 
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) 
        );

        if ( $result ) {
            $this->item_id = $wpdb->insert_id;
            do_action( 'smliser_plugin_saved', $this );
            return $this->get_item_id();
        }

        return new WP_Error( 'db_insert_error', $wpdb->last_error );
    }

    /**
     * Upload a plugin.
     */
    public function update() {
        global $smliser_repo, $wpdb;
        $file = $this->file;
    
        if ( ! empty( $file['name'] ) ) {
            $slug = $smliser_repo->update_plugin( $file, $this->get_slug() );
            if ( is_wp_error( $slug ) ) {
                return $slug;
            }
            $this->set_slug( $slug );
            $this->set_download_link( site_url( '/plugin/' . $slug ) );
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
     * Update Plugin
     * 
     * @param array $data   Associative array of column_name => value.
     * @return bool true if updated | false otherwise.
     */
    public function update_data( $data ) {
        if ( empty( $data ) ) {
            return false;
        }

        $d_format = array();

        foreach ( $data as $column => $value ) {
            $d_format[] = $this->get_data_format( $value );
        }

        global $wpdb;

        if ( $wpdb->update( SMLISER_PLUGIN_ITEM_TABLE, $data, array( 'id' => $this->get_item_id() ), $d_format, array( '%d' ) ) ) {
            do_action( 'smliser_plugin_saved', $this );
            return true;
        }

        return false;
    }
    
    public function delete() {
        if ( empty( $this->item_id ) ) {
            return false; // A valid plugin should have an ID.
        }
    
        global $smliser_repo, $wpdb;
        try {
            $item_id        = $this->get_item_id();
            $file_delete    = $smliser_repo->delete( $this->get_slug() );
    
            if ( is_wp_error( $file_delete ) ) {
                throw new Exception( $file_delete->get_error_message() );
            }
    
            $plugin_deletion    = $wpdb->delete( SMLISER_PLUGIN_ITEM_TABLE, array( 'id' => $item_id ), array( '%d' ) );
            $meta_deletion      = $wpdb->delete( SMLISER_PLUGIN_META_TABLE, array( 'plugin_id' => $item_id ), array( '%d' ) );
    
            if ( false === $plugin_deletion || false === $meta_deletion ) {
                throw new Exception( 'An issue occurred during the deletion' );
            }
        } catch ( Exception $e ) {
            return false;
        }
    
        return true;
    }

    /**
     * Add a new metadata.
     * 
     * @param mixed $key Meta Key.
     * @param mixed $value Meta value.
     * @return bool True on success, false on failure.
     */
    public function add_meta( $key, $value ) {
        if ( did_action( 'smliser_plugin_saved' ) > 0 ) {
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
        }

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
        $self->set_item_id( $result['id'] );
        $self->set_name( $result['name'] );
        $self->set_slug( $result['slug'] );
        $self->set_version( $result['version'] );
        $self->set_author( $result['author'] );
        $self->set_author_profile( $result['author_profile'] );
        $self->set_required( $result['requires'] );
        $self->set_tested( $result['tested'] );
        $self->set_required_php( $result['requires_php'] );
        $self->set_download_link( $result['download_link'] );
        $self->set_created_at( $result['created_at'] );
        $self->set_last_updated( $result['last_updated'] );
        
        /** Set file information */
        global $smliser_repo;
        $plugin_file_path   = $smliser_repo->get_plugin( $self->get_slug() );
        if ( ! is_wp_error( $plugin_file_path ) ) {
            $self->set_file( $plugin_file_path );
        } else {
            $self->set_file( null );
        }

        /** Section informations */
        $sections = array(
            'description'   => $smliser_repo->get_description( $self->get_slug() ),
            'changelog'     => $smliser_repo->get_changelog( $self->get_slug() ),
            'installation'  => $smliser_repo->get_installation_text( $self->get_slug() ),
        );
        $self->set_section( $sections );

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
     */
    public function formalize_response() {
        $pseudo_slug    = explode( '/', $this->get_slug() )[0];
        $data = array(
            'name'      => $this->get_name(),
            'id'        => $pseudo_slug,
            'slug'      => $this->get_slug(),
            'plugin'    => $this->get_slug(),
            'version'   => $this->get_version(),
            'author'    => $this->get_author(),
            'author_profile' => $this->get_author_profile(),
            'url'       => $this->get_url(),
            'package'   => $this->get_download_link(),
            'icons'     => array(
                '2x'        => '',
                '1x'        => '',
            ),
            'banners'   => array(
                '2x'        => '',
                '1x'        => '',
            ),
            'requires'      => $this->get_required(),
            'tested'        => $this->get_tested(),
            'requires_php'  => $this->get_required_php(),
            'requires_plugins'  => '',
            'last_updated'  => $this->get_last_updated(),
            'sections'      => $this->get_sections(),
        );

        return $data;
    }

    /*
    |-------------------------------
    | ACTION HANDLERS / CONTROLLERS
    |-------------------------------
    */

    /**
     * Syncronise License data with the plugin.
     * 
     * @param Smliser_license.
     */
    public static function action_handler() {
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
                $self->delete();
                break;
        }
        
    }

    /**
     * Form controller.
     */
    public static function plugin_upload_controller () {
        if ( isset( $_POST['smliser_plugin_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_plugin_form_nonce'] ) ), 'smliser_plugin_form_nonce' ) ) {
            global $smliser_repo;
            $is_new     = isset( $_POST['smliser_plugin_upload_new'] ) ? true : false;
            $is_update  = isset( $_POST['smliser_plugin_upload_update'] ) ? true : false;

            if ( $is_new ) {
                $self = new self();
            } elseif ( $is_update ) {
                $id   = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
                $obj = new self();
                $self = $obj->get_plugin( $id );
            }

            $file = isset( $_FILES['smliser_plugin_file'] ) ? $_FILES['smliser_plugin_file'] : '';
            $self->set_file( $file );
            $self->set_name( isset( $_POST['smliser_plugin_name']  ) ? sanitize_text_field( $_POST['smliser_plugin_name'] ) : '' );
            $self->set_author( isset( $_POST['smliser_plugin_author']  ) ? sanitize_text_field( $_POST['smliser_plugin_author'] ) : '' );
            $self->set_author_profile( isset( $_POST['smliser_plugin_author_profile']  ) ? sanitize_text_field( $_POST['smliser_plugin_author_profile'] ) : '' );
            $self->set_required( isset( $_POST['smliser_plugin_requires']  ) ? sanitize_text_field( $_POST['smliser_plugin_requires'] ) : '' );
            $self->set_tested( isset( $_POST['smliser_plugin_tested']  ) ? sanitize_text_field( $_POST['smliser_plugin_tested'] ) : '' );
            $self->set_required_php( isset( $_POST['smliser_plugin_requires_php']  ) ? sanitize_text_field( $_POST['smliser_plugin_requires_php'] ) : '' );
            $self->set_version( isset( $_POST['smliser_plugin_version']  ) ? sanitize_text_field( $_POST['smliser_plugin_version'] ) : '' );

            if ( $is_new ) {
                $item_id = $self->save();
                if ( is_wp_error( $item_id ) ) {
                    set_transient( 'smliser_form_validation_message', $item_id->get_error_message(), 5 );
                    wp_safe_redirect( smliser_repository_admin_action_page() );
                    exit;
                }
                wp_safe_redirect( smliser_repository_admin_action_page( 'edit', $item_id ) );
                exit;
            }
            
            if ( $is_update ) {
                $update = $self->update();
                if ( is_wp_error( $update ) ) {
                    set_transient( 'smliser_form_validation_message', $update->get_error_message(), 5 );
                }
                
                set_transient( 'smliser_form_success', true, 4 );
                wp_safe_redirect( smliser_repository_admin_action_page( 'edit', $id ) );
                exit;
            }

        }
    }

}