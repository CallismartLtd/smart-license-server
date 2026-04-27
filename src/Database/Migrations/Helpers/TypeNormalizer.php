<?php
/**
 * Type Normalizer for Database Abstraction
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Database\Migrations
 * @since 0.2.0
 */

namespace SmartLicenseServer\Database\Migrations\Helpers;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Normalizes SQL data types across different database engines.
 *
 * Handles type conversion between MySQL, PostgreSQL, and SQLite,
 * allowing migrations to be written in a database-agnostic way.
 *
 * @since 0.2.0
 */
class TypeNormalizer {

    /**
     * Type mappings across database engines.
     *
     * Maps canonical types to engine-specific types.
     *
     * @var array<string, array<string, string>> $type_maps
     */
    private static $type_maps = [
        // String types
        'CHAR' => [
            'mysql'  => 'CHAR',
            'pgsql'  => 'CHAR',
            'sqlite' => 'TEXT',
        ],
        'VARCHAR' => [
            'mysql'  => 'VARCHAR',
            'pgsql'  => 'VARCHAR',
            'sqlite' => 'TEXT',
        ],
        'TEXT' => [
            'mysql'  => 'TEXT',
            'pgsql'  => 'TEXT',
            'sqlite' => 'TEXT',
        ],
        'LONGTEXT' => [
            'mysql'  => 'LONGTEXT',
            'pgsql'  => 'TEXT',
            'sqlite' => 'TEXT',
        ],
        'MEDIUMTEXT' => [
            'mysql'  => 'MEDIUMTEXT',
            'pgsql'  => 'TEXT',
            'sqlite' => 'TEXT',
        ],

        // Numeric types
        'TINYINT' => [
            'mysql'  => 'TINYINT',
            'pgsql'  => 'SMALLINT',
            'sqlite' => 'INTEGER',
        ],
        'SMALLINT' => [
            'mysql'  => 'SMALLINT',
            'pgsql'  => 'SMALLINT',
            'sqlite' => 'INTEGER',
        ],
        'MEDIUMINT' => [
            'mysql'  => 'MEDIUMINT',
            'pgsql'  => 'INTEGER',
            'sqlite' => 'INTEGER',
        ],
        'INT' => [
            'mysql'  => 'INT',
            'pgsql'  => 'INTEGER',
            'sqlite' => 'INTEGER',
        ],
        'INTEGER' => [
            'mysql'  => 'INT',
            'pgsql'  => 'INTEGER',
            'sqlite' => 'INTEGER',
        ],
        'BIGINT' => [
            'mysql'  => 'BIGINT',
            'pgsql'  => 'BIGINT',
            'sqlite' => 'INTEGER',
        ],
        'FLOAT' => [
            'mysql'  => 'FLOAT',
            'pgsql'  => 'FLOAT',
            'sqlite' => 'REAL',
        ],
        'DOUBLE' => [
            'mysql'  => 'DOUBLE',
            'pgsql'  => 'DOUBLE PRECISION',
            'sqlite' => 'REAL',
        ],
        'DECIMAL' => [
            'mysql'  => 'DECIMAL',
            'pgsql'  => 'NUMERIC',
            'sqlite' => 'REAL',
        ],

        // Boolean
        'BOOLEAN' => [
            'mysql'  => 'TINYINT(1)',
            'pgsql'  => 'BOOLEAN',
            'sqlite' => 'INTEGER',
        ],

        // Date/Time
        'DATE' => [
            'mysql'  => 'DATE',
            'pgsql'  => 'DATE',
            'sqlite' => 'TEXT',
        ],
        'TIME' => [
            'mysql'  => 'TIME',
            'pgsql'  => 'TIME',
            'sqlite' => 'TEXT',
        ],
        'DATETIME' => [
            'mysql'  => 'DATETIME',
            'pgsql'  => 'TIMESTAMP',
            'sqlite' => 'TEXT',
        ],
        'TIMESTAMP' => [
            'mysql'  => 'TIMESTAMP',
            'pgsql'  => 'TIMESTAMP',
            'sqlite' => 'TEXT',
        ],

        // JSON
        'JSON' => [
            'mysql'  => 'JSON',
            'pgsql'  => 'JSONB',
            'sqlite' => 'TEXT',
        ],

        // Binary
        'BLOB' => [
            'mysql'  => 'BLOB',
            'pgsql'  => 'BYTEA',
            'sqlite' => 'BLOB',
        ],
        'LONGBLOB' => [
            'mysql'  => 'LONGBLOB',
            'pgsql'  => 'BYTEA',
            'sqlite' => 'BLOB',
        ],

        // Enum
        'ENUM' => [
            'mysql'  => 'ENUM',
            'pgsql'  => 'VARCHAR',  // PostgreSQL: use VARCHAR or CREATE TYPE
            'sqlite' => 'TEXT',
        ],
    ];

    /**
     * Normalize a data type for the target database engine.
     *
     * Converts a canonical type name (possibly with modifiers like VARCHAR(255))
     * into the appropriate type for the target database engine.
     *
     * @param string $type   The canonical type (e.g., 'VARCHAR(255)', 'BIGINT(20)', 'DATETIME')
     * @param string $engine The target database engine ('mysql', 'pgsql', 'sqlite')
     *
     * @return string The normalized type for the target engine
     *
     * @example
     * normalize('VARCHAR(255)', 'mysql')  // Returns 'VARCHAR(255)'
     * normalize('VARCHAR(255)', 'sqlite') // Returns 'TEXT'
     * normalize('BIGINT(20)', 'pgsql')    // Returns 'BIGINT'
     * normalize('DATETIME', 'pgsql')      // Returns 'TIMESTAMP'
     */
    public static function normalize( string $type, string $engine ) : string {
        $engine = strtolower( $engine );

        // Extract base type and modifiers
        $base_type = self::extract_base_type( $type );
        $modifiers = self::extract_modifiers( $type );

        // Get the normalized type
        $normalized = self::get_normalized_type( $base_type, $engine );

        // Reapply modifiers where applicable
        return self::apply_modifiers( $normalized, $modifiers, $engine );
    }

    /**
     * Extract the base type from a type string.
     *
     * @param string $type Type string (e.g., 'VARCHAR(255)', 'BIGINT(20)')
     *
     * @return string The base type (e.g., 'VARCHAR', 'BIGINT')
     */
    private static function extract_base_type( string $type ) : string {
        // Remove everything after first space or parenthesis
        return strtoupper( preg_replace( '/[\s(].*/i', '', $type ) );
    }

    /**
     * Extract modifiers from a type string.
     *
     * @param string $type Type string (e.g., 'VARCHAR(255)', 'DECIMAL(10,2)')
     *
     * @return array Array of modifiers (e.g., ['255'] or ['10', '2'])
     */
    private static function extract_modifiers( string $type ) : array {
        // Match content within parentheses
        if ( preg_match( '/\(([^)]+)\)/i', $type, $matches ) ) {
            return array_map( 'trim', explode( ',', $matches[1] ) );
        }
        return [];
    }

    /**
     * Get the normalized type for a specific engine.
     *
     * @param string $base_type The base type (e.g., 'VARCHAR')
     * @param string $engine    The database engine
     *
     * @return string The normalized type
     */
    private static function get_normalized_type( string $base_type, string $engine ) : string {
        $base_type = strtoupper( $base_type );

        // Check if type mapping exists
        if ( ! isset( self::$type_maps[ $base_type ] ) ) {
            // Unknown type, return as-is
            return $base_type;
        }

        if ( ! isset( self::$type_maps[ $base_type ][ $engine ] ) ) {
            // Engine not in mapping, return as-is
            return $base_type;
        }

        return self::$type_maps[ $base_type ][ $engine ];
    }

    /**
     * Apply modifiers to the normalized type.
     *
     * Different databases have different rules for modifiers.
     *
     * @param string $normalized_type The normalized type
     * @param array  $modifiers       Array of modifiers
     * @param string $engine          The database engine
     *
     * @return string The type with modifiers applied
     */
    private static function apply_modifiers( string $normalized_type, array $modifiers, string $engine ) : string {
        if ( empty( $modifiers ) ) {
            return $normalized_type;
        }

        switch ( $engine ) {
            case 'mysql':
                // MySQL supports most modifiers
                return $normalized_type . '(' . implode( ',', $modifiers ) . ')';

            case 'pgsql':
                // PostgreSQL has stricter modifier rules
                if ( in_array( $normalized_type, [ 'VARCHAR', 'CHAR' ] ) ) {
                    return $normalized_type . '(' . $modifiers[0] . ')';
                }
                if ( in_array( $normalized_type, [ 'NUMERIC', 'DECIMAL' ] ) ) {
                    return $normalized_type . '(' . implode( ',', $modifiers ) . ')';
                }
                return $normalized_type;

            case 'sqlite':
                // SQLite ignores most modifiers for compatibility
                return $normalized_type;

            default:
                return $normalized_type . '(' . implode( ',', $modifiers ) . ')';
        }
    }

    /**
     * Check if a type supports length modifier (VARCHAR, CHAR, etc.)
     *
     * @param string $type The type to check
     *
     * @return bool True if type supports length modifier
     */
    public static function supports_length( string $type ) : bool {
        $base_type = self::extract_base_type( $type );
        return in_array( $base_type, [ 'VARCHAR', 'CHAR', 'VARBINARY', 'BINARY' ], true );
    }

    /**
     * Check if a type supports precision/scale (DECIMAL, NUMERIC, etc.)
     *
     * @param string $type The type to check
     *
     * @return bool True if type supports precision/scale
     */
    public static function supports_precision( string $type ) : bool {
        $base_type = self::extract_base_type( $type );
        return in_array( $base_type, [ 'DECIMAL', 'NUMERIC', 'FLOAT', 'DOUBLE' ], true );
    }

    /**
     * Get all available canonical types.
     *
     * @return array<string> Array of canonical type names
     */
    public static function get_available_types() : array {
        return array_keys( self::$type_maps );
    }
}