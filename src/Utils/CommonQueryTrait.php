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
 * - A shared database abstraction via `smliser_db()`
 */
trait CommonQueryTrait {

    use SanitizeAwareTrait;

    /**
     * Get a single entity by ID.
     *
     * @param int    $id
     * @param string $table
     * @return static|null
     */
    protected static function get_self_by_id( $id, $table ) : ?static {
        return static::get_self_by( 'id', $id, $table );
    }

    /**
     * Get a single entity by arbitrary column.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $table
     * @return static|null
     */
    protected static function get_self_by( string $column, $value, string $table ) : ?static {
        $db     = smliser_db();
        $column = static::sanitize_key( $column );

        if ( empty( $column ) ) {
            return null;
        }

        $qb = \smliserQueryBuilder();

        $sql = $qb
            ->select( '*' )
            ->from( $table )
            ->where( "{$column} = ?", [ $value ] )
            ->limit( 1 )
            ->build();

        $result = $db->get_row( $sql, $qb->get_bindings() );

        return $result ? static::from_array( (array) $result ) : null;
    }

    /**
     * Get multiple entities by column value.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $table
     * @return static[]
     */
    protected static function get_all_self_by( string $column, $value, string $table ) : array {
        $db     = smliser_db();
        $column = static::sanitize_key( $column );

        if ( empty( $column ) ) {
            return [];
        }

        $qb = \smliserQueryBuilder();

        $sql = $qb
            ->select( '*' )
            ->from( $table )
            ->where( "{$column} = ?", [ $value ] )
            ->build();

        $results = $db->get_results( $sql, $qb->get_bindings() );

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
     * Get paginated entities.
     *
     * @param string $table
     * @param int    $page
     * @param int    $limit
     * @return static[]
     */
    protected static function get_all_self( string $table, int $page = 1, int $limit = 25 ) : array {
        $db     = smliser_db();
        $page   = max( 1, $page );
        $limit  = max( 1, $limit );
        $offset = $db->calculate_query_offset( $page, $limit );

        $qb = \smliserQueryBuilder();

        $sql = $qb
            ->select( '*' )
            ->from( $table )
            ->limit( $limit )
            ->offset( $offset )
            ->build();

        $results = $db->get_results( $sql, $qb->get_bindings() );

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
     * Check if record exists by column value.
     *
     * @param string $column
     * @param mixed  $value
     * @param string $table
     * @return bool
     */
    protected static function exists_by( string $column, $value, string $table ) : bool {
        $db     = smliser_db();
        $column = static::sanitize_key( $column );

        if ( empty( $column ) ) {
            return false;
        }

        $qb = \smliserQueryBuilder();

        $sql = $qb
            ->select( '1' )
            ->from( $table )
            ->where( "{$column} = ?", [ $value ] )
            ->limit( 1 )
            ->build();

        $result = $db->get_var( $sql, $qb->get_bindings() );

        return ! empty( $result );
    }
}