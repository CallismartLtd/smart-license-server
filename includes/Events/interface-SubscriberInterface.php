<?php
/**
 * Subscriber interface file.
 *
 * A subscriber is a self-describing listener that declares which events
 * it cares about and which of its own methods should handle each one.
 * This lets a single class group several related listeners together
 * without registering them individually.
 *
 * ## Implementing a subscriber
 *
 *   class LicenseEventSubscriber implements SubscriberInterface {
 *
 *       public static function subscribed_events(): array {
 *           return [
 *               LicenseCreated::class   => 'on_created',
 *               LicenseSuspended::class => 'on_suspended',
 *               LicenseExpired::class   => [ 'on_expired', 20 ], // custom priority
 *           ];
 *       }
 *
 *       public function on_created( LicenseCreated $event ): void { ... }
 *       public function on_suspended( LicenseSuspended $event ): void { ... }
 *       public function on_expired( LicenseExpired $event ): void { ... }
 *   }
 *
 * ## Registering
 *
 *   smliser_events()->subscribe( LicenseEventSubscriber::class );
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Contract for self-describing multi-event subscribers.
 */
interface SubscriberInterface {

    /**
     * Return a map of event class names to handler method names.
     *
     * Each value may be:
     *   - A string method name          — uses the default priority (10).
     *   - [string $method, int $priority] — uses the specified priority.
     *
     * @return array<class-string<AbstractEvent>, string|array{string, int}>
     */
    public static function subscribed_events(): array;
}