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
 * Matches every Nth day, counted from a fixed universal epoch —
 * deliberately NOT from when this task/matcher happened to be
 * constructed.
 *
 * Scheduler rebuilds every ScheduledTask from scratch on every tick
 * (WP cron, crontab, CLI — nothing persists in memory between runs).
 * An anchor tied to construction time would therefore silently shift
 * every tick, collapsing "every 4 days" down to "whatever day the
 * next tick happens to land on". Anchoring to a fixed epoch instead
 * makes matches() a pure function of the calendar date — stable no
 * matter how many times the process restarts.
 */
final class EveryNDays implements DayMatcher {

    /**
     * Fixed reference date ("day zero") every cadence counts from.
     * Arbitrary but must never change once tasks are relying on it —
     * changing it would shift every existing every_days() schedule.
     */
    private const EPOCH = '1970-01-01';

    /**
     * Epoch as a DateTimeImmutable, built once.
     *
     * @var DateTimeImmutable
     */
    private DateTimeImmutable $epoch;

    /**
     * Constructor.
     *
     * @param int $n Run every N days. Must be >= 1.
     * @throws InvalidArgumentException If $n < 1.
     */
    public function __construct( private int $n ) {
        if ( $n < 1 ) {
            throw new InvalidArgumentException( 'EveryNDays: n must be at least 1.' );
        }

        $this->epoch = new DateTimeImmutable( self::EPOCH );
    }

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        $target_date = $date->setTime( 0, 0, 0 );

        // %a is the total day count, always non-negative regardless of
        // direction — the epoch is always in the past for any real date.
        $diff_days = (int) $this->epoch->diff( $target_date )->format( '%a' );

        return $diff_days % $this->n === 0;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        return sprintf( 'Every %d day(s)', $this->n );
    }
}