<?php
/**
 * Event dispatcher class file.
 *
 * The central event bus for SmartLicenseServer. Listeners are registered
 * against event class names and called in priority order when the
 * corresponding event is dispatched.
 *
 * Works identically in WordPress and CLI environments — it is a
 * self-contained PHP dispatcher with no dependency on WP hooks.
 *
 * ## Quick reference
 *
 *   // Get the dispatcher
 *   $events = smliser_events();
 *
 *   // Register a class-based listener
 *   $events->listen( LicenseCreated::class, SendWelcomeEmail::class );
 *
 *   // Register a closure listener
 *   $events->listen( LicenseCreated::class, function( LicenseCreated $event ) {
 *       // ...
 *   });
 *
 *   // Register a subscriber (groups multiple event→method mappings)
 *   $events->subscribe( LicenseEventSubscriber::class );
 *
 *   // Dispatch an event
 *   smliser_dispatch( new LicenseCreated( $license_id, $product_slug ) );
 *
 *   // Dispatch asynchronously (pushed to the background job queue)
 *   smliser_dispatch_async( new LicenseCreated( $license_id, $product_slug ) );
 *
 *   // Remove a listener
 *   $events->forget( LicenseCreated::class, SendWelcomeEmail::class );
 *
 *   // Check if any listeners are registered
 *   $events->has_listeners( LicenseCreated::class );
 *
 * ## Listener priority
 *
 *   Lower numbers run first. Default is 10. Negative values are allowed.
 *
 *   $events->listen( OrderPlaced::class, AuditLog::class, priority: 1 );
 *   $events->listen( OrderPlaced::class, SendEmail::class, priority: 20 );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events;

use Closure;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Central event bus — singleton dispatcher.
 */
class EventDispatcher {

    /*
    |--------------------------------------------
    | SINGLETON
    |--------------------------------------------
    */

    /**
     * @var static|null
     */
    private static ?self $instance = null;

    /**
     * @return static
     */
    public static function instance(): static {
        if ( static::$instance === null ) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    private function __construct() {}

    /*
    |--------------------------------------------
    | REGISTRY
    |--------------------------------------------
    */

    /**
     * Registered listeners keyed by event class name.
     *
     * Shape: [ event_class => [ priority => [ listener, ... ] ] ]
     *
     * @var array<string, array<int, array<int, class-string<ListenerInterface>|Closure>>>
     */
    private array $listeners = [];

    /**
     * Sorted listener cache — rebuilt whenever the registry changes.
     *
     * @var array<string, array<int, class-string<ListenerInterface>|Closure>>
     */
    private array $sorted = [];

    /*
    |--------------------------------------------
    | REGISTRATION
    |--------------------------------------------
    */

    /**
     * Register a listener for an event.
     *
     * @param  class-string<AbstractEvent>              $event    Fully-qualified event class name.
     * @param  class-string<ListenerInterface>|Closure  $listener Class name or closure.
     * @param  int                                      $priority Lower runs first. Default 10.
     * @return static Fluent.
     * @throws InvalidArgumentException If the listener class does not implement ListenerInterface.
     */
    public function listen( string $event, string|Closure $listener, int $priority = 10 ): static {
        if ( is_string( $listener ) ) {
            $this->assert_listener_class( $listener );
        }

        $this->listeners[ $event ][ $priority ][] = $listener;

        // Invalidate the sorted cache for this event.
        unset( $this->sorted[ $event ] );

        return $this;
    }

    /**
     * Register a subscriber — a class that declares its own event→method map.
     *
     * @param  class-string<SubscriberInterface> $subscriber
     * @return static Fluent.
     * @throws InvalidArgumentException If the class does not implement SubscriberInterface.
     */
    public function subscribe( string $subscriber ): static {
        if ( ! class_exists( $subscriber ) ) {
            throw new InvalidArgumentException(
                sprintf( 'EventDispatcher: subscriber class "%s" does not exist.', $subscriber )
            );
        }

        if ( ! in_array( SubscriberInterface::class, class_implements( $subscriber ) ?: [], true ) ) {
            throw new InvalidArgumentException(
                sprintf( 'EventDispatcher: "%s" must implement SubscriberInterface.', $subscriber )
            );
        }

        $instance = new $subscriber();

        foreach ( $subscriber::subscribed_events() as $event => $spec ) {
            [ $method, $priority ] = is_array( $spec ) ? $spec : [ $spec, 10 ];

            $this->listen(
                $event,
                Closure::fromCallable( [ $instance, $method ] ),
                $priority
            );
        }

        return $this;
    }

    /**
     * Remove a specific listener from an event.
     *
     * @param  string                                   $event
     * @param  class-string<ListenerInterface>|Closure  $listener
     * @return bool True if a listener was removed, false if not found.
     */
    public function forget( string $event, string|Closure $listener ): bool {
        if ( ! isset( $this->listeners[ $event ] ) ) {
            return false;
        }

        $removed = false;

        foreach ( $this->listeners[ $event ] as $priority => &$bucket ) {
            foreach ( $bucket as $i => $registered ) {
                if ( $registered === $listener ) {
                    unset( $bucket[ $i ] );
                    $removed = true;
                }
            }

            if ( empty( $bucket ) ) {
                unset( $this->listeners[ $event ][ $priority ] );
            }
        }

        if ( $removed ) {
            unset( $this->sorted[ $event ] );
        }

        return $removed;
    }

    /**
     * Remove all listeners for an event, or all listeners entirely.
     *
     * @param  string|null $event Event class name, or null to flush everything.
     * @return void
     */
    public function flush( ?string $event = null ): void {
        if ( $event === null ) {
            $this->listeners = [];
            $this->sorted    = [];
            return;
        }

        unset( $this->listeners[ $event ], $this->sorted[ $event ] );
    }

    /**
     * Whether any listeners are registered for the given event.
     *
     * @param  string $event
     * @return bool
     */
    public function has_listeners( string $event ): bool {
        return ! empty( $this->listeners[ $event ] );
    }

    /**
     * Return all listeners registered for an event, in priority order.
     *
     * @param  string $event
     * @return array<int, class-string<ListenerInterface>|Closure>
     */
    public function listeners_for( string $event ): array {
        return $this->sorted_listeners( $event );
    }

    /*
    |--------------------------------------------
    | DISPATCH
    |--------------------------------------------
    */

    /**
     * Dispatch an event synchronously.
     *
     * Calls each registered listener in priority order. If a listener
     * calls $event->stop_propagation(), remaining listeners are skipped.
     *
     * Any Throwable thrown by a listener is re-thrown after the event
     * name is added to the message for easier debugging.
     *
     * @param  AbstractEvent $event
     * @return AbstractEvent The same event, possibly with propagation stopped.
     * @throws RuntimeException Wrapping any listener exception.
     */
    public function dispatch( AbstractEvent $event ): AbstractEvent {
        foreach ( $this->sorted_listeners( $event->name() ) as $listener ) {
            if ( $event->is_propagation_stopped() ) {
                break;
            }

            try {
                $this->call_listener( $listener, $event );
            } catch ( Throwable $e ) {
                throw new RuntimeException(
                    sprintf(
                        'EventDispatcher: listener threw an exception for event "%s": %s',
                        $event->name(),
                        $e->getMessage()
                    ),
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return $event;
    }

    /**
     * Dispatch an event asynchronously by pushing it to the job queue.
     *
     * The event is serialised and wrapped in an AsyncEventJob. Registered
     * listeners will be called when the queue worker processes the job —
     * not immediately. Use this for expensive side-effects (emails, webhooks,
     * analytics writes) that should not block the current request.
     *
     * @param  AbstractEvent $event
     * @return void
     */
    public function dispatch_async( AbstractEvent $event ): void {
        smliser_job_queue()->push( new Jobs\AsyncEventJob( $event ) );
    }

    /*
    |--------------------------------------------
    | PRIVATE HELPERS
    |--------------------------------------------
    */

    /**
     * Return listeners for an event sorted by priority, rebuilding the
     * cache if the registry has changed since the last sort.
     *
     * @param  string $event
     * @return array<int, class-string<ListenerInterface>|Closure>
     */
    private function sorted_listeners( string $event ): array {
        if ( isset( $this->sorted[ $event ] ) ) {
            return $this->sorted[ $event ];
        }

        $buckets = $this->listeners[ $event ] ?? [];

        if ( empty( $buckets ) ) {
            return $this->sorted[ $event ] = [];
        }

        ksort( $buckets ); // sort priority buckets ascending (lower = first)

        $this->sorted[ $event ] = array_merge( ...array_values( $buckets ) );

        return $this->sorted[ $event ];
    }

    /**
     * Invoke a single listener — class name or closure — with the event.
     *
     * @param  class-string<ListenerInterface>|Closure $listener
     * @param  AbstractEvent                           $event
     * @return void
     */
    private function call_listener( string|Closure $listener, AbstractEvent $event ): void {
        if ( $listener instanceof Closure ) {
            $listener( $event );
            return;
        }

        ( new $listener() )->handle( $event );
    }

    /**
     * Assert that a class-string implements ListenerInterface.
     *
     * @param  string $class
     * @throws InvalidArgumentException
     */
    private function assert_listener_class( string $class ): void {
        if ( ! class_exists( $class ) ) {
            throw new InvalidArgumentException(
                sprintf( 'EventDispatcher: listener class "%s" does not exist.', $class )
            );
        }

        if ( ! in_array( ListenerInterface::class, class_implements( $class ) ?: [], true ) ) {
            throw new InvalidArgumentException(
                sprintf(
                    'EventDispatcher: "%s" must implement %s.',
                    $class,
                    ListenerInterface::class
                )
            );
        }
    }
}