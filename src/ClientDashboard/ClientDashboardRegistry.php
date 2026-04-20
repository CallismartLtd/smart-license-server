<?php
/**
 * Client Dashboard Registry
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\ClientDashboard\TemplateHandlers\Overview;
use SmartLicenseServer\Contracts\AbstractDashboardRegistry;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Dashboard menu and content callback registry.
 * 
 * 
 * Extends AbstractDashboardRegistry for the client-facing dashboard.
 * Boots with no hardcoded defaults — all menus are registered by the
 * application at bootstrap time.
 *
 * Enforces that every registered handler implements DashboardHandlerInterface.
 * 
 * @example to register a menu in the client dashboard:
 * ```php
 *     clientDashboardRegistry()->register( 'licenses', [
 *          'title'   => 'License',
 *          'slug'    => 'licenses',
 *          'handler' => [LicenseHandler::class, 'handle'],
 *          'icon'    => 'ti ti-creative-commons-nd',
 *      ] );
 * 
 *
 */
class ClientDashboardRegistry extends AbstractDashboardRegistry {
    /*
    |-------------
    | BOOTSTRAP
    |-------------
    */

    /**
     * No hardcoded defaults — client menus are fully application-defined.
     *
     * {@inheritDoc}
     */
    protected function boot() : void {
        if ( $this->booted ) {
            return;
        }

        $this->menu = [
            'overview' => [
                'title'   => 'Overview',
                'slug'    => 'overview',
                'handler' => [ Overview::class, 'handle' ],
                'icon'    => 'ti ti-home',
            ],

        ];

        $this->booted = true;
    }

    /*
    |-----------
    | REGISTER
    |-----------
    */

    /**
     * {@inheritDoc}
     *
     * Overrides parent to additionally enforce that the handler
     * implements DashboardHandlerInterface.
     *
     * @throws EnvironmentBootstrapException
     */
    public function register( string $key, array $data, ?int $position = null ) : void {
        $this->assert_handler_contract( $data['handler'] ?? null );
        parent::register( $key, $data, $position );
    }

    /*
    |----------
    | HANDLERS
    |----------
    */

    /**
     * Resolve and return the handler instance for a given slug.
     *
     * @param string $slug
     * @return DashboardHandlerInterface|null
     */
    public function get_handler( string $slug ) : ?DashboardHandlerInterface {
        $item = $this->get( $slug );

        if ( null === $item ) {
            return null;
        }

        $handler = $item['handler'];

        // Handler may be a class-string or a [class, method] callable.
        // For the dashboard, handlers are always instantiable classes.
        $class = is_array( $handler ) ? $handler[0] : $handler;

        if ( is_string( $class ) && class_exists( $class ) ) {
            return new $class;
        }

        return null;
    }

    /**
     * Return all registered handlers as slug => handler instance map.
     *
     * @return array<string, DashboardHandlerInterface>
     */
    public function get_handlers() : array {
        $handlers = [];

        foreach ( $this->all() as $key => $item ) {
            $handler = $this->get_handler( $key );

            if ( $handler instanceof DashboardHandlerInterface ) {
                $handlers[ $key ] = $handler;
            }
        }

        return $handlers;
    }

    /*
    |----------------
    | PRIVATE HELPERS
    |----------------
    */

    /**
     * Assert that a handler implements DashboardHandlerInterface.
     *
     * @param mixed $handler
     * @throws EnvironmentBootstrapException
     */
    private function assert_handler_contract( mixed $handler ) : void {
        $class = null;

        if ( is_string( $handler ) ) {
            $class = $handler;
        } elseif ( is_array( $handler ) ) {
            $class = $handler[0] ?? null;
        }

        if ( ! $class || ! is_string( $class ) ) {
            throw new EnvironmentBootstrapException(
                'dashboard_error',
                'Dashboard handler must be a class string or [class, method] array.'
            );
        }

        if ( ! class_exists( $class ) ) {
            throw new EnvironmentBootstrapException(
                'dashboard_error',
                sprintf( 'Dashboard handler class "%s" does not exist.', $class )
            );
        }

        if ( ! in_array( DashboardHandlerInterface::class, class_implements( $class ) ?: [], true ) ) {
            throw new EnvironmentBootstrapException(
                'dashboard_error',
                sprintf(
                    'Dashboard handler "%s" must implement %s.',
                    $class,
                    DashboardHandlerInterface::class
                )
            );
        }
    }

    public function is_root_menu(string $key): bool {
        return false;
    }
}