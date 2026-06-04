<?php
/**
 * Scheduled task class file.
 *
 * Represents a single recurring task registered with the Scheduler.
 * Built via the fluent API on Scheduler::call() or Scheduler::dispatch()
 * and never constructed directly.
 *
 * ## Examples
 *
 *   // Run a closure daily at 02:00
 *   smliser_scheduler()
 *       ->call( function() {
 *           smliser_db()->query( 'DELETE FROM ...' );
 *       })
 *       ->daily_at( '02:00' )
 *       ->label( 'Prune old records' );
 *
 *   // Dispatch a queued job every Sunday at 03:00
 *   smliser_scheduler()
 *       ->dispatch( PruneAnalyticsLogsJob::class, ['retention_days' => 90] )
 *       ->weekly_on( 'sunday', '03:00' );
 *
 *   // Run a static method every 15 minutes
 *   smliser_scheduler()
 *       ->call( [MyClass::class, 'my_method'] )
 *       ->every_minutes( 15 );
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
     * Specific time-of-day to run (H:i format).
     * Used by daily_at(), weekly_on() etc.
     * Null means run as soon as the interval elapses.
     *
     * @var string|null
     */
    private ?string $time_of_day = null;

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
        $this->time_of_day      = '00:00';
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
        $this->assert_valid_time( $time );
        $this->interval_seconds = 86400;
        $this->time_of_day      = $time;
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
        $this->assert_valid_time( $time );
        $this->interval_seconds = 604800;
        $this->day_of_week      = $this->parse_day_of_week( $day );
        $this->time_of_day      = $time;
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

        $this->assert_valid_time( $time );
        $this->interval_seconds = -1; // Sentinel: calendar month, not a raw second count.
        $this->day_of_month     = $day;
        $this->time_of_day      = $time;
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
     * @param DateTimeImmutable $now
     * @return DateTimeImmutable
     */
    private function compute_first_run( DateTimeImmutable $now ): DateTimeImmutable {
        $first = $now;

        if ( $this->time_of_day !== null ) {
            [ $h, $m ] = explode( ':', $this->time_of_day );
            $first = $first->setTime( (int) $h, (int) $m, 0 );

            // If the scheduled time has already passed today, advance by one interval
            // and re-apply the time-of-day so the slot stays canonical.
            if ( $first < $now ) {
                $first = $this->interval_seconds === -1
                    ? $first->modify( '+1 month' )
                    : $first->modify( "+{$this->interval_seconds} seconds" );

                [ $h, $m ] = explode( ':', $this->time_of_day );
                $first = $first->setTime( (int) $h, (int) $m, 0 );
            }
        }

        if ( $this->day_of_week !== null ) {
            $current_dow = (int) $first->format( 'w' );
            $days_ahead  = ( $this->day_of_week - $current_dow + 7 ) % 7;

            if ( $days_ahead === 0 ) {
                // Already the right weekday — only stay if the time slot is still ahead.
                // Otherwise push a full week forward to avoid an immediate double-fire.
                if ( $first <= $now ) {
                    $first = $first->modify( '+7 days' );
                }
            } else {
                $first = $first->modify( "+{$days_ahead} days" );
            }
        }

        if ( $this->day_of_month !== null ) {
            $first = $first->setDate(
                (int) $first->format( 'Y' ),
                (int) $first->format( 'm' ),
                $this->day_of_month
            );

            if ( $first < $now ) {
                $first = $first->modify( '+1 month' );
                // Re-apply the day after the month advance in case setDate drifted.
                $first = $first->setDate(
                    (int) $first->format( 'Y' ),
                    (int) $first->format( 'm' ),
                    $this->day_of_month
                );
            }
        }

        return $first;
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
        // does not shift the entire future schedule forward.
        $anchor = $last_ran_at;

        if ( $this->time_of_day !== null ) {
            [ $h, $m ] = explode( ':', $this->time_of_day );
            $canonical = $anchor->setTime( (int) $h, (int) $m, 0 );

            // Only snap back if the task ran after the scheduled time (late pickup).
            // If it somehow ran before (e.g. manual trigger), keep the real run time.
            if ( $canonical <= $last_ran_at ) {
                $anchor = $canonical;
            }
        }

        // Advance by one interval from the canonical anchor.
        $next = $this->interval_seconds === -1
            ? $anchor->modify( '+1 month' )
            : $anchor->modify( "+{$this->interval_seconds} seconds" );

        // Re-apply time-of-day to the next slot.
        if ( $this->time_of_day !== null ) {
            [ $h, $m ] = explode( ':', $this->time_of_day );
            $next = $next->setTime( (int) $h, (int) $m, 0 );
        }

        // Apply day-of-week constraint.
        if ( $this->day_of_week !== null ) {
            $current_dow = (int) $next->format( 'w' );
            $days_ahead  = ( $this->day_of_week - $current_dow + 7 ) % 7;

            if ( $days_ahead === 0 ) {
                // Already on the right weekday — but if that slot is now in the past
                // (e.g. due to a late pickup spanning midnight), push a full week.
                $now = new DateTimeImmutable();
                if ( $next <= $now ) {
                    $next = $next->modify( '+7 days' );
                }
            } else {
                $next = $next->modify( "+{$days_ahead} days" );
            }
        }

        // Apply day-of-month constraint.
        if ( $this->day_of_month !== null ) {
            $next = $next->setDate(
                (int) $next->format( 'Y' ),
                (int) $next->format( 'm' ),
                $this->day_of_month
            );

            // If the computed date is still in the past relative to the last run,
            // advance one calendar month.
            if ( $next <= $last_ran_at ) {
                $next = $next->modify( '+1 month' );
            }
        }

        return $next;
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

    /**
     * Generate a stable ID for any callable.
     *
     * Not called internally — available as a utility for the Scheduler
     * when auto-generating task IDs before constructing a ScheduledTask.
     *
     * @param callable $callable
     * @return string
     */
    private function generate_id( callable $callable ): string {

        // Function name.
        if ( is_string( $callable ) ) {
            return 'func:' . strtolower( $callable );
        }

        // Class method or object method.
        if ( is_array( $callable ) ) {
            $class  = $callable[0];
            $method = $callable[1];

            // Static method.
            if ( is_string( $class ) ) {
                return 'static:' . $class . '::' . $method;
            }

            // Object method.
            if ( is_object( $class ) ) {
                return 'object:' . get_class( $class ) . '::' . $method . '#' . spl_object_hash( $class );
            }
        }

        // Closure.
        if ( $callable instanceof \Closure ) {
            return 'closure:' . spl_object_hash( $callable );
        }

        // Invokable object.
        if ( is_object( $callable ) && method_exists( $callable, '__invoke' ) ) {
            return 'invokable:' . get_class( $callable ) . '#' . spl_object_hash( $callable );
        }

        // Fallback.
        return 'task:' . md5( uniqid( '', true ) );
    }
}