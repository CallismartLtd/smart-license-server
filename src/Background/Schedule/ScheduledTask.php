<?php
/**
 * Scheduled task class file.
 *
 * Represents a single recurring task registered with the Scheduler.
 * Built via the fluent API on Scheduler::call() or Scheduler::dispatch()
 * and never constructed directly.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background\Schedule
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Schedule;

use Closure;
use DateTimeImmutable;
use InvalidArgumentException;
use ReflectionFunction;

/**
 * Fluent scheduled task definition.
 *
 * Holds the callable, the schedule definition, and the computed
 * next run time. The Scheduler evaluates whether it is due and
 * calls execute() when the time comes.
 */
class ScheduledTask {

    /*
    |----------------------
    | TASK IDENTITY
    |----------------------
    */

    /**
     * Unique identifier for this task.
     *
     * Auto-generated from the callable if not explicitly set via id().
     *
     * @var string
     */
    private string $id;

    /**
     * Human-readable label for admin display.
     *
     * @var string
     */
    private string $label = '';

    /*
    |-------------
    | CALLABLE
    |-------------
    */

    /**
     * The callable to invoke when this task is due.
     *
     * @var callable
     */
    private $callable;

    /*
    |----------------------
    | SCHEDULE STATE
    |----------------------
    */

    /**
     * Interval in seconds between executions.
     * Derived from the fluent schedule methods.
     *
     * A value of  0  means no schedule has been defined yet.
     * A value of -1  is a sentinel meaning "one calendar month" — used
     * by monthly_on() to avoid the inaccuracy of a fixed 30-day offset.
     *
     * @var int
     */
    private int $interval_seconds = 0;

    /**
     * Specific time-of-day to run, kept as the original "H:i" string
     * purely for display purposes (get_schedule_description()).
     * Null means run as soon as the interval elapses.
     *
     * @var string|null
     */
    private ?string $time_of_day = null;

    /**
     * Real DateTimeImmutable representation of $time_of_day, holding
     * only an hour/minute — built once when the time is set, and used
     * for every date/time calculation instead of re-parsing the string.
     *
     * @var DateTimeImmutable|null
     */
    private ?DateTimeImmutable $time_of_day_template = null;

    /**
     * Day of week constraint (0 = Sunday … 6 = Saturday).
     * Used by weekly_on(). Null means no day constraint.
     *
     * @var int|null
     */
    private ?int $day_of_week = null;

    /**
     * Day of month constraint (1–28).
     * Used by monthly_on(). Null means no day-of-month constraint.
     *
     * @var int|null
     */
    private ?int $day_of_month = null;

    /*
    |---------------
    | CONSTRUCTOR
    |---------------
    */

    /**
     * Constructor.
     *
     * @param string   $id       Task ID.
     * @param callable $callable The callable to invoke when this task is due.
     */
    public function __construct( string $id, callable $callable ) {
        if ( empty( $id ) ) {
            throw new InvalidArgumentException( 'ScheduledTask: Task ID must not be empty.' );
        }

        $this->id       = $id;
        $this->callable = $callable;
    }

    /*
    |------------------------------
    | FLUENT SCHEDULE DEFINITION
    |------------------------------
    */

    /**
     * Run every N minutes.
     *
     * @param int $minutes
     * @return static Fluent.
     */
    public function every_minutes( int $minutes ): static {
        $this->interval_seconds = max( 1, $minutes ) * 60;
        return $this;
    }

    /**
     * Run every N hours.
     *
     * @param int $hours
     * @return static Fluent.
     */
    public function every_hours( int $hours ): static {
        $this->interval_seconds = max( 1, $hours ) * 3600;
        return $this;
    }

    /**
     * Run once every hour at the top of the hour.
     *
     * @return static Fluent.
     */
    public function hourly(): static {
        $this->interval_seconds = 3600;
        return $this;
    }

    /**
     * Run once every day at midnight.
     *
     * @return static Fluent.
     */
    public function daily(): static {
        $this->interval_seconds = 86400;
        $this->set_time_of_day( '00:00' );
        return $this;
    }

    /**
     * Run once every day at a specific time.
     *
     * @param string $time Time in H:i format e.g. '08:00', '23:30'.
     * @return static Fluent.
     * @throws InvalidArgumentException On invalid time format.
     */
    public function daily_at( string $time ): static {
        $this->interval_seconds = 86400;
        $this->set_time_of_day( $time );
        return $this;
    }

    /**
     * Run once every week on a specific day and time.
     *
     * @param string $day  Day name e.g. 'monday', 'sunday'. Case-insensitive.
     * @param string $time Time in H:i format e.g. '02:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException On invalid day or time.
     */
    public function weekly_on( string $day, string $time = '00:00' ): static {
        $this->interval_seconds = 604800;
        $this->day_of_week      = $this->parse_day_of_week( $day );
        $this->set_time_of_day( $time );
        return $this;
    }

    /**
     * Run once every month on a specific day and time.
     *
     * Uses a calendar-month interval (+1 month) rather than a fixed
     * 30-day offset, so January → February → March etc. are always
     * correct regardless of month length.
     *
     * @param int    $day  Day of month (1–28). Values above 28 are rejected
     *                     to guarantee the date exists in every month.
     * @param string $time Time in H:i format e.g. '06:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException On invalid day or time.
     */
    public function monthly_on( int $day = 1, string $time = '00:00' ): static {
        if ( $day < 1 || $day > 28 ) {
            throw new InvalidArgumentException(
                'ScheduledTask: day of month must be between 1 and 28.'
            );
        }

        $this->interval_seconds = -1; // Sentinel: calendar month, not a raw second count.
        $this->day_of_month     = $day;
        $this->set_time_of_day( $time );
        return $this;
    }

    /*
    |--------------------------------------------
    | IDENTITY FLUENT METHODS
    |--------------------------------------------
    */

    /**
     * Set a human-readable label for this task.
     *
     * @param string $label
     * @return static Fluent.
     */
    public function label( string $label ): static {
        $this->label = $label;
        return $this;
    }

    /**
     * Set an explicit unique ID for this task.
     *
     * @param string $id
     * @return static Fluent.
     */
    public function id( string $id ): static {
        $this->id = $id;
        return $this;
    }

    /*
    |--------------------------------------------
    | EXECUTION
    |--------------------------------------------
    */

    /**
     * Execute the registered callable.
     *
     * Called by the Scheduler when the task is determined to be due.
     * Any exception thrown by the callable is re-thrown so the Scheduler
     * can record the failure without the runner dying.
     *
     * @return void
     * @throws \Throwable On any failure inside the callable.
     */
    public function execute(): void {
        try {
            ( $this->callable )();
        } catch ( \Throwable $e ) {
            // Re-throw so the Scheduler can record the failure.
            throw $e;
        }
    }

    /*
    |--------------------------------------------
    | DUE EVALUATION
    |--------------------------------------------
    */

    /**
     * Determine whether this task is due to run.
     *
     * Computes the next expected run time from the last run time
     * and the schedule definition, then checks if now is at or past it.
     *
     * @param DateTimeImmutable|null $last_ran_at Last execution time, or null if never run.
     * @return bool True if the task should run now.
     */
    public function is_due( ?DateTimeImmutable $last_ran_at ): bool {
        if ( $this->interval_seconds === 0 ) {
            return false; // No schedule defined yet.
        }

        $now = new DateTimeImmutable();

        // Never run before — check if we are at or past the first eligible run time.
        if ( $last_ran_at === null ) {
            return $now >= $this->compute_first_run( $now );
        }

        $next = $this->compute_next_run( $last_ran_at );
        return $now >= $next;
    }

    /**
     * Compute the first eligible run time for a task that has never run.
     *
     * Resolves the calendar date against any day-of-week / day-of-month
     * constraint FIRST, then applies the time-of-day. Only if that
     * combined slot is still not in the future do we advance by exactly
     * one cycle — never more than once. (Applying the time-of-day check
     * before the day constraint, or vice versa, independently of each
     * other, is what previously caused first runs to overshoot by a
     * full extra week/month whenever "now" fell on the wrong weekday
     * AND after the target clock time.)
     *
     * @param DateTimeImmutable $now
     * @return DateTimeImmutable
     */
    private function compute_first_run( DateTimeImmutable $now ): DateTimeImmutable {
        $candidate = $this->apply_time_of_day( $this->resolve_constrained_date( $now ) );

        // Only push forward if there's an actual clock target and it has
        // already elapsed on the resolved date. A plain interval-only
        // task (no time_of_day, no day constraint) is due immediately.
        if ( $this->time_of_day_template !== null && $candidate <= $now ) {
            $candidate = $this->apply_time_of_day( $this->advance_one_cycle( $candidate ) );
        }

        return $candidate;
    }

    /**
     * Compute the next run datetime after the given last run time.
     *
     * To avoid a late pickup causing the next run to land in the past
     * (and therefore firing again immediately), we anchor to the canonical
     * scheduled slot rather than the actual run timestamp.
     *
     * Example: daily-at-13:00 task picked up at 13:47.
     *   Anchor  = 13:00 (snap back to scheduled time)
     *   Next    = anchor + 24h → tomorrow 13:00  ✓
     *
     * @param DateTimeImmutable $last_ran_at
     * @return DateTimeImmutable
     */
    public function compute_next_run( DateTimeImmutable $last_ran_at ): DateTimeImmutable {
        // Snap the anchor back to the canonical scheduled time so a late pickup
        // does not shift the entire future schedule forward. If it somehow ran
        // before the scheduled time (e.g. manual trigger), keep the real run time.
        $canonical = $this->apply_time_of_day( $last_ran_at );
        $anchor    = ( $canonical <= $last_ran_at ) ? $canonical : $last_ran_at;

        $next = $this->apply_time_of_day( $this->advance_one_cycle( $anchor ) );

        // Safety net: after a long outage (or a hand-edited last_ran_at),
        // one cycle may still not be enough to reach the future — keep
        // advancing until it actually is. is_due() already filters out
        // interval_seconds === 0, so this always terminates.
        $now = new DateTimeImmutable();
        while ( $next <= $now ) {
            $next = $this->apply_time_of_day( $this->advance_one_cycle( $next ) );
        }

        return $next;
    }

    /*
    |------------------------------------------------
    | DATE / TIME CALCULATION HELPERS
    |------------------------------------------------
    */

    /**
     * Resolve the calendar date satisfying the day-of-week or
     * day-of-month constraint (if any), at or after $from's date.
     * Time-of-day is intentionally untouched here — see apply_time_of_day().
     *
     * @param DateTimeImmutable $from
     * @return DateTimeImmutable
     */
    private function resolve_constrained_date( DateTimeImmutable $from ): DateTimeImmutable {
        if ( $this->day_of_week !== null ) {
            $current_dow = (int) $from->format( 'w' );
            $days_ahead  = ( $this->day_of_week - $current_dow + 7 ) % 7;

            return $from->modify( "+{$days_ahead} days" );
        }

        if ( $this->day_of_month !== null ) {
            $current_day = (int) $from->format( 'd' );
            $candidate   = $from->setDate( (int) $from->format( 'Y' ), (int) $from->format( 'm' ), $this->day_of_month );

            // Target day already passed this month — roll to next month.
            if ( $this->day_of_month < $current_day ) {
                $candidate = $candidate->modify( '+1 month' );
            }

            return $candidate;
        }

        return $from;
    }

    /**
     * Advance a resolved date by exactly one schedule cycle, respecting
     * whichever constraint defines the cycle (day-of-week, day-of-month,
     * or a raw interval for plain daily/hourly/minute-based schedules).
     *
     * @param DateTimeImmutable $date
     * @return DateTimeImmutable
     */
    private function advance_one_cycle( DateTimeImmutable $date ): DateTimeImmutable {
        if ( $this->day_of_week !== null ) {
            return $date->modify( '+7 days' );
        }

        if ( $this->day_of_month !== null ) {
            $next = $date->modify( '+1 month' );
            return $next->setDate( (int) $next->format( 'Y' ), (int) $next->format( 'm' ), $this->day_of_month );
        }

        return $this->interval_seconds === -1
            ? $date->modify( '+1 month' )
            : $date->modify( "+{$this->interval_seconds} seconds" );
    }

    /**
     * Apply the stored time-of-day template to a date, using the real
     * DateTimeImmutable object built once by set_time_of_day() rather
     * than re-parsing the "H:i" string on every call.
     *
     * @param DateTimeImmutable $date
     * @return DateTimeImmutable
     */
    private function apply_time_of_day( DateTimeImmutable $date ): DateTimeImmutable {
        if ( $this->time_of_day_template === null ) {
            return $date;
        }

        return $date->setTime(
            (int) $this->time_of_day_template->format( 'H' ),
            (int) $this->time_of_day_template->format( 'i' ),
            0
        );
    }

    /**
     * Validate and store a time-of-day, building the real
     * DateTimeImmutable template used for all later calculations.
     *
     * @param string $time Time in H:i format.
     * @return void
     * @throws InvalidArgumentException On invalid time format.
     */
    private function set_time_of_day( string $time ): void {
        $this->assert_valid_time( $time );

        // The "!" flag resets every field not specified (year, month, day...)
        // to the Unix epoch, leaving a real DateTimeImmutable that carries
        // only the parsed hour/minute — no manual explode()/cast needed.
        $template = DateTimeImmutable::createFromFormat( '!H:i', $time );

        if ( $template === false ) {
            throw new InvalidArgumentException(
                sprintf( 'ScheduledTask: unable to parse time "%s".', $time )
            );
        }

        $this->time_of_day          = $time;
        $this->time_of_day_template = $template;
    }

    /*
    |------------------
    | INSPECTION API
    |------------------
    */

    /**
     * Return the task ID.
     *
     * @return string
     */
    public function get_id(): string {
        return $this->id;
    }

    /**
     * Return the task label.
     *
     * @return string
     */
    public function get_label(): string {
        return $this->label;
    }

    /**
     * Return the interval in seconds.
     *
     * Returns -1 for monthly tasks (calendar-month interval sentinel).
     *
     * @return int
     */
    public function get_interval_seconds(): int {
        return $this->interval_seconds;
    }

    /**
     * Return a human-readable description of the schedule.
     *
     * @return string
     */
    public function get_schedule_description(): string {
        if ( $this->interval_seconds === 0 ) {
            return 'No schedule defined.';
        }

        $time = $this->time_of_day ?? 'any time';

        if ( $this->day_of_month !== null ) {
            return sprintf( 'Monthly on day %d at %s', $this->day_of_month, $time );
        }

        if ( $this->day_of_week !== null ) {
            $days = [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ];
            return sprintf( 'Weekly on %s at %s', $days[ $this->day_of_week ], $time );
        }

        return match ( $this->interval_seconds ) {
            60      => 'Every minute',
            3600    => 'Every hour',
            86400   => sprintf( 'Daily at %s', $time ),
            default => sprintf( 'Every %d seconds', $this->interval_seconds ),
        };
    }

    /**
     * Describe the callable.
     *
     * @return string
     */
    public function describe_callable(): string {
        $callable = $this->callable;

        // Simple function name or "Class::method" string.
        if ( is_string( $callable ) ) {
            return $callable . '()';
        }

        // Array callable — [class-or-object, method].
        if ( is_array( $callable ) ) {
            [ $target, $method ] = $callable;

            // Static method.
            if ( is_string( $target ) ) {
                return $target . '::' . $method . '()';
            }

            // Object method.
            if ( is_object( $target ) ) {
                return get_class( $target ) . '->' . $method . '()';
            }
        }

        // Closure.
        if ( $callable instanceof Closure ) {
            $ref = new ReflectionFunction( $callable );

            return sprintf(
                'Closure(%s:%d)',
                basename( $ref->getFileName() ),
                $ref->getStartLine()
            );
        }

        // Invokable object.
        if ( is_object( $callable ) && method_exists( $callable, '__invoke' ) ) {
            return get_class( $callable ) . '()';
        }

        return 'Unknown callable';
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Assert that a time string is in valid H:i format.
     *
     * @param string $time
     * @throws InvalidArgumentException
     */
    private function assert_valid_time( string $time ): void {
        if ( ! preg_match( '/^\d{1,2}:\d{2}$/', $time ) ) {
            throw new InvalidArgumentException(
                sprintf( 'ScheduledTask: "%s" is not a valid time. Use H:i format e.g. "08:00".', $time )
            );
        }

        [ $h, $m ] = explode( ':', $time );

        if ( (int) $h > 23 || (int) $m > 59 ) {
            throw new InvalidArgumentException(
                sprintf( 'ScheduledTask: "%s" is out of range. Hours must be 0-23, minutes 0-59.', $time )
            );
        }
    }

    /**
     * Parse a day name string to a day-of-week integer (0 = Sunday … 6 = Saturday).
     *
     * @param string $day
     * @return int
     * @throws InvalidArgumentException On unrecognised day name.
     */
    private function parse_day_of_week( string $day ): int {
        $map = [
            'sunday'    => 0,
            'monday'    => 1,
            'tuesday'   => 2,
            'wednesday' => 3,
            'thursday'  => 4,
            'friday'    => 5,
            'saturday'  => 6,
        ];

        $key = strtolower( trim( $day ) );

        if ( ! array_key_exists( $key, $map ) ) {
            throw new InvalidArgumentException(
                sprintf( 'ScheduledTask: "%s" is not a valid day name.', $day )
            );
        }

        return $map[ $key ];
    }
}