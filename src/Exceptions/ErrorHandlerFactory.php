<?php
/**
 * Error Handler Factory for Smart License Server.
 *
 * Manages global configuration and provides environment-aware factory methods.
 * Automatically detects CLI vs HTTP environments and creates appropriate handlers.
 * Supports manual environment specification for testing and special cases.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class ErrorHandlerFactory {

    /**
     * Detected or configured environment.
     *
     * @var string 'http', 'cli', or 'auto'.
     */
    private static string $environment = 'auto';

    /**
     * Global debug mode.
     *
     * @var bool
     */
    private static bool $debug_mode = false;

    /**
     * Global log handler callback.
     *
     * @var \Closure|null
     */
    private static ?\Closure $log_handler = null;

    /**
     * Cache for detected SAPI.
     *
     * @var string|null
     */
    private static ?string $detected_sapi = null;

    /**
     * Configure the error handler factory globally.
     *
     * Allows setting debug mode, logger, and environment detection strategy.
     *
     * Usage:
     *   ErrorHandlerFactory::configure([
     *       'debug' => true,
     *       'environment' => 'auto',  // 'auto', 'http', 'cli'
     *       'logger' => function(array $data): void { ... }
     *   ]);
     *
     * @param array{
     *     debug?: bool,
     *     environment?: 'auto'|'http'|'cli',
     *     logger?: \Closure
     * } $config Configuration array.
     * @return void
     */
    public static function configure( array $config = [] ) : void {

        // Resolve debug (fallback to current or default)
        $debug = array_key_exists( 'debug', $config ) ? (bool) $config['debug'] : self::$debug_mode;

        // Resolve environment (fallback to current).
        $environment = self::$environment;

        if ( array_key_exists( 'environment', $config ) ) {
            $env = strtolower( (string) $config['environment'] );

            if ( in_array($env, ['auto', 'http', 'cli'], true) ) {
                $environment = $env;
            }
        }

        // Resolve logger (fallback to current).
        $logger = self::$log_handler;

        if ( array_key_exists('logger', $config) ) {
            $logger = $config['logger'] instanceof \Closure
                ? $config['logger']
                : null;
        }

        self::$debug_mode  = $debug;
        self::$environment = $environment;
        self::$log_handler = $logger;

        AbstractErrorHandler::configure( $debug, $logger );
    }

    /**
     * Detect the current environment.
     *
     * Determines whether running in CLI or HTTP environment by checking PHP_SAPI.
     *
     * @return string 'cli' or 'http'.
     */
    private static function detectEnvironment() : string {
        if ( self::$detected_sapi !== null ) {
            return self::$detected_sapi;
        }

        $sapi = php_sapi_name();
        
        // CLI SAPIs.
        if ( in_array( $sapi, [ 'cli', 'phpdbg', 'cli-server' ], true ) ) {
            self::$detected_sapi = 'cli';
        } else {
            // Default to HTTP.
            self::$detected_sapi = 'http';            
        }


        return self::$detected_sapi;
    }

    /**
     * Determine which handler class to use.
     *
     * If environment is 'auto', detects current SAPI and chooses accordingly.
     * If environment is 'http' or 'cli', uses specified handler.
     *
     * @return string Handler class name (fully qualified).
     */
    private static function getHandlerClass() : string {
        $environment = self::$environment === 'auto' 
            ? self::detectEnvironment() 
            : self::$environment;

        return $environment === 'cli' 
            ? CliErrorHandler::class 
            : HttpErrorHandler::class;
    }

    /**
     * Create an error handler instance.
     *
     * Automatically selects appropriate handler based on environment.
     * Supports manual specification of handler via configuration.
     *
     * Usage:
     *   $error = ErrorHandlerFactory::create('Error message', 'Title', 500);
     *   $error->setExit(false);  // Manual control
     *   $output = $error->render();
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return AbstractErrorHandler Instance of appropriate handler subclass.
     */
    public static function create( $message = '', $title = '', $args = [] ) : AbstractErrorHandler {
        $handler_class = self::getHandlerClass();
        return $handler_class::create( $message, $title, $args );
    }

    /**
     * Create and immediately display an error.
     *
     * Shortcut for create() followed by display().
     * Automatically exits based on handler's exit configuration.
     *
     * Usage:
     *   ErrorHandlerFactory::abort('Database error', 'DB Error', 500);
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return void
     */
    public static function abort( $message = '', $title = '', $args = [] ) : void {
        static::create( $message, $title, $args )->display();
    }

    /**
     * Register all error handlers.
     *
     * Configures PHP's error, exception, and shutdown handlers.
     * Automatically uses correct handler for current environment.
     *
     * Usage:
     *   ErrorHandlerFactory::configure(['debug' => true]);
     *   ErrorHandlerFactory::registerHandlers();
     *
     * @param bool $handle_errors Whether to register error handler.
     * @param bool $handle_exceptions Whether to register exception handler.
     * @param bool $handle_shutdown Whether to register shutdown handler.
     * @return void
     */
    public static function registerHandlers( bool $handle_errors = true, bool $handle_exceptions = true, bool $handle_shutdown = true ) : void {
        $handler_class = self::getHandlerClass();
        $handler_class::registerHandlers( $handle_errors, $handle_exceptions, $handle_shutdown );
    }

    /**
     * Unregister all error handlers.
     *
     * Restores PHP's default error handling behavior.
     *
     * Usage:
     *   ErrorHandlerFactory::unregisterHandlers();
     *
     * @return void
     */
    public static function unregisterHandlers() : void {
        HttpErrorHandler::unregisterHandlers();
        CliErrorHandler::unregisterHandlers();
    }

    /**
     * Get current environment setting.
     *
     * Returns the configured or auto-detected environment.
     *
     * @return string 'auto', 'http', or 'cli'.
     */
    public static function getEnvironment() : string {
        return self::$environment;
    }

    /**
     * Get the detected SAPI.
     *
     * Useful for debugging or logging what PHP_SAPI was detected.
     *
     * @return string Current SAPI name.
     */
    public static function getDetectedSapi() : string {
        return self::detectEnvironment();
    }

    /**
     * Get current debug mode.
     *
     * @return bool
     */
    public static function isDebugMode() : bool {
        return self::$debug_mode;
    }

    /**
     * Get the handler class that will be used.
     *
     * Useful for testing and introspection.
     *
     * @return string Fully qualified class name.
     */
    public static function getHandlerClassName() : string {
        return self::getHandlerClass();
    }

    /**
     * Reset factory to defaults.
     *
     * Clears cached SAPI detection and resets environment to 'auto'.
     * Useful for testing.
     *
     * @return void
     */
    public static function reset() : void {
        self::$environment      = 'auto';
        self::$debug_mode       = false;
        self::$log_handler      = null;
        self::$detected_sapi    = null;
    }
}