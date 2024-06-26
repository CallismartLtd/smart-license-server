<?php
/**
 * File name smliser-formating-functions.php
 * Utility function file for all formatting related operation.
 * 
 * @author Callistus
 * @since 1.0.0
 * @package Smliser\functions
 */

defined( 'ABSPATH' ) || exit;

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
	if ( empty( $timestamp ) ) {
		return $timestamp;
	}

    return  ( true === $use_i18n ) ? date_i18n( smliser_datetime_format(), $timestamp ) : wp_date( smliser_datetime_format(), $timestamp );
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
 * Convert duration to a readable date.
 * 
 * @param int|float $duration The duration in seconds.
 * @return string|bool $readable_format A formatted string from year to seconds or false. 
 */
function smliser_readable_duration( $duration ) {
    if ( is_string( $duration ) ) {
        return $duration;
    }
    
    if ( ! is_int( $duration ) && ! is_float( $duration ) ) {
        return false;
    }

    $duration = round( $duration );
    $years      = floor( $duration / ( 365 * 24 * 3600 ) );
    $duration   %= ( 365 * 24 * 3600 );
    $months     = floor( $duration / ( 30 * 24 * 3600 ) );
    $duration   %= ( 30 * 24 * 3600 );
    $weeks      = floor( $duration / ( 7 * 24 * 3600 ) );
    $duration   %= ( 7 * 24 * 3600 );
    $days       = floor( $duration / ( 24 * 3600 ) );
    $duration   %= ( 24 * 3600 );
    $hours      = floor( $duration / 3600 );
    $duration   %= 3600;
    $minutes    = floor( $duration / 60 );
    $seconds    = $duration % 60;

    $readable_parts = array();
    if ( $years > 0 ) {
        $readable_parts[] = $years . ' year' . ( $years > 1 ? 's' : '' );
    }

    if ( $months > 0 ) {
        $readable_parts[] = $months . ' month' . ( $months > 1 ? 's' : '' );
    }

    if ( $weeks > 0 ) {
        $readable_parts[] = $weeks . ' week' . ( $weeks > 1 ? 's' : '' );
    }

    if ( $days > 0 ) {
        $readable_parts[] = $days . ' day' . ( $days > 1 ? 's' : '' );
    }

    if ( $hours > 0 ) {
        $readable_parts[] = $hours . ' hour' . ( $hours > 1 ? 's' : '' );
    }

    if ( $minutes > 0 ) {
        $readable_parts[] = $minutes . ' minute' . ( $minutes > 1 ? 's' : '' );
    }

    if ( $seconds > 0 ) {
        $readable_parts[] = $seconds . ' second' . ( $seconds > 1 ? 's' : '' );
    }

    $readable_format = implode( ', ', $readable_parts );
    return $readable_format;
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
	$date_format = get_option( 'date_format' );
	$time_format = get_option( 'time_format' );

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
