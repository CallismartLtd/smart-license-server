<?php
/**
 * Datastore class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

use Callismart\DBPrism\Database;
use Callismart\DBPrism\Query\SQLBuilder;
use RuntimeException;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Schema\SchemaRegistry;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Provides unified API to manage data retrieved from the database
 */
abstract class DataStore {
    use SanitizeAwareTrait, CacheAwareTrait;

    /**
     * The active database abstraction layer.
     * 
     * @var Database $DB
     */
    protected static Database $DB;

    protected static Cache $cache;

    /**
     * Get a single entity by an arbitrary column.
     * 
     * Intended for unique or near-unique lookups
     * (e.g. identifier, slug, token).
     * 
     * @param string $column Column name.
     * @param mixed  $value  Column value.
     * @param string $table  Database table name.
     */
    protected static function fetch_by( string $column, mixed $value, string $table ) : ?array {
        static::ensure_db();
        
        $column = self::sanitize_key( $column );

        if ( empty( $column ) ) {
            return null;
        }

        $sql    = static::query()
            ->select()->from( $table )
            ->where( $column, '=', $value )->limit(1);

        return static::$DB->get_row( $sql->build(), $sql->get_bindings() );
    }

    /**
     * Get entries from a given table.
     * 
     * Use sparingly. Intended for admin or
     * internal system operations.
     * 
     * @param string $table     The target table name.
     * @param int $page         The pagination number.
     * @param int $limit        The maximum record to fetch
     * @return array<int,array>
     */
    protected static function fetch( string $table, int $page = 1, int $limit = 25 ) : array {
        static::ensure_db();

        $offset = static::$DB->calculate_query_offset( $page, $limit );
        $sql    = static::query()
            ->select()->from( $table )
            ->limit( $limit )->offset( $offset );
        
        return static::$DB->get_results( $sql->build(), $sql->get_bindings() );

    }


    final public static function set_database( Database $database ): void {
        if ( isset( static::$DB ) ) {
            throw new RuntimeException(
                'The DataStore database has already been initialized.'
            );
        }

        static::$DB = $database;
    }

    final public static function set_cache( Cache $cache ): void {
        if ( isset( static::$cache ) ) {
            throw new RuntimeException(
                'The DataStore cache has already been initialized.'
            );
        }

        static::$cache = $cache;
    }

    /**
     * Helper method to hydrate from array.
     * 
     * Use only when child class's `set_*` methods matches
     * column names.
     * 
     * @param string $table The database table name.
     * @param array $data
     * @return static
     */
    protected static function from_array_helper( string $table, array $data ) : static {
        $self   = new static();
        $cols   = SchemaRegistry::instance()->get_table_column_names( $table );
        foreach ( $cols as $col ) {
            if ( ! isset( $data[$col] ) ) {
                continue;
            }

            $method = "set_{$col}";

            if ( is_callable( [$self, $method] ) ) {
                $self->{$method}( $data[$col] );
            }
        }
        
        return $self;
    }

    protected static function query() : SQLBuilder {
        return \smliserQueryBuilder( static::$DB->get_driver() );
    }

    /**
     * Ensure dbal has been initialized.
     * 
     * @throws RuntimeException
     */
    protected static function ensure_db() : void {
        if ( isset( static::$DB ) ) {
            return;
        }

        throw new RuntimeException(
            'The database abstraction layer must be set early.'
        );
    }
    
    protected static function ensure_cache() : void {
        if ( isset( static::$cache ) ) {
            return;
        }

        throw new \RuntimeException(
            'The cache abstraction layer must be set early.'
        );
    }
}