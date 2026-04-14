<?php

namespace SmartLicenseServer\Core\Dates;

use InvalidArgumentException;

/**
 * A robust, fluent DateDuration class for handling time durations with no hardcoded constants.
 * Supports dynamic unit conversion and intuitive construction methods.
 */
class DateDuration {
	/**
	 * The duration in seconds (base unit).
	 *
	 * @var float
	 */
	private float $seconds = 0;

	/**
	 * Unit conversion factors (dynamically calculated from base SI units).
	 * Each unit is defined in seconds relative to the base unit.
	 *
	 * @var array<string, float>
	 */
	private static array $units = [];

	/**
	 * Whether units have been initialized.
	 *
	 * @var bool
	 */
	private static bool $unitsInitialized = false;

	/**
	 * Initialize unit conversion factors once.
	 * This approach avoids hardcoding and allows future extensibility.
	 */
	private static function initializeUnits( ) : void {
		if ( self::$unitsInitialized ) {
			return;
		}

		// Define base time units in seconds (SI base unit)
		self::$units = [
			'microsecond' => 0.000001,
			'millisecond' => 0.001,
			'second'      => 1,
			'minute'      => 60,                    // 60 seconds
			'hour'        => 60 * 60,               // 3,600 seconds
			'day'         => 60 * 60 * 24,          // 86,400 seconds
			'week'        => 60 * 60 * 24 * 7,      // 604,800 seconds
		];

		// Aliases for convenience
		self::$units['microseconds'] = self::$units['microsecond'];
		self::$units['milliseconds'] = self::$units['millisecond'];
		self::$units['seconds'] = self::$units['second'];
		self::$units['minutes'] = self::$units['minute'];
		self::$units['hours'] = self::$units['hour'];
		self::$units['days'] = self::$units['day'];
		self::$units['weeks'] = self::$units['week'];

		// Abbreviated forms
		self::$units['μs'] = self::$units['microsecond'];
		self::$units['ms'] = self::$units['millisecond'];
		self::$units['s'] = self::$units['second'];
		self::$units['m'] = self::$units['minute'];
		self::$units['h'] = self::$units['hour'];
		self::$units['d'] = self::$units['day'];
		self::$units['w'] = self::$units['week'];

		self::$unitsInitialized = true;
	}

	/**
	 * Private constructor. Use static factory methods instead.
	 *
	 * @param float $seconds
	 */
	private function __construct( float $seconds = 0 ) {
		self::initializeUnits( );
		$this->seconds = ( float ) $seconds;
	}

	/**
	 * Create a duration from seconds.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromSeconds( float $value ) : self {
		return new self( $value );
	}

	/**
	 * Create a duration from minutes.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromMinutes( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['minute'] );
	}

	/**
	 * Create a duration from hours.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromHours( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['hour'] );
	}

	/**
	 * Create a duration from days.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromDays( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['day'] );
	}

	/**
	 * Create a duration from weeks.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromWeeks( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['week'] );
	}

	/**
	 * Create a duration from milliseconds.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromMilliseconds( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['millisecond'] );
	}

	/**
	 * Create a duration from microseconds.
	 *
	 * @param float $value
	 * @return self
	 */
	public static function fromMicroseconds( float $value ) : self {
		self::initializeUnits( );
		return new self( $value * self::$units['microsecond'] );
	}

	/**
	 * Create a duration from a unit string (e.g., "5 minutes", "2.5 hours").
	 *
	 * @param string $value
	 * @param string $unit
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public static function from( string $value, string $unit ) : self {
		self::initializeUnits( );

		$unit = strtolower( trim( $unit ) );

		if ( ! isset( self::$units[ $unit ] ) ) {
			throw new InvalidArgumentException(
				"Unknown time unit: '{$unit}'. Supported units: " . implode( ', ', array_keys( self::$units ) )
			);
		}

		$numericValue = ( float ) $value;

		return new self( $numericValue * self::$units[ $unit ] );
	}

	/**
	 * Add a duration value to this duration (fluent interface).
	 *
	 * @param float $value
	 * @param string $unit
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function add( float $value, string $unit ) : self {
		self::initializeUnits( );

		$unit = strtolower( trim( $unit ) );

		if ( ! isset( self::$units[ $unit ] ) ) {
			throw new InvalidArgumentException(
				"Unknown time unit: '{$unit}'. Supported units: " . implode( ', ', array_keys( self::$units ) )
			);
		}

		$this->seconds += $value * self::$units[ $unit ];

		return $this;
	}

	/**
	 * Subtract a duration value from this duration (fluent interface).
	 *
	 * @param float $value
	 * @param string $unit
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function subtract( float $value, string $unit ) : self {
		return $this->add( -$value, $unit );
	}

	/**
	 * Add seconds (fluent).
	 *
	 * @param float $seconds
	 * @return self
	 */
	public function addSeconds( float $seconds ) : self {
		$this->seconds += $seconds;
		return $this;
	}

	/**
	 * Add minutes (fluent).
	 *
	 * @param float $minutes
	 * @return self
	 */
	public function addMinutes( float $minutes ) : self {
		return $this->add( $minutes, 'minute' );
	}

	/**
	 * Add hours (fluent).
	 *
	 * @param float $hours
	 * @return self
	 */
	public function addHours( float $hours ) : self {
		return $this->add( $hours, 'hour' );
	}

	/**
	 * Add days (fluent).
	 *
	 * @param float $days
	 * @return self
	 */
	public function addDays( float $days ) : self {
		return $this->add( $days, 'day' );
	}

	/**
	 * Add weeks (fluent).
	 *
	 * @param float $weeks
	 * @return self
	 */
	public function addWeeks( float $weeks ) : self {
		return $this->add( $weeks, 'week' );
	}

	/**
	 * Get the total duration in seconds.
	 *
	 * @return float
	 */
	public function toSeconds( ) : float {
		return $this->seconds;
	}

	/**
	 * Get the total duration in minutes.
	 *
	 * @return float
	 */
	public function toMinutes( ) : float {
		self::initializeUnits( );
		return $this->seconds / self::$units['minute'];
	}

	/**
	 * Get the total duration in hours.
	 *
	 * @return float
	 */
	public function toHours( ) : float {
		self::initializeUnits( );
		return $this->seconds / self::$units['hour'];
	}

	/**
	 * Get the total duration in days.
	 *
	 * @return float
	 */
	public function toDays( ) : float {
		self::initializeUnits( );
		return $this->seconds / self::$units['day'];
	}

	/**
	 * Get the total duration in weeks.
	 *
	 * @return float
	 */
	public function toWeeks( ) : float {
		self::initializeUnits( );
		return $this->seconds / self::$units['week'];
	}

	/**
	 * Get the total duration in milliseconds.
	 *
	 * @return float
	 */
	public function toMilliseconds( ) : float {
		self::initializeUnits( );
		return $this->seconds / self::$units['millisecond'];
	}

	/**
	 * Get duration in a specific unit.
	 *
	 * @param string $unit
	 * @return float
	 * @throws InvalidArgumentException
	 */
	public function to( string $unit ) : float {
		self::initializeUnits( );

		$unit = strtolower( trim( $unit ) );

		if ( ! isset( self::$units[ $unit ] ) ) {
			throw new InvalidArgumentException(
				"Unknown time unit: '{$unit}'. Supported units: " . implode( ', ', array_keys( self::$units ) )
			);
		}

		return $this->seconds / self::$units[ $unit ];
	}

	/**
	 * Get a human-readable breakdown of the duration.
	 * Returns an array with days, hours, minutes, and seconds.
	 *
	 * @return array<string, int>
	 */
	public function breakdown( ) : array {
		self::initializeUnits( );

		$remaining = ( int ) $this->seconds;

		$days = ( int ) floor( $remaining / self::$units['day'] );
		$remaining -= $days * self::$units['day'];

		$hours = ( int ) floor( $remaining / self::$units['hour'] );
		$remaining -= $hours * self::$units['hour'];

		$minutes = ( int ) floor( $remaining / self::$units['minute'] );
		$remaining -= $minutes * self::$units['minute'];

		$seconds = ( int ) $remaining;

		return [
			'days'    => $days,
			'hours'   => $hours,
			'minutes' => $minutes,
			'seconds' => $seconds,
		];
	}

	/**
	 * Format duration as a human-readable string (e.g., "2d 3h 45m 30s").
	 *
	 * @param bool $includeZero Include units with zero value
	 * @return string
	 */
	public function format( bool $includeZero = false ) : string {
		$breakdown = $this->breakdown( );
		$parts = [];

		foreach ( $breakdown as $unit => $value ) {
			if ( $value > 0 || $includeZero ) {
				$shortUnit = substr( $unit, 0, 1 );
				$parts[] = "{$value}{$shortUnit}";
			}
		}

		return ! empty( $parts ) ? implode( ' ', $parts ) : '0s';
	}

	/**
	 * Get a detailed human-readable string (e.g., "2 days, 3 hours, 45 minutes, 30 seconds").
	 *
	 * @return string
	 */
	public function formatDetailed( ) : string {
		$breakdown = $this->breakdown( );
		$parts = [];

		foreach ( $breakdown as $unit => $value ) {
			if ( $value > 0 ) {
				$singular = rtrim( $unit, 's' );
				$label = $value === 1 ? $singular : $unit;
				$parts[] = "{$value} {$label}";
			}
		}

		return ! empty( $parts ) ? implode( ', ', $parts ) : '0 seconds';
	}

	/**
	 * Check if this duration is equal to another.
	 *
	 * @param DateDuration $other
	 * @return bool
	 */
	public function equals( DateDuration $other ) : bool {
		return abs( $this->seconds - $other->seconds ) < 0.0001; // Account for float precision
	}

	/**
	 * Check if this duration is greater than another.
	 *
	 * @param DateDuration $other
	 * @return bool
	 */
	public function isGreaterThan( DateDuration $other ) : bool {
		return $this->seconds > $other->seconds + 0.0001;
	}

	/**
	 * Check if this duration is less than another.
	 *
	 * @param DateDuration $other
	 * @return bool
	 */
	public function isLessThan( DateDuration $other ) : bool {
		return $this->seconds < $other->seconds - 0.0001;
	}

	/**
	 * Check if this duration is greater than or equal to another.
	 *
	 * @param DateDuration $other
	 * @return bool
	 */
	public function isGreaterThanOrEqual( DateDuration $other ) : bool {
		return $this->isGreaterThan( $other ) || $this->equals( $other );
	}

	/**
	 * Check if this duration is less than or equal to another.
	 *
	 * @param DateDuration $other
	 * @return bool
	 */
	public function isLessThanOrEqual( DateDuration $other ) : bool {
		return $this->isLessThan( $other ) || $this->equals( $other );
	}

	/**
	 * Multiply the duration by a scalar value.
	 *
	 * @param float $factor
	 * @return self
	 */
	public function multiply( float $factor ) : self {
		return new self( $this->seconds * $factor );
	}

	/**
	 * Divide the duration by a scalar value.
	 *
	 * @param float $divisor
	 * @return self
	 * @throws InvalidArgumentException
	 */
	public function divide( float $divisor ) : self {
		if ( 0 === $divisor ) {
			throw new InvalidArgumentException( 'Cannot divide duration by zero' );
		}

		return new self( $this->seconds / $divisor );
	}

	/**
	 * Get the supported time units.
	 *
	 * @return array<string, float>
	 */
	public static function getSupportedUnits( ) : array {
		self::initializeUnits( );
		return self::$units;
	}

	/**
	 * Magic method: string representation.
	 *
	 * @return string
	 */
	public function __toString( ) : string {
		return $this->format( );
	}
}