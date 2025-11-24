<?php
/**
 * The Smliser_Plugin class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 * @package Smliser\classes
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\PluginRepository;
use SmartLicenseServer\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Represents a typical plugin hosted in this repository.
 */
class Smliser_Plugin extends AbstractHostedApp {
    /**
     * The plugin database table name.
     * 
     * @var string
     */
    const TABLE = SMLISER_PLUGIN_ITEM_TABLE;

    /**
     * Plugin metadata table
     * 
     * @var string
     */
    const META_TABLE = SMLISER_PLUGIN_META_TABLE;

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
     * Class constructor.
     */
    public function __construct() {}

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

    /*
    |----------
    | GETTERS.
    |----------
    */

    /**
     * Get Item ID
     */
    public function get_item_id() {
        return $this->get_id();
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

    /*
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Get a plugin by it's ID.
     * 
     * @param int $id The plugin ID.
     * @return self|null
     */
    public static function get_plugin( $id = 0 ) {
        $db     = smliser_dbclass();
        $table  = self::TABLE;
        $id     = absint( $id );

        if ( empty( $id ) ) {
            return null;
        }

        $sql    = "SELECT * FROM {$table} WHERE `id` = ?";
        $result = $db->get_row( $sql, [$id] );

        if ( $result ) {
            return self::from_array( $result );
        }

        return null;
    }

    /**
     * Create new plugin or update the existing one.
     * 
     * @return true|Exception True on success, false on failure.
     */
    public function save() : true|Exception {
        $db     = smliser_dbclass();
        $table  = self::TABLE;

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
            $result = $db->update( $table, $plugin_data, array( 'id' => absint( $this->get_id() ) ) );

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

            $result = $db->insert( $table, $plugin_data );

            $this->set_id( $db->get_insert_id() );
        }

        return ( false !== $result ) ? true : new Exception( 'db_insert_error', $db->get_last_error() );
    }

    /**
     * Delete a plugin.
     * 
     * @return bool True on success, false on otherwise.
     */
    public function delete() : bool {
        $db     = smliser_dbclass();

        if ( empty( $this->id ) ) {
            return false; // A valid plugin should have an ID.
        }
    
        $repo_class = new PluginRepository();
        
        $id             = $this->get_id();
        $file_delete    = $repo_class->delete_from_repo( $this->get_slug() );

        if ( is_smliser_error( $file_delete ) ) {
            return $file_delete;
        }

        $plugin_deletion    = $db->delete( self::TABLE, array( 'id' => $id ) );
        $meta_deletion      = $db->delete( self::META_TABLE, array( 'plugin_id' => $id ) );

        return ( $plugin_deletion || $meta_deletion ) !== false;
    }

    /**
     * Add a new metadata.
     * 
     * @param mixed $key Meta Key.
     * @param mixed $value Meta value.
     * @return bool True on success, false on failure.
     */
    public function add_meta( $key, $value ) {
        $db     = smliser_dbclass();

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

        $result = $db->insert( self::META_TABLE, $data );
        
        return $result !== false;
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
        $db     = smliser_dbclass();
        $table  = self::META_TABLE;

        $key    = sanitize_text_field( $key );
        $value  = sanitize_text_field( is_array( $value ) ? maybe_serialize( $value ) : $value );

        // Prepare data for insertion/update.
        $data = array(
            'plugin_id'     => absint( $this->get_item_id() ),
            'meta_key'      => $key,
            'meta_value'    => $value,
        );

        $meta_id = $db->get_var( "SELECT `id` FROM {$table} WHERE `plugin_id` = ? AND `meta_key` = ?", [absint( $this->get_item_id() ), $key] );

        if ( ! $meta_id ) {
            $inserted = $db->insert( $table, $data );

            return $inserted !== false;
        } else {
            $updated = $db->update( $table, 
                array( 'meta_value' => $value ),
                array(
                    'plugin_id' => absint( $this->get_item_id() ),
                    'id'        => $meta_id,
                    'meta_key'  => $key,
                )
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
        $db     = smliser_dbclass();
        $table  = self::META_TABLE;

        $sql    = "SELECT `meta_value` FROM {$table} WHERE `plugin_id` = ? AND `meta_key` = ?";
        $params = [absint( $this->get_item_id() ), sanitize_text_field( $meta_key )];
        

        $result = $db->get_var( $sql, $params );

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
        $db     = smliser_dbclass();
        $table  = self::META_TABLE;

        $item_id    = absint( $this->get_item_id() );
        $meta_key   = sanitize_text_field( $meta_key );
        $where      = array(
            'plugin_id' => $item_id,
            'meta_key'  => $meta_key
        );

        $deleted = $db->delete( $table, $where );

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
        $self->set_id( $result['id'] ?? 0 );
        $self->set_name( $result['name'] ?? '' );
        $self->set_slug( $result['slug'] ?? '' );
        $self->set_version( $result['version'] ?? '' );
        $self->set_author( $result['author'] ?? '' );
        $self->set_author_profile( $result['author_profile'] ?? '' );
        $self->set_required( $result['requires'] ?? '' );
        $self->set_tested( $result['tested'] ?? '' );
        $self->set_required_php( $result['requires_php'] ?? '' );
        $self->set_download_url( $result['download_link'] ?? '' );
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

        $self->set_support_url( $self->get_meta( 'support_url', '' ) );
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
            'author'            => sprintf( '<a href="%s">%s</a>', $this->get_author_profile(), $this->get_author() ),
            'author_profile'    => $this->get_author_profile(),
            'homepage'          => $this->get_homepage(),
            'package'           => $this->get_download_link(),
            'download_link'     => $this->get_download_link(),
            'banners'           => $this->get_banners(),
            'screenshots'       => $this->get_screenshots(),
            'icons'             => $this->get_icons(),
            'requires'          => $this->get_required(),
            'tested'            => $this->get_tested(),
            'requires_php'      => $this->get_required_php(),
            'requires_plugins'  => [],
            'tags'              => [],
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
        $db         = smliser_dbclass();
        $table_name = SMLISER_MONETIZATION_TABLE;
        $query      = "SELECT COUNT(*) FROM {$table_name} WHERE `item_type` = ? AND `item_id` = ? AND `enabled` = ?";
        $params     = [$this->get_type(), absint( $this->id ), '1'];
        return $db->get_var( $query, $params ) > 0;
    }

    /**
     * Get the database table name
     * 
     * @return string
     */
    public static function get_db_table() {
        return self::TABLE;
    }

    /**
     * Get the database metadate table name.
     * 
     * @return string
     */
    public static function get_db_meta_table() {
        return self::META_TABLE;
    }
}