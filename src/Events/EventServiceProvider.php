<?php
/**
 * Event service provider class file.
 *
 * The single place where all listener and subscriber registrations are
 * declared. Boot this once at application startup — it wires every
 * listener into the EventDispatcher.
 *
 * ## Registering your listeners
 *
 * Add entries to the $listen array for class-based listeners:
 *
 *   protected array $listen = [
 *       LicenseCreated::class => [
 *           SendLicenseEmail::class,
 *           WriteAuditLog::class,
 *       ],
 *       LicenseSuspended::class => [
 *           NotifyOwner::class,
 *       ],
 *   ];
 *
 * Add subscriber class names to $subscribers for grouped registrations:
 *
 *   protected array $subscribers = [
 *       LicenseEventSubscriber::class,
 *   ];
 *
 * ## Booting
 *
 * Called once at application startup (e.g. from Environment::setup() or SetUp):
 *
 *   EventServiceProvider::instance()->boot();
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Events
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Events;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Bootstraps all event listeners and subscribers.
 */
class EventServiceProvider {

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
    | REGISTRATION MAPS
    |--------------------------------------------
    */

    /**
     * Map of event class names to arrays of listener class names.
     *
     * Each listener class must implement ListenerInterface.
     * Priority defaults to 10; override in boot() if needed.
     *
     * @var array<class-string<AbstractEvent>, array<int, class-string<ListenerInterface>>>
     */
    protected array $listen = [
        // LicenseCreated::class => [
        //     SendLicenseEmail::class,
        // ],
    ];

    /**
     * Subscriber classes to register.
     *
     * Each class must implement SubscriberInterface.
     *
     * @var array<int, class-string<SubscriberInterface>>
     */
    protected array $subscribers = [
        // LicenseEventSubscriber::class,
    ];

    /*
    |--------------------------------------------
    | BOOT
    |--------------------------------------------
    */

    /**
     * Register all declared listeners and subscribers with the dispatcher.
     *
     * Idempotent — safe to call multiple times; subsequent calls after
     * the first are no-ops.
     *
     * @return void
     */
    public function boot(): void {
        static $booted = false;

        if ( $booted ) {
            return;
        }

        $booted     = true;
        $dispatcher = EventDispatcher::instance();

        // Register flat listener map.
        foreach ( $this->listen as $event => $listeners ) {
            foreach ( $listeners as $listener ) {
                $dispatcher->listen( $event, $listener );
            }
        }

        // Register subscribers.
        foreach ( $this->subscribers as $subscriber ) {
            $dispatcher->subscribe( $subscriber );
        }

        $this->register( $dispatcher );
    }

    /**
     * Extension point for subclasses to register additional listeners
     * programmatically rather than through the $listen / $subscribers arrays.
     *
     * Called at the end of boot() — always after the declarative maps.
     *
     * @param  EventDispatcher $dispatcher
     * @return void
     */
    protected function register( EventDispatcher $dispatcher ): void {
        // Override in your application service provider subclass.
    }
}