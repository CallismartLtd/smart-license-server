<?php
/**
 * "Every N days" matcher file.
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
 * Matches every Nth day, counted from a fixed anchor date.
 *
 * The anchor is required because "every 4 days" is meaningless without
 * a day zero to count from — ScheduledTask anchors it to the task's
 * own creation time, so the cadence is stable for the life of the task
 * regardless of when the scheduler happens to tick.
 */
final class EveryNDays implements DayMatcher {

    /**
     * Anchor date, normalised to midnight — only the calendar date matters.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $anchor_date;

    /**
     * Constructor.
     *
     * @param int               $n      Run every N days. Must be >= 1.
     * @param DateTimeImmutable $anchor Reference point to count from (day zero).
     * @throws InvalidArgumentException If $n < 1.
     */
    public function __construct(
        private int $n,
        DateTimeImmutable $anchor
    ) {
        if ( $n < 1 ) {
            throw new InvalidArgumentException( 'EveryNDays: n must be at least 1.' );
        }

        $this->anchor_date = $anchor->setTime( 0, 0, 0 );
    }

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        $target_date = $date->setTime( 0, 0, 0 );
        $diff_days   = (int) $this->anchor_date->diff( $target_date )->format( '%r%a' );

        // Dates before the anchor are never eligible — the cadence only
        // counts forward from the moment the task was created.
        return $diff_days >= 0 && $diff_days % $this->n === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        return sprintf( 'Every %d day(s)', $this->n );
    }
}