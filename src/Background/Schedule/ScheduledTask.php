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
use RuntimeException;
use SmartLicenseServer\Background\Schedule\DayMatchers\DayMatcher;
use SmartLicenseServer\Background\Schedule\DayMatchers\DaysOfMonth;
use SmartLicenseServer\Background\Schedule\DayMatchers\EveryDay;
use SmartLicenseServer\Background\Schedule\DayMatchers\EveryNDays;
use SmartLicenseServer\Background\Schedule\DayMatchers\EveryNMonths;
use SmartLicenseServer\Background\Schedule\DayMatchers\Weekdays;

/**
 * Fluent scheduled task definition.
 *
 * A schedule is composed of two independent parts:
 *   - a DayMatcher, deciding which calendar dates qualify (any day,
 *     specific weekdays, specific days-of-month, every N days, every
 *     N months), and
 *   - a set of TimeOfDay values, deciding what time(s) on those dates.
 *
 * Every calendar-based pattern — daily(), weekly_on(), every_days(4),
 * twice-daily via daily_at('08:00', '20:00') — reduces to the same
 * "walk forward and ask the matcher" algorithm in next_slot_after().
 * Pure sub-day intervals (every_minutes()/every_hours()) bypass all of
 * this and just add seconds, since they have no notion of a day at all.
 *
 * IMPORTANT: Scheduler rebuilds every ScheduledTask from scratch on
 * every tick crontab, CLI — see Scheduler's docblock; nothing
 * persists in memory between runs, only last_ran_at/next_run_at via
 * Settings). Nothing in this class may rely on "when was this object
 * constructed" for scheduling math — every_days()/every_months() are
 * deliberately anchored to a fixed epoch inside their DayMatcher
 * (not to a timestamp captured here) for exactly that reason.
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
     * Interval in seconds — only meaningful for a pure sub-day interval
     * schedule (every_minutes()/every_hours()/hourly()). Stays 0 for
     * any calendar-based schedule, where $day_matcher drives the cadence
     * instead. A value of 0 with $day_matcher also null means "no
     * schedule defined yet".
     *
     * @var int
     */
    private int $interval_seconds = 0;

    /**
     * Decides which calendar dates this task runs on. Null for a pure
     * sub-day interval schedule, or before any schedule method has
     * been called.
     *
     * @var DayMatcher|null
     */
    private ?DayMatcher $day_matcher = null;

    /**
     * Time(s) of day to run on each date $day_matcher accepts. Always
     * has at least one entry once a calendar-based schedule method has
     * been called (defaults to 00:00 when no time is given).
     *
     * @var TimeOfDay[]
     */
    private array $times = [];

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
    |-------------------------------------------------
    | FLUENT SCHEDULE DEFINITION — SUB-DAY INTERVALS
    |-------------------------------------------------
    */

    /**
     * Run every N minutes. No day pattern, no fixed clock time.
     *
     * @param int $minutes
     * @return static Fluent.
     */
    public function every_minutes( int $minutes ): static {
        $this->day_matcher      = null;
        $this->times            = [];
        $this->interval_seconds = max( 1, $minutes ) * 60;
        return $this;
    }

    /**
     * Run once every minute.
     * 
     * @return static
     */
    public function minutely() : static {
        return $this->every_minutes( 1 );
    }

    /**
     * Run every N hours. No day pattern, no fixed clock time.
     *
     * @param int $hours
     * @return static Fluent.
     */
    public function every_hours( int $hours ): static {
        $this->day_matcher      = null;
        $this->times            = [];
        $this->interval_seconds = max( 1, $hours ) * 3600;
        return $this;
    }

    /**
     * Run once every hour, on the hour.
     *
     * @return static Fluent.
     */
    public function hourly(): static {
        return $this->every_hours( 1 );
    }

    /*
    |------------------------------
    | FLUENT SCHEDULE DEFINITION — CALENDAR PATTERNS
    |------------------------------
    */

    /**
     * Run once every day at midnight.
     *
     * @return static Fluent.
     */
    public function daily(): static {
        $this->interval_seconds = 0;
        $this->day_matcher      = new EveryDay();
        $this->set_times();
        return $this;
    }

    /**
     * Run every day at one or more specific times.
     *
     *   ->daily_at( '08:00' )              // once a day
     *   ->daily_at( '08:00', '20:00' )     // twice a day
     *
     * @param string ...$times One or more times in H:i format.
     * @return static Fluent.
     * @throws InvalidArgumentException On any invalid time.
     */
    public function daily_at( string ...$times ): static {
        $this->interval_seconds = 0;
        $this->day_matcher      = new EveryDay();
        $this->set_times( ...$times );
        return $this;
    }

    /**
     * Run every N days, counted from a fixed epoch (see EveryNDays) —
     * NOT from when this task happens to be registered, since Scheduler
     * rebuilds tasks fresh on every tick.
     *
     *   ->every_days( 4 )                  // every 4 days, at midnight
     *   ->every_days( 4, '06:00' )         // every 4 days, at 06:00
     *
     * @param int    $n        Run every N days. Must be >= 1.
     * @param string ...$times One or more times in H:i format. Defaults to '00:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException If $n < 1, or on any invalid time.
     */
    public function every_days( int $n, string ...$times ): static {
        $this->interval_seconds = 0;
        $this->day_matcher      = $n === 1 ? new EveryDay() : new EveryNDays( $n );
        $this->set_times( ...$times );
        return $this;
    }

    /**
     * Run once every 7 days, at midnight, not tied to a specific weekday.
     * Shorthand for every_days(7) — use weekly_on() to pin a weekday.
     *
     * @return static Fluent.
     */
    public function weekly(): static {
        return $this->every_days( 7 );
    }

    /**
     * Run weekly on one or more specific weekdays.
     *
     *   ->weekly_on( 'sunday', '03:00' )
     *   ->weekly_on( ['monday', 'thursday'], '09:00' )
     *
     * @param string|int|array<string|int> $days     Day name(s) (case-insensitive) or 0-6 (0 = Sunday).
     * @param string                       ...$times One or more times in H:i format. Defaults to '00:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException On an unrecognised day, or any invalid time.
     */
    public function weekly_on( string|int|array $days, string ...$times ): static {
        $days = is_array( $days ) ? $days : [ $days ];
        $ints = array_map( fn( string|int $day ) => $this->parse_day_of_week( $day ), $days );

        $this->interval_seconds = 0;
        $this->day_matcher      = new Weekdays( ...$ints );
        $this->set_times( ...$times );
        return $this;
    }

    /**
     * Run once a calendar month, on the 1st, at midnight. Shorthand for
     * every_months(1, 1) — use monthly_on() to pin a different day.
     *
     * @return static Fluent.
     */
    public function monthly(): static {
        return $this->every_months( 1, 1 );
    }

    /**
     * Run monthly on one or more specific days-of-month.
     *
     *   ->monthly_on( 1, '00:00' )
     *   ->monthly_on( [1, 15], '00:00' )   // twice a month
     *
     * @param int|array<int> $days     Day(s) of month, 1-28.
     * @param string         ...$times One or more times in H:i format. Defaults to '00:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException On an out-of-range day, or any invalid time.
     */
    public function monthly_on( int|array $days, string ...$times ): static {
        $days = is_array( $days ) ? $days : [ $days ];

        $this->interval_seconds = 0;
        $this->day_matcher      = new DaysOfMonth( ...$days );
        $this->set_times( ...$times );
        return $this;
    }

    /**
     * Run every N calendar months, on a specific day-of-month, counted
     * from a fixed epoch month (see EveryNMonths) — NOT from when this
     * task happens to be registered, since Scheduler rebuilds tasks
     * fresh on every tick.
     *
     *   ->every_months( 2, 15, '06:00' )   // 15th, every other month
     *
     * @param int    $n        Run every N months. Must be >= 1.
     * @param int    $day      Day of month, 1-28. Default 1.
     * @param string ...$times One or more times in H:i format. Defaults to '00:00'.
     * @return static Fluent.
     * @throws InvalidArgumentException On invalid $n/$day, or any invalid time.
     */
    public function every_months( int $n, int $day = 1, string ...$times ): static {
        $this->interval_seconds = 0;
        $this->day_matcher      = new EveryNMonths( $n, $day );
        $this->set_times( ...$times );
        return $this;
    }

    /**
     * Replace the time(s) of day for whichever calendar pattern is
     * already configured. Lets a day pattern and its time(s) be
     * chained separately when that reads better:
     *
     *   ->every_days( 4 )->at( '06:00' )
     *
     * @param string ...$times One or more times in H:i format.
     * @return static Fluent.
     * @throws InvalidArgumentException On any invalid time.
     */
    public function at( string ...$times ): static {
        $this->set_times( ...$times );
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
     * @param DateTimeImmutable|null $last_ran_at Last execution time, or null if never run.
     * @return bool True if the task should run now.
     */
    public function is_due( ?DateTimeImmutable $last_ran_at ): bool {
        if ( $this->day_matcher === null && $this->interval_seconds === 0 ) {
            return false; // No schedule defined yet.
        }

        $now = new DateTimeImmutable();

        // Never run before — resolve the first eligible slot from right
        // now. (Deliberately "now", not a stored construction time —
        // see the class docblock on why nothing here may depend on that.)
        $next = $last_ran_at === null
            ? $this->compute_next_run( $now, true )
            : $this->compute_next_run( $last_ran_at, false );

        return $now >= $next;
    }

    /**
     * Compute the next run datetime after $after.
     *
     * For a calendar-based schedule this walks forward day by day
     * (see next_slot_after()) so a late pickup is naturally absorbed —
     * comparing the real elapsed-since-last-run timestamp against the
     * actual candidate slots, rather than adding a fixed interval to a
     * possibly-late timestamp, means lateness never compounds into
     * future runs drifting later and later.
     *
     * @param DateTimeImmutable $after     Reference point to compute the next run after.
     * @param bool              $inclusive Whether a slot exactly at $after counts (used
     *                                     for a task's very first run).
     * @return DateTimeImmutable
     */
    public function compute_next_run( DateTimeImmutable $after, bool $inclusive = false ): DateTimeImmutable {
        if ( $this->day_matcher !== null ) {
            return $this->next_slot_after( $after, $inclusive );
        }

        return $inclusive ? $after : $after->modify( "+{$this->interval_seconds} seconds" );
    }

    /*
    |------------------------------------------------
    | CALENDAR CALCULATION
    |------------------------------------------------
    */

    /**
     * How far ahead to search for a matching date before giving up.
     * Generous even for a sparse "every 6 months" pattern; cheap either
     * way since this only ever walks whole days, never sub-day steps.
     */
    private const MAX_LOOKAHEAD_DAYS = 732;

    /**
     * Walk forward from $after, day by day, until $day_matcher accepts
     * a date AND one of $times on that date is still ahead of $after
     * (or at-or-after it, when $inclusive).
     *
     * This single loop is what makes twice-daily, multi-weekday, every-
     * N-days and every-N-months all "just work" without separate code
     * paths: the matcher and the time set are fully decoupled, and every
     * pattern is answered by the same walk.
     *
     * @param DateTimeImmutable $after
     * @param bool              $inclusive
     * @return DateTimeImmutable
     * @throws RuntimeException If no matching slot is found within MAX_LOOKAHEAD_DAYS.
     */
    private function next_slot_after( DateTimeImmutable $after, bool $inclusive ): DateTimeImmutable {
        $cursor = $after->setTime( 0, 0, 0 );

        for ( $day = 0; $day <= self::MAX_LOOKAHEAD_DAYS; $day++ ) {
            if ( $this->day_matcher->matches( $cursor ) ) {
                foreach ( $this->sorted_times() as $time ) {
                    $candidate = $time->apply_to( $cursor );

                    if ( $inclusive ? $candidate >= $after : $candidate > $after ) {
                        return $candidate;
                    }
                }
            }

            $cursor    = $cursor->modify( '+1 day' );
            $inclusive = true; // Any time on a later day already qualifies.
        }

        throw new RuntimeException(
            sprintf( 'ScheduledTask "%s": no matching run date found within %d days.', $this->id, self::MAX_LOOKAHEAD_DAYS )
        );
    }

    /**
     * $times sorted ascending by time-of-day, so next_slot_after() checks
     * each date's candidates in chronological order.
     *
     * @return TimeOfDay[]
     */
    private function sorted_times(): array {
        $times = $this->times;

        usort(
            $times,
            fn( TimeOfDay $a, TimeOfDay $b ) => $a->to_seconds_since_midnight() <=> $b->to_seconds_since_midnight()
        );

        return $times;
    }

    /**
     * Validate and store the time-of-day set for a calendar-based schedule.
     *
     * @param string ...$times One or more times in H:i format. Defaults to '00:00'.
     * @return void
     * @throws InvalidArgumentException On any invalid time.
     */
    private function set_times( string ...$times ): void {
        if ( empty( $times ) ) {
            $times = [ '00:00' ];
        }

        $this->times = array_map( [ TimeOfDay::class, 'from_string' ], $times );
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
     * Return the interval in seconds for a pure sub-day interval schedule
     * (every_minutes()/every_hours()/hourly()).
     *
     * Returns 0 when this task instead uses a calendar-based schedule
     * (daily(), weekly_on(), every_days(), etc.) — use
     * get_schedule_description() or get_day_matcher() for those.
     *
     * @return int
     */
    public function get_interval_seconds(): int {
        return $this->interval_seconds;
    }

    /**
     * Return the day matcher driving this task's calendar pattern, or
     * null for a pure sub-day interval schedule.
     *
     * @return DayMatcher|null
     */
    public function get_day_matcher(): ?DayMatcher {
        return $this->day_matcher;
    }

    /**
     * Return the configured time(s) of day, sorted ascending. Empty for
     * a pure sub-day interval schedule.
     *
     * @return TimeOfDay[]
     */
    public function get_times(): array {
        return $this->sorted_times();
    }

    /**
     * Return a human-readable description of the schedule.
     *
     * @return string
     */
    public function get_schedule_description(): string {
        if ( $this->day_matcher === null && $this->interval_seconds === 0 ) {
            return 'No schedule defined.';
        }

        if ( $this->day_matcher === null ) {
            return match ( $this->interval_seconds ) {
                60      => 'Every minute',
                3600    => 'Every hour',
                default => sprintf( 'Every %d seconds', $this->interval_seconds ),
            };
        }

        $times = implode( ', ', array_map( 'strval', $this->sorted_times() ) );

        return sprintf( '%s at %s', $this->day_matcher->describe(), $times );
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
     * Resolve a day name or an already-valid 0-6 integer to a
     * day-of-week integer (0 = Sunday … 6 = Saturday). Range validation
     * for an integer input is left to Weekdays itself.
     *
     * @param string|int $day
     * @return int
     * @throws InvalidArgumentException On an unrecognised day name.
     */
    private function parse_day_of_week( string|int $day ): int {
        if ( is_int( $day ) ) {
            return $day;
        }

        static $map = [
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