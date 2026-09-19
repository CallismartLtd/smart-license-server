<?php
/**
 * Time-of-day value object file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule
 * @since   0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * An immutable hour:minute pair, parsed once from an "H:i" string and
 * reused for every date calculation that needs a clock time applied.
 */
final class TimeOfDay {

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Private constructor — use from_string() to build an instance.
     *
     * @param int $hour   0-23.
     * @param int $minute 0-59.
     */
    private function __construct(
        private int $hour,
        private int $minute
    ) {}

    /**
     * Parse an "H:i" string into a TimeOfDay.
     *
     * @param string $time Time in H:i format, e.g. '08:00', '23:30'.
     * @return self
     * @throws InvalidArgumentException On invalid format or out-of-range values.
     */
    public static function from_string( string $time ): self {
        if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
            throw new InvalidArgumentException(
                sprintf( 'TimeOfDay: "%s" is not a valid time. Use H:i format e.g. "08:00".', $time )
            );
        }

        [ $hour, $minute ] = array_map( 'intval', explode( ':', $time ) );

        if ( $hour > 23 || $minute > 59 ) {
            throw new InvalidArgumentException(
                sprintf( 'TimeOfDay: "%s" is out of range. Hours must be 0-23, minutes 0-59.', $time )
            );
        }

        return new self( $hour, $minute );
    }

    /*
    |----------------------
    | APPLICATION
    |----------------------
    */

    /**
     * Return a copy of $date with this time-of-day applied.
     *
     * @param DateTimeImmutable $date
     * @return DateTimeImmutable
     */
    public function apply_to( DateTimeImmutable $date ): DateTimeImmutable {
        return $date->setTime( $this->hour, $this->minute, 0 );
    }

    /**
     * Seconds since midnight — used to sort a set of times ascending.
     *
     * @return int
     */
    public function to_seconds_since_midnight(): int {
        return ( $this->hour * 3600 ) + ( $this->minute * 60 );
    }

    /*
    |----------------------
    | DISPLAY
    |----------------------
    */

    /**
     * Render as "H:i", zero-padded.
     *
     * @return string
     */
    public function __toString(): string {
        return sprintf( '%02d:%02d', $this->hour, $this->minute );
    }
}