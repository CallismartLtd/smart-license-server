<?php
/**
 * Global Error Handler for Smart License Server.
 *
 * Manages error handling configuration and display across environments.
 * Uses singleton pattern to avoid SAPI-related static state issues.
 * Automatically detects CLI vs HTTP environments and creates appropriate handlers.
 *
 * Features:
 * - Environment-aware handler selection (HTTP/CLI/auto)
 * - Runtime error reporting configuration
 * - Error display and logging control
 * - Fluent interface for method chaining
 * - SAPI-safe instance-based state management
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

use Throwable;

class GlobalErrorHandler {

    /**
     * Singleton instance.
     *
     * @var static|null
     */
    private static $instance = null;

    /**
     * Detected or configured environment.
     *
     * @var string 'http', 'cli', or 'auto'.
     */
    private string $environment = 'auto';

    /**
     * Debug mode enabled?
     *
     * @var bool
     */
    private bool $debug_mode = false;

    /**
     * Display error enabled?
     *
     * @var bool
     */
    private bool $display_errors = false;

    /**
     * Log handler callback.
     *
     * @var \Closure|null
     */
    private ?\Closure $log_handler = null;

    /**
     * Detected SAPI.
     *
     * @var string|null
     */
    private ?string $detected_sapi = null;

    /**
     * Handler class to use.
     *
     * @var CliErrorHandler|HttpErrorHandler $handler_class
     */
    private AbstractErrorHandler $handler_class;

    /**
     * Private constructor - use instance().
     */
    private function __construct() {}

    /**
     * Get singleton instance.
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
     * Reset singleton to new instance (for testing).
     *
     * @return void
     */
    public static function reset() : void {
        static::$instance = null;
    }

    /**
     * Detect the current environment.
     *
     * Checks PHP_SAPI to determine CLI or HTTP environment.
     *
     * @return string 'cli' or 'http'.
     */
    private function detectEnvironment() : string {
        if ( null !== $this->detected_sapi ) {
            return $this->detected_sapi;
        }

        $sapi = php_sapi_name();
        
        if ( in_array( $sapi, [ 'cli', 'phpdbg', 'cli-server' ], true ) ) {
            $this->detected_sapi = 'cli';
        } else {
            $this->detected_sapi = 'http';
        }

        return $this->detected_sapi;
    }

    /**
     * Determine which handler class to use.
     *
     * @return AbstractErrorHandler
     */
    private function resolveHandlerClass() : AbstractErrorHandler {
        if ( ! isset( $this->handler_class ) ) {
            $environment = $this->environment === 'auto' 
                ? $this->detectEnvironment() 
                : $this->environment;
            
            $handler_class  = $environment === 'cli' ? CliErrorHandler::class : HttpErrorHandler::class; 

            $this->handler_class = new $handler_class;           
        }

        
        return $this->handler_class;
    }

    /**
     * Configure the error handler execution context.
     *
     * This method defines internal handler behavior without touching
     * PHP runtime configuration. It is responsible for:
     *
     * - Selecting execution environment (auto, http, cli)
     * - Enabling or disabling debug mode for handler output
     * - Registering optional logging callbacks
     * - Resolving the appropriate handler implementation class
     *   (e.g. HTTP or CLI specific handler).
     *
     * @param array{
     *     debug?: bool,
     *     environment?: 'auto'|'http'|'cli',
     *     logger?: \Closure
     * } $config Handler context configuration.
     *
     * @return static
     */
    public function bindContext( array $config = [] ) : static {
        if ( array_key_exists( 'debug', $config ) ) {
            $this->debug_mode = (bool) $config['debug'];
        }

        if ( array_key_exists( 'environment', $config ) ) {
            $env = strtolower( (string) $config['environment'] );
            if ( in_array( $env, [ 'auto', 'http', 'cli' ], true ) ) {
                $this->environment = $env;
            }
        }

        if ( array_key_exists( 'logger', $config ) ) {
            $this->log_handler = $config['logger'] instanceof \Closure 
                ? $config['logger'] 
                : null;
        }

        if ( array_key_exists( 'display_errors', $config ) ) {
            $this->display_errors = $config['display_errors'];
        }

        $this->resolveHandlerClass();
        
        $this->handler_class->setDebug( $this->debug_mode );
        $this->handler_class->setDisplayErrors( $this->display_errors );

        if ( $this->log_handler ) {
            $this->handler_class->setLogHandler( $this->log_handler );
        }

        return $this;
    }

    /**
     * Create an error handler instance.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return AbstractErrorHandler
     */
    public function create( $message = '', $title = '', $args = [] ) : AbstractErrorHandler {
        $handler_class = clone $this->handler_class;
        if ( $message instanceof Exception ) {
            $handler_class->setException( $message );
        } else {
            $handler_class->setMessage( $message )
                ->setTitle( $title )
                ->setConfig( $args );
        }

        return $handler_class;
    }

    /**
     * Create and display error and exit.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return void
     */
    public function abort( $message = '', $title = '', $args = [] ) : void {
        $this->create( $message, $title, $args )
            ->setExit( true )
            ->display();
    }

    /**
     * Register global error handlers.
     *
     * @param bool $handle_errors Whether to register error handler.
     * @param bool $handle_exceptions Whether to register exception handler.
     * @param bool $handle_shutdown Whether to register shutdown handler.
     * @return static
     */
    public function registerHandlers( bool $handle_errors = true, bool $handle_exceptions = true, bool $handle_shutdown = true ) : static {
        $this->handler_class->registerHandlers( $handle_errors, $handle_exceptions, $handle_shutdown );
        return $this;
    }

    /**
     * Unregister global error handlers.
     *
     * @return static
     */
    public function unregisterHandlers() : static {
        $this->handler_class->unregisterHandlers();
        return $this;
    }

    /**
     * Get current environment.
     *
     * @return string
     */
    public function getEnvironment() : string {
        return $this->environment;
    }

    /**
     * Get detected SAPI.
     *
     * @return string
     */
    public function getDetectedSapi() : string {
        return $this->detectEnvironment();
    }

    /**
     * Check if debug mode enabled.
     *
     * @return bool
     */
    public function isDebugMode() : bool {
        return $this->debug_mode;
    }

    /**
     * Enable development mode (show all errors).
     *
     * @return static
     */
    public function enableDevelopment() : static {
        return $this->bootstrap([
            'debug'           => true,
            'error_reporting' => E_ALL,
            'display_errors'  => true,
            'log_errors'      => true,
        ]);
    }

    /**
     * Enable production mode (hide errors, log only).
     *
     * @return static
     */
    public function enableProduction() : static {
        return $this->bootstrap([
            'debug'           => false,
            'error_reporting' => E_ALL,
            'display_errors'  => false,
            'log_errors'      => true,
        ]);
    }

    /**
     * Get current error reporting level.
     *
     * @return int
     */
    public function getErrorReporting() : int {
        return error_reporting();
    }

    /**
     * Check if error display is enabled.
     *
     * @return bool
     */
    public function isDisplayErrors() : bool {
        return (bool) ini_get( 'display_errors' );
    }

    /**
     * Get error log file path.
     *
     * @return string|false
     */
    public function getErrorLogPath() {
        return ini_get( 'error_log' ) ?: false;
    }

    /**
     * Bootstrap the global error handling system.
     *
     * This is the authoritative runtime entry point responsible for:
     *
     * - Applying PHP runtime error configuration (error_reporting, ini_set)
     * - Enabling or disabling error display and logging at runtime level
     * - Registering global error, exception, and shutdown handlers
     * - Activating the handler system for the current process
     *
     * This method mutates PHP runtime state and should typically be called
     * once during application bootstrap.
     *
     * @param array{
     *     environment?: 'auto'|'http'|'cli',
     *     debug?: bool,
     *     error_reporting?: int,
     *     display_errors?: bool,
     *     log_errors?: bool,
     *     log_path?: string,
     *     logger?: \Closure
     * } $config Runtime error system configuration.
     *
     * @return static
     */
    public function bootstrap( array $config = [] ) : static {
        $config = array_merge([
            'environment'     => 'auto',
            'debug'           => false,
            'error_reporting' => E_ALL,
            'display_errors'  => false,
            'log_errors'      => true,
            'log_path'        => null,
            'logger'          => null,
        ], $config);

        // Bind error handler context.
        $this->bindContext([
            'debug'             => $config['debug'],
            'environment'       => $config['environment'],
            'logger'            => $config['logger'],
            'display_errors'    => $config['display_errors'],
        ]);

        // ONLY place runtime mutation happens.
        error_reporting( $config['error_reporting'] );

        ini_set( 'display_errors', $config['display_errors'] ? '1' : '0' );
        ini_set( 'display_startup_errors', $config['display_errors'] ? '1' : '0' );

        ini_set( 'log_errors', $config['log_errors'] ? '1' : '0' );

        if ( ! empty( $config['log_path'] ) ) {
            ini_set( 'error_log', $config['log_path'] );
        }

        return $this;
    }

    /**
     * Get current configuration.
     *
     * @return array{
     *     environment: string,
     *     debug: bool,
     *     error_reporting: int,
     *     display_errors: bool,
     *     log_errors: bool,
     *     log_path: string|false,
     *     handler_class: string
     * }
     */
    public function getConfiguration() : array {
        return [
            'environment'       => $this->environment,
            'debug'             => $this->debug_mode,
            'error_reporting'   => $this->getErrorReporting(),
            'display_errors'    => $this->isDisplayErrors(),
            'log_errors'        => (bool) ini_get( 'log_errors' ),
            'log_path'          => $this->getErrorLogPath(),
            'handler_class'     => $this->handler_class,
        ];
    }

    /**
     * Log error.
     * 
     * @param Throwable|string $error
     */
    public function log( Throwable|string $error ) {
        if ( is_string( $error ) ) {
            $error = new Exception( 'unknown_error', $error );
        } elseif ( ! $error instanceof Exception ) {
            $error  = new Exception(
                'uncaught_exception',
                $error->getMessage(),
                [
                    'status'    => 500,
                    'title'     => get_class( $error ),
                    'file'      => $error->getFile(),
                    'line'      => $error->getLine(),
                ]
            );
        }

        $this->handler_class->logError( $error );
    }
}