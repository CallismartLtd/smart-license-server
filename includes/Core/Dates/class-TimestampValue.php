<?php

namespace SmartLicenseServer\Core\Dates;

use InvalidArgumentException;
use DateTimeImmutable;
use DateInterval;

/**
 * Immutable timestamp utility with calendar-accurate month/year support.
 */
class TimestampValue {

	/**
	 * Unix timestamp (seconds).
	 *
	 * @var int
	 */
	private int $timestamp;

	private function __construct( int $timestamp ) {
		$this->timestamp = $timestamp;
	}

	/* ---------------------------------------------------------------------
	 * Factory
	 * ------------------------------------------------------------------ */

	public static function fromTimestamp( int $timestamp ) : self {
		return new self( $timestamp );
	}

	public static function now() : self {
		return new self( time() );
	}

	/* ---------------------------------------------------------------------
	 * Safe arithmetic (seconds-based)
	 * ------------------------------------------------------------------ */

	public function addSeconds( int $value ) : self {
		return new self( $this->timestamp + $value );
	}

	public function addMinutes( int $value ) : self {
		return $this->addSeconds( $value * 60 );
	}

	public function addHours( int $value ) : self {
		return $this->addSeconds( $value * 3600 );
	}

	public function addDays( int $value ) : self {
		return $this->addSeconds( $value * 86400 );
	}

	public function addWeeks( int $value ) : self {
		return $this->addSeconds( $value * 604800 );
	}

	/* ---------------------------------------------------------------------
	 * Calendar-accurate operations
	 * ------------------------------------------------------------------ */

	/**
	 * Add calendar months (accurate per calendar rules).
	 *
	 * @param int $months
	 * @return self
	 */
	public function addMonths( int $months ) : self {
		$dt = $this->toDateTime();
		$dt = $dt->add( new DateInterval( 'P' . $months . 'M' ) );

		return new self( $dt->getTimestamp() );
	}

	/**
	 * Add calendar years.
	 *
	 * @param int $years
	 * @return self
	 */
	public function addYears( int $years ) : self {
		$dt = $this->toDateTime();
		$dt = $dt->add( new DateInterval( 'P' . $years . 'Y' ) );

		return new self( $dt->getTimestamp() );
	}

	/**
	 * Add calendar months using clamp-based logic (billing-safe).
	 *
	 * This prevents PHP DateInterval overflow behavior and ensures:
	 * - no drifting renewal dates
	 * - no cross-month spillover
	 * - consistent subscription cycles
	 *
	 * @param int $months
	 * @return self
	 */
	public function addMonthsClamped( int $months ) : self {
		$dt = $this->toDateTime();

		$day = (int) $dt->format( 'j' );
		$year = (int) $dt->format( 'Y' );
		$month = (int) $dt->format( 'n' );
		$hour = (int) $dt->format( 'H' );
		$minute = (int) $dt->format( 'i' );
		$second = (int) $dt->format( 's' );

		// Target month/year
		$month += $months;

		while ( $month > 12 ) {
			$month -= 12;
			$year++;
		}

		while ( $month < 1 ) {
			$month += 12;
			$year--;
		}

		// Last day of target month
		$lastDay = (int) date( 't', strtotime( "{$year}-{$month}-01" ) );

		// Clamp day
		$day = min( $day, $lastDay );

		$newTimestamp = (new DateTimeImmutable())
			->setDate( $year, $month, $day )
			->setTime( $hour, $minute, $second )
			->getTimestamp();

		return new self( $newTimestamp );
	}

	/* ---------------------------------------------------------------------
	 * Comparison
	 * ------------------------------------------------------------------ */

	public function isPast() : bool {
		return $this->timestamp < time();
	}

	public function isFuture() : bool {
		return $this->timestamp > time();
	}

	public function isBefore( self $other ) : bool {
		return $this->timestamp < $other->timestamp;
	}

	public function isAfter( self $other ) : bool {
		return $this->timestamp > $other->timestamp;
	}

	public function isExpired() : bool {
		return $this->isPast();
	}

	public function isNow() : bool {
		return $this->timestamp === time();
	}

	/* ---------------------------------------------------------------------
	 * Output
	 * ------------------------------------------------------------------ */

	public function value() : int {
		return $this->timestamp;
	}

	public function toDateTime() : DateTimeImmutable {
		return ( new DateTimeImmutable() )->setTimestamp( $this->timestamp );
	}

	/**
	 * Human-safe formatting
	 */
	public function format( string $format = 'Y-m-d H:i:s' ) : string {
		return $this->toDateTime()->format( $format );
	}
}