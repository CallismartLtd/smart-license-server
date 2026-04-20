<?php
/**
 * Abstract event class file.
 *
 * All application events extend this class. An event is an immutable
 * value object describing something that happened in the system — it
 * carries context but has no behaviour of its own.
 *
 * ## Defining an event
 *
 *   class LicenseCreated extends AbstractEvent {
 *       public function __construct(
 *           public readonly int    $license_id,
 *           public readonly string $product_slug,
 *           public readonly int    $owner_id,
 *       ) {
 *           parent::__construct();
 *       }
 *   }
 *
 * ## Dispatching
 *
 *   smliser_dispatch( new LicenseCreated( $id, $slug, $owner ) );
 *
 * ## Stopping propagation
 *
 *   // Inside a listener:
 *   $event->stop_propagation();
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Base class for all application events.
 */
abstract class AbstractEvent {

    /*
    |--------------------------------------------
    | STATE
    |--------------------------------------------
    */

    /**
     * Unix timestamp (with microseconds) when the event was created.
     *
     * @var float
     */
    private float $dispatched_at;

    /**
     * Whether propagation has been stopped by a listener.
     *
     * @var bool
     */
    private bool $propagation_stopped = false;

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    /**
     * Subclasses must call parent::__construct() to record the creation time.
     */
    public function __construct() {
        $this->dispatched_at = microtime( true );
    }

    /*
    |--------------------------------------------
    | PROPAGATION
    |--------------------------------------------
    */

    /**
     * Signal to the dispatcher that no further listeners should be called.
     *
     * @return void
     */
    public function stop_propagation(): void {
        $this->propagation_stopped = true;
    }

    /**
     * Whether propagation has been stopped.
     *
     * @return bool
     */
    public function is_propagation_stopped(): bool {
        return $this->propagation_stopped;
    }

    /*
    |--------------------------------------------
    | METADATA
    |--------------------------------------------
    */

    /**
     * The fully-qualified class name of this event, used as its canonical name.
     *
     * @return string
     */
    public function name(): string {
        return static::class;
    }

    /**
     * Unix timestamp (float, microsecond precision) when this event was created.
     *
     * @return float
     */
    public function dispatched_at(): float {
        return $this->dispatched_at;
    }

    /**
     * Serialise the event to an array for logging or async job payloads.
     *
     * Subclasses should override this to include their own public properties.
     * The base implementation captures the common metadata fields.
     *
     * @return array<string, mixed>
     */
    public function to_array(): array {
        return [
            'event'         => $this->name(),
            'dispatched_at' => $this->dispatched_at,
        ];
    }
}