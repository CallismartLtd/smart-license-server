<?php
/**
 * Formatting utilities.
 *
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

defined( 'SMLISER_ABSPATH' ) || exit;

class Format {

    /**
     * Format bytes into human readable form.
     */
    public static function bytes( int $bytes, int $precision = 2 ): string {

        $units = [ 'B', 'KB', 'MB', 'GB', 'TB', 'PB' ];

        $bytes = max( $bytes, 0 );
        $pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
        $pow   = min( $pow, count( $units ) - 1 );

        $bytes /= ( 1 << ( 10 * $pow ) );

        return round( $bytes, $precision ) . ' ' . $units[ $pow ];
    }

    /**
     * Format seconds into readable duration.
     */
    public static function duration( int $seconds, string $format = 'long' ): string {

        $units = [
            'day'    => 86400,
            'hour'   => 3600,
            'minute' => 60,
            'second' => 1,
        ];

        $short = [
            'day'    => 'd',
            'hour'   => 'h',
            'minute' => 'm',
            'second' => 's',
        ];

        $parts = [];

        foreach ( $units as $name => $div ) {

            $value = intdiv( $seconds, $div );

            if ( $value > 0 ) {

                if ( $format === 'short' ) {
                    $parts[] = $value . $short[$name];
                } else {
                    $parts[] = $value . ' ' . $name . ( $value > 1 ? 's' : '' );
                }

                $seconds %= $div;
            }
        }

        return $parts ? implode( ' ', $parts ) : ( $format === 'short' ? '0s' : '0 seconds' );
    }

    /**
     * Short duration format (e.g. 1h 20m 4s).
     */
    public static function short_duration( int $seconds ): string {

        $map = [
            'd' => 86400,
            'h' => 3600,
            'm' => 60,
            's' => 1,
        ];

        $parts = [];

        foreach ( $map as $unit => $value ) {

            $count = intdiv( $seconds, $value );

            if ( $count > 0 ) {
                $parts[] = $count . $unit;
                $seconds %= $value;
            }
        }

        return $parts ? implode( ' ', $parts ) : '0s';
    }

    /**
     * Format number with thousands separator.
     */
    public static function number( float|int $number, int $decimals = 0 ): string {
        return number_format( $number, $decimals, '.', ',' );
    }

    /**
     * Format percentage.
     */
    public static function percent( float $value, int $precision = 2 ): string {
        return number_format( $value * 100, $precision ) . '%';
    }

    /**
     * Format timestamp.
     */
    public static function datetime( int $timestamp, string $format = 'Y-m-d H:i:s' ): string {
        return date( $format, $timestamp );
    }

    /**
     * Human-readable time difference.
     */
    public static function time_ago( int $timestamp ): string {

        $diff = time() - $timestamp;

        if ( $diff < 60 ) {
            return $diff . ' seconds ago';
        }

        if ( $diff < 3600 ) {
            return floor( $diff / 60 ) . ' minutes ago';
        }

        if ( $diff < 86400 ) {
            return floor( $diff / 3600 ) . ' hours ago';
        }

        return floor( $diff / 86400 ) . ' days ago';
    }

    /**
     * Convert duration (seconds) to human readable form.
     *
     * Shows the two most significant units.
     *
     * @param int|float $duration Duration in seconds.
     * @return string
     */
    public static function duration_ago( int|float $duration ): string {

        if ( ! is_numeric( $duration ) ) {
            return 'Invalid duration';
        }

        if ( $duration <= 0 ) {
            return '0 seconds';
        }

        $units = [
            'year'        => 31536000,
            'month'       => 2592000,
            'week'        => 604800,
            'day'         => 86400,
            'hour'        => 3600,
            'minute'      => 60,
            'second'      => 1,
            'millisecond' => 0.001,
        ];

        $parts     = [];
        $remaining = (float) $duration;

        foreach ( $units as $name => $seconds ) {

            $value = floor( $remaining / $seconds );

            if ( $value > 0 ) {

                $parts[] = $value . ' ' . $name . ( $value > 1 ? 's' : '' );
                $remaining -= $value * $seconds;

                // Only show two significant units.
                if ( count( $parts ) === 2 ) {
                    break;
                }
            }
        }

        // Handle very small durations (< 1ms)
        if ( empty( $parts ) ) {
            $ms = round( $duration * 1000, 2 );
            return $ms . ' milliseconds';
        }

        return implode( ', ', $parts );
    }

    /**
     * Calculate the difference between two dates and return a human readable string.
     *
     * Shows the two most significant units.
     *
     * @param string $start_date Start date in 'Y-m-d H:i:s' format.
     * @param string $end_date   End date in 'Y-m-d H:i:s' format.
     * @return string
     */
    public static function time_diff_string( string $start_date, string $end_date ): string {

        try {
            $start = new \DateTime( $start_date );
            $end   = new \DateTime( $end_date );
        } catch ( \Exception $e ) {
            return 'Invalid date';
        }

        $interval = $start->diff( $end );

        $units = [
            'year'   => $interval->y,
            'month'  => $interval->m,
            'day'    => $interval->d,
            'hour'   => $interval->h,
            'minute' => $interval->i,
            'second' => $interval->s,
        ];

        $parts = [];

        foreach ( $units as $name => $value ) {

            if ( $value > 0 ) {

                $parts[] = $value . ' ' . $name . ( $value > 1 ? 's' : '' );

                // Keep only the two most significant units.
                if ( count( $parts ) === 2 ) {
                    break;
                }
            }
        }

        return empty( $parts ) ? '0 seconds' : implode( ', ', $parts );
    }

    /**
     * Format large numbers (1K, 1M, 1B).
     */
    public static function compact_number( int|float $number ): string {

        if ( $number >= 1_000_000_000 ) {
            return round( $number / 1_000_000_000, 1 ) . 'B';
        }

        if ( $number >= 1_000_000 ) {
            return round( $number / 1_000_000, 1 ) . 'M';
        }

        if ( $number >= 1000 ) {
            return round( $number / 1000, 1 ) . 'K';
        }

        return (string) $number;
    }

    /**
     * Truncate long string.
     */
    public static function truncate( string $string, int $length = 50 ): string {

        if ( strlen( $string ) <= $length ) {
            return $string;
        }

        return substr( $string, 0, $length ) . '...';
    }

    /**
     * Mask sensitive string (for logs).
     */
    public static function mask( string $value, int $visible = 4 ): string {

        $len = strlen( $value );

        if ( $len <= $visible ) {
            return $value;
        }

        return str_repeat( '*', $len - $visible ) . substr( $value, -$visible );
    }

    /**
     * Format boolean.
     */
    public static function bool( bool $value ): string {
        return $value ? 'true' : 'false';
    }

    /**
     * Format yes/no.
     */
    public static function yes_no( bool $value ): string {
        return $value ? 'Yes' : 'No';
    }

    /**
     * Limit array display length.
     */
    public static function array_preview( array $array, int $limit = 5 ): array {
        return array_slice( $array, 0, $limit );
    }

    /**
     * Format key-value table (for CLI/debug output).
     */
    public static function table( array $data ): string {

        $lines = [];

        foreach ( $data as $key => $value ) {
            $lines[] = str_pad( $key, 20 ) . ': ' . $value;
        }

        return implode( PHP_EOL, $lines );
    }

    /**
     * Generate a cryptographically secure UUID version 4.
     *
     * This function creates a random UUID compliant with RFC 4122 §4.4,
     * which defines version 4 (random) UUIDs.
     *
     * Behavior:
     * - Generates 16 bytes (128 bits) of cryptographically secure randomness.
     * - Sets the UUID version field to 4 (binary 0100).
     * - Sets the variant field to RFC 4122 standard (binary 10xx).
     * - Formats the result as a canonical UUID string:
     *   xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
     *
     * Format Details:
     * - Total length: 36 characters (32 hex digits + 4 hyphens)
     * - Version nibble (position 13): always "4"
     * - Variant bits (position 17): one of 8, 9, A, or B
     *
     * @example output:
     *   ```550e8400-e29b-41d4-a716-446655440000```
     *
     * @return string RFC 4122-compliant UUID v4.
     * @throws \Exception If a secure random source is unavailable.
     */
    public static function uuid_4() : string {
        $data = random_bytes(16);

        // Set version to 0100 (UUID version 4).
        $data[6] = chr( ord( $data[6] ) & 0x0f | 0x40 );

        // Set variant to 10xx (RFC 4122 variant).
        $data[8] = chr( ord( $data[8] ) & 0x3f | 0x80 );

        return vsprintf(
            '%s%s-%s-%s-%s-%s%s%s',
            str_split( bin2hex( $data ), 4 )
        );
    }

    /**
     * Format label from snake_case to readable string.
     * 
     * @return string
     */
    public static function label( $label ) : string {
        return ucwords( str_replace( '_', ' ', $label ) );
    }

    /**
     * Recursively implode a multi-dimensional array.
     *
     * This function flattens nested arrays into a string representation with
     * configurable separators for different levels of nesting.
     *
     * Features:
     * - Handles arbitrary nesting depth
     * - Customizable separators per level
     * - Optional key inclusion
     * - Handles mixed types (scalars, objects, null)
     * - Optional pretty printing with indentation
     *
     * @param array  $array      The array to implode
     * @param string $separator  Primary separator (default: ', ')
     * @param array  $options    {
     *     Optional. Configuration options.
     *
     *     @type string|array $level_separators  Separators for different nesting levels.
     *                                            Can be a string (used for all levels) or
     *                                            array of strings (one per level).
     *                                            Default: [', ', '; ', ' | ', ' > ']
     *     @type bool         $include_keys      Include array keys in output.
     *                                            Default: false
     *     @type string       $key_separator     Separator between key and value.
     *                                            Default: ': '
     *     @type bool         $pretty            Enable pretty printing with indentation.
     *                                            Default: false
     *     @type int          $indent_size       Spaces per indent level (if pretty=true).
     *                                            Default: 2
     *     @type bool         $skip_empty        Skip empty arrays and null values.
     *                                            Default: false
     *     @type int          $max_depth         Maximum recursion depth (0=unlimited).
     *                                            Default: 0
     *     @type callable     $value_formatter   Optional callback to format values.
     *                                            Receives ($value, $key, $depth).
     *                                            Default: null
     * }
     * @param int    $depth      Internal recursion depth tracker
     *
     * @return string Imploded string representation
     *
     * @example
     * // Basic usage
     * $arr = ['a' => 1, 'b' => [2, 3], 'c' => ['d' => 4]];
     * echo Format::implode_deep($arr);
     * // Output: 1, 2; 3, 4
     *
     * @example
     * // With keys
     * echo Format::implode_deep($arr, ', ', ['include_keys' => true]);
     * // Output: a: 1, b: 2; 3, c: d: 4
     *
     * @example
     * // Pretty printing
     * echo Format::implode_deep($arr, ', ', ['pretty' => true, 'include_keys' => true]);
     * // Output (formatted):
     * // a: 1,
     * //   b: 2;
     * //      3,
     * //   c: d: 4
     *
     * @example
     * // Custom separators per level
     * echo Format::implode_deep($arr, null, [
     *     'level_separators' => [' > ', ' >> ', ' >>> '],
     *     'include_keys' => true
     * ]);
     * // Output: a: 1 > b: 2 >> 3 > c: d: 4
     *
     * @example
     * // With value formatter
     * echo Format::implode_deep($arr, ', ', [
     *     'value_formatter' => function($value, $key, $depth) {
     *         return is_numeric($value) ? "#{$value}" : $value;
     *     }
     * ]);
     * // Output: #1, #2; #3, #4
     */
    public static function implode_deep( array $array, ?string $separator = null, array $options = [], int $depth = 0 ): string {
        // Default options
        $defaults = [
            'level_separators' => [ ', ', '; ', ' | ', ' > ' ],
            'include_keys'     => false,
            'key_separator'    => ': ',
            'pretty'           => false,
            'indent_size'      => 2,
            'skip_empty'       => false,
            'max_depth'        => 0,
            'value_formatter'  => null,
        ];

        $options = array_merge( $defaults, $options );

        // Determine separator for this level
        if ( $separator !== null ) {
            $current_separator = $separator;
        } elseif ( is_array( $options['level_separators'] ) ) {
            $separator_index   = min( $depth, count( $options['level_separators'] ) - 1 );
            $current_separator = $options['level_separators'][ $separator_index ];
        } else {
            $current_separator = (string) $options['level_separators'];
        }

        // Check max depth
        if ( $options['max_depth'] > 0 && $depth >= $options['max_depth'] ) {
            return '[max depth reached]';
        }

        // Pretty printing setup
        $indent = '';
        $line_break = '';

        if ( $options['pretty'] ) {
            $indent     = str_repeat( ' ', $depth * $options['indent_size'] );
            $line_break = "\n" . $indent;
        }

        $result = [];

        foreach ( $array as $key => $value ) {
            // Skip empty values if requested
            if ( $options['skip_empty'] ) {
                if ( is_null( $value ) || ( is_array( $value ) && empty( $value ) ) || $value === '' ) {
                    continue;
                }
            }

            // Format the value
            $formatted_value = static::deep_value( $value, $key, $depth, $options );

            // Include key if requested
            if ( $options['include_keys'] ) {
                $formatted_value = $key . $options['key_separator'] . $formatted_value;
            }

            $result[] = $formatted_value;
        }

        // Join with separator
        if ( $options['pretty'] ) {
            return $line_break . implode( $current_separator . $line_break, $result );
        }

        return implode( $current_separator, $result );
    }

    /**
     * Format a single value for deep implode.
     *
     * Internal helper function that handles type conversion and recursion.
     *
     * @param mixed    $value   The value to format
     * @param string|int $key   The array key
     * @param int      $depth   Current recursion depth
     * @param array    $options Options from parent function
     *
     * @return string Formatted value
     */
    public static function deep_value( $value, $key, int $depth, array $options ): string {
        // Apply custom formatter if provided
        if ( is_callable( $options['value_formatter'] ) ) {
            $custom = call_user_func( $options['value_formatter'], $value, $key, $depth );
            
            if ( $custom !== null && ! is_array( $custom ) ) {
                return (string) $custom;
            }
        }

        // Handle different types
        if ( is_array( $value ) ) {
            // Recursive call for nested arrays
            return static::implode_deep( $value, null, $options, $depth + 1 );
        }

        if ( is_object( $value ) ) {
            // Handle objects
            if ( method_exists( $value, '__toString' ) ) {
                return (string) $value;
            }

            if ( $value instanceof \JsonSerializable ) {
                return json_encode( $value );
            }

            return get_class( $value ) . ' object';
        }

        if ( is_bool( $value ) ) {
            return $value ? 'true' : 'false';
        }

        if ( is_null( $value ) ) {
            return 'null';
        }

        if ( is_resource( $value ) ) {
            return 'resource(' . get_resource_type( $value ) . ')';
        }

        // Scalar values (string, int, float)
        return (string) $value;
    }

    /**
     * Implode array with automatic smart formatting.
     *
     * Convenience wrapper that automatically chooses the best formatting
     * based on array structure and depth.
     *
     * @param array $array The array to implode
     * @param bool  $include_keys Whether to include keys (default: auto-detect)
     *
     * @return string Formatted string
     *
     * @example
     * $arr = ['name' => 'John', 'age' => 30, 'tags' => ['php', 'wordpress']];
     * echo Format::smart_implode($arr);
     * // Output: name: John, age: 30, tags: php; wordpress
     */
    public static function smart_implode( array $array, ?bool $include_keys = null ): string {
        // Auto-detect if we should include keys
        if ( $include_keys === null ) {
            $include_keys = ! array_is_list( $array );
        }

        // Detect depth
        $max_depth = static::array_max_depth( $array );

        // Choose formatting based on depth
        if ( $max_depth <= 1 ) {
            // Shallow array - simple format
            return static::implode_deep( $array, ', ', [
                'include_keys' => $include_keys,
            ] );
        }

        if ( $max_depth <= 3 ) {
            // Medium depth - use different separators per level
            return static::implode_deep( $array, null, [
                'include_keys'     => $include_keys,
                'level_separators' => [ ', ', '; ', ' | ' ],
            ] );
        }

        // Deep array - use pretty printing
        return static::implode_deep( $array, null, [
            'include_keys' => $include_keys,
            'pretty'       => true,
        ] );
    }

    /**
     * Implode array to HTML list.
     *
     * Converts a multi-dimensional array to an HTML unordered or ordered list.
     *
     * @param array  $array   The array to convert
     * @param string $type    List type: 'ul' or 'ol' (default: 'ul')
     * @param array  $options {
     *     Optional. HTML options.
     *
     *     @type string $class      CSS class for list elements
     *     @type bool   $include_keys Include keys as labels
     *     @type int    $max_depth   Maximum nesting depth
     * }
     * @param int    $depth   Internal depth tracker
     *
     * @return string HTML list
     *
     * @example
     * $menu = [
     *     'Home' => '/',
     *     'Products' => [
     *         'Category 1' => '/cat1',
     *         'Category 2' => '/cat2',
     *     ],
     *     'About' => '/about',
     * ];
     * echo Format::implode_deep_html($menu, 'ul', ['include_keys' => true]);
     */
    public static function implode_deep_html( array $array, string $type = 'ul', array $options = [], int $depth = 0 ): string {
        $defaults = [
            'class'        => '',
            'include_keys' => false,
            'max_depth'    => 0,
        ];

        $options = array_merge( $defaults, $options );

        // Check max depth
        if ( $options['max_depth'] > 0 && $depth >= $options['max_depth'] ) {
            return '';
        }

        $type  = in_array( $type, [ 'ul', 'ol' ], true ) ? $type : 'ul';
        $class = ! empty( $options['class'] ) ? ' class="' . esc_attr( $options['class'] ) . '"' : '';

        $html = "<{$type}{$class}>";

        foreach ( $array as $key => $value ) {
            $html .= '<li>';

            if ( $options['include_keys'] && ! is_numeric( $key ) ) {
                $html .= '<strong>' . esc_html( $key ) . ':</strong> ';
            }

            if ( is_array( $value ) ) {
                $html .= static::implode_deep_html( $value, $type, $options, $depth + 1 );
            } else {
                $html .= esc_html( static::deep_value( $value, $key, $depth, [] ) );
            }

            $html .= '</li>';
        }

        $html .= "</{$type}>";

        return $html;
    }

    /**
     * Calculate maximum depth of an array.
     *
     * @param array $array The array to analyze
     * @param int   $depth Current depth (internal)
     *
     * @return int Maximum depth
     */
    public static function array_max_depth( array $array, int $depth = 0 ): int {
        $max_depth = $depth;

        foreach ( $array as $value ) {
            if ( is_array( $value ) ) {
                $current_depth = static::array_max_depth( $value, $depth + 1 );
                $max_depth     = max( $max_depth, $current_depth );
            }
        }

        return $max_depth;
    }

    /**
     * Trim text to a specified number of words.
     *
     * @param string      $text The text to trim.
     * @param int         $num  Number of words to keep.
     * @param string|null $more String to append when trimmed.
     *
     * @return string
     */
    public static function trim_words( string $text, int $num = 50, ?string $more = null ): string {

        if ( null === $more ) {
            $more = '&hellip;';
        }

        // Remove scripts and styles.
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

        // Strip remaining HTML.
        $text = strip_tags( $text );

        $words = preg_split( '/\s+/', trim( $text ), $num + 1, PREG_SPLIT_NO_EMPTY );

        if ( count( $words ) > $num ) {

            array_pop( $words );
            return implode( ' ', $words ) . $more;
        }

        return implode( ' ', $words );
    }

    /**
     * Trim text to a specified number of words (UTF-8 safe).
     *
     * Handles emoji, HTML entities, and multibyte characters correctly.
     *
     * @param string      $text The text to trim.
     * @param int         $num  Number of words to keep.
     * @param string|null $more String appended when text is trimmed.
     *
     * @return string
     */
    public static function trim_words_utf8( string $text, int $num = 50, ?string $more = null ): string {

        if ( null === $more ) {
            $more = '&hellip;';
        }

        // Remove script/style blocks.
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );

        // Strip remaining HTML.
        $text = strip_tags( $text );

        // Decode entities so word counting works correctly.
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // Normalize whitespace.
        $text = preg_replace( '/\s+/u', ' ', trim( $text ) );

        // Split words (UTF-8 aware).
        $words = preg_split( '/\s+/u', $text, $num + 1, PREG_SPLIT_NO_EMPTY );

        if ( count( $words ) > $num ) {

            array_pop( $words );
            $text = implode( ' ', $words );

            // Remove trailing punctuation before ellipsis.
            $text = rtrim( $text, " \t\n\r\0\x0B.,!?:;" );

            return $text . $more;
        }

        return implode( ' ', $words );
    }

/*
    |--------------------------------------------
    | ENCODING / DECODING
    |--------------------------------------------
    */

    /**
     * Encoding strategy: JSON.
     *
     * @var string
     */
    const ENCODING_JSON = 'json';

    /**
     * Encoding strategy: PHP native serialize().
     *
     * Use only when PHP-specific types (objects with classes, resources)
     * must survive a round-trip exactly. Prefer JSON for plain data.
     *
     * @var string
     */
    const ENCODING_PHP = 'php';

    /**
     * Encode a value for database or cache storage.
     *
     * Scalars (string, int, float, bool) and null are returned as-is —
     * they need no encoding. Arrays and objects are encoded using the
     * chosen strategy.
     *
     * @param mixed  $value    The value to encode.
     * @param string $strategy Encoding strategy: Format::ENCODING_JSON (default)
     *                         or Format::ENCODING_PHP.
     * @return mixed Encoded string for arrays/objects, original value otherwise.
     */
    public static function encode( mixed $value, string $strategy = self::ENCODING_JSON ): mixed {
        if ( ! is_array( $value ) && ! is_object( $value ) ) {
            return $value;
        }

        if ( $strategy === self::ENCODING_PHP ) {
            return serialize( $value );
        }

        return json_encode( $value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
    }

    /**
     * Decode a value retrieved from database or cache storage.
     *
     * Detects whether the value is JSON or PHP-serialized and decodes
     * it accordingly. Returns the original value untouched if it is
     * neither — no guessing, no silent failures.
     *
     * @param mixed $value The value to decode.
     * @return mixed Decoded array/object for encoded strings, original value otherwise.
     */
    public static function decode( mixed $value ): mixed {
        if ( ! is_string( $value ) ) {
            return $value;
        }

        if ( static::is_json_encoded( $value ) ) {
            $decoded = json_decode( $value, true );

            if ( json_last_error() === JSON_ERROR_NONE ) {
                return $decoded;
            }
        }

        if ( static::is_php_serialized( $value ) ) {
            $decoded = @unserialize( $value );

            // unserialize() returns false on failure — but false is also a
            // valid serialized value, so check the original string too.
            if ( $decoded !== false || $value === serialize( false ) ) {
                return $decoded;
            }
        }

        return $value;
    }

    /**
     * Determine whether a value is a JSON-encoded string.
     *
     * Checks the outer structure only (starts with { or [) then confirms
     * it parses cleanly.
     *
     * @param mixed $value The value to inspect.
     * @return bool True if the value is a valid JSON-encoded string.
     */
    public static function is_json_encoded( mixed $value ): bool {
        if ( ! is_string( $value ) || empty( $value ) ) {
            return false;
        }

        $trimmed = ltrim( $value );

        if ( ! str_starts_with( $trimmed, '{' ) && ! str_starts_with( $trimmed, '[' ) ) {
            return false;
        }

        json_decode( $value );

        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Determine whether a value is a PHP-serialized string.
     *
     * Inspects the string structure against all known PHP serialization
     * formats without actually unserializing it:
     *   N;          — null
     *   b:1;        — bool
     *   i:42;       — int
     *   d:3.14;     — float
     *   s:3:"foo";  — string
     *   a:2:{...}   — array
     *   O:8:"..";   — object
     *   E:..;       — enum (PHP 8.1+)
     *
     * @param mixed $value  The value to inspect.
     * @param bool  $strict If true (default), requires a valid closing character.
     * @return bool True if the value is a PHP-serialized string.
     */
    public static function is_php_serialized( mixed $value, bool $strict = true ): bool {
        if ( ! is_string( $value ) ) {
            return false;
        }

        $value = trim( $value );

        // Serialized null.
        if ( $value === 'N;' ) {
            return true;
        }

        // Minimum length: "b:1;" = 4 chars.
        if ( strlen( $value ) < 4 ) {
            return false;
        }

        // All serialized strings have a colon at position 1.
        if ( $value[1] !== ':' ) {
            return false;
        }

        if ( $strict ) {
            $last = substr( $value, -1 );
            if ( $last !== ';' && $last !== '}' ) {
                return false;
            }
        }

        $token = $value[0];

        switch ( $token ) {
            case 's':
                // Serialized strings end with ";  (quote then semicolon).
                if ( $strict && substr( $value, -2, 1 ) !== '"' ) {
                    return false;
                }
                // Fall through to regex check.
            case 'a':
            case 'O':
            case 'E':
                return (bool) preg_match( "/^{$token}:[0-9]+:/s", $value );

            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';
                return (bool) preg_match( "/^{$token}:[0-9.E+\-]+;{$end}/", $value );
        }

        return false;
    }

    /**
     * Determine whether a value has been encoded by Format::encode().
     *
     * Returns true for both JSON and PHP-serialized strings.
     * Useful as a gate before calling Format::decode().
     *
     * @param mixed $value The value to inspect.
     * @return bool True if the value is JSON or PHP-serialized.
     */
    public static function is_encoded( mixed $value ): bool {
        return static::is_json_encoded( $value ) || static::is_php_serialized( $value );
    }
}