<?php
/**
 * Abstract Base Error Handler Class for Smart License Server.
 *
 * Provides core error handling functionality independent of output environment.
 * Integrates seamlessly with the Exception class for structured error handling.
 * Respects exception design patterns and provides production-grade error handling.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

abstract class AbstractErrorHandler {

    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $defaults = [
        'response'      => 500,
        'link_url'      => '',
        'link_text'     => '',
        'back_link'     => false,
        'charset'       => 'utf-8',
        'code'          => 'smliser_error',
        'exit'          => true,
        'debug'         => false
    ];

    /**
     * Current configuration.
     *
     * @var array
     */
    protected array $config = [];

    /**
     * Error message.
     *
     * @var string
     */
    protected string $message = '';

    /**
     * Error title.
     *
     * @var string
     */
    protected string $title = '';

    /**
     * Error code.
     *
     * @var string
     */
    protected string $code = '';

    /**
     * Error object (if using exception).
     *
     * @var Exception|null
     */
    protected ?Exception $error_object = null;

    /**
     * Global debug mode configuration.
     *
     * @var bool
     */
    protected static bool $global_debug_mode = false;

    /**
     * Global log handler callback.
     *
     * @var \Closure|null
     */
    protected static ?\Closure $log_handler = null;

    /**
     * Configure error handler globally.
     *
     * @param bool $debug Whether to enable debug mode globally.
     * @param \Closure|null $log_handler Optional callback for logging errors.
     * @return void
     */
    public static function configure( bool $debug = false, ?\Closure $log_handler = null ) : void {
        static::$global_debug_mode  = $debug;
        static::$log_handler        = $log_handler;
    }

    /**
     * Constructor.
     *
     * @param string|Exception|null $message Optional. Error message or exception object.
     * @param string|int $title Optional. Error title or HTTP response code.
     * @param array|int $args Optional. Additional arguments or HTTP response code.
     */
    public function __construct( $message = '', $title = '', $args = [] ) {
        $this->config = $this->defaults;
        $this->initialize( $message, $title, $args );
    }

    /**
     * Initialize error handler with message, title, and arguments.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return self
     */
    private function initialize( $message, $title, $args ) : self {
        // Handle HTTP response code passed as title.
        if ( is_int( $title ) ) {
            $args  = [ 'response' => $title ];
            $title = '';
        } elseif ( is_int( $args ) ) {
            $args = [ 'response' => $args ];
        }

        // Merge configuration.
        if ( is_array( $args ) ) {
            $this->config = array_merge( $this->config, $args );
        }

        // Process exception/error object.
        if ( $this->isErrorObject( $message ) ) {
            $this->error_object = $message;
            $this->processErrorObject( $message );
        }

        // Set message and title.
        $this->message = $this->sanitizeMessage( $message );
        $this->title   = $title ?: $this->title ?: 'Fatal Error';
        $this->code    = $this->config['code'];

        return $this;
    }

    /**
     * Check if value is an error object.
     *
     * @param mixed $value Value to check.
     * @return bool
     */
    private function isErrorObject( $value ) : bool {
        return $value instanceof Exception;
    }

    /**
     * Process error object - RESPECTS EXCEPTION CLASS DESIGN.
     *
     * Extracts HTTP status and title from Exception's structured data.
     * Does not reimplement Exception's rendering logic.
     *
     * @param Exception $error Error object.
     * @return void
     */
    private function processErrorObject( Exception $error ) : void {
        // Get error code from Exception.
        $this->code = $error->get_error_code() ?: $this->config['code'];

        // Extract HTTP status from error data.
        $data = $error->get_error_data();
        
        if ( is_array( $data ) && isset( $data['status'] ) ) {
            $this->config['response'] = (int) $data['status'];
        } elseif ( is_object( $data ) && property_exists( $data, 'status' ) ) {
            $this->config['response'] = (int) $data->status;
        }

        // Extract title from error data or use default.
        $this->title = '';
        if ( is_array( $data ) && isset( $data['title'] ) ) {
            $this->title = $data['title'];
        } elseif ( is_object( $data ) && property_exists( $data, 'title' ) ) {
            $this->title = $data->title;
        }
        $this->title = $this->title ?: 'Application Error';

        // Get first message from Exception.
        $this->message = $error->get_error_message();
    }

    /**
     * Sanitize error message.
     *
     * @param mixed $message Message to sanitize.
     * @return string
     */
    private function sanitizeMessage( $message ) : string {
        if ( is_object( $message ) && method_exists( $message, 'get_error_message' ) ) {
            return $message->get_error_message();
        }

        if ( ! is_string( $message ) ) {
            return '';
        }

        return $message ?: '';
    }

    /**
     * Set error message.
     *
     * @param string $message Error message.
     * @return self
     */
    public function setMessage( string $message ) : self {
        $this->message = $message;
        return $this;
    }

    /**
     * Get error message.
     *
     * @return string
     */
    public function getMessage() : string {
        return $this->message ?: 'An unknown fatal error occurred.';
    }

    /**
     * Set error title.
     *
     * @param string $title Error title.
     * @return self
     */
    public function setTitle( string $title ) : self {
        $this->title = $title;
        return $this;
    }

    /**
     * Get error title.
     *
     * @return string
     */
    public function getTitle() : string {
        return $this->title ?: 'Fatal Error';
    }

    /**
     * Set error code.
     *
     * @param string $code Error code.
     * @return self
     */
    public function setCode( string $code ) : self {
        $this->code = $code;
        $this->config['code'] = $code;
        return $this;
    }

    /**
     * Get error code.
     *
     * @return string
     */
    public function getCode() : string {
        return $this->code ?: $this->config['code'];
    }

    /**
     * Set HTTP response code.
     *
     * @param int $code HTTP response code.
     * @return self
     */
    public function setResponseCode( int $code ) : self {
        $this->config['response'] = $code;
        return $this;
    }

    /**
     * Get HTTP response code.
     *
     * @return int
     */
    public function getResponseCode() : int {
        return (int) $this->config['response'];
    }

    /**
     * Set character encoding.
     *
     * @param string $charset Character encoding.
     * @return self
     */
    public function setCharset( string $charset ) : self {
        $this->config['charset'] = $charset;
        return $this;
    }

    /**
     * Get character encoding.
     *
     * @return string
     */
    public function getCharset() : string {
        return $this->config['charset'];
    }

    /**
     * Enable/disable debug mode for current instance.
     *
     * @param bool $debug Whether to enable debug mode.
     * @return self
     */
    public function setDebug( bool $debug ) : self {
        $this->config['debug'] = $debug;
        return $this;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug() : bool {
        return $this->config['debug'] ?? static::$global_debug_mode;
    }

    /**
     * Enable/disable auto-exit after display.
     *
     * @param bool $exit Whether to exit.
     * @return self
     */
    public function setExit( bool $exit ) : self {
        $this->config['exit'] = $exit;
        return $this;
    }

    /**
     * Check if auto-exit is enabled.
     *
     * @return bool
     */
    public function shouldExit() : bool {
        return $this->config['exit'];
    }

    /**
     * Set back link display.
     *
     * @param bool $show Whether to show back link.
     * @return self
     */
    public function setBackLink( bool $show ) : self {
        $this->config['back_link'] = $show;
        return $this;
    }

    /**
     * Set custom link.
     *
     * @param string $url URL for the link.
     * @param string $text Link text.
     * @return self
     */
    public function setLink( string $url, string $text ) : self {
        $this->config['link_url']  = $url;
        $this->config['link_text'] = $text;
        return $this;
    }

    /**
     * Render error output (environment-specific).
     *
     * Must be implemented by subclasses for their specific environment.
     *
     * @return string
     */
    abstract public function render() : string;

    /**
     * Send output headers (if applicable to environment).
     *
     * HTTP handlers send HTTP headers. CLI handlers are no-op.
     *
     * @return self
     */
    abstract public function sendHeaders() : self;

    /**
     * Display error and optionally exit.
     *
     * @return void
     */
    public function display() : void {
        $this->sendHeaders();
        echo $this->render();

        if ( $this->shouldExit() ) {
            exit;
        }
    }

    /**
     * Static factory method - must be implemented by subclasses.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return static
     */
    abstract public static function create( $message = '', $title = '', $args = [] ) : static;

    /**
     * Create and display error.
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
     * Centralized error logging - RESPECTS EXCEPTION STRUCTURE.
     *
     * Logs using Exception's to_array() method for consistent structured data.
     *
     * @param Exception $exception The exception to log.
     * @return void
     */
    protected static function logError( Exception $exception ) : void {
        if ( static::$log_handler ) {
            ( static::$log_handler )( $exception->to_array() );
        } else {
            error_log( json_encode( $exception->to_array() ) );
        }
    }

    /**
     * Handle fatal errors as callback for set_error_handler().
     *
     * CREATES EXCEPTION OBJECT from error - respects Exception class design.
     *
     * Usage:
     *   set_error_handler( [ HttpErrorHandler::class, 'handleError' ] );
     *
     * @param int $errno Error number.
     * @param string $errstr Error message.
     * @param string $errfile Error file.
     * @param int $errline Error line.
     * @return bool Return true to stop PHP's internal error handler.
     */
    public static function handleError( int $errno, string $errstr, string $errfile, int $errline ) : bool {

        $title = static::getErrorTitle( $errno );

        // Non-fatal errors: log and continue.
        if ( ! static::isFatalError( $errno ) ) {

            $exception = new Exception(
                'non_fatal_' . $errno,
                $errstr,
                [
                    'status' => 500,
                    'title'  => $title,
                    'file'   => $errfile,
                    'line'   => $errline,
                ]
            );

            static::logError( $exception );

            return true;
        }

        // CREATE EXCEPTION FROM ERROR - respects Exception class.
        $exception = new Exception(
            'error_' . $errno,
            $errstr,
            [
                'status' => 500,
                'title' => $title,
                'file' => $errfile,
                'line' => $errline,
            ]
        );

        // Log using Exception's structured data.
        static::logError( $exception );

        // Display using handler.
        static::create( $exception )
            ->setDebug( static::$global_debug_mode )
            ->display();

        return true;
    }

    /**
     * Handle exceptions as callback for set_exception_handler().
     *
     * RESPECTS EXCEPTION CLASS - wraps standard exceptions in Exception class.
     *
     * Usage:
     *   set_exception_handler( [ HttpErrorHandler::class, 'handleException' ] );
     *
     * @param \Throwable $exception The uncaught exception.
     * @return void
     */
    public static function handleException( \Throwable $exception ) : void {
        // Only handle our Exception class specially.
        if ( ! $exception instanceof Exception ) {
            // Wrap standard exceptions in our Exception class for consistency.
            $exception = new Exception(
                'uncaught_exception',
                $exception->getMessage(),
                [
                    'status'    => 500,
                    'title'     => get_class( $exception ),
                    'file'      => $exception->getFile(),
                    'line'      => $exception->getLine(),
                ]
            );
        }

        // Log using Exception's structured data.
        static::logError( $exception );

        // Display using handler.
        static::create( $exception )
            ->setDebug( static::$global_debug_mode )
            ->display();
    }

    /**
     * Handle shutdown errors as callback for register_shutdown_function().
     *
     * CREATES EXCEPTION FROM SHUTDOWN ERROR - respects Exception class design.
     *
     * This catches fatal errors that occur during script execution.
     *
     * Usage:
     *   register_shutdown_function( [ HttpErrorHandler::class, 'handleShutdown' ] );
     *
     * @return void
     */
    public static function handleShutdown() : void {
        $error = error_get_last();

        // Only handle fatal errors.
        if ( ! $error || ! static::isFatalError( $error['type'] ) ) {
            return;
        }

        // Create Exception object from shutdown error - respects Exception class.
        $exception = new Exception(
            'fatal_' . $error['type'],
            $error['message'],
            [
                'status' => 500,
                'title' => static::getErrorTitle( $error['type'] ),
                'file' => $error['file'] ?? 'unknown',
                'line' => $error['line'] ?? 0,
            ]
        );

        // Log error.
        static::logError( $exception );

        // Display, exit on critical errors.
        static::create( $exception )
            ->setDebug( false )
            ->setExit( true )
            ->display();
    }

    /**
     * Register all error handlers.
     *
     * Convenience method to set up error, exception, and shutdown handlers.
     * Configurable with global debug settings.
     *
     * Usage:
     *   HttpErrorHandler::registerHandlers();
     *   // Or with custom configuration:
     *   AbstractErrorHandler::configure( true, $logger_callback );
     *   HttpErrorHandler::registerHandlers();
     *
     * @param bool $handle_errors Whether to register error handler.
     * @param bool $handle_exceptions Whether to register exception handler.
     * @param bool $handle_shutdown Whether to register shutdown handler.
     * @return void
     */
    public static function registerHandlers( bool $handle_errors = true, bool $handle_exceptions = true, bool $handle_shutdown = true ) : void {
        if ( $handle_errors ) {
            set_error_handler( [ static::class, 'handleError' ] );
        }

        if ( $handle_exceptions ) {
            set_exception_handler( [ static::class, 'handleException' ] );
        }

        if ( $handle_shutdown ) {
            register_shutdown_function( [ static::class, 'handleShutdown' ] );
        }
    }

    /**
     * Unregister all error handlers.
     *
     * Usage:
     *   AbstractErrorHandler::unregisterHandlers();
     *
     * @return void
     */
    public static function unregisterHandlers() : void {
        restore_error_handler();
        restore_exception_handler();
    }

    /**
     * Tells whether the error is fatal
     * 
     * @param int $error_type
     * @return bool
     */
    protected static function isFatalError( int $error_type ) : bool {
        static $fatal = [
            E_ERROR,
            E_PARSE,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
        ];

        return in_array( $error_type, $fatal, true );
    }

    /**
     * Get human-readable error title from error type.
     *
     * @param int $error_type
     * @return string
     */
    protected static function getErrorTitle( int $error_type ) : string {
        static $error_types = null;

        if ( $error_types === null ) {
            $error_types = [
                E_ERROR             => 'Fatal Error',
                E_WARNING           => 'Warning',
                E_PARSE             => 'Parse Error',
                E_NOTICE            => 'Notice',
                E_CORE_ERROR        => 'Core Fatal Error',
                E_CORE_WARNING      => 'Core Warning',
                E_COMPILE_ERROR     => 'Compile Error',
                E_COMPILE_WARNING   => 'Compile Warning',
                E_USER_ERROR        => 'User Error',
                E_USER_WARNING      => 'User Warning',
                E_USER_NOTICE       => 'User Notice',
                E_RECOVERABLE_ERROR => 'Recoverable Error',
                E_DEPRECATED        => 'Deprecated',
                E_USER_DEPRECATED   => 'User Deprecated',
            ];

            if ( defined( 'E_STRICT' ) ) {
                /** @disregard PHP 8.4 deprecation */
                $error_types[E_STRICT] = 'Strict Notice';
            }
        }

        return $error_types[ $error_type ] ?? 'Unknown Error';
    }
}