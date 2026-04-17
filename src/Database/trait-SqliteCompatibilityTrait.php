<?php
namespace SmartLicenseServer\Database;

trait SqliteCompatibilityTrait {

    /**
     * Translates common MySQL syntax/functions to SQLite equivalents.
     */
    protected function translate_mysql_to_sqlite( string $sql ) : string {
        // 1. Standardize Identifiers
        $sql = str_replace( '`', '"', $sql );

        // 2. Base Date/Time Constants
        $sql = str_ireplace( 'CURDATE()', "date('now')", $sql );
        $sql = str_ireplace( 'NOW()', "datetime('now')", $sql );

        // 3. Generic DATE_FORMAT Handler
        if ( stripos( $sql, 'DATE_FORMAT' ) !== false ) {
            $sql = preg_replace_callback(
                '/DATE_FORMAT\s*\(\s*([\w\"\.]+)\s*,\s*([\'"])(.+?)\2\s*\)/i',
                [ $this, 'map_date_format' ],
                $sql
            );
        }

        // 4. Robust DATE_SUB / DATE_ADD Handler
        // Matches units: SECOND, MINUTE, HOUR, DAY, WEEK, MONTH, YEAR
        $sql = preg_replace_callback(
            '/(DATE_SUB|DATE_ADD)\s*\(\s*(date\(\'now\'\)|datetime\(\'now\'\)|[\w\"\.]+)\s*,\s*INTERVAL\s+([\?\w\d]+)\s+([a-z]+)\s*\)/i',
            function( $matches ) {
                $func     = strtoupper( $matches[1] );
                $subject  = $matches[2];
                $val      = $matches[3];
                $unit     = strtolower( $matches[4] ) . 's'; // pluralize for SQLite
                $operator = ( $func === 'DATE_SUB' ) ? '-' : '+';
                
                return "datetime($subject, '$operator' || $val || ' $unit')";
            },
            $sql
        );

        // 5. Basic Function Mapping
        $mappings = [
            '/\bDATE\s*\(/i'   => 'date(',
            '/\bIFNULL\s*\(/i' => 'coalesce(',
            '/\bRAND\(\)/i'    => 'random()',
        ];

        return preg_replace( array_keys( $mappings ), array_values( $mappings ), $sql );
    }

    /**
     * Maps MySQL DATE_FORMAT tokens to SQLite strftime tokens.
     */
    protected function map_date_format( array $matches ) : string {
        $column = $matches[1];
        $format = $matches[3];

        // MySQL to SQLite Token Map
        $token_map = [
            '%Y' => '%Y', // 4-digit year
            '%y' => '%y', // 2-digit year
            '%m' => '%m', // month (01-12)
            '%d' => '%d', // day of month (01-31)
            '%H' => '%H', // hour (00-23)
            '%i' => '%M', // minutes (00-59) -> SQLite uses %M
            '%s' => '%S', // seconds (00-59) -> SQLite uses %S
            '%w' => '%w', // day of week (0-6)
            '%j' => '%j', // day of year (001-366)
        ];

        $translated_format = strtr( $format, $token_map );

        return "strftime('$translated_format', $column)";
    }
}