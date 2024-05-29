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
     * Class constructor.
     * 
     * @param mixed $data Product data or ID.
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
    public function set_author__profile( $url ) {
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
     * Set sections.
     */
}
