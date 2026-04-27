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

use ErrorException;

abstract class AbstractErrorHandler {

    /*
    |------------------------------------------
    | DEFAULT CONFIGURATION
    |------------------------------------------
    */

    /**
     * Default configuration.
     *
     * @var array
     */
    protected array $defaults = [
        'response'          => 500,
        'link_url'          => '',
        'link_text'         => '',
        'back_link'         => false,
        'charset'           => 'utf-8',
        'code'              => 'smliser_error',
        'exit'              => true,
        'debug'             => null,
        'display_errors'    => false
    ];

    /*
    |------------------------------------------
    | INTERNAL STATE
    |------------------------------------------
    */

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
     * Throwable object being handled.
     *
     * @var \Throwable|null
     */
    protected ?\Throwable $error_object = null;

    /**
     * Global log handler callback.
     *
     * @var \Closure|null
     */
    protected ?\Closure $log_handler = null;

    /**
     * Flag indicating fatal error has been handled.
     *
     * Prevents duplicate handling between handleError() and handleShutdown().
     *
     * @var bool
     */
    protected bool $handled = false;

    /*
    |------------------------------------------
    | CONSTRUCTOR & INITIALIZATION
    |------------------------------------------
    */

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
        if ( is_int( $title ) ) {
            $args   = [ 'response' => $title ];
            $title  = '';
        } elseif ( is_int( $args ) ) {
            $args = [ 'response' => $args ];
        }

        if ( is_array( $args ) ) {
            $this->config = array_merge( $this->config, $args );
        }

        if ( $this->isErrorObject( $message ) ) {
            $this->error_object = $message;
        }

        $this->message = $this->sanitizeMessage( $message );
        $this->title   = $title ?: $this->title ?: 'Fatal Error';
        $this->code    = $this->config['code'];

        return $this;
    }

    /**
     * Check if value is a Throwable object.
     *
     * @param mixed $value Value to check.
     * @return bool
     */
    private function isErrorObject( $value ) : bool {
        return $value instanceof \Throwable;
    }

    /**
     * Extract message from Throwable or string.
     *
     * @param mixed $message Message or Throwable.
     * @return string
     */
    private function sanitizeMessage( $message ) : string {
        if ( $message instanceof \Throwable ) {
            return $message->getMessage();
        }
        return is_string( $message ) ? $message : '';
    }

    /*
    |------------------------------------------
    | PUBLIC API - MESSAGE & TITLE
    |------------------------------------------
    */

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

    /*
    |------------------------------------------
    | PUBLIC API - ERROR & RESPONSE CODES
    |------------------------------------------
    */

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

    /*
    |------------------------------------------
    | PUBLIC API - CONFIGURATION
    |------------------------------------------
    */

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
        return $this->config['debug'] ?? false;
    }

    /**
     * Enable/disable display error for current instance.
     *
     * @param bool $display Whether to enable error display.
     * @return self
     */
    public function setDisplayErrors( bool $display ) : self {
        $this->config['display_errors'] = $display;
        return $this;
    }

    /**
     * Check if error display is enabled.
     *
     * @return bool
     */
    public function displaysError() : bool {
        return $this->config['display_errors'] ?? false;
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
     * Set the Throwable object.
     *
     * @param \Throwable $throwable Throwable to handle.
     * @return void
     */
    public function setException( \Throwable $throwable ) : void {
        $this->error_object = $throwable;
    }

    /**
     * Set configuration property.
     *
     * @param array $args Configuration arguments.
     * @return static
     */
    public function setConfig( array $args ) : static {
        $this->config = $this->defaults;
        $this->initialize( $this->message, $this->title, $args );
        return $this;
    }

    /**
     * Set the error log handler.
     *
     * @param \Closure $log_handler Callback for logging errors.
     * @return static
     */
    public function setLogHandler( \Closure $log_handler ) : static {
        $this->log_handler = $log_handler;
        return $this;
    }

    /*
    |------------------------------------------
    | PUBLIC API - LINKS
    |------------------------------------------
    */

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

    /*
    |------------------------------------------
    | ABSTRACT METHODS - ENVIRONMENT SPECIFIC
    |------------------------------------------
    */

    /**
     * Render error output (environment-specific).
     *
     * Must be implemented by subclasses for their specific environment.
     *
     * @return string
     */
    abstract public function render() : string;

    /**
     * Render warning/minor error (lightweight, no full page wrapper).
     *
     * Used for non-fatal errors that should be visible but not interrupt flow.
     * Subclasses implement environment-specific rendering.
     *
     * @return string
     */
    abstract public function renderWarning() : string;

    /**
     * Send output headers (if applicable to environment).
     *
     * HTTP handlers send HTTP headers. CLI handlers are no-op.
     *
     * @return self
     */
    abstract public function sendHeaders() : self;

    /*
    |------------------------------------------
    | PUBLIC API - DISPLAY & LOGGING
    |------------------------------------------
    */

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
     * Display warning without full page wrapper.
     *
     * Used for minor errors in debug or display_errors mode.
     * Does not exit, allows script to continue.
     *
     * @return void
     */
    public function displayWarning() : void {
        echo $this->renderWarning();
    }

    /**
     * Log error - human and machine-readable format.
     *
     * Outputs structured data: timestamp, class, message, file, line, trace (debug only).
     * Parses correctly by both humans and log aggregators.
     *
     * @param \Throwable $throwable Throwable to log.
     * @return void
     */
    public function logError( \Throwable $throwable ) : void {
        $errline    = $throwable->getLine();
        $log_data = [
            'timestamp'  => date( 'Y-m-d H:i:s' ),
            'type'       => get_class( $throwable ),
            'message'    => $throwable->getMessage(),
            'file'       => $throwable->getFile() . ' (' . $errline . ')',
            'line'       => $errline,
        ];

        if ( $this->isDebug() ) {
            $log_data['trace'] = $throwable->getTraceAsString();
        }

        if ( $this->log_handler ) {
            ( $this->log_handler )( $log_data );
        } else {
            $json = json_encode( $log_data, \JSON_UNESCAPED_SLASHES | \JSON_PARTIAL_OUTPUT_ON_ERROR );
            error_log( $json );
        }
    }

    /*
    |------------------------------------------
    | ERROR HANDLER CALLBACKS
    |------------------------------------------
    */

    /**
     * Handle fatal errors as callback for set_error_handler().
     *
     * Converts PHP errors to ErrorException for structured handling.
     * Non-fatal errors are logged and optionally displayed. Fatal errors are displayed.
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
    public function handleError( int $errno, string $errstr, string $errfile, int $errline ) : bool {
        if ( ! $this->isDebug() || ! ( error_reporting() & $errno ) ) {
            return true; // Ignore non-debug or non-reported errors
        }
        
        $exception = new \ErrorException( $errstr, 0, $errno, $errfile, $errline );

        if ( $this->isFatalError( $errno ) ) {
            // Handle our canonical fatal errors with full display and logging.
            $this->handleException( $exception );
            return true;
        }

        $this->logError( $exception );

        // Display as warning in debug + display_errors mode
        if ( $this->isDebug() && $this->displaysError() ) {
            $this->error_object = $exception;
            $this->title        = $this->getErrorTitle( $errno );
            $this->message      = $errstr;
            $this->displayWarning();
        }

        return true;
        
    }

    /**
     * Handle exceptions as callback for set_exception_handler().
     *
     * Accepts any Throwable - no wrapping, preserves full trace.
     *
     * Usage:
     *   set_exception_handler( [ HttpErrorHandler::class, 'handleException' ] );
     *
     * @param \Throwable $throwable The uncaught exception.
     * @return void
     */
    public function handleException( \Throwable $throwable ) : void {
        $this->error_object = $throwable;
        $this->message      = $throwable->getMessage();
        $this->code         = (string) $throwable->getCode();

        if ( $throwable instanceof ErrorException ) {
            $errno  = $throwable->getSeverity();
        } else {
            $errno  = \E_ERROR;
        }

        $this->title        = $this->getErrorTitle( $errno );

        $this->logError( $throwable );

        $this->handled  = true;
        $this->display();
    }

    /**
     * Handle shutdown errors as callback for register_shutdown_function().
     *
     * Catches fatal errors that occur during script execution.
     * Only runs if no fatal error was already handled by handleError().
     * Uses ErrorException for native PHP error representation.
     *
     * @return void
     */
    public function handleShutdown() : void {
        if ( $this->handled ) {
            return;
        }

        $error = error_get_last();

        if ( ! $error || ! $this->isFatalError( $error['type'] ) ) {
            return;
        }

        $exception = new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'] ?? 'unknown',
            $error['line'] ?? 0
        );

        $this->error_object = $exception;
        $this->title = $this->getErrorTitle( $error['type'] );
        $this->message = $error['message'];
        $this->logError( $exception );
        $this->handled = true;
        $this->setExit( true )->display();
    }

    /*
    |------------------------------------------
    | HANDLER REGISTRATION
    |------------------------------------------
    */

    /**
     * Register all error handlers.
     *
     * Convenience method to set up error, exception, and shutdown handlers.
     * Configurable with global debug settings.
     *
     * @param bool $handle_errors Whether to register error handler.
     * @param bool $handle_exceptions Whether to register exception handler.
     * @param bool $handle_shutdown Whether to register shutdown handler.
     * @return void
     */
    public function registerHandlers( bool $handle_errors = true, bool $handle_exceptions = true, bool $handle_shutdown = true ) : void {
        if ( $handle_errors ) {
            set_error_handler( [ $this, 'handleError' ] );
        }

        if ( $handle_exceptions ) {
            set_exception_handler( [ $this, 'handleException' ] );
        }

        if ( $handle_shutdown ) {
            register_shutdown_function( [ $this, 'handleShutdown' ] );
        }
    }

    /**
     * Unregister all error handlers.
     *
     * @return void
     */
    public function unregisterHandlers() : void {
        restore_error_handler();
        restore_exception_handler();
    }

    /*
    |------------------------------------------
    | UTILITY METHODS
    |------------------------------------------
    */

    /**
     * Check whether the error is fatal.
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
            E_USER_ERROR,
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
        }

        return $error_types[ $error_type ] ?? 'Unknown Error';
    }
}