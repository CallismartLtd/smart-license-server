<?php
/**
 * Database adapter registry class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Schema;

use Callismart\DBPrism\Adapters\Contracts\DatabaseAdapterInterface;
use Callismart\DBPrism\Adapters\MysqliAdapter;
use Callismart\DBPrism\Adapters\PdoAdapter;
use Callismart\DBPrism\Adapters\PostgresAdapter;
use Callismart\DBPrism\Adapters\SqliteAdapter;
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Exceptions\DatabaseException;

/**
 * Database adapter registry.
 *
 * Manages database adapters, groups adapters by supported database engine,
 * and maintains the default adapter for each registered engine.
 *
 * Core adapters are registered by the application and cannot be overridden
 * or removed by custom registrations. Custom adapters may be registered by
 * extensions and may provide support for existing or previously unsupported
 * database engines.
 */
class DatabaseAdapterRegistry extends AbstractRegistry {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    private static $instance;

    /**
     * Database adapters grouped by database engine.
     *
     * @var array<string, array<string, class-string<DatabaseAdapterInterface>>>
     */
    protected $engines = [];

    /**
     * Default adapter IDs grouped by database engine.
     *
     * @var array<string, string>
     */
    protected $defaults = [];

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function instance() : self {
        if ( ! isset( self::$instance ) ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Load core database adapters.
     *
     * @return void
     */
    protected function load_core() : void {
        $this->register_core_adapter(
            'mysql',
            'mysqli',
            MysqliAdapter::class,
            true
        );

        $this->register_core_adapter(
            'mysql',
            'pdo',
            PdoAdapter::class
        );

        $this->register_core_adapter(
            'pgsql',
            'pgsql',
            PostgresAdapter::class,
            true
        );

        $this->register_core_adapter(
            'pgsql',
            'pdo',
            PdoAdapter::class
        );

        $this->register_core_adapter(
            'sqlite',
            'sqlite',
            SqliteAdapter::class,
            true
        );

        $this->register_core_adapter(
            'sqlite',
            'pdo',
            PdoAdapter::class
        );
    }

    /**
     * Register a core database adapter for a database engine.
     *
     * Core adapters are stored in both the global registry and the engine
     * index. Core adapters take precedence over custom adapters with the
     * same adapter ID.
     *
     * @param string $engine Database engine identifier.
     * @param string $adapter_id Unique adapter identifier.
     * @param class-string<DatabaseAdapterInterface> $class_string Adapter class.
     * @param bool $default Whether to make the adapter the engine default.
     * @return void
     *
     * @throws DatabaseException If the adapter class is invalid or the
     *                           adapter ID conflicts with another core adapter.
     */
    protected function register_core_adapter(
        string $engine,
        string $adapter_id,
        string $class_string,
        bool $default = false
    ) : void {
        $this->assert_valid_engine( $engine );
        $this->assert_valid_adapter_id( $adapter_id );
        $this->assert_implements_interface( $class_string );

        if (
            isset( $this->core[ $adapter_id ] ) &&
            $this->core[ $adapter_id ] !== $class_string
        ) {
            throw new DatabaseException(
                'adapter_id_conflict',
                null,
                [
                    'adapter_id'        => $adapter_id,
                    'existing_adapter'  => $this->core[ $adapter_id ],
                    'requested_adapter' => $class_string,
                    'engine'            => $engine,
                ]
            );
        }

        $this->core[ $adapter_id ] = $class_string;
        $this->engines[ $engine ][ $adapter_id ] = $class_string;

        if ( $default || ! isset( $this->defaults[ $engine ] ) ) {
            $this->defaults[ $engine ] = $adapter_id;
        }
    }

    /**
     * Register a custom database adapter for a database engine.
     *
     * If no adapter ID is supplied, the adapter's fully-qualified class name
     * is used as its ID. Core adapters always take precedence over custom
     * adapters with the same ID.
     *
     * If the specified database engine has no default adapter, the newly
     * registered adapter is automatically assigned as its default adapter.
     *
     * @param string $engine Database engine identifier.
     * @param class-string<DatabaseAdapterInterface> $class_string Adapter class.
     * @param string|null $adapter_id Optional adapter ID.
     * @return static
     *
     * @throws DatabaseException If the adapter class or adapter ID is invalid.
     */
    public function add_adapter(
        string $engine,
        string $class_string,
        ?string $adapter_id = null
    ) : static {
        $this->ensure_core();
        $this->assert_valid_engine( $engine );
        $this->assert_implements_interface( $class_string );

        $adapter_id = $adapter_id ?: $this->get_adapter_id( $class_string );

        $this->assert_valid_adapter_id( $adapter_id );

        // Core adapters always take precedence.
        if ( isset( $this->core[ $adapter_id ] ) ) {
            return $this;
        }

        $this->remove_engine_indexes( $adapter_id );

        $this->custom[ $adapter_id ] = $class_string;
        $this->engines[ $engine ][ $adapter_id ] = $class_string;

        // A newly introduced engine needs a default adapter.
        if ( ! isset( $this->defaults[ $engine ] ) ) {
            $this->defaults[ $engine ] = $adapter_id;
        }

        return $this;
    }

    /**
     * Get all adapters registered for a database engine.
     *
     * @param string $engine Database engine identifier.
     * @param bool $instantiate Whether to instantiate the adapters.
     * @return array<string, class-string<DatabaseAdapterInterface>|DatabaseAdapterInterface>
     */
    public function for_engine(
        string $engine,
        bool $instantiate = false
    ) : array {
        $this->ensure_core();

        $adapters = $this->engines[ $engine ] ?? [];

        return $instantiate
            ? $this->instantiate_adapters( $adapters )
            : $adapters;
    }

    /**
     * Select a database adapter for a database engine.
     *
     * If an adapter ID is provided, that adapter is selected when it is
     * registered for the specified database engine.
     *
     * When no adapter ID is supplied, the configured default adapter is
     * selected. If the engine has no configured default, the first registered
     * adapter is selected.
     *
     * A configured default that no longer exists is treated as an invalid
     * registry state rather than silently falling back to another adapter.
     *
     * @param string $engine Database engine identifier.
     * @param string|null $adapter_id Optional adapter ID to select explicitly.
     * @return class-string<DatabaseAdapterInterface>
     *
     * @throws DatabaseException If no adapter is registered for the engine,
     *                           the requested adapter is not registered for
     *                           the engine, or the configured default is
     *                           invalid.
     */
    public function select(
        string $engine,
        ?string $adapter_id = null
    ) : string {
        $this->ensure_core();

        $adapters = $this->engines[ $engine ] ?? [];

        if ( ! $adapters ) {
            throw new DatabaseException(
                'no_adapter_for_engine',
                null,
                [
                    'engine' => $engine,
                ]
            );
        }

        if ( null !== $adapter_id ) {
            if ( ! isset( $adapters[ $adapter_id ] ) ) {
                throw new DatabaseException(
                    'adapter_engine_mismatch',
                    null,
                    [
                        'engine'     => $engine,
                        'adapter_id' => $adapter_id,
                    ]
                );
            }

            return $adapters[ $adapter_id ];
        }

        $default_id = $this->defaults[ $engine ] ?? null;

        if ( null !== $default_id ) {
            if ( ! isset( $adapters[ $default_id ] ) ) {
                throw new DatabaseException(
                    'default_adapter_invalid',
                    null,
                    [
                        'engine'     => $engine,
                        'adapter_id' => $default_id,
                    ]
                );
            }

            return $adapters[ $default_id ];
        }

        $class_string = reset( $adapters );

        return $class_string;
    }

    /**
     * Get the default adapter for a database engine.
     *
     * @param string $engine Database engine identifier.
     * @param bool $instantiate Whether to instantiate the adapter.
     * @return class-string<DatabaseAdapterInterface>|DatabaseAdapterInterface|null
     *
     * @throws DatabaseException If a configured default adapter is no longer
     *                           registered for the specified engine.
     */
    public function get_default(
        string $engine,
        bool $instantiate = false
    ) {
        $this->ensure_core();

        $adapter_id = $this->defaults[ $engine ] ?? null;

        if ( null === $adapter_id ) {
            return null;
        }

        $class_string = $this->engines[ $engine ][ $adapter_id ] ?? null;

        if ( null === $class_string ) {
            throw new DatabaseException(
                'default_adapter_invalid',
                null,
                [
                    'engine'     => $engine,
                    'adapter_id' => $adapter_id,
                ]
            );
        }

        return $instantiate ? new $class_string : $class_string;
    }

    /**
     * Set the default adapter for a database engine.
     *
     * The adapter must already be registered for the specified engine.
     *
     * @param string $engine Database engine identifier.
     * @param string $adapter_id Adapter identifier.
     * @return static
     *
     * @throws DatabaseException If the adapter is not registered for the
     *                           specified engine.
     */
    public function set_default(
        string $engine,
        string $adapter_id
    ) : static {
        $this->ensure_core();

        if ( ! isset( $this->engines[ $engine ][ $adapter_id ] ) ) {
            throw new DatabaseException(
                'adapter_engine_mismatch',
                null,
                [
                    'engine'     => $engine,
                    'adapter_id' => $adapter_id,
                ]
            );
        }

        $this->defaults[ $engine ] = $adapter_id;

        return $this;
    }

    /**
     * Get all registered database adapters.
     *
     * @param bool $assoc Whether to preserve keys by adapter ID.
     * @param bool $instantiate Whether to instantiate the adapters.
     * @return array<int|string, class-string<DatabaseAdapterInterface>|DatabaseAdapterInterface>
     */
    public function all(
        bool $assoc = true,
        bool $instantiate = false
    ) : array {
        return parent::all( $assoc, $instantiate );
    }

    /**
     * Get all core database adapters.
     *
     * @return array<string, class-string<DatabaseAdapterInterface>>
     */
    public function core() : array {
        return parent::core();
    }

    /**
     * Get all custom database adapters.
     *
     * @return array<string, class-string<DatabaseAdapterInterface>>
     */
    public function custom() : array {
        return parent::custom();
    }

    /**
     * Remove a custom database adapter.
     *
     * Core adapters cannot be removed. Removing a custom adapter also removes
     * its engine registrations and any default assignments referring to it.
     *
     * @param string $id Adapter identifier.
     * @return bool True if the adapter was removed, false otherwise.
     */
    public function remove( $id ) : bool {
        if ( ! parent::remove( $id ) ) {
            return false;
        }

        $this->remove_engine_indexes( $id );

        foreach ( $this->defaults as $engine => $default_id ) {
            if ( $default_id === $id ) {
                unset( $this->defaults[ $engine ] );
            }
        }

        return true;
    }

    /**
     * Get all registered database engine identifiers.
     *
     * @return string[]
     */
    public function engines() : array {
        $this->ensure_core();

        return array_keys( $this->engines );
    }

    /**
     * Get the default adapter IDs grouped by database engine.
     *
     * @return array<string, string>
     */
    public function defaults() : array {
        $this->ensure_core();

        return $this->defaults;
    }

    /**
     * Remove an adapter from all engine indexes.
     *
     * @param string $adapter_id Adapter identifier.
     * @return void
     */
    protected function remove_engine_indexes( string $adapter_id ) : void {
        foreach ( array_keys( $this->engines ) as $engine ) {
            if ( isset( $this->engines[ $engine ][ $adapter_id ] ) ) {
                unset( $this->engines[ $engine ][ $adapter_id ] );
            }

            if ( ! $this->engines[ $engine ] ) {
                unset( $this->engines[ $engine ] );
            }
        }
    }

    /**
     * Instantiate a collection of database adapters.
     *
     * @param array<string, class-string<DatabaseAdapterInterface>> $adapters
     * @return array<string, DatabaseAdapterInterface>
     */
    protected function instantiate_adapters( array $adapters ) : array {
        foreach ( $adapters as $id => $class_string ) {
            $adapters[ $id ] = new $class_string;
        }

        return $adapters;
    }

    /**
     * Assert that a database engine identifier is valid.
     *
     * @param string $engine Database engine identifier.
     * @return void
     *
     * @throws DatabaseException If the engine identifier is empty.
     */
    protected function assert_valid_engine( string $engine ) : void {
        if ( '' === trim( $engine ) ) {
            throw new DatabaseException(
                'adapter_id_invalid',
                'Database engine identifier cannot be empty.',
                [
                    'engine' => $engine,
                ]
            );
        }
    }

    /**
     * Assert that a database adapter ID is valid.
     *
     * @param string $adapter_id Adapter identifier.
     * @return void
     *
     * @throws DatabaseException If the adapter ID is empty.
     */
    protected function assert_valid_adapter_id( string $adapter_id ) : void {
        if ( '' === trim( $adapter_id ) ) {
            throw new DatabaseException(
                'adapter_id_invalid',
                null,
                [
                    'adapter_id' => $adapter_id,
                ]
            );
        }
    }

    /**
     * Assert database adapter interface compliance.
     *
     * @param class-string $class_string
     * @return void
     *
     * @throws DatabaseException If the class does not exist or does not
     *                           implement DatabaseAdapterInterface.
     */
    protected function assert_implements_interface(
        string $class_string
    ) : void {
        if ( ! class_exists( $class_string ) ) {
            throw new DatabaseException(
                'adapter_class_not_found',
                null,
                [
                    'class' => $class_string,
                ]
            );
        }

        if (
            ! in_array(
                DatabaseAdapterInterface::class,
                class_implements( $class_string ) ?: [],
                true
            )
        ) {
            throw new DatabaseException(
                'adapter_interface_invalid',
                null,
                [
                    'class'     => $class_string,
                    'interface' => DatabaseAdapterInterface::class,
                ]
            );
        }
    }

    /**
     * Resolve an adapter ID from its class string.
     *
     * The fully-qualified class name is used as the fallback identifier
     * because DatabaseAdapterInterface does not define an adapter ID contract.
     *
     * @param class-string<DatabaseAdapterInterface> $class_string
     * @return string
     */
    protected function get_adapter_id( string $class_string ) : string {
        return $class_string;
    }
}