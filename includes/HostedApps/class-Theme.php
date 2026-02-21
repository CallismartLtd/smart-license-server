<?php
/**
 * The Hosted Theme class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps.
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\FileSystem\ThemeRepository;
use SmartLicenseServer\Utils\CommonQueryTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a typical theme hosted in the repository.
 */
class Theme extends AbstractHostedApp {
    use CommonQueryTrait;
    /**
     * The database table for themes.
     * 
     * @var string
     */
    const TABLE = SMLISER_THEMES_TABLE;

    /**
     * The theme metadata table
     * 
     * @var string
     */
    const META_TABLE    = SMLISER_THEMES_META_TABLE;

    /**
     * WordPress version requirement.
     * 
     * @var string $requires_at_least The minimum WordPress version required to run the theme.
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
     * Author .
     * 
     * @var array $author The properties of the theme author.
     */
    protected $author = [
        'user_nicename' => '',
        'profile'       => '',
        'avatar'        => '',
        'display_name'  => '',
        'author'        => '',
        'author_url'    => ''
    ];

    /**
     * The theme's screenshot.png url
     * 
     * @var string $screenshot_url
     */
    protected $screenshot_url = '';

    /**
     * Class constructor
     */
    public function __construct() {}

    /**
     * Get application type
     * @return string
     */
    public function get_type() : string {
        return 'theme';
    }

    /**
     * Get the theme author name
     * 
     * @param string $property The author property to return. 
     * @return string
     */
    public function get_author( $context = 'author' ) {
        $author = parent::get_author();

        if ( ! empty( $context ) && array_key_exists( $context, (array) $author ) ) {
            return $author[ $context ];
        }

        return $author;

    }

    /**
     * Get the author profile URL
     * 
     * @return string
     */
    public function get_author_profile() {
        return $this->author['author_url'];
    }

    /**
     * Get WordPress required version for theme.
     */
    public function requires_at_least() {
        return $this->requires_at_least;
    }

    /**
     * Get WordPress version tested up to.
     */
    public function get_tested_up_to() {
        return $this->tested_up_to;
    }

    /**
     * Get PHP required version for theme.
     */
    public function get_required_php() {
        return $this->requires_php;
    }

    /**
     * Get the theme screenshot URL.
     * 
     * @return string
     */
    public function get_screenshot_url() : string {
        return $this->screenshot_url;
    }

    /**
     * Get the theme main icon
     */
    public function get_icon() : string {
        return smliser_get_placeholder_icon( $this->get_type() );
    }

    /*
    |---------------
    | SETTER METHODS
    |---------------
    */
    /**
     * Set WordPress required version for theme.
     * 
     * @param string $version
     */
    public function set_requires_at_least( $version ) {
        $this->requires_at_least = $version;
    }

    /**
     * Set WordPress tested up to version for theme.
     * 
     * @param string $version
     */
    public function set_tested_up_to( $version ) {
        $this->tested_up_to = $version;
    }

    /**
     * Set PHP required version for theme.
     * 
     * @param string $version
     */
    public function set_requires_php( $version ) {
        $this->requires_php = $version;
    }

    /**
     * Set the theme screenshot URL.
     * 
     * @param string $url
     */
    public function set_screenshot_url( string $url ) {
        $this->screenshot_url = $url;
    }
    
    /**
    |--------------
    | CRUD METHODS
    |--------------
    */

    /**
     * Get a theme by its id
     * 
     * @param int $id
     * @return self|null
     */
    public static function get_theme( $id = 0 ) : ?self {
        return self::get_self_by_id( $id, self::TABLE );
    }

    /**
     * Delete a theme.
     * 
     * @return bool True on success, false on otherwise.
     */
    public function delete() : bool {
        $db     = smliser_dbclass();

        if ( empty( $this->id ) ) {
            return false; // A valid theme should have an ID.
        }
            
        $id             = $this->get_id();
        $theme_deletion = $db->delete( self::TABLE, array( 'id' => $id ) );
        $meta_deletion  = $db->delete( self::META_TABLE, array( 'theme_id' => $id ) );

        if ( ! empty( $this->meta_data ) ) {
            $this->meta_data = [];
        }

        return ( $theme_deletion > 0 && $meta_deletion > 0 );
    }

    /**
     * Get the database table name.
     * 
     * @return string
     */
    public static function get_db_table() : string {
        return self::TABLE;
    }

    /**
     * Get database fields.
     * 
     * @return array<int, string>
     */
    public function get_fillable() : array {
        return array( 'owner_id', 'name', 'author', 'status', 'download_link' );
    }

    /**
     * Get the database table name.
     * 
     * @return string
     */
    public static function get_db_meta_table() : string {
        return self::META_TABLE;
    }

    /**
     * Get the foreign key column name for metadata table
     * 
     * @return string
     */
    protected function get_meta_foreign_key() : string {
        return 'theme_id';
    }

    /**
    |--------------------
    | UTILITY METHODS
    |--------------------
    */

    /**
     * Converts associative array to object of this class.
     * 
     * @param array $result The associative array representing a theme.
     * @return self
     */
    public static function from_array( $result ) : static {
        $self = new static();
        $self->set_id( $result['id'] ?? 0 );
        $self->set_owner_id( $result['owner_id'] ?? 0 );
        $self->set_name( $result['name'] ?? '' );
        $self->set_slug( $result['slug'] ?? '' );
        $self->set_download_url( $result['download_link'] ?? '' );
        $self->set_created_at( $result['created_at'] ?? '' );
        $self->set_updated_at( $result['updated_at'] ?? '' );
        $self->set_status( $result['status'] ?? self::STATUS_ACTIVE );

        $self->load_meta();

        $repo_class = new ThemeRepository();

        $theme_metadata = $repo_class->get_metadata( $self->get_slug() );
        $self->set_version( $theme_metadata['version'] ?? '' );
        $self->set_tags( $theme_metadata['tags'] ?? [] );
        $self->set_author( array(
            'user_nicename' => $theme_metadata['author_nicename'] ?? '',
            'profile'       => $theme_metadata['author_profile'] ?? '',
            'avatar'        => $theme_metadata['author_avatar'] ?? '',
            'display_name'  => $theme_metadata['author_display_name'] ?? '',
            'author'        => $theme_metadata['author'] ?? '',
            'author_url'    => $theme_metadata['author_uri'] ?? '',
        ));

        $self->set_author_profile( $theme_metadata['author_profile'] ?? '' );
        $self->set_requires_at_least( $theme_metadata['requires_at_least'] ?? '' );
        $self->set_tested_up_to( $theme_metadata['tested_up_to'] ?? '' );
        $self->set_requires_php( $theme_metadata['requires_php'] ?? '' );
        $license    = array(
            'license'       => $theme_metadata['license'] ?? '',
            'license_uri'   => $theme_metadata['license_uri'] ?? ''
        );

        $self->set_license( $license );
        
        $self->set_homepage( $self->get_meta( 'homepage_url' ) ?: $theme_metadata['theme_uri'] ?? '' );
        
        $theme_file   = $repo_class->locate( $self->get_slug() );
        if ( ! is_smliser_error( $theme_file ) ) {
            $self->set_file( $theme_file );
        } else {
            $self->set_file( '' );
        }

        /** 
         * Section informations 
         */
        $sections = array(
            'description'   => $repo_class->get_description( $self->get_slug() ),
            'changelog'     => $repo_class->get_changelog( $self->get_slug() ),
            'installation'  => '',
            'screenshots'   =>  [],
        );
        
        $self->set_section( $sections );
        $manifest = $repo_class->get_app_dot_json( $self );
        $self->set_manifest( $manifest );

        /**
         * The theme screenshot
         */
        $self->set_screenshot_url( $repo_class->get_assets( $self->get_slug(), 'screenshot' ) );

        /**
         * Screenshots
         */
        $self->set_screenshots( $repo_class->get_assets( $self->get_slug(), 'screenshots' ) );

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
     * Concrete implementation of rest response for themes.
     * 
     * @return array
     */
    public function get_rest_response() : array {
        $data = array(
            'name'                  => $this->get_name(),
            'slug'                  => $this->get_slug(),
            'status'                => $this->get_status(),
            'type'                  => $this->get_type(),
            'version'               => $this->get_version(),
            'preview_url'           => $this->get_meta( 'preview_url' ),
            'author'                => $this->get_author( '' ),
            'manifest'              => $this->get_manifest(),
            'icon'                  => $this->get_icon(),
            'screenshot_url'        => $this->get_screenshot_url(),
            'rating'                => $this->get_ratings(),
            'num_ratings'           => $this->get_num_ratings(),
            'reviews_url'           => '',
            'downloaded'            => 0,
            'last_updated'          => date( 'Y-m-d', strtotime( $this->get_last_updated() ) ),
            'last_updated_time'     => date( 'Y-m-d H:i:s', strtotime( $this->get_last_updated() ) ),
            'creation_time'         => $this->get_date_created(),
            'homepage'              => $this->get_homepage(),
            'sections'              => $this->get_sections(),
            'download_link'         => $this->get_download_url(),
            'tags'                  => $this->get_tags(),
            'requires'                  => $this->requires_at_least(),
            'requires_php'              => $this->get_required_php(),
            'is_monetized'              => $this->is_monetized(),
            'external_support_url'      => $this->get_support_url(),
            'support_url'               => $this->get_support_url(),
            'external_repository_url'   => '',
            'monetization'              => [],
            'created_at'                => $this->get_date_created(),
            'updated_at'                => $this->get_last_updated()
        );
        
        if ( $this->is_monetized() ) {
            $monetization = Monetization::get_by_app( $this->get_type(), $this->get_id() );

            $data['monetization'] = $monetization->to_array();
        }

        return $data;

    }

}