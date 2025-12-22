<?php
/**
 * The Hosted PLugin class file.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 * @package Smliser\classes
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\FileSystem\PluginRepository;
use SmartLicenseServer\Exceptions\Exception;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a typical plugin hosted in this repository.
 */
class Plugin extends AbstractHostedApp {
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
     * WordPress version requirement.
     * 
     * @var string $requires_at_least The minimum WordPress version required to run the plugin.
     */
    protected $requires_at_least = '';

    /**
     * Version Tested up to.
     * 
     * @var string $tested_up_to The latest WordPress version the app has been tested with.
     */
    protected $tested_up_to = '';

    /**
     * PHP version requirement.
     * 
     * @var string $requires_php The minimum PHP version required to run the app.
     */
    protected $requires_php = '';

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
    public function get_type() : string {
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
    public function set_requires_at_least( $wp_version ) {
        $this->requires_at_least = sanitize_text_field( $wp_version );
    }

    /**
     * Set WordPress version tested up to.
     * 
     * @param string $version
     */
    public function set_tested_up_to( $version ) {
        $this->tested_up_to = sanitize_text_field( $version );
    }

    /**
     * Set the required PHP version.
     * 
     * @param string $version
     */
    public function set_requires_php( $version ) {
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
     * Get the author profile.
     */
    public function get_author_profile() {
        return $this->author_profile;
    }

    /**
     * Get WordPress required version for plugin.
     */
    public function get_requires_at_least() {
        return $this->requires_at_least;
    }

    /**
     * Get WordPress version tested up to.
     */
    public function get_tested_up_to() {
        return $this->tested_up_to ;
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
     * Get the icon
     * 
     * @return string
     */
    public function get_icon() : string {
        $icons = $this->get_icons();
                if ( ! empty($icons['1x'] ) ) {
            return $icons['1x'];
        }
        
        if ( ! empty($icons['2x'] ) ) {
            return $icons['2x'];
        }

        return smliser_get_placeholder_icon( $this->get_type() );
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
        $db         = smliser_dbclass();
        $table      = self::TABLE;

        $file       = $this->file;
        $filename   = strtolower( str_replace( ' ', '-', $this->get_name() ) );
        $repo_class = new PluginRepository();

        $plugin_data = array(
            'name'          => $this->get_name(),
            'author'        => $this->get_author(),
            'status'        => $this->get_status(),
            'author_profile'=> $this->get_author_profile(),
            'download_link' => $this->get_download_link(),
            'last_updated'  => \gmdate( 'Y-m-d H:i:s' ),
        );

        if ( $this->get_id() ) {
            if ( is_array( $this->file ) ) {
                $slug = $repo_class->upload_zip( $file, $this->get_slug(), true );
                if ( is_smliser_error( $slug ) ) {
                    return $slug;
                }

                if ( $slug !== $this->get_slug() ) {
                    $plugin_data['slug'] = $slug;
                    $this->set_slug( $slug );
                }
            }

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
            $plugin_data['created_at']  = \gmdate( 'Y-m-d H:i:s' );

            $result = $db->insert( $table, $plugin_data );

            $this->set_id( $db->get_insert_id() );
        }

        return ( false !== $result ) ? true : new Exception( 'db_insert_error', $db->get_last_error() );
    }

    /**
     * Deletes a plugin.
     *
     * @return bool True on success, false on failure.
     */
    public function delete() : bool {
        $plugin_id = absint( $this->get_id() );

        if ( ! $plugin_id ) {
            return false;
        }

        $db          = smliser_dbclass();
        $repo_class  = new PluginRepository();
        $slug        = $this->get_slug();

        $file_delete = $repo_class->trash( $slug );

        if ( is_smliser_error( $file_delete ) ) {
            error_log( 'Plugin delete failed: ' . $file_delete->get_error_message() );
            return false;
        }

        $meta_deleted = $db->delete(
            self::META_TABLE,
            [ 'plugin_id' => $plugin_id ]
        );

        $plugin_deleted = $db->delete(
            self::TABLE,
            [ 'id' => $plugin_id ]
        );

        if ( isset( $this->meta_data ) ) {
            $this->meta_data = [];
        }

        return ( $plugin_deleted > 0 || $meta_deleted > 0 );
    }

    /*
    |---------------
    |UTILITY METHODS
    |---------------
    */

    /**
     * Converts associative array to object of this class.
     */
    public static function from_array( $result ) : static {
        $self = new static();
        $self->set_id( $result['id'] ?? 0 );
        $self->set_name( $result['name'] ?? '' );
        $self->set_slug( $result['slug'] ?? '' );
        $self->set_author( $result['author'] ?? '' );
        $self->set_author_profile( $result['author_profile'] ?? '' );

        $self->load_meta();
        /** 
         * Set file information
         * 
         * @var \SmartLicenseServer\FileSystem\PluginRepository $repo_class
         */
        $repo_class = SmliserSoftwareCollection::get_app_repository_class( $self->get_type() );

        $plugin_meta    = $repo_class->get_metadata( $self->get_slug() );        
        $self->set_version( $plugin_meta['stable_tag'] ?? '' );
        $self->set_requires_at_least( $plugin_meta['requires_at_least'] ?? '' );
        $self->set_tested_up_to( $plugin_meta['tested_up_to'] ?? '' );
        $self->set_requires_php( $plugin_meta['requires_php'] ?? '' );
        $self->set_tags( $plugin_meta['tags'] ?? array() );

        $self->set_download_url( $result['download_link'] ?? '' );
        $self->set_created_at( $result['created_at'] ?? '' );
        $self->set_last_updated( $result['last_updated'] ?? '' );
        
        $file       = $repo_class->locate( $self->get_slug() );

        if ( ! is_smliser_error( $file ) ) {
            $self->set_file( $file );
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
            'faq'           => $repo_class->get_faq( $self->get_slug() ),

        );
        $self->set_section( $sections );

        $manifest = $repo_class->get_app_dot_json( $self );
        $self->set_manifest( $manifest );

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
        $self->set_homepage( $self->get_meta( 'homepage_url', '' ) );

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
            'icon'              => $this->get_icon(),
            'requires'          => $this->get_requires_at_least(),
            'tested'            => $this->get_tested_up_to(),
            'requires_php'      => $this->get_required_php(),
            'requires_plugins'  => [],
            'tags'              => $this->get_tags(),
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
            $monetization = Monetization::get_by_app( $this->get_type(), $this->get_id() );

            $data['monetization'] = $monetization->to_array() ?: [];
        }

        return $data;
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

    /**
     * Get the foreign key column name for metadata table
     * 
     * @return string
     */
    protected function get_meta_foreign_key() : string {
        return 'plugin_id';
    }
}