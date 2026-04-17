<?php
/**
 * Event system global helper functions.
 *
 * Provides the public-facing smliser_* API for the event system,
 * consistent with the rest of the plugin's helper conventions.
 *
 * ## Usage
 *
 *   // Access the dispatcher
 *   smliser_events()->listen( LicenseCreated::class, SendLicenseEmail::class );
 *
 *   // Dispatch synchronously
 *   smliser_dispatch( new LicenseCreated( $id, $slug ) );
 *
 *   // Dispatch asynchronously (to the background job queue)
 *   smliser_dispatch_async( new LicenseCreated( $id, $slug ) );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

defined( 'SMLISER_ABSPATH' ) || exit;

use SmartLicenseServer\Events\AbstractEvent;
use SmartLicenseServer\Events\EventDispatcher;

if ( ! function_exists( 'smliser_events' ) ) {
    /**
     * Return the EventDispatcher singleton.
     *
     * Use this to register listeners at runtime, inspect registered
     * listeners, or dispatch events directly.
     *
     * @return EventDispatcher
     */
    function smliser_events(): EventDispatcher {
        return EventDispatcher::instance();
    }
}

if ( ! function_exists( 'smliser_dispatch' ) ) {
    /**
     * Dispatch an event synchronously.
     *
     * Calls all registered listeners in priority order within the
     * current request/process. Returns the event after dispatch so
     * callers can inspect whether propagation was stopped.
     *
     * @param  AbstractEvent $event
     * @return AbstractEvent
     */
    function smliser_dispatch( AbstractEvent $event ): AbstractEvent {
        return EventDispatcher::instance()->dispatch( $event );
    }
}

if ( ! function_exists( 'smliser_dispatch_async' ) ) {
    /**
     * Dispatch an event asynchronously via the background job queue.
     *
     * The event is serialised and pushed onto the queue. Listeners
     * will be called when the worker next processes jobs — not
     * immediately. Use this for side-effects that should not block
     * the current request (emails, webhooks, analytics).
     *
     * The event class must implement a static from_array() factory
     * to support re-hydration in the worker process.
     *
     * @param  AbstractEvent $event
     * @return void
     */
    function smliser_dispatch_async( AbstractEvent $event ): void {
        EventDispatcher::instance()->dispatch_async( $event );
    }
}