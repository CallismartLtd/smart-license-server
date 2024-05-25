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
 * Format Timestamp to date
 * 
 * @param int $timestamp   The timestamp.
 */
function smliser_tstmp_to_date( $timestamp ) {
	if ( empty( $timestamp ) ) {
		return $timestamp;
	}

    return  date_i18n( smliser_datetime_format(), $timestamp );
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
	$locale = smliser_locale_date_format();
	$format = $includeTime ? $locale->date_format . ' ' . $locale->time_format : $locale->date_format;
	return ! empty( $dateString ) ? date_i18n( $format, strtotime( $dateString ) ) : 'N/A';
}