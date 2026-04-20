<?php
/**
 * Listener interface file.
 *
 * Any class that wants to receive events must implement this interface.
 * The dispatcher calls handle() for every event the listener is
 * subscribed to.
 *
 * ## Implementing a listener
 *
 *   class SendLicenseEmail implements ListenerInterface {
 *
 *       public function handle( AbstractEvent $event ): void {
 *           assert( $event instanceof LicenseCreated );
 *           smliser_mailer()->send( ... );
 *       }
 *   }
 *
 * ## Registering
 *
 *   smliser_events()->listen( LicenseCreated::class, SendLicenseEmail::class );
 *
 *   // Or with a closure:
 *   smliser_events()->listen( LicenseCreated::class, function( LicenseCreated $event ) {
 *       // ...
 *   });
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Contract for class-based event listeners.
 */
interface ListenerInterface {

    /**
     * Handle the incoming event.
     *
     * @param  AbstractEvent $event The event instance.
     * @return void
     */
    public function handle( AbstractEvent $event ): void;
}