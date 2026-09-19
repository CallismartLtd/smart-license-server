<?php
/**
 * "Every N months" matcher file.
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
 * Matches a specific day-of-month, but only every Nth calendar month,
 * counted from a fixed epoch month — deliberately NOT from when this
 * task/matcher happened to be constructed.
 *
 * Scheduler rebuilds every ScheduledTask from scratch on every tick
 * (WP cron, crontab, CLI — nothing persists in memory between runs).
 * An anchor tied to construction time would therefore silently shift
 * every tick, breaking the "every N months" cadence. Anchoring to a
 * fixed epoch month instead makes matches() a pure function of the
 * calendar date — stable no matter how many times the process restarts.
 */
final class EveryNMonths implements DayMatcher {

    /**
     * Fixed reference month ("month zero") every cadence counts from.
     * Arbitrary but must never change once tasks are relying on it —
     * changing it would shift every existing every_months() schedule.
     */
    private const EPOCH_MONTH_INDEX = 0; // January, year 0 — i.e. absolute month index with no offset.

    /**
     * Constructor.
     *
     * @param int $n            Run every N months. Must be >= 1.
     * @param int $day_of_month Day of month to run on, 1-28.
     * @throws InvalidArgumentException On invalid $n or $day_of_month.
     */
    public function __construct(
        private int $n,
        private int $day_of_month
    ) {
        if ( $n < 1 ) {
            throw new InvalidArgumentException( 'EveryNMonths: n must be at least 1.' );
        }

        if ( $day_of_month < 1 || $day_of_month > 28 ) {
            throw new InvalidArgumentException(
                'EveryNMonths: day of month must be between 1 and 28.'
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        if ( (int) $date->format( 'd' ) !== $this->day_of_month ) {
            return false;
        }

        $target_month_index = ( (int) $date->format( 'Y' ) * 12 ) + ( (int) $date->format( 'n' ) - 1 );
        $diff_months        = $target_month_index - self::EPOCH_MONTH_INDEX;

        // Defensive floor-modulo — every realistic date is long after the
        // epoch, but this keeps the result well-defined either way.
        return ( ( $diff_months % $this->n ) + $this->n ) % $this->n === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        return $this->n === 1
            ? sprintf( 'Monthly on day %d', $this->day_of_month )
            : sprintf( 'Every %d month(s) on day %d', $this->n, $this->day_of_month );
    }
}