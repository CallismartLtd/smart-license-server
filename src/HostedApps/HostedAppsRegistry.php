<?php
/**
 * Hosted Apps Registry class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\HostedApps;

use InvalidArgumentException;
use Override;
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\FileSystem\WPPluginRepository;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\FileSystem\SoftwareRepository;
use SmartLicenseServer\FileSystem\WPThemeRepository;

/**
 * Centralized registry for managing any type of 
 * hosted application in this system.
 * 
 * This registry stores the app types, their classes, their storage
 * directory classes and their labels. Apps of every type share one database
 * table (see AbstractHostedApp::get_db_table()), so a type has no table of
 * its own.
 */
class HostedAppsRegistry extends AbstractRegistry {
	/**
	 * Singleton instance.
	 * 
	 * @var static|null $instance
	 */
	protected static ?self $instance = null;

	/**
	 * Class constructor.
	 */
	private function __construct(
		protected Container $container
	) {}

	/**
	 * Get the singleton instance of this class.
	 * 
	 * @param ?Container $container
	 * @return static
	 */
	public static function instance( ?Container $container = null ) : static {
		if ( null === static::$instance ) {
			if ( null === $container ) {
				throw new EnvironmentBootstrapException(
					'host_app_error',
					sprintf( 'The hosted apps registry must be called early with %s', Container::class )
				);
			}
			static::$instance = new static( $container );
		}

		return static::$instance;
	}

	/**
	 * Add a hosted app class to the registry.
	 * 
	 * @param string $type The unique type identifier for the app.
	 * @param array{
	 *  class: class-string<HostedAppsInterface>,
	 *  directory_class: class-string<Repository>,
	 *  labels?: array{singular: string, plural: string}
	 * }|null $data The app class, its directory class and, optionally, the
	 *              names people read (see get_app_type_labels()).
	 * @return static
	 * @throws \InvalidArgumentException When a key is missing or invalid, or the type is a core type.
	 */
	public function register( string $type, array $data ) : static {
		$this->ensure_core();

		$type = $this->normalize_app_type( $type );

		if ( ! isset( $data['class'] ) || ! isset( $data['directory_class'] ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Hosted app registration for type "%s" must include "class" and "directory_class" keys.',
					$type
				)
			);
		}

		$class_string = $data['class'];
		$this->assert_implements_interface( $class_string );

		if ( ! \is_subclass_of( $data['directory_class'], Repository::class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Directory class must extend "%s"', Repository::class )
			);
		}

		if ( isset( $data['labels'] ) ) {
			$this->assert_labels( $type, $data['labels'] );
		}

		// Core registry entries always win.
		if ( isset( $this->core[$type] ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Cannot register hosted app type "%s" because it is reserved by the core system.',
					$type
				)
			);
		}

		$this->custom[$type] = $data;

		return $this;
	}

	/**
	 * {@inheritDoc}
	 * 
	 * Forbids calling add() method directly on this registry. Use register() instead.
	 * 
	 * @throws \BadMethodCallException
	 */
	#[Override]
	public function add( string $class_string ) : static {
		throw new \BadMethodCallException(
			'Direct calls to add() method are not allowed on HostedAppsRegistry. Use register() method instead.'
		);
	}

	/**
	 * {@inheritDoc}
	 * 
	 * Forbid calling get() method directly on this registry. Use get_app() instead.
	 * 
	 * @throws \BadMethodCallException
	 */
	#[Override]
	public function get( $id ) : ?string {
		throw new \BadMethodCallException(
			'Direct calls to get() method are not allowed on HostedAppsRegistry. Use get_app_type_class() method instead.'
		);
	}

	/**
	 * Get hosted app data by its type.
	 * 
	 * @param string $type The unique type identifier for the app.
	 * @return array{
	 *  class: class-string<HostedAppsInterface>,
	 *  directory_class: class-string<Repository>,
	 *  labels?: array{singular: string, plural: string}
	 * }|null $entry 
	 */
	public function get_app_type_data( string $type ) : ?array {
		$this->ensure_core();

		$type  = $this->normalize_app_type( $type );
		$entry = $this->core[ $type ] ?? $this->custom[ $type ] ?? null;
	   
		/**
		 * @var array{
		 *  class: class-string<HostedAppsInterface>,
		 *  directory_class: class-string<Repository>,
		 *  labels?: array{singular: string, plural: string}
		 * }|null $entry 
		 */
		return $entry;
	}

	/**
	 * Get the class string for a registered hosted app.
	 *
	 * @param string $type The unique type identifier for the app.
	 * @return class-string<HostedAppsInterface>|null The class string if found, null otherwise.
	 */
	public function get_app_type_class( string $type ) : ?string {
		$entry = $this->get_app_type_data( $type );
	   
		if ( ! $entry ) {
			return null;
		}

		return $entry['class'];
	}

	/**
	 * Get directory class for an app type.
	 * 
	 * @param string $type The app type.
	 * @return Repository|null
	 */
	public function get_app_type_directory_class( string $type ) : ?Repository {
		$entry = $this->get_app_type_data( $type );
	   
		if ( ! $entry ) {
			return null;
		}

		if ( ! \is_subclass_of( $entry['directory_class'], Repository::class ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Directory class for app type "%s" must extend "%s"', $type, Repository::class )
			);
		}

		return $this->container->get( $entry['directory_class'] );
	}

	/**
	 * Get the names people read for an app type, e.g. "WordPress Plugin" and "WordPress Plugins".
	 *
	 * The registered labels; for a type registered without labels, the
	 * type key in words ("desktop-app" gives "Desktop App") for both, since
	 * a plural cannot be guessed safely ("Software" is its own plural).
	 *
	 * @param string $type The app type.
	 * @return array{singular: string, plural: string}|null Null when the type is not registered.
	 */
	public function get_app_type_labels( string $type ) : ?array {
		$entry = $this->get_app_type_data( $type );

		if ( ! $entry ) {
			return null;
		}

		if ( isset( $entry['labels'] ) ) {
			return $entry['labels'];
		}

		$words = ucwords( str_replace( [ '-', '_' ], ' ', $this->normalize_app_type( $type ) ) );

		return [ 'singular' => $words, 'plural' => $words ];
	}

	/**
	 * The name people read for an app type, e.g. "WordPress Plugin".
	 *
	 * @param string $type   The app type.
	 * @param bool   $plural The plural ("WordPress Plugins") instead.
	 * @return string The type in words when it is not registered.
	 */
	public function get_app_type_label( string $type, bool $plural = false ) : string {
		$labels = $this->get_app_type_labels( $type );

		if ( null === $labels ) {
			return ucwords( str_replace( [ '-', '_' ], ' ', $this->normalize_app_type( $type ) ) );
		}

		return $plural ? $labels['plural'] : $labels['singular'];
	}

	/**
	 * Get all registered app types.
	 * 
	 * @return string[] Array of registered app types.
	 */
	public function app_types() : array {
		$this->ensure_core();
		return array_keys( array_merge( $this->core, $this->custom ) );
	}

	/**
	 * Tells whether a given app type is registered.
	 * 
	 * @param string $type The unique type identifier for the app.
	 * @return bool True if the app type is registered, false otherwise.
	 */
	public function is_app_type_registered( string $type ) : bool {
		$this->ensure_core();
		$type = $this->normalize_app_type( $type );
		return isset( $this->core[ $type ] ) || isset( $this->custom[ $type ] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function assert_implements_interface( string $class_string ) : void {
		if ( ! is_a( $class_string, HostedAppsInterface::class, true ) ) {
			throw new \InvalidArgumentException(
				sprintf(
					'Hosted app class "%s" must implement %s interface.',
					$class_string,
					HostedAppsInterface::class
				)
			);
		}
	}

	/**
	 * Check the labels of a registration.
	 *
	 * @param string $type   The app type.
	 * @param mixed  $labels The labels given.
	 * @return void
	 * @throws \InvalidArgumentException When the labels are not two non-empty strings.
	 */
	protected function assert_labels( string $type, mixed $labels ) : void {
		$valid = is_array( $labels )
			&& is_string( $labels['singular'] ?? null ) && '' !== trim( $labels['singular'] )
			&& is_string( $labels['plural'] ?? null ) && '' !== trim( $labels['plural'] );

		if ( ! $valid ) {
			throw new \InvalidArgumentException(
				sprintf( 'The "labels" of hosted app type "%s" must be an array with the non-empty strings "singular" and "plural".', $type )
			);
		}
	}

	/**
	 * {@inheritDoc}
	 */
	protected function load_core() : void {
		if ( $this->core_loaded ) {
			return;
		}

		static $core_hosted_apps = [
			'wpplugin' => [
				'class'           => WPPlugin::class,
				'directory_class' => WPPluginRepository::class,
				'labels'          => [ 'singular' => 'WordPress Plugin', 'plural' => 'WordPress Plugins' ],
			],
			'wptheme' => [
				'class'           => WPTheme::class,
				'directory_class' => WPThemeRepository::class,
				'labels'          => [ 'singular' => 'WordPress Theme', 'plural' => 'WordPress Themes' ],
			],
			'software' => [
				'class'           => Software::class,
				'directory_class' => SoftwareRepository::class,
				'labels'          => [ 'singular' => 'Software', 'plural' => 'Software' ],
			],
		];

		foreach ( $core_hosted_apps as $type => $data ) {
			$this->core[ $type ] = $data;
		}

		$this->core_loaded = true;
	}

	/**
	 * Normalize an app type as given (a URL segment, a query or form field):
	 * lowercase, without surrounding whitespace.
	 *
	 * Every app type has exactly one name ("wpplugin", "wptheme", "software",
	 * or a registered custom type); no other spelling is accepted.
	 *
	 * @param string $type
	 * @return string
	 */
	public function normalize_app_type( string $type ) : string {
		return strtolower( trim( $type ) );
	}

}