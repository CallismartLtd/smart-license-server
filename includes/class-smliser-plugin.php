<?php
/**
 * The Smart License Product Class file.
 * 
 * @package Smliser\classes
 */

defined( 'ABSPATH' ) || exit;

class Smliser_Plugin {

    /**
     * Item ID.
     * @var int $item_id The plugin ID.
     */
    private $item_id = 0;

    /**
     * The name of the plugin..
     * 
     * @var string $name The plugin's name.
     */
    private $name = '';
        
    /**
     * License key for the plugin.
     * 
     * @var string $license_key
     */
    private $license_key = '';

    /**
     * Plugin slug.
     * 
     * @var string $slug Plugin slug.
     */
    private $slug = '';

    /**
     * Plugin Version.
     * 
     * @var string $version The current version of the plugin.
     */
    private $version = '';

    /**
     * Author .
     * 
     * @var string $author The author of the plugin
     */
    private $author = '';

    /**
     * Author profile.
     * 
     * @var string $author_profile The author's profile link or information.
     */
    private $author_profile = '';

    /**
     * WordPress Version requirement.
     * 
     * @var string $requires The minimum WordPress version required to run the plugin.
     */
    private $requires = '';

    /**
     * Version Tested up to.
     * 
     * @var string $tested The latest WordPress version the plugin has been tested with.
     */
    private $tested = '';

    /**
     * PHP version requirement.
     * 
     * @var string $requires_php The minimum PHP version required to run the plugin.
     */
    private $requires_php = '';

    /**
     * Update time.
     * 
     * @var string $last_updated The last time the plugin was updated
     */
    private $last_updated = '';

    /**
     * Date created
     * 
     * @var string $created_at When plugin was created.
     */
    private $created_at;

    /**
     * An array of different sections of plugin information (e.g., description, installation, FAQ).
     * 
     * @var array $sections
     */
    private $sections = array();

    /**
     * An array of banner images for the plugin..
     * 
     * @var array $banners
     */
    private $banners = array();

    /**
     * Icons
     */
    private $icons = array();

    /**
     * Download link.
     * 
     * @var $download_link The file url.
     */
    private $download_link = '#';

    /**
     * Plugin zip file.
     * 
     * @var string $file The plugin file.
     */
    private $file;

    /**
     * Class constructor.
     */
    public function __construct() {}

    /*
    |---------------
    | Setters
    |---------------
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
        $this->requires_php = $version;
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

    /*
    |-------------
    | Getters.
    |-------------
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

    /*
    |--------------
    | Crud Methods
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
            return $result;

        }
        return false;
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
            'last_updated'  => sanitize_text_field( $this->get_last_updated() ),
            'download_link' => esc_url_raw( $this->get_download_link() ),
        );

        // Database insertion.
        $result = $wpdb->insert( 
            SMLISER_PLUGIN_ITEM_TABLE, 
            $plugin_data, 
            array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) 
        );

        if ( $result ) {
            $this->item_id = $wpdb->insert_id;
            return $this->get_item_id();
        }

        return new WP_Error( 'db_insert_error', $wpdb->last_error );
    }

    /**
     * Update Plugin
     */
    public function update() {

    }

    /**
     * Form controller.
     */
    public static function plugin_upload_controller () {
        if ( isset( $_POST['smliser_plugin_form_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['smliser_plugin_form_nonce'] ) ), 'smliser_plugin_form_nonce' ) ) {
            global $smliser_repo;
            $self = new self();
            $file = $_FILES['smliser_plugin_file'];

            $self->set_file( $file );
            $self->set_name( isset( $_POST['smliser_plugin_name']  ) ? sanitize_text_field( $_POST['smliser_plugin_name'] ) : '' );
            $self->set_author( isset( $_POST['smliser_plugin_author']  ) ? sanitize_text_field( $_POST['smliser_plugin_author'] ) : '' );
            $self->set_author_profile( isset( $_POST['smliser_plugin_author_profile']  ) ? sanitize_text_field( $_POST['smliser_plugin_author_profile'] ) : '' );
            $self->set_required( isset( $_POST['smliser_plugin_requires']  ) ? sanitize_text_field( $_POST['smliser_plugin_requires'] ) : '' );
            $self->set_tested( isset( $_POST['smliser_plugin_tested']  ) ? sanitize_text_field( $_POST['smliser_plugin_tested'] ) : '' );
            $self->set_required_php( isset( $_POST['smliser_plugin_requires_php']  ) ? sanitize_text_field( $_POST['smliser_plugin_requires_php'] ) : '' );
            $is_new     = isset( $_POST['smliser_plugin_upload_new'] ) ? true : false;
            $is_update  = isset( $_POST['smliser_plugin_upload_update'] ) ? true : false;

            if ( $is_new ) {
                $item_id = $self->save();
                var_dump( $item_id );
            }

        }
    }

    /**
     * convert database result to Smliser_plugin
     */
    private static function convert_db_result( $result ) {
        $self = new self();
        $self->set_name( $result['name'] );
        $self->set_license_key( $result['license_key'] );
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
        
        
        //$self->set_file( $result['tested'] );
    }

}
