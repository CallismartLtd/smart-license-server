<?php
/**
 * File name smliser-formating-functions.php
 * Utility function file for all formatting related operation.
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\functions
 */

use SmartLicenseServer\Core\URL;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Format Timestamp to date according to WordPress time settings
 * 
 * @param int $timestamp    The timestamp.
 * @param bool $use_i18n    Whether the given time stamp is Unix timestamp or not,
 *                          when unix timestamp is given, the function will use wp_date to convert
 *                          to local time, else the timestamp will be assumed to be the local timestamp hence
 *                          date_i18n will be used.
 * @return string formated local date.
 */
function smliser_tstmp_to_date( $timestamp, $use_i18n = true ) {
    // Ensure that the timestamp is valid
    if ( empty( $timestamp ) || ! is_numeric( $timestamp ) ) {
        return '';
    }

    // Format date based on the flag $use_i18n
    $date_format = smliser_datetime_format();
    
    if ( true === $use_i18n ) {
        return date_i18n( $date_format, $timestamp );
    } else {
        return wp_date( $date_format, $timestamp );
    }
}


/**
 * Calculate the difference between two dates and return it as a human-readable string.
 *
 * @param string $start_date The start date in 'Y-m-d H:i:s' format.
 * @param string $end_date The end date in 'Y-m-d H:i:s' format.
 * 
 * @return string The human-readable difference between the two dates.
 * 
 * @since 1.0.4
 */
function smliser_time_diff_string( $start_date, $end_date ) {
    // Create DateTime objects from the provided timestamps.
    $start  = new DateTime( $start_date );
    $end    = new DateTime( $end_date );

    // Calculate the difference between the two DateTime objects.
    $interval = $start->diff( $end );

    // Build the human-readable string.
    $parts = [];

    if ( $interval->y !== 0 ) {
        $parts[] = $interval->y . ' year' . ( $interval->y > 1 ? 's' : '' );
    }
    if ( $interval->m !== 0 ) {
        $parts[] = $interval->m . ' month' . ( $interval->m > 1 ? 's' : '' );
    }
    if ( $interval->d !== 0 ) {
        $parts[] = $interval->d . ' day' . ( $interval->d > 1 ? 's' : '' );
    }
    if ( $interval->h !== 0 ) {
        $parts[] = $interval->h . ' hour' . ( $interval->h > 1 ? 's' : '' );
    }
    if ( $interval->i !== 0 ) {
        $parts[] = $interval->i . ' minute' . ( $interval->i > 1 ? 's' : '' );
    }
    if ( $interval->s !== 0 ) {
        $parts[] = $interval->s . ' second' . ( $interval->s > 1 ? 's' : '' );
    }

    // If no difference at all.
    if ( empty( $parts ) ) {
        return '0 seconds';
    }

    // Join the parts into a single string.
    return implode( ', ', $parts );
}

/**
 * Convert duration to a readable format.
 * 
 * Returns the two most significant time units for better readability.
 * Examples: "2 years, 3 months" or "5 minutes, 30 seconds"
 *
 * @param int|float $duration The duration in seconds.
 * @return string|bool A formatted string or false on invalid input.
 */
function smliser_readable_duration( $duration ) {
    if ( is_string( $duration ) ) {
        return $duration;
    }
    
    if ( ! is_numeric( $duration ) ) {
        return false;
    }
    
    if ( $duration <= 0 ) {
        return 'Now';
    }
    
    $units = array(
        'year'        => 365 * 24 * 3600,
        'month'       => 30 * 24 * 3600,
        'week'        => 7 * 24 * 3600,
        'day'         => 24 * 3600,
        'hour'        => 3600,
        'minute'      => 60,
        'second'      => 1,
        'millisecond' => 0.001,
    );
    
    $parts = array();
    $remaining = $duration;
    
    foreach ( $units as $name => $seconds ) {
        $value = floor( $remaining / $seconds );
        
        if ( $value > 0 ) {
            $parts[] = $value . ' ' . $name . ( $value > 1 ? 's' : '' );
            $remaining -= $value * $seconds;
            
            // Stop after 2 significant units
            if ( count( $parts ) === 2 ) {
                break;
            }
        }
    }
    
    return empty( $parts ) ? '0 seconds' : implode( ', ', $parts );
}

/**
 * Extracts the date portion from a date and time string.
 *
 * @param string $dateTimeString The date and time string.
 * @return null|string The extracted date in 'Y-m-d' format.
 */
function smliser_extract_only_date( $datetimestring ) {

	if ( empty( $datetimestring ) ) {
		return $datetimestring;
	}

	$date_object = new DateTime( $datetimestring );
	return $date_object->format( 'Y-m-d' );
}

/**
 * Get the local Date and time format
 * 
 * @since 1.0.3
 * @return object|null stdClass or null
 */
function smliser_locale_date_format() {
	$date_format = smliser_settings_adapter()->get( 'date_format', 'Y-m-d', false );
	$time_format = smliser_settings_adapter()->get( 'time_format', 'g:i a', false );

	$format = new stdClass();
	$format->date_format = $date_format;
	$format->time_format = $time_format;
	return $format;
}

/**
 * Retrives a combination of local date and time format
 */
function smliser_datetime_format() {
	return smliser_locale_date_format()->date_format . ' ' . smliser_locale_date_format()->time_format;
}

/**
 * Function to format date to a human-readable format or show 'Not Available'.
 *
 * @param string $dateString Date String.
 * @param bool   $includeTime Whether to include the time aspect. Default is true.
 * @return string Formatted date or 'Not Available'.
 */
function smliser_check_and_format( $dateString, $includeTime = false ) {
    if ( smliser_is_empty_date( $dateString ) ) {
        return 'N/A';
    }
	$locale = smliser_locale_date_format();
	$format = $includeTime ? $locale->date_format . ' ' . $locale->time_format : $locale->date_format;
	return ! empty( $dateString ) ? date_i18n( $format, strtotime( $dateString ) ) : 'N/A';
}


/**
 * Filter callback function to allow our tags into wp_kses filter
 */
function smliser_allowed_html( $wp_html, $context ) {
    // Define the allowed HTML tags and attributes.
    $allowed_tags = array(
        'div' => array(
            'class' => array(),
            'style' => array(),
        ),
        'table' => array(
            'class' => array(),
            'style' => array(),
        ),
        'thead' => array(),
        'tbody' => array(),
        'tr' => array(),
        'th' => array(
            'class' => array(),
            'style' => array(),
        ),
        'td' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h1' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h2' => array(
            'class' => array(),
            'style' => array(),
        ),
        'h3' => array(
            'class' => array(),
            'style' => array(),
        ),
        'p' => array(
            'class' => array(),
            'style' => array(),
        ),
        'a' => array(
            'href' => array(),
            'title' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'target' => array(),
            'item-id'   => array()
        ),
        'span' => array(
            'class' => array(),
            'style' => array(),
        ),
        'form' => array(
            'action' => array(),
            'method' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
        ),
        'input' => array(
            'type' => array(),
            'name' => array(),
            'value' => array(),
            'placeholder' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'accept' => array(),
            'required' => array(),
            'readonly' => array(),

        ),
        'button' => array(
            'type' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
        ),
        'select' => array(
            'name' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'required' => array(),
            'readonly' => array(),
        ),
        'option' => array(
            'value' => array(),
            'selected' => array(),
        ),
        'textarea' => array(
            'name' => array(),
            'rows' => array(),
            'cols' => array(),
            'class' => array(),
            'style' => array(),
            'id' => array(),
            'required' => array(),
            'readonly' => array(),
        ),
        'span' => array(
            'class' => array(),
            'title' => array(),
        ),
        'label' => array(
            'name' => array(),
            'id' => array(),
            'class' => array(),
            'title' => array(),
            'data-title' => array(),
        ),    
    
    );

    return array_merge( $wp_html, $allowed_tags );
}

/**
 * Safely JSON-encode data for use inside HTML attributes.
 *
 * This ensures that characters like quotes, apostrophes, tags, and ampersands
 * are safely escaped so they won’t break the HTML structure or parsing.
 *
 * @param mixed $data The data to encode.
 * @return string The safely JSON-encoded string for use in attributes.
 */
function smliser_json_encode_attr( $data ) {
	return smliser_safe_json_encode( $data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT );
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
 * echo smliser_implode_deep($arr);
 * // Output: 1, 2; 3, 4
 *
 * @example
 * // With keys
 * echo smliser_implode_deep($arr, ', ', ['include_keys' => true]);
 * // Output: a: 1, b: 2; 3, c: d: 4
 *
 * @example
 * // Pretty printing
 * echo smliser_implode_deep($arr, ', ', ['pretty' => true, 'include_keys' => true]);
 * // Output (formatted):
 * // a: 1,
 * //   b: 2;
 * //      3,
 * //   c: d: 4
 *
 * @example
 * // Custom separators per level
 * echo smliser_implode_deep($arr, null, [
 *     'level_separators' => [' > ', ' >> ', ' >>> '],
 *     'include_keys' => true
 * ]);
 * // Output: a: 1 > b: 2 >> 3 > c: d: 4
 *
 * @example
 * // With value formatter
 * echo smliser_implode_deep($arr, ', ', [
 *     'value_formatter' => function($value, $key, $depth) {
 *         return is_numeric($value) ? "#{$value}" : $value;
 *     }
 * ]);
 * // Output: #1, #2; #3, #4
 */
function smliser_implode_deep( array $array, ?string $separator = null, array $options = [], int $depth = 0 ): string {
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
        $formatted_value = smliser_format_deep_value( $value, $key, $depth, $options );

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
function smliser_format_deep_value( $value, $key, int $depth, array $options ): string {
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
        return smliser_implode_deep( $value, null, $options, $depth + 1 );
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
 * echo smliser_implode_deep_smart($arr);
 * // Output: name: John, age: 30, tags: php; wordpress
 */
function smliser_implode_deep_smart( array $array, ?bool $include_keys = null ): string {
    // Auto-detect if we should include keys
    if ( $include_keys === null ) {
        $include_keys = ! array_is_list( $array );
    }

    // Detect depth
    $max_depth = smliser_array_max_depth( $array );

    // Choose formatting based on depth
    if ( $max_depth <= 1 ) {
        // Shallow array - simple format
        return smliser_implode_deep( $array, ', ', [
            'include_keys' => $include_keys,
        ] );
    }

    if ( $max_depth <= 3 ) {
        // Medium depth - use different separators per level
        return smliser_implode_deep( $array, null, [
            'include_keys'     => $include_keys,
            'level_separators' => [ ', ', '; ', ' | ' ],
        ] );
    }

    // Deep array - use pretty printing
    return smliser_implode_deep( $array, null, [
        'include_keys' => $include_keys,
        'pretty'       => true,
    ] );
}

/**
 * Calculate maximum depth of an array.
 *
 * @param array $array The array to analyze
 * @param int   $depth Current depth (internal)
 *
 * @return int Maximum depth
 */
function smliser_array_max_depth( array $array, int $depth = 0 ): int {
    $max_depth = $depth;

    foreach ( $array as $value ) {
        if ( is_array( $value ) ) {
            $current_depth = smliser_array_max_depth( $value, $depth + 1 );
            $max_depth     = max( $max_depth, $current_depth );
        }
    }

    return $max_depth;
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
 * echo smliser_implode_deep_html($menu, 'ul', ['include_keys' => true]);
 */
function smliser_implode_deep_html( array $array, string $type = 'ul', array $options = [], int $depth = 0 ): string {
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
            $html .= smliser_implode_deep_html( $value, $type, $options, $depth + 1 );
        } else {
            $html .= esc_html( smliser_format_deep_value( $value, $key, $depth, [] ) );
        }

        $html .= '</li>';
    }

    $html .= "</{$type}>";

    return $html;
}

/**
 * Implode array to JSON string.
 *
 * Convenience function that ensures proper JSON encoding with error handling.
 *
 * @param array $array   The array to encode
 * @param int   $options JSON encoding options (default: JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
 *
 * @return string JSON string
 * @throws \Exception If JSON encoding fails
 */
function smliser_implode_deep_json( array $array, int $options = JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ): string {
    $json = json_encode( $array, $options );

    if ( $json === false ) {
        throw new \Exception( 'JSON encoding failed: ' . json_last_error_msg() );
    }

    return $json;
}

/**
 * Implode array to CSV string.
 *
 * Flattens array to CSV format (single level only).
 *
 * @param array  $array     The array to convert
 * @param string $delimiter CSV delimiter (default: ',')
 * @param string $enclosure Field enclosure (default: '"')
 *
 * @return string CSV string
 */
function smliser_implode_deep_csv( array $array, string $delimiter = ',', string $enclosure = '"' ): string {
    // Flatten nested arrays first
    $flat = [];
    
    array_walk_recursive( $array, function( $value ) use ( &$flat ) {
        $flat[] = $value;
    } );

    // Build CSV
    $output = fopen( 'php://temp', 'r+' );
    fputcsv( $output, $flat, $delimiter, $enclosure );
    rewind( $output );
    $csv = stream_get_contents( $output );
    fclose( $output );

    return rtrim( $csv, "\n" );
}



/**
 * Render table-style pagination.
 *
 * The $pagination array must contain:
 *
 * - total (int)  Total number of records.
 * - page (int)   Current page (1-based).
 * - limit (int)  Items per page.
 *
 * Example:
 *
 * [
 *     'total' => 125,
 *     'page'  => 3,
 *     'limit' => 25,
 * ]
 *
 * @param array{
 *     total: int,
 *     page: int,
 *     limit: int
 * } $pagination Pagination data.
 * @param string $base_url Optional base URL for pagination links (default: current URL without 'paged' and 'limit' query params).
 * @param string $page_param Optional query parameter name for page number (default: 'paged').
 *
 * @return void
 */
function smliser_render_pagination( array $pagination, string $base_url = '', string $page_param = 'paged' ) : void {

    $total       = isset( $pagination['total'] ) ? (int) $pagination['total'] : 0;
    $page        = isset( $pagination['page'] ) ? max( 1, (int) $pagination['page'] ) : 1;
    $limit       = isset( $pagination['limit'] ) ? max( 1, (int) $pagination['limit'] ) : 20;
    $total_pages = (int) ceil( $total / $limit );

    if ( $total <= 0 ) {
        return;
    }

    $page = min( $page, $total_pages );

    $window = 2;
    $start  = max( 1, $page - $window );
    $end    = min( $total_pages, $page + $window );
    

    $base_url   = $base_url ? new URL( $base_url ) : smliser_get_current_url();
    $base_url   = $base_url->remove_query_param( array( $page_param, 'limit' ) );
    $prev_page  = max( 1, $page - 1 );
    $next_page  = min( $total_pages, $page + 1 );

    $offset    = ( $page - 1 ) * $limit;
    $remaining = max( 0, $total - $offset );
    $displayed = min( $limit, $remaining );
    ?>

    <p class="smliser-table-count">
        <?php
        printf(
            esc_html__( '%1$d of %2$d %3$s', 'smliser' ),
            absint( $displayed ),
            absint( $total ),
            esc_html( _n( 'item', 'items', $total, 'smliser' ) )
        );
        ?>
    </p>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="smliser-tablenav-pages">
            <span class="smliser-displaying-num">
                <?php
                printf(
                    esc_html__( 'Page %1$d of %2$d', 'smliser' ),
                    absint( $page ),
                    absint( $total_pages )
                );
                ?>
            </span>

            <span class="smliser-pagination-links">

                <?php if ( $page > 1 ) : ?>
                    <a class="prev-page button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $prev_page, 'limit' => $limit ) ) ); ?>">&laquo;</a>
                <?php else : ?>
                    <span class="smliser-navspan button disabled">&laquo;</span>
                <?php endif; ?>

                <?php
                // First page.
                if ( $start > 1 ) :
                    ?>
                    <a class="button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => 1, 'limit' => $limit ) ) ); ?>">1</a>
                    <?php if ( $start > 2 ) : ?>
                        <span class="smliser-navspan button disabled">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                // Window pages.
                for ( $i = $start; $i <= $end; $i++ ) :
                    $class = ( $i === $page ) ? 'button current' : 'button';
                    ?>
                    <a class="<?php echo esc_attr( $class ); ?>"
                       href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $i, 'limit' => $limit ) ) ); ?>">
                        <?php echo absint( $i ); ?>
                    </a>
                <?php endfor; ?>

                <?php
                // Last page.
                if ( $end < $total_pages ) :
                    if ( $end < $total_pages - 1 ) :
                        ?>
                        <span class="smliser-navspan button disabled">…</span>
                    <?php
                    endif;
                    ?>
                    <a class="button"
                       href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $total_pages, 'limit' => $limit ) ) ); ?>">
                        <?php echo absint( $total_pages ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( $page < $total_pages ) : ?>
                    <a class="next-page button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $next_page, 'limit' => $limit ) ) ); ?>">&raquo;</a>
                <?php else : ?>
                    <span class="smliser-navspan button disabled">&raquo;</span>
                <?php endif; ?>

            </span>
        </div>
    <?php endif; ?>
    <?php
}