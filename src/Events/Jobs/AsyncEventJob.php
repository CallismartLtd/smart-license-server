<?php
/**
 * Async event job class file.
 *
 * Wraps an AbstractEvent so it can be pushed onto the background job
 * queue and dispatched synchronously when the worker processes it.
 *
 * This is the bridge between the event bus and the existing
 * SmartLicenseServer background job system. The job serialises the
 * event payload into its JobDTO, then re-hydrates and re-dispatches
 * the event inside the worker process.
 *
 * You do not instantiate this class directly. Use:
 *
 *   smliser_dispatch_async( new LicenseCreated( $id, $slug ) );
 *
 * which delegates to EventDispatcher::dispatch_async().
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events\Jobs
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events\Jobs;

use SmartLicenseServer\Background\Jobs\JobHandlerInterface;
use SmartLicenseServer\Background\Queue\JobDTO;
use SmartLicenseServer\Events\AbstractEvent;
use SmartLicenseServer\Events\EventDispatcher;
use RuntimeException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Background job handler that re-dispatches a serialised event.
 */
class AsyncEventJob implements JobHandlerInterface {

    /*
    |--------------------------------------------
    | JOB IDENTITY
    |--------------------------------------------
    */

    /**
     * {@inheritdoc}
     */
    public static function get_job_name(): string {
        return 'async_event';
    }

    /**
     * {@inheritdoc}
     */
    public static function get_job_description(): string {
        return 'Dispatches an event asynchronously in the background.';
    }

    /*
    |--------------------------------------------
    | CONSTRUCTION
    |--------------------------------------------
    */

    /**
     * The event to dispatch asynchronously.
     *
     * Stored so build_dto() can serialise it immediately.
     *
     * @var AbstractEvent|null
     */
    private ?AbstractEvent $event;

    /**
     * @param AbstractEvent|null $event Pass the event when enqueueing; omit when the
     *                                  worker instantiates the handler for execution.
     */
    public function __construct( ?AbstractEvent $event = null ) {
        $this->event = $event;
    }

    /*
    |--------------------------------------------
    | ENQUEUE
    |--------------------------------------------
    */

    /**
     * Build the JobDTO that will be stored in the queue.
     *
     * Serialises the event class name and its array representation so
     * the worker can reconstruct and re-dispatch it.
     *
     * @return JobDTO
     * @throws RuntimeException If called without an event.
     */
    public function build_dto(): JobDTO {
        if ( $this->event === null ) {
            throw new RuntimeException( 'AsyncEventJob::build_dto() called without an event.' );
        }

        return new JobDTO([
            JobDTO::KEY_ID          => static::get_job_name(),
            JobDTO::KEY_JOB_CLASS   =>$this->event->name(),
            JobDTO::KEY_PAYLOAD     => $this->event->to_array(),
            JobDTO::KEY_PRIORITY    => 5
        ]);
    }

    /*
    |--------------------------------------------
    | EXECUTE
    |--------------------------------------------
    */

    /**
     * Re-hydrate the event and dispatch it synchronously inside the worker.
     *
     * The event class must implement a static from_array() factory method
     * (or the subclass can override this method for custom hydration).
     *
     * @param  array $payload The job payload, as stored on the JobDTO.
     * @return void
     * @throws RuntimeException If the event class is missing or not hydratable.
     */
    public function handle( array $payload ): mixed {
        $event_class = $payload['event_class'] ?? null;
        $event_data  = $payload['event_data']  ?? [];

        if ( empty( $event_class ) || ! class_exists( $event_class ) ) {
            throw new RuntimeException(
                sprintf( 'AsyncEventJob: event class "%s" not found.', $event_class )
            );
        }

        if ( ! method_exists( $event_class, 'from_array' ) ) {
            throw new RuntimeException(
                sprintf(
                    'AsyncEventJob: "%s" must implement a static from_array( array $data ) factory to support async dispatch.',
                    $event_class
                )
            );
        }

        $event = $event_class::from_array( $event_data );

        return EventDispatcher::instance()->dispatch( $event );
    }
}