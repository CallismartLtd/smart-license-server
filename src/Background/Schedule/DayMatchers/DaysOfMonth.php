<?php
/**
 * Day-of-month-set matcher file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule\DayMatchers
 * @since   0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule\DayMatchers;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Matches a set of one or more days-of-month (1-28).
 *
 * Capped at 28 so every matching day exists in every month — the
 * same guarantee the original monthly_on() made.
 */
final class DaysOfMonth implements DayMatcher {

    /**
     * Days-of-month this matcher fires on.
     *
     * @var int[]
     */
    private array $days;

    /**
     * Constructor.
     *
     * @param int ...$days One or more of 1-28.
     * @throws InvalidArgumentException If empty, or any value is out of range.
     */
    public function __construct( int ...$days ) {
        if ( empty( $days ) ) {
            throw new InvalidArgumentException( 'DaysOfMonth: at least one day is required.' );
        }

        foreach ( $days as $day ) {
            if ( $day < 1 || $day > 28 ) {
                throw new InvalidArgumentException(
                    sprintf( 'DaysOfMonth: "%d" is out of range. Days must be between 1 and 28.', $day )
                );
            }
        }

        $this->days = array_values( array_unique( $days ) );
    }

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        return in_array( (int) $date->format( 'd' ), $this->days, true );
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        return 'Monthly on day(s) ' . implode( ', ', $this->days );
    }
}