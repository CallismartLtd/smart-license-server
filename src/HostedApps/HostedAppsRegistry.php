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
use SmartLicenseServer\FileSystem\PluginRepository;
use SmartLicenseServer\FileSystem\Repository;
use SmartLicenseServer\FileSystem\SoftwareRepository;
use SmartLicenseServer\FileSystem\ThemeRepository;

/**
 * Centralized registry for managing any type of 
 * hosted application in this system.
 * 
 * This registry stores the app types, their classes, database table names.
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
    private function __construct() {}

    /**
     * Get the singleton instance of this class.
     * 
     * @return static
     */
    public static function instance() : static {
        if ( null === static::$instance ) {
            static::$instance   = new static();
        }

        return static::$instance;
    }

    /**
     * Add a hosted app class to the registry.
     * 
     * @param string $type The unique type identifier for the app.
     * @param array{
     *  class: class-string<HostedAppsInterface>,
     *  directory_class: Repository,
     *  table: string 
     * }|null $data The app class and its database table name.
     * @return static
     */
    public function register( string $type, array $data ) : static {
        $this->ensure_core();

        $type   = $this->normalize_app_type( $type );

        if ( ! isset( $data['class'] ) || ! isset( $data['table'] ) || ! isset( $data['directory_class'] ) ) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Hosted app registration for type "%s" must include "class", "directory_class" and "table" keys.',
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
     *  directory_class: Repository,
     *  table: string,
     * }|null $entry 
     *
     */
    public function get_app_type_data( string $type ) : ?array {
        $this->ensure_core();

        $type   = $this->normalize_app_type( $type );
        $entry  = $this->core[ $type ] ?? $this->custom[ $type ] ?? null;
       
        /**
         * @var array{
         *  class: class-string<HostedAppsInterface>,
         *  directory_class: Repository,
         *  table: string,
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
     * Get the database table name for a registered hosted app.
     * 
     * @param string $type The unique type identifier for the app.
     * @return string|null The table name if found, null otherwise.
     */
    public function get_app_type_table( string $type ) : ?string {
        $entry  = $this->get_app_type_data( $type );
       
        if ( ! $entry ) {
            return null;
        }

        return $entry['table'];
    }

    /**
     * Get directory class for an app type.
     * 
     * @param string $type The app type.
     * @return Repository|null
     */
    public function get_app_type_directory_class( string $type ) : ?Repository {
        $entry  = $this->get_app_type_data( $type );
       
        if ( ! $entry ) {
            return null;
        }

        if ( ! \is_subclass_of( $entry['directory_class'], Repository::class ) ) {
            throw new InvalidArgumentException(
                sprintf( 'Directory class for app type "%s" must extend "%s"', $type, Repository::class )
            );
        }

        return new $entry['directory_class']();
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
     * {@inheritDoc}
     */
    protected function load_core() : void {
        if ( $this->core_loaded ) {
            return;
        }

        static $core_hosted_apps    = [
            'plugin'    => [
                'class'             => Plugin::class,
                'directory_class'   => PluginRepository::class,
                'table'             => Plugin::TABLE
            ],
            'theme'     => [
                'class'             => Theme::class,
                'directory_class'   => ThemeRepository::class,
                'table'             => Theme::TABLE
            ],
            'software'  => [
                'class'             => Software::class,
                'directory_class'   => SoftwareRepository::class,
                'table'             => Software::TABLE
            ]
        ];

        foreach ( $core_hosted_apps as $type => $data ) {
            $this->core[ $type ]    = $data;
        }

        $this->core_loaded = true;
    }

    protected function normalize_app_type( string $type ) : string {
        $type = strtolower( $type );

        return $type   = match ( $type ) {
            'plugins', 'plugin'     => 'plugin',
            'themes', 'theme'       => 'theme',
            'softwares', 'software' => 'software',
            default => $type
        };
    }
}