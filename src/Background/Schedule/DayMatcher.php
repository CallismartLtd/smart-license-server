<?php
/**
 * Day matcher contract file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule\DayMatchers
 * @since   0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule\DayMatchers;

use DateTimeImmutable;

/**
 * Decides whether a given calendar date qualifies as a run day for a
 * schedule. ScheduledTask walks forward day by day and asks each
 * candidate date "does this count?" — every recurrence pattern
 * (any day, specific weekdays, specific days-of-month, every N days,
 * every N months) is just a different answer to that one question.
 */
interface DayMatcher {

    /**
     * Whether $date qualifies as a run day under this pattern.
     *
     * Only the calendar date is considered — time-of-day is applied
     * separately by ScheduledTask once a matching date is found.
     *
     * @param DateTimeImmutable $date
     * @return bool
     */
    public function matches( DateTimeImmutable $date ): bool;

    /**
     * Human-readable description for admin display, e.g.
     * "Weekly on Monday, Thursday" or "Every 4 days".
     *
     * @return string
     */
    public function describe(): string;
}