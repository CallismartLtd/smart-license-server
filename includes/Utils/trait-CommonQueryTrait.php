<?php
/**
 * Common database query trait file.
 * 
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Common reusable database query helpers for entity classes.
 *
 * This trait assumes:
 * - The consuming class implements `from_array( array $data )`
 * - A shared database abstraction via `smliser_dbclass()`
 */
trait CommonQueryTrait {

    use SanitizeAwareTrait;

    /**
     * Get a single entity by ID.
     *
     * @param int    $id    Entity ID.
     * @param string $table Database table name.
     * @return static|null
     */
    protected static function get_self_by_id( $id, $table ) : ?static {
        return self::get_self_by( 'id', $id, $table );
    }

    /**
     * Get a single entity by an arbitrary column.
     *
     * Intended for unique or near-unique lookups
     * (e.g. identifier, slug, token).
     *
     * @param string $column Column name.
     * @param mixed  $value  Column value.
     * @param string $table  Database table name.
     * @return static|null
     */
    protected static function get_self_by( string $column, $value, string $table ) : ?static {
        $db     = smliser_dbclass();
        $column = self::sanitize_key( $column );

        if ( empty( $column ) ) {
            return null;
        }

        $sql    = "SELECT * FROM {$table} WHERE `{$column}` = ? LIMIT 1";
        $result = $db->get_row( $sql, [ $value ] );

        return $result ? static::from_array( (array) $result ) : null;
    }

    /**
     * Get multiple entities by a column value.
     *
     * Useful for foreign-key style lookups
     * (e.g. owner_id, user_id).
     *
     * @param string $column Column name.
     * @param mixed  $value  Column value.
     * @param string $table  Database table name.
     * @return static[]
     */
    protected static function get_all_self_by( string $column, $value, string $table ) : array {
        $db     = smliser_dbclass();
        $column = self::sanitize_key( $column );

        if ( empty( $column ) ) {
            return [];
        }

        $sql     = "SELECT * FROM {$table} WHERE `{$column}` = ?";
        $results = $db->get_results( $sql, [ $value ] );

        if ( empty( $results ) ) {
            return [];
        }

        $entities = [];

        foreach ( $results as $row ) {
            $entities[] = static::from_array( (array) $row );
        }

        return $entities;
    }

    /**
     * Get all entities from a table.
     *
     * Use sparingly. Intended for admin or
     * internal system operations.
     *
     * @param string $table Database table name.
     * @param string $page The current pagination number.
     * @param int    $limit Optional limit.
     * @return static[]
     */
    protected static function get_all_self( string $table, int $page = 1, int $limit = 25 ) : array {
        $db = smliser_dbclass();

        $sql    = "SELECT * FROM {$table} LIMIT ? OFFSET ?";
        $offset = $db->calculate_query_offset( $page, $limit );

        $results = $db->get_results( $sql, [$limit, $offset] );

        if ( empty( $results ) ) {
            return [];
        }

        $entities = [];

        foreach ( $results as $row ) {
            $entities[] = static::from_array( (array) $row );
        }

        return $entities;
    }

    /**
     * Check if a record exists by column value.
     *
     * Lightweight existence check without hydration.
     *
     * @param string $column Column name.
     * @param mixed  $value  Column value.
     * @param string $table  Database table name.
     * @return bool
     */
    protected static function exists_by( string $column, $value, string $table ) : bool {
        $db     = smliser_dbclass();
        $column = self::sanitize_key( $column );

        if ( empty( $column ) ) {
            return false;
        }

        $sql    = "SELECT 1 FROM {$table} WHERE `{$column}` = ? LIMIT 1";
        $result = $db->get_var( $sql, [ $value ] );

        return ! empty( $result );
    }
}
