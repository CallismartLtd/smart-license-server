<?php
/**
 * The Hosted Theme class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps.
 */

namespace SmartLicenseServer\HostedApps;

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\Monetization;
use SmartLicenseServer\FileSystem\WPThemeRepository;
use SmartLicenseServer\Utils\Format;

/**
 * Represents a typical theme hosted in the repository.
 */
class WPTheme extends AbstractHostedApp {
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
		return 'wptheme';
	}

	/**
	 * Get the author profile URL
	 *
	 * @return URL|null
	 */
	public function get_author_profile() : ?URL {
		return $this->author_profile;
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
		return $this->default_icon()->url();
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

	/*
	|--------------------
	| UTILITY METHODS
	|--------------------
	*/

	/**
	 * Converts associative array to object of this class.
	 * 
	 * @param array $result The associative array representing a theme.
	 * @return static
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
		/** @var WPThemeRepository */
		$repo_class = HostedAppsRegistry::instance()->get_app_type_directory_class( 'wptheme' );

		$theme_metadata = $repo_class->get_metadata( $self->get_slug() );
		$self->set_version( $theme_metadata['version'] ?? '' );
		$self->set_tags( $theme_metadata['tags'] ?? [] );

		// The stored author is the owner's; style.css only fills what was never set.
		$self->set_author( (string) ( $result['author'] ?? '' ) ?: (string) ( $theme_metadata['author'] ?? '' ) );
		$self->set_author_profile( (string) ( $result['author_profile'] ?? '' ) ?: (string) ( $theme_metadata['author_uri'] ?? '' ) );
		$self->set_requires_at_least( $theme_metadata['requires_at_least'] ?? '' );
		$self->set_tested_up_to( $theme_metadata['tested_up_to'] ?? '' );
		$self->set_requires_php( $theme_metadata['requires_php'] ?? '' );
		$license = [
			'license'     => $theme_metadata['license'] ?? '',
			'license_uri' => $theme_metadata['license_uri'] ?? ''
		];

		$self->set_license( $license );
		
		$self->set_homepage( $self->get_meta( 'homepage_url' ) ?: $theme_metadata['theme_uri'] ?? '' );

		/** 
		 * Section informations 
		 */
		$sections = [
			'description'  => $repo_class->get_description( $self->get_slug() ),
			'changelog'    => $repo_class->get_changelog( $self->get_slug(), 'txt' ),
			'installation' => $repo_class->get_installation( $self->get_slug(), 'txt' ),
			'screenshots'  => [],
		];
		
		$self->set_section( $sections );
		$manifest = $repo_class->get_app_dot_json( $self );
		$self->set_manifest( $manifest );

		/**
		 * The theme screenshot
		 */
		$self->set_screenshot_url( $repo_class->get_screenshot( $self->get_slug() ) );

		/**
		 * Screenshots
		 */
		$self->set_screenshots( $repo_class->get_assets( $self->get_slug(), 'screenshots' ) );

		/**
		 * Set short description
		 */
		$self->short_description = $repo_class->get_short_description( $self->get_slug() );

		$self->set_ratings( $self->get_meta( 'ratings',
			[
				'5' => 0,
				'4' => 0,
				'3' => 0,
				'2' => 0,
				'1' => 0,
			]
		) );

		$self->set_num_ratings( $self->get_meta( 'num_ratings', 0 ) );
		$self->set_active_installs( $self->get_meta( 'active_installs', 0 ) );
		$self->set_support_url( (string) $self->get_meta( 'support_url', '' ) );

		return $self;
	}

	/**
	 * The author as the WordPress.org themes API gives it, for theme
	 * installers that read that shape. Built from the stored name and
	 * profile URL; nothing else about the author is kept.
	 *
	 * @return array{user_nicename: string, profile: string, avatar: string, display_name: string, author: string, author_url: string}
	 */
	protected function rest_author() : array {
		$name    = $this->get_author();
		$profile = $this->get_author_profile();
		$url     = $profile && $profile->is_valid() ? $profile->url() : '';

		return [
			'user_nicename' => Format::slugify( $name ),
			'profile'       => $url,
			'avatar'        => '',
			'display_name'  => $name,
			'author'        => $name,
			'author_url'    => $url,
		];
	}

	/**
	 * Concrete implementation of rest response for themes.
	 * 
	 * @return array
	 */
	public function get_rest_response() : array {
		$data = [
			'name'                    => $this->get_name(),
			'slug'                    => $this->get_slug(),
			'status'                  => $this->get_status(),
			'type'                    => $this->get_type(),
			'version'                 => $this->get_version(),
			'preview_url'             => $this->get_meta( 'preview_url' ),
			'author'                  => $this->rest_author(),
			'manifest'                => $this->get_manifest(),
			'icon'                    => $this->get_icon(),
			'screenshot_url'          => $this->get_screenshot_url(),
			'rating'                  => $this->get_average_rating(),
			'num_ratings'             => $this->get_num_ratings(),
			'reviews_url'             => '',
			'downloaded'              => 0,
			'last_updated'            => $this->get_last_updated()->format( 'Y-m-d' ),
			'last_updated_time'       => $this->get_last_updated()->format( 'Y-m-d H:i:s' ),
			'creation_time'           => $this->get_date_created()->format( 'Y-m-d H:i:s' ),
			'homepage'                => $this->get_homepage(),
			'sections'                => $this->get_sections(),
			'download_link'           => $this->get_download_url(),
			'tags'                    => $this->get_tags(),
			'requires'                => $this->requires_at_least(),
			'requires_php'            => $this->get_required_php(),
			'is_monetized'            => $this->is_monetized(),
			'external_support_url'    => $this->get_support_url(),
			'support_url'             => $this->get_support_url(),
			'external_repository_url' => '',
			'monetization'            => [],
			'created_at'              => $this->get_date_created()->format( 'Y-m-d H:i:s' ),
			'updated_at'              => $this->get_last_updated()->format( 'Y-m-d H:i:s' )
		];
		
		if ( $this->is_monetized() ) {
			$monetization = Monetization::get_by_app( $this->get_type(), $this->get_id() );

			$data['monetization'] = $monetization->to_array();
		}

		return $data;

	}

}