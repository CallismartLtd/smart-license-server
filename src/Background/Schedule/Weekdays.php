<?php
/**
 * Weekday-set matcher file.
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
 * Matches a set of one or more weekdays (0 = Sunday … 6 = Saturday).
 *
 * A single day gives the original weekly_on('sunday') behaviour;
 * multiple days allow patterns like "every Monday and Thursday".
 */
final class Weekdays implements DayMatcher {

    /**
     * Weekday numbers this matcher fires on, 0 (Sunday) through 6 (Saturday).
     *
     * @var int[]
     */
    private array $days;

    /**
     * Display names, indexed 0-6, for describe().
     */
    private const DAY_NAMES = [
        'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday',
    ];

    /**
     * Constructor.
     *
     * @param int ...$days One or more of 0-6.
     * @throws InvalidArgumentException If empty, or any value is out of range.
     */
    public function __construct( int ...$days ) {
        if ( empty( $days ) ) {
            throw new InvalidArgumentException( 'Weekdays: at least one day is required.' );
        }

        foreach ( $days as $day ) {
            if ( $day < 0 || $day > 6 ) {
                throw new InvalidArgumentException(
                    sprintf( 'Weekdays: "%d" is not a valid weekday. Use 0 (Sunday) through 6 (Saturday).', $day )
                );
            }
        }

        $this->days = array_values( array_unique( $days ) );
    }

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        return in_array( (int) $date->format( 'w' ), $this->days, true );
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        $names = array_map( fn( int $day ) => self::DAY_NAMES[ $day ], $this->days );
        return 'Weekly on ' . implode( ', ', $names );
    }
}