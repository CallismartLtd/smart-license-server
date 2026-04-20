<?php
/**
 * Client dashboard post registry class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\ClientDashboard
 */
declare( strict_types=1 );

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\ClientDashboard\Handlers\AuthController;
use SmartLicenseServer\ClientDashboard\Handlers\ClientSettingsController;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Exceptions\SecurityException;

/**
 * Client Dashboard Post registry class.
 * 
 * Holds allowed post actions from the client dashboard excluding authentication endpoints.
 */
class ClientDashboardPostRegistry {

    /**
     * Allowed actions.
     * 
     * @var array<string, callable> $actions
     */
    protected array $actions = [];

    /**
     * Core actions.
     * 
     * @var array<string, callable> $core_actions
     */
    private array $core_actions = [
        'logout'            => [AuthController::class, 'handle_logout'],
        'user-preference'   => [ClientSettingsController::class, 'set_user_preference']
    ];

    /**
     * Singleton instance.
     * 
     * @var static
     */
    private static $instance = null;

    /**
     * Tracks the boot state
     * 
     * @var bool $booted
     */
    private bool $is_booted = false;

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->boot_core();
    }

    /**
     * Boot all the core dashoard actions.
     * 
     * @return void
     */
    private function boot_core() : void {
        if ( $this->is_booted ) {
            return;
        }

        foreach ( $this->core_actions as $post_name => $callback ) {
            $this->register( $post_name, $callback );
        }

        unset( $this->core_actions );

        $this->is_booted = true;
        
    }

    /**
     * Get class instance.
     * 
     * @return static
     */
    public static function instance() : static {
        if ( null === static::$instance ) {
            static::$instance = new static;
        }

        return static::$instance;
    }

    /**
     * Register a post action.
     * 
     * @param string   $post_name The post name.
     * @param callable $callback  The callback handler for this post.
     * @return static
     */
    public function register( string $post_name, callable $callback ) : static {
        if ( isset( $this->actions[ $post_name ] ) ) {
            throw new SecurityException(
                'duplicate_registry',
                sprintf(
                    'The action name "%1$s" has already been registered. Use %2$s::replace() to override it.',
                    $post_name,
                    static::class
                )
            );
        }

        $this->actions[ $post_name ] = $callback;

        return $this;
    }

    /**
     * Replace an existing action handler.
     *
     * @param string   $post_name
     * @param callable $callback
     * @return static
     */
    public function replace( string $post_name, callable $callback ) : static {
        if ( ! isset( $this->actions[ $post_name ] ) ) {
            throw new SecurityException(
                'missing_registry',
                sprintf(
                    'Cannot replace "%1$s" because it is not registered.',
                    $post_name
                )
            );
        }

        $this->actions[ $post_name ] = $callback;

        return $this;
    }

    /**
     * Check if an action exists.
     *
     * @param string $post_name
     * @return bool
     */
    public function has( string $post_name ) : bool {
        return isset( $this->actions[ $post_name ] );
    }

    /**
     * Get a registered action handler.
     *
     * @param string $post_name
     * @return callable
     */
    public function get( string $post_name ) : callable {
        if ( ! isset( $this->actions[ $post_name ] ) ) {
            throw new SecurityException(
                'invalid_action',
                sprintf(
                    'The action "%1$s" is not registered.',
                    $post_name
                )
            );
        }

        return $this->actions[ $post_name ];
    }

    /**
     * Execute an action.
     *
     * @param string $post_name
     * @param Request $request
     * @return mixed
     */
    public function dispatch( string $post_name, Request $request ) : mixed {
        $handler = $this->get( $post_name );

        return $handler( $request );
    }

    /**
     * Remove a registered action.
     *
     * @param string $post_name
     * @return static
     */
    public function remove( string $post_name ) : static {
        unset( $this->actions[ $post_name ] );

        return $this;
    }

    /**
     * Get all registered actions.
     *
     * @return array<string, callable>
     */
    public function all() : array {
        return $this->actions;
    }
}