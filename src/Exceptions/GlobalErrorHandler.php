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
     * Debug mode enabled.
     *
     * @var bool
     */
    private bool $debug_mode = false;

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
     * @var class-string<CliErrorHandler|HttpErrorHandler>
     */
    private string $handler_class = HttpErrorHandler::class;

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
            static::$instance = new static();
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
        if ( $this->detected_sapi !== null ) {
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
     * @return class-string<CliErrorHandler|HttpErrorHandler>
     */
    private function resolveHandlerClass() : string {
        $environment = $this->environment === 'auto' 
            ? $this->detectEnvironment() 
            : $this->environment;

        return $environment === 'cli' ? CliErrorHandler::class : HttpErrorHandler::class;
    }

    /**
     * Configure error handling.
     *
     * @param array{
     *     debug?: bool,
     *     environment?: 'auto'|'http'|'cli',
     *     logger?: \Closure
     * } $config Configuration array.
     * @return static
     */
    public function configure( array $config = [] ) : static {
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

        $this->handler_class = $this->resolveHandlerClass();
        AbstractErrorHandler::configure( $this->debug_mode, $this->log_handler );

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
        return $this->handler_class::create( $message, $title, $args );
    }

    /**
     * Create and display error.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return void
     */
    public static function abort( $message = '', $title = '', $args = [] ) : void {
        static::instance()
            ->create( $message, $title, $args )
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
        $this->handler_class::registerHandlers( $handle_errors, $handle_exceptions, $handle_shutdown );
        return $this;
    }

    /**
     * Unregister global error handlers.
     *
     * @return static
     */
    public function unregisterHandlers() : static {
        $this->handler_class::unregisterHandlers();
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
     * Get handler class name.
     *
     * @return class-string<CliErrorHandler|HttpErrorHandler>
     */
    public function getHandlerClassName() : string {
        return $this->handler_class;
    }

    /**
     * Enable development mode (show all errors).
     *
     * @return static
     */
    public function enableDevelopment() : static {
        $this->configure( [ 'debug' => true ] );

        error_reporting( E_ALL );
        ini_set( 'display_errors', '1' );
        ini_set( 'display_startup_errors', '1' );

        return $this;
    }

    /**
     * Enable production mode (hide errors, log only).
     *
     * @return static
     */
    public function enableProduction() : static {
        $this->configure( [ 'debug' => false ] );

        error_reporting( E_ALL );
        ini_set( 'display_errors', '0' );
        ini_set( 'display_startup_errors', '0' );
        ini_set( 'log_errors', '1' );

        $log_path = ini_get( 'error_log' );
        if ( ! $log_path || '' === $log_path ) {
            $log_dir = sys_get_temp_dir();
            ini_set( 'error_log', $log_dir . '/php_errors.log' );
        }

        return $this;
    }

    /**
     * Set error reporting level.
     *
     * @param int $level Error reporting level (E_* constants).
     * @return static
     */
    public function setErrorReporting( int $level ) : static {
        error_reporting( $level );
        return $this;
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
     * Enable or disable error display.
     *
     * @param bool $display Whether to display errors.
     * @return static
     */
    public function setDisplayErrors( bool $display ) : static {
        ini_set( 'display_errors', $display ? '1' : '0' );
        return $this;
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
     * Configure error logging.
     *
     * @param bool $log Whether to log errors.
     * @param string|null $path Optional log file path.
     * @return static
     */
    public function setLogErrors( bool $log, ?string $path = null ) : static {
        ini_set( 'log_errors', $log ? '1' : '0' );

        if ( $path ) {
            ini_set( 'error_log', $path );
        }

        return $this;
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
     * Setup complete error handling stack.
     *
     * @param array{
     *     environment?: 'auto'|'http'|'cli',
     *     debug?: bool,
     *     error_reporting?: int,
     *     display_errors?: bool,
     *     log_errors?: bool,
     *     log_path?: string,
     *     logger?: \Closure
     * } $config Setup configuration.
     * @return static
     */
    public function setup( array $config = [] ) : static {
        $this->configure([
            'debug'         => $config['debug'] ?? false,
            'environment'   => $config['environment'] ?? 'auto',
            'logger'        => $config['logger'] ?? null,
        ]);

        if ( isset( $config['error_reporting'] ) ) {
            $this->setErrorReporting( (int) $config['error_reporting'] );
        } else {
            $this->setErrorReporting( E_ALL );
        }

        if ( isset( $config['display_errors'] ) ) {
            $this->setDisplayErrors( (bool) $config['display_errors'] );
        }

        if ( isset( $config['log_errors'] ) && $config['log_errors'] ) {
            $this->setLogErrors( true, $config['log_path'] ?? null );
        }

        $this->registerHandlers();

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
}