<?php
/**
 * File name smliser-formating-functions.php
 * Utility function file for all formatting related operation.
 * 
 * @author Callistus
 * @since 0.2.0
 * @package Smliser\functions
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Utils\Format;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Calculate the difference between two dates and return a human-readable string.
 *
 * @param string $start_date Start date in 'Y-m-d H:i:s' format.
 * @param string $end_date   End date in 'Y-m-d H:i:s' format.
 * @return string
 *
 * @since 0.2.0
 */
function smliser_time_diff_string( $start_date, $end_date ): string {

    return Format::time_diff_string(
        (string) $start_date,
        (string) $end_date
    );
}

/**
 * Convert duration to readable format.
 *
 * Backward compatible wrapper for Format::duration_ago().
 *
 * @param int|float|string $duration Duration in seconds.
 * @return string
 */
function smliser_readable_duration( $duration ): string {

    if ( is_string( $duration ) ) {
        return $duration;
    }

    if ( ! is_numeric( $duration ) ) {
        return 'Invalid duration';
    }

    return Format::duration_ago( (float) $duration );
}

/**
 * Get the local Date and time format
 * 
 * @since 0.2.0
 */
function smliser_locale_date_format() {
	$date_format = smliser_settings_adapter()->get( 'date_format', 'Y-m-d', false );
	$time_format = smliser_settings_adapter()->get( 'time_format', 'g:i a', false );
    return [ $date_format, $time_format ];
}

/**
 * Retrives a combination of local date and time format.
 * 
 * @return string
 */
function smliser_datetime_format() : string {
	return implode( ' ', smliser_locale_date_format() );
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
 */
function smliser_implode_deep( array $array, ?string $separator = null, array $options = [], int $depth = 0 ): string {
    return Format::implode_deep( $array, $separator, $options, $depth );
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
    return Format::deep_value( $value, $key, $depth, $options );
}

/**
 * Implode array with automatic smart formatting.
 */
function smliser_implode_deep_smart( array $array, ?bool $include_keys = null ): string {
    return Format::smart_implode( $array, $include_keys );
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
    return Format::array_max_depth( $array, $depth );
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
 */
function smliser_implode_deep_html( array $array, string $type = 'ul', array $options = [], int $depth = 0 ): string {
    return Format::implode_deep_html( $array, $type, $options, $depth );
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
    $base_url   = $base_url->remove_query_param( $page_param, 'limit' );
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

/**
 * Trim words to a specified number of words.
 * 
 * @param string $text The text to trim.
 * @param int $num The number of words to trim to.
 * @param string $more The string to append to indicate more.
 */
function smliser_trim_words( string $text, int $num = 50, ?string $more = null ) : string {
    return Format::trim_words( $text, $num, $more );
}

/**
 * Trim words to a specified number of words(UTF-8 safe).
 * 
 * @param string $text The text to trim.
 * @param int $num The number of words to trim to.
 * @param string $more The string to append to indicate more.
 */
function smliser_trim_words_utf8( string $text, int $num = 50, ?string $more = null ) : string {
    return Format::trim_words_utf8( $text, $num, $more );
}

/**
 * Helper: Format label from snake_case to readable format
 */
function smliser_format_label( $label ) {
    Format::label( $label );
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
function smliser_generate_uuid_v4() : string {
    return Format::uuid_4();
}