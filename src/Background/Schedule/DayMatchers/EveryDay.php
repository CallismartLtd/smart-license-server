<?php
/**
 * "Every day" matcher file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule\DayMatchers
 * @since   0.3.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule\DayMatchers;

use DateTimeImmutable;

/**
 * Matches every calendar date — the pattern behind daily()/daily_at().
 */
final class EveryDay implements DayMatcher {

    /**
     * {@inheritdoc}
     */
    public function matches( DateTimeImmutable $date ): bool {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(): string {
        return 'Daily';
    }
}