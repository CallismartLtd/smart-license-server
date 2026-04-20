<?php
/**
 * Job Data Transfer Object file.
 *
 * Represents a single unit of deferred work — the "envelope" that
 * carries everything a worker needs to locate, execute, and track
 * a background job through its full lifecycle.
 *
 * The canonical envelope fields (queue, status, attempts, etc.) are
 * strictly validated on assignment. The payload field is intentionally
 * open — it holds whatever argument array the handler function needs
 * and is passed through as-is.
 *
 * ## Basic usage
 *
 *   // Via named constructor (recommended at dispatch sites):
 *   $job = JobDTO::make(
 *       job_class : SendLicenseExpiryEmailJob::class,
 *       payload   : [
 *           'license_id' => 42,
 *           'recipient'  => 'user@example.com',
 *           'days_left'  => 7,
 *       ],
 *       queue     : JobDTO::QUEUE_DEFAULT,
 *   );
 *
 *   // Via constructor (e.g. when rehydrating from storage):
 *   $job = new JobDTO([
 *       'id'        => 99,
 *       'job_class' => SendLicenseExpiryEmailJob::class,
 *       'payload'   => ['license_id' => 42, 'recipient' => 'user@example.com', 'days_left' => 7],
 *       'status'    => JobDTO::STATUS_PENDING,
 *       'queue'     => JobDTO::QUEUE_DEFAULT,
 *   ]);
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Background
 * @since   0.2.0
 *
 * @property int|null          $id             Storage-assigned identifier. Null until persisted.
 * @property string            $job_class      Fully-qualified handler class name.
 * @property string            $queue          Processing lane: critical | default | low.
 * @property int               $priority       1 (highest) – 10 (lowest). Default 5.
 * @property string            $status         Lifecycle state of the job.
 * @property array             $payload        Handler-specific argument array.
 * @property int               $attempts       Number of times execution has been attempted.
 * @property int               $max_attempts   Maximum allowed attempts before archiving as failed.
 * @property DateTimeImmutable      $available_at   Earliest datetime the job may be dequeued.
 * @property DateTimeImmutable      $created_at     Datetime the DTO was first constructed.
 * @property DateTimeImmutable|null $started_at     Datetime the worker claimed the job. Null until claimed.
 * @property DateTimeImmutable|null $completed_at   Datetime the job completed. Null until finished.
 * @property mixed                  $result         Return value captured by the worker after handle().
 * @property string|null            $error_message  Last failure reason. Null on success.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Background\Queue;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Core\DTO;
use DateTimeImmutable;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

final class JobDTO extends DTO {

    /*
    |----------------------
    | STATUS CONSTANTS
    |----------------------
    */

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_RETRYING  = 'retrying';

    /*
    |----------------------
    | QUEUE CONSTANTS
    |----------------------
    */

    public const QUEUE_CRITICAL = 'critical';
    public const QUEUE_DEFAULT  = 'default';
    public const QUEUE_LOW      = 'low';

    /*
    |----------------------
    | FIELD KEY CONSTANTS
    |----------------------
    */

    public const KEY_ID            = 'id';
    public const KEY_JOB_CLASS     = 'job_class';
    public const KEY_QUEUE         = 'queue';
    public const KEY_PRIORITY      = 'priority';
    public const KEY_STATUS        = 'status';
    public const KEY_PAYLOAD       = 'payload';
    public const KEY_ATTEMPTS      = 'attempts';
    public const KEY_MAX_ATTEMPTS  = 'max_attempts';
    public const KEY_AVAILABLE_AT  = 'available_at';
    public const KEY_CREATED_AT    = 'created_at';
    public const KEY_STARTED_AT    = 'started_at';
    public const KEY_COMPLETED_AT  = 'completed_at';
    public const KEY_RESULT        = 'result';
    public const KEY_ERROR_MESSAGE = 'error_message';

    /*
    |----------------------
    | VALID VALUE SETS
    |----------------------
    */

    private const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_RUNNING,
        self::STATUS_COMPLETED,
        self::STATUS_FAILED,
        self::STATUS_RETRYING,
    ];

    private const VALID_QUEUES = [
        self::QUEUE_CRITICAL,
        self::QUEUE_DEFAULT,
        self::QUEUE_LOW,
    ];

    /*
    |----------------------
    | CONSTRUCTOR
    |----------------------
    */

    /**
     * Constructor.
     *
     * Applies defaults for all optional fields before delegating
     * to the parent DTO so that a freshly constructed JobDTO is
     * always in a valid, ready-to-dispatch state.
     *
     * @param array<string, mixed> $data Initial job data. 'job_class' is required.
     * @throws InvalidArgumentException If 'job_class' is missing or any value is invalid.
     */
    public function __construct( array $data = [] ) {
        if ( empty( $data[ self::KEY_JOB_CLASS ] ) ) {
            throw new InvalidArgumentException(
                'JobDTO: "job_class" is required.'
            );
        }

        // Apply defaults for optional fields before assignment so that
        // cast() always receives a value for every canonical field.
        $data = array_merge( $this->defaults(), $data );

        parent::__construct( $data );
    }

    /*
    |----------------------
    | NAMED CONSTRUCTOR
    |----------------------
    */

    /**
     * Create a new job envelope ready for dispatch.
     *
     * Preferred over the raw constructor at call sites because the
     * named parameters make intent explicit and all other fields
     * fall back to sensible defaults.
     *
     * @param string $job_class   Fully-qualified handler class name.
     * @param array  $payload     Argument array passed to the handler's handle() method.
     * @param string $queue       Processing lane. Default: 'default'.
     * @param int    $priority    1 (highest) – 10 (lowest). Default: 5.
     * @param int    $max_attempts Maximum delivery attempts. Default: 3.
     * @param int    $delay       Seconds to wait before the job becomes available. Default: 0.
     * @return static
     * @throws InvalidArgumentException On any invalid argument.
     */
    public static function make(
        string $job_class,
        array  $payload      = [],
        string $queue        = self::QUEUE_DEFAULT,
        int    $priority     = 5,
        int    $max_attempts = 3,
        int    $delay        = 0,
    ): static {
        $available_at = $delay > 0
            ? new DateTimeImmutable( "+{$delay} seconds" )
            : new DateTimeImmutable();

        return new static( [
            self::KEY_JOB_CLASS    => $job_class,
            self::KEY_PAYLOAD      => $payload,
            self::KEY_QUEUE        => $queue,
            self::KEY_PRIORITY     => $priority,
            self::KEY_MAX_ATTEMPTS => $max_attempts,
            self::KEY_AVAILABLE_AT => $available_at,
        ] );
    }

    /*
    |----------------------
    | DTO HOOKS
    |----------------------
    */

    /**
     * {@inheritdoc}
     */
    protected function allowed_keys(): array {
        return [
            self::KEY_ID,
            self::KEY_JOB_CLASS,
            self::KEY_QUEUE,
            self::KEY_PRIORITY,
            self::KEY_STATUS,
            self::KEY_PAYLOAD,
            self::KEY_ATTEMPTS,
            self::KEY_MAX_ATTEMPTS,
            self::KEY_AVAILABLE_AT,
            self::KEY_CREATED_AT,
            self::KEY_STARTED_AT,
            self::KEY_COMPLETED_AT,
            self::KEY_RESULT,
            self::KEY_ERROR_MESSAGE,
        ];
    }

    /**
     * {@inheritdoc}
     *
     * Canonical fields are strictly coerced and validated.
     * The payload field passes through as an array without inspection —
     * its contents are the handler's responsibility.
     *
     * @throws InvalidArgumentException On any invalid value.
     */
    protected function cast( string $key, mixed $value ): mixed {
        return match ( $key ) {

            self::KEY_ID => $this->cast_id( $value ),

            self::KEY_JOB_CLASS => $this->cast_job_class( $value ),

            self::KEY_QUEUE => $this->cast_queue( $value ),

            self::KEY_PRIORITY => $this->cast_priority( $value ),

            self::KEY_STATUS => $this->cast_status( $value ),

            self::KEY_PAYLOAD => $this->cast_payload( $value ),

            self::KEY_ATTEMPTS,
            self::KEY_MAX_ATTEMPTS => $this->cast_attempt_count( $key, $value ),

            self::KEY_AVAILABLE_AT,
            self::KEY_CREATED_AT => $this->cast_datetime( $key, $value ),

            self::KEY_STARTED_AT,
            self::KEY_COMPLETED_AT => $value !== null
                ? $this->cast_datetime( $key, $value )
                : null,

            self::KEY_RESULT => $value, // Open — any return value is valid.

            self::KEY_ERROR_MESSAGE => $value !== null ? (string) $value : null,

            default => $value,
        };
    }

    /*
    |----------------------
    | INDIVIDUAL CAST HELPERS
    |----------------------
    */

    /**
     * Cast the id field.
     *
     * @param mixed $value
     * @return int|null
     */
    private function cast_id( mixed $value ): ?int {
        if ( $value === null ) {
            return null;
        }

        $id = (int) $value;

        if ( $id < 1 ) {
            throw new InvalidArgumentException(
                'JobDTO: "id" must be a positive integer when provided.'
            );
        }

        return $id;
    }

    /**
     * Cast and validate the job_class field.
     *
     * Ensures the class exists and implements JobHandlerInterface
     * so workers never receive an unresolvable envelope.
     *
     * @param mixed $value
     * @return string
     * @throws InvalidArgumentException
     */
    private function cast_job_class( mixed $value ): string {
        $class = trim( (string) $value );

        if ( $class === '' ) {
            throw new InvalidArgumentException(
                'JobDTO: "job_class" must be a non-empty string.'
            );
        }

        if ( ! class_exists( $class ) ) {
            throw new InvalidArgumentException(
                sprintf( 'JobDTO: job class "%s" does not exist.', $class )
            );
        }

        if ( ! is_a( $class, JobHandlerInterface::class, true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'JobDTO: "%s" must implement %s.',
                    $class,
                    JobHandlerInterface::class
                )
            );
        }

        return $class;
    }

    /**
     * Cast and validate the queue field.
     *
     * @param mixed $value
     * @return string
     * @throws InvalidArgumentException
     */
    private function cast_queue( mixed $value ): string {
        $queue = strtolower( trim( (string) $value ) );

        if ( ! in_array( $queue, self::VALID_QUEUES, true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'JobDTO: "%s" is not a valid queue. Allowed: %s.',
                    $queue,
                    implode( ', ', self::VALID_QUEUES )
                )
            );
        }

        return $queue;
    }

    /**
     * Cast and clamp the priority field to 1–10.
     *
     * @param mixed $value
     * @return int
     */
    private function cast_priority( mixed $value ): int {
        return min( 10, max( 1, (int) $value ) );
    }

    /**
     * Cast and validate the status field.
     *
     * @param mixed $value
     * @return string
     * @throws InvalidArgumentException
     */
    private function cast_status( mixed $value ): string {
        $status = strtolower( trim( (string) $value ) );

        if ( ! in_array( $status, self::VALID_STATUSES, true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'JobDTO: "%s" is not a valid status. Allowed: %s.',
                    $status,
                    implode( ', ', self::VALID_STATUSES )
                )
            );
        }

        return $status;
    }

    /**
     * Cast the payload field.
     *
     * Only enforces that the value is an array — contents are the
     * handler's responsibility.
     *
     * @param mixed $value
     * @return array
     * @throws InvalidArgumentException
     */
    private function cast_payload( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'JobDTO: "payload" must be an array, %s given.',
                    get_debug_type( $value )
                )
            );
        }

        return $value;
    }

    /**
     * Cast an attempt-count field (attempts / max_attempts).
     *
     * @param string $key
     * @param mixed  $value
     * @return int
     * @throws InvalidArgumentException
     */
    private function cast_attempt_count( string $key, mixed $value ): int {
        $count = (int) $value;
        $min   = self::KEY_MAX_ATTEMPTS === $key ? 1 : 0;

        if ( $count < $min ) {
            throw new InvalidArgumentException(
                sprintf( 'JobDTO: "%s" must be at least %d.', $key, $min )
            );
        }

        return $count;
    }

    /**
     * Coerce a datetime field to DateTimeImmutable.
     *
     * Accepts a DateTimeImmutable (pass-through), a datetime string,
     * or a Unix timestamp integer.
     *
     * @param string $key
     * @param mixed  $value
     * @return DateTimeImmutable
     * @throws InvalidArgumentException On unparseable input.
     */
    private function cast_datetime( string $key, mixed $value ): DateTimeImmutable {
        if ( $value instanceof DateTimeImmutable ) {
            return $value;
        }

        if ( is_int( $value ) ) {
            return ( new DateTimeImmutable() )->setTimestamp( $value );
        }

        if ( is_string( $value ) && $value !== '' ) {
            $dt = DateTimeImmutable::createFromFormat( 'Y-m-d H:i:s', $value )
                ?: new DateTimeImmutable( $value );

            if ( $dt === false ) {
                throw new InvalidArgumentException(
                    sprintf( 'JobDTO: cannot parse "%s" as a datetime for field "%s".', $value, $key )
                );
            }

            return $dt;
        }

        // Fallback — use now.
        return new DateTimeImmutable();
    }

    /*
    |----------------------
    | DEFAULTS
    |----------------------
    */

    /**
     * Return default values for all optional fields.
     *
     * Merged with incoming data in the constructor so that every
     * field has a value before cast() is called, and callers only
     * need to supply what they care about.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array {
        return [
            self::KEY_ID            => null,
            self::KEY_QUEUE         => self::QUEUE_DEFAULT,
            self::KEY_PRIORITY      => 5,
            self::KEY_STATUS        => self::STATUS_PENDING,
            self::KEY_PAYLOAD       => [],
            self::KEY_ATTEMPTS      => 0,
            self::KEY_MAX_ATTEMPTS  => 3,
            self::KEY_AVAILABLE_AT  => new DateTimeImmutable(),
            self::KEY_CREATED_AT    => new DateTimeImmutable(),
            self::KEY_STARTED_AT    => null,
            self::KEY_COMPLETED_AT  => null,
            self::KEY_RESULT        => null,
            self::KEY_ERROR_MESSAGE => null,
        ];
    }

    /*
    |----------------------
    | CONVENIENCE HELPERS
    |----------------------
    */

    /**
     * Whether the job is in a pending or retrying state and
     * has not yet exceeded its maximum attempt allowance.
     *
     * @return bool
     */
    public function is_dispatchable(): bool {
        return in_array( $this->props[ self::KEY_STATUS ], [ self::STATUS_PENDING, self::STATUS_RETRYING ], true )
            && $this->props[ self::KEY_ATTEMPTS ] < $this->props[ self::KEY_MAX_ATTEMPTS ];
    }

    /**
     * Whether the job has exhausted all allowed attempts.
     *
     * @return bool
     */
    public function has_exceeded_max_attempts(): bool {
        return $this->props[ self::KEY_ATTEMPTS ] >= $this->props[ self::KEY_MAX_ATTEMPTS ];
    }

    /**
     * Whether the job is available to be picked up right now.
     *
     * @return bool
     */
    public function is_available(): bool {
        return $this->props[ self::KEY_AVAILABLE_AT ] <= new DateTimeImmutable();
    }

    /**
     * Return the handler class resolved from job_class.
     *
     * @return JobHandlerInterface
     */
    public function resolve_handler(): JobHandlerInterface {
        $class = $this->props[ self::KEY_JOB_CLASS ];
        return new $class();
    }

    /*
    |----------------------
    | SERIALIZATION
    |----------------------
    */

    /**
     * {@inheritdoc}
     *
     * DateTimeImmutable fields are serialised to ISO 8601 strings
     * so the storage adapter can persist them without extra mapping.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): mixed {
        $data = $this->props;

        foreach ( [ self::KEY_AVAILABLE_AT, self::KEY_CREATED_AT, self::KEY_STARTED_AT, self::KEY_COMPLETED_AT ] as $key ) {
            if ( $data[ $key ] instanceof DateTimeImmutable ) {
                $data[ $key ] = $data[ $key ]->format( 'Y-m-d H:i:s' );
            }
        }

        return $data;
    }

    /**
     * Return all properties as a plain array, with datetime fields
     * serialised to strings suitable for database persistence.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return $this->jsonSerialize();
    }
}