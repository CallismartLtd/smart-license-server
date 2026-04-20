<?php
/**
 * Error Handler Class for Smart License Server
 *
 * Provides a standalone error display mechanism without WordPress dependencies.
 * Integrates seamlessly with the Exception class for structured error handling,
 * respecting exception design patterns and providing production-grade error display.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class ErrorHandler {

    /**
     * Default configuration
     *
     * @var array
     */
    private array $defaults = [
        'response'      => 500,
        'link_url'      => '',
        'link_text'     => '',
        'back_link'     => false,
        'charset'       => 'utf-8',
        'code'          => 'smliser_error',
        'exit'          => true,
        'debug'         => false,
    ];

    /**
     * Current configuration
     *
     * @var array
     */
    private array $config = [];

    /**
     * Error message
     *
     * @var string
     */
    private string $message = '';

    /**
     * Error title
     *
     * @var string
     */
    private string $title = '';

    /**
     * Error code
     *
     * @var string
     */
    private string $code = '';

    /**
     * Error object (if using exception)
     *
     * @var Exception|null
     */
    private ?Exception $error_object = null;

    /**
     * HTML head content
     *
     * @var array
     */
    private array $head_content = [];

    /**
     * CSS styles
     *
     * @var array
     */
    private array $styles = [];

    /**
     * Custom attributes for HTML tag
     *
     * @var array
     */
    private array $html_attributes = [];

    /**
     * Custom attributes for body tag
     *
     * @var array
     */
    private array $body_attributes = [];

    /**
     * Global debug mode configuration
     *
     * @var bool
     */
    private static bool $debug_mode = false;

    /**
     * Global log handler callback
     *
     * @var \Closure|null
     */
    private static ?\Closure $log_handler = null;

    /**
     * Configure error handler globally
     *
     * @param bool $debug Whether to enable debug mode globally
     * @param \Closure|null $log_handler Optional callback for logging errors
     * @return void
     */
    public static function configure( bool $debug = false, ?\Closure $log_handler = null ) : void {
        self::$debug_mode = $debug;
        self::$log_handler = $log_handler;
    }

    /**
     * Constructor
     *
     * @param string|Exception|null $message Optional. Error message or exception object.
     * @param string|int $title Optional. Error title or HTTP response code.
     * @param array|int $args Optional. Additional arguments or HTTP response code.
     */
    public function __construct( $message = '', $title = '', $args = [] ) {
        $this->config   = $this->defaults;
        $this->initialize( $message, $title, $args );
    }

    /**
     * Initialize error handler with message, title, and arguments
     *
     * @param string|Exception $message Error message or exception
     * @param string|int $title Error title or HTTP code
     * @param array|int $args Additional arguments or HTTP code
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
     * Check if value is an error object
     *
     * @param mixed $value Value to check
     * @return bool
     */
    private function isErrorObject( $value ) : bool {
        return $value instanceof Exception;
    }

    /**
     * Process error object - RESPECTS EXCEPTION CLASS DESIGN
     *
     * Extracts HTTP status and title from Exception's structured data
     * without reimplementing Exception's rendering logic.
     *
     * @param Exception $error Error object
     * @return void
     */
    private function processErrorObject( Exception $error ) : void {
        // Get error code from Exception
        $this->code = $error->get_error_code() ?: $this->config['code'];

        // Extract HTTP status from error data
        $data = $error->get_error_data();
        
        if ( is_array( $data ) && isset( $data['status'] ) ) {
            $this->config['response'] = (int) $data['status'];
        } elseif ( is_object( $data ) && property_exists( $data, 'status' ) ) {
            $this->config['response'] = (int) $data->status;
        }

        // Extract title from error data or use default
        $this->title = '';
        if ( is_array( $data ) && isset( $data['title'] ) ) {
            $this->title = $data['title'];
        } elseif ( is_object( $data ) && property_exists( $data, 'title' ) ) {
            $this->title = $data->title;
        }
        $this->title = $this->title ?: 'Application Error';

        // Get first message from Exception
        // Don't use render() here - just get the raw message
        // Rendering happens in render() method
        $this->message = $error->get_error_message();
    }

    /**
     * Sanitize error message
     *
     * @param mixed $message Message to sanitize
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
     * Set error message
     *
     * @param string $message Error message
     * @return self
     */
    public function setMessage( string $message ) : self {
        $this->message = $message;
        return $this;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getMessage() : string {
        return $this->message ?: 'An unknown fatal error occurred.';
    }

    /**
     * Set error title
     *
     * @param string $title Error title
     * @return self
     */
    public function setTitle( string $title ) : self {
        $this->title = $title;
        return $this;
    }

    /**
     * Get error title
     *
     * @return string
     */
    public function getTitle() : string {
        return $this->title ?: 'Fatal Error';
    }

    /**
     * Set error code
     *
     * @param string $code Error code
     * @return self
     */
    public function setCode( string $code ) : self {
        $this->code = $code;
        $this->config['code'] = $code;
        return $this;
    }

    /**
     * Get error code
     *
     * @return string
     */
    public function getCode() : string {
        return $this->code ?: $this->config['code'];
    }

    /**
     * Set HTTP response code
     *
     * @param int $code HTTP response code
     * @return self
     */
    public function setResponseCode( int $code ) : self {
        $this->config['response'] = $code;
        return $this;
    }

    /**
     * Get HTTP response code
     *
     * @return int
     */
    public function getResponseCode() : int {
        return (int) $this->config['response'];
    }

    /**
     * Set character encoding
     *
     * @param string $charset Character encoding
     * @return self
     */
    public function setCharset( string $charset ) : self {
        $this->config['charset'] = $charset;
        return $this;
    }

    /**
     * Get character encoding
     *
     * @return string
     */
    public function getCharset() : string {
        return $this->config['charset'];
    }

    /**
     * Enable/disable debug mode
     *
     * @param bool $debug Whether to enable debug mode
     * @return self
     */
    public function setDebug( bool $debug ) : self {
        $this->config['debug'] = $debug;
        return $this;
    }

    /**
     * Check if debug mode is enabled
     *
     * @return bool
     */
    public function isDebug() : bool {
        return $this->config['debug'];
    }

    /**
     * Enable/disable auto-exit after display
     *
     * @param bool $exit Whether to exit
     * @return self
     */
    public function setExit( bool $exit ) : self {
        $this->config['exit'] = $exit;
        return $this;
    }

    /**
     * Check if auto-exit is enabled
     *
     * @return bool
     */
    public function shouldExit() : bool {
        return $this->config['exit'];
    }

    /**
     * Set back link display
     *
     * @param bool $show Whether to show back link
     * @return self
     */
    public function setBackLink( bool $show ) : self {
        $this->config['back_link'] = $show;
        return $this;
    }

    /**
     * Set custom link
     *
     * @param string $url URL for the link
     * @param string $text Link text
     * @return self
     */
    public function setLink( string $url, string $text ) : self {
        $this->config['link_url']  = $url;
        $this->config['link_text'] = $text;
        return $this;
    }

    /**
     * Add meta tag to HTML head
     *
     * @param string $name Attribute name
     * @param string $content Attribute content
     * @param array $attributes Additional attributes
     * @return self
     */
    public function addMeta( string $name, string $content, array $attributes = [] ) : self {
        $meta = array_merge(
            [ 'name' => $name, 'content' => $content ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'meta', 'data' => $meta ];
        return $this;
    }

    /**
     * Add link to HTML head
     *
     * @param string $rel Relationship type
     * @param string $href URL
     * @param array $attributes Additional attributes
     * @return self
     */
    public function addLink( string $rel, string $href, array $attributes = [] ) : self {
        $link = array_merge(
            [ 'rel' => $rel, 'href' => $href ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'link', 'data' => $link ];
        return $this;
    }

    /**
     * Add custom style
     *
     * @param string $selector CSS selector
     * @param array $properties CSS properties as key-value pairs
     * @return self
     */
    public function addStyle( string $selector, array $properties ) : self {
        $this->styles[ $selector ] = $properties;
        return $this;
    }

    /**
     * Set multiple styles at once
     *
     * @param array $styles Styles array (selector => properties)
     * @return self
     */
    public function setStyles( array $styles ) : self {
        $this->styles = array_merge( $this->styles, $styles );
        return $this;
    }

    /**
     * Set custom HTML attributes
     *
     * @param array $attributes Attributes for html tag
     * @return self
     */
    public function setHtmlAttributes( array $attributes ) : self {
        $this->html_attributes = array_merge( $this->html_attributes, $attributes );
        return $this;
    }

    /**
     * Set custom body attributes
     *
     * @param array $attributes Attributes for body tag
     * @return self
     */
    public function setBodyAttributes( array $attributes ) : self {
        $this->body_attributes = array_merge( $this->body_attributes, $attributes );
        return $this;
    }

    /**
     * Add CSS class to body tag
     *
     * @param string $class CSS class name
     * @return self
     */
    public function addBodyClass( string $class ) : self {
        $existing = $this->body_attributes['class'] ?? '';
        $this->body_attributes['class'] = trim( $existing . ' ' . $class );
        return $this;
    }

    /**
     * Render HTML head section
     *
     * @return string
     */
    private function renderHead() : string {
        $html = '';

        // Meta tags
        $html .= "\n\t<meta charset=\"" . htmlspecialchars( $this->config['charset'], ENT_QUOTES ) . '">' . "\n";
        $html .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";

        // Additional meta tags
        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'meta' ) {
                $html .= "\t<meta";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
                }
                $html .= ">\n";
            }
        }

        $html .= "\t<title>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</title>\n";

        // Styles
        $html .= $this->renderStyles();

        // Additional links
        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'link' ) {
                $html .= "\t<link";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
                }
                $html .= ">\n";
            }
        }

        return $html;
    }

    /**
     * Render CSS styles
     *
     * @return string
     */
    private function renderStyles() : string {
        $default_styles = [
            'body' => [
                'font-family' => 'Arial, sans-serif',
                'background' => '#f4f4f4',
                'padding' => '50px',
                'margin' => '0',
            ],
            '.error-container' => [
                'max-width' => '80%',
                'margin' => 'auto',
                'background' => '#ffffff',
                'padding' => '30px',
                'border-radius' => '8px',
                'box-shadow' => '0px 4px 15px rgba(0, 0, 0, 0.1)',
                'overflow-wrap' => 'anywhere',
            ],
            'h1' => [
                'color' => '#e74c3c',
                'margin-top' => '0',
                'font-size' => '24px',
            ],
            'p' => [
                'font-size' => '16px',
                'color' => '#333',
            ],
            'a' => [
                'color' => '#3498db',
                'text-decoration' => 'none',
            ],
            'a:hover' => [
                'text-decoration' => 'underline',
            ],
            'pre' => [
                'max-width' => '100%',
                'background-color' => '#f1f1f1',
                'overflow-x' => 'auto',
                'padding' => '10px',
                'scrollbar-width' => 'thin',
                'border-radius' => '4px',
            ],
        ];

        // Merge with custom styles
        $all_styles = array_merge( $default_styles, $this->styles );

        $css = "\t<style>\n";
        foreach ( $all_styles as $selector => $properties ) {
            $css .= "\t\t" . $selector . " {\n";
            foreach ( $properties as $property => $value ) {
                $css .= "\t\t\t" . $property . ": " . $value . ";\n";
            }
            $css .= "\t\t}\n";
        }
        $css .= "\t</style>\n";

        return $css;
    }

    /**
     * Render HTML attributes
     *
     * @param array $attributes Attributes array
     * @return string
     */
    private function renderAttributes( array $attributes ) : string {
        $html = '';
        foreach ( $attributes as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ' ', $value );
            }
            $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
        }
        return $html;
    }

    /**
     * Render link HTML
     *
     * @return string
     */
    private function renderLinks() : string {
        $html = '';

        if ( ! empty( $this->config['link_url'] ) && ! empty( $this->config['link_text'] ) ) {
            $html .= '<p><a href="' . htmlspecialchars( $this->config['link_url'], ENT_QUOTES ) . '">';
            $html .= htmlspecialchars( $this->config['link_text'], ENT_QUOTES, 'UTF-8' );
            $html .= '</a></p>' . "\n";
        }

        if ( $this->config['back_link'] ) {
            $html .= '<p><a href="javascript:history.back()">Go Back</a></p>' . "\n";
        }

        return $html;
    }

    /**
     * Wrap text content in HTML template
     *
     * @param string $content The content to wrap
     * @return string
     */
    private function wrapInHtml( string $content ) : string {
        $html_attrs = $this->renderAttributes( $this->html_attributes );
        $body_attrs = $this->renderAttributes( $this->body_attributes );

        $html = "<!DOCTYPE html>\n";
        $html .= "<html" . $html_attrs . ">\n";
        $html .= "<head>\n";
        $html .= $this->renderHead();
        $html .= "</head>\n";
        $html .= "<body" . $body_attrs . ">\n";
        $html .= "\t<div class=\"error-container\">\n";
        $html .= "\t\t<h1>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</h1>\n";
        $html .= "\t\t<div class=\"error-message\">\n";
        $html .= "\t\t\t<pre>" . $content . "</pre>\n";
        $html .= "\t\t</div>\n";

        $links = $this->renderLinks();
        if ( ! empty( $links ) ) {
            $html .= "\t\t<div class=\"error-links\">\n";
            $html .= $links;
            $html .= "\t\t</div>\n";
        }

        $html .= "\t</div>\n";
        $html .= "</body>\n";
        $html .= "</html>";

        return $html;
    }

    /**
     * Render complete HTML - DELEGATES TO EXCEPTION CLASS FOR RENDERING
     *
     * If rendering an Exception, uses its render() method which respects
     * the Exception class's formatting and data structure.
     *
     * @return string
     */
    public function render() : string {
        // If rendering an Exception, use its render() method
        if ( $this->error_object instanceof Exception ) {
            // For HTTP, use trace based on debug configuration
            $include_trace = $this->config['debug'];
            $rendered = $this->error_object->render( $include_trace );
            
            // Wrap Exception's output in HTML template
            return $this->wrapInHtml( $rendered );
        }

        // Fallback for string messages
        $html_attrs = $this->renderAttributes( $this->html_attributes );
        $body_attrs = $this->renderAttributes( $this->body_attributes );

        $html = "<!DOCTYPE html>\n";
        $html .= "<html" . $html_attrs . ">\n";
        $html .= "<head>\n";
        $html .= $this->renderHead();
        $html .= "</head>\n";
        $html .= "<body" . $body_attrs . ">\n";
        $html .= "\t<div class=\"error-container\">\n";
        $html .= "\t\t<h1>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</h1>\n";
        $html .= "\t\t<div class=\"error-message\">\n";
        $html .= "\t\t\t" . $this->getMessage() . "\n";
        $html .= "\t\t</div>\n";

        $links = $this->renderLinks();
        if ( ! empty( $links ) ) {
            $html .= "\t\t<div class=\"error-links\">\n";
            $html .= $links;
            $html .= "\t\t</div>\n";
        }

        $html .= "\t</div>\n";
        $html .= "</body>\n";
        $html .= "</html>";

        return $html;
    }

    /**
     * Send HTTP headers
     *
     * @return self
     */
    public function sendHeaders() : self {
        if ( ! headers_sent() ) {
            http_response_code( $this->getResponseCode() );
            header( 'Content-Type: text/html; charset=' . $this->getCharset() );
        }
        return $this;
    }

    /**
     * Display error and optionally exit
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
     * Static factory method
     *
     * @param string|Exception $message Error message or exception
     * @param string|int $title Error title or HTTP code
     * @param array|int $args Additional arguments or HTTP code
     * @return self
     */
    public static function create( $message = '', $title = '', $args = [] ) : self {
        return new self( $message, $title, $args );
    }

    /**
     * Create and display error
     *
     * @param string|Exception $message Error message or exception
     * @param string|int $title Error title or HTTP code
     * @param array|int $args Additional arguments or HTTP code
     * @return void
     */
    public static function abort( $message = '', $title = '', $args = [] ) : void {
        static::create( $message, $title, $args )->display();
    }

    /**
     * Centralized error logging - RESPECTS EXCEPTION STRUCTURE
     *
     * Logs using Exception's to_array() method for consistent structured data.
     *
     * @param Exception $exception The exception to log
     * @return void
     */
    private static function logError( Exception $exception ) : void {
        if ( self::$log_handler ) {
            ( self::$log_handler )( $exception->to_array() );
        } else {
            error_log( json_encode( $exception->to_array() ) );
        }
    }

    /**
     * Handle fatal errors as callback for set_error_handler()
     *
     * CREATES EXCEPTION OBJECT from error - respects Exception class design
     *
     * Usage:
     *   set_error_handler( [ ErrorHandler::class, 'handleError' ] );
     *
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool Return true to stop PHP's internal error handler
     */
    public static function handleError( int $errno, string $errstr, string $errfile, int $errline ) : bool {
        $error_types = [
            E_ERROR                 => 'Fatal Error',
            E_WARNING               => 'Warning',
            E_PARSE                 => 'Parse Error',
            E_NOTICE                => 'Notice',
            E_CORE_ERROR            => 'Core Fatal Error',
            E_CORE_WARNING          => 'Core Warning',
            E_COMPILE_ERROR         => 'Compile Error',
            E_COMPILE_WARNING       => 'Compile Warning',
            E_USER_ERROR            => 'User Error',
            E_USER_WARNING          => 'User Warning',
            E_USER_NOTICE           => 'User Notice',
            E_RECOVERABLE_ERROR     => 'Recoverable Error',
            E_DEPRECATED            => 'Deprecated',
            E_USER_DEPRECATED       => 'User Deprecated',
        ];

        if ( defined( 'E_STRICT' ) ) {
            $error_types[E_STRICT] = 'Strict Notice';
        }

        $title = $error_types[ $errno ] ?? 'Unknown Error';

        // Non-fatal errors: log and continue
        if ( in_array( $errno, [ E_WARNING, E_NOTICE, E_DEPRECATED, E_USER_NOTICE, E_USER_WARNING ], true ) ) {
            error_log( "{$title}: {$errstr} in " . basename( $errfile ) . " on line {$errline}" );
            return true;
        }

        // CREATE EXCEPTION FROM ERROR - respects Exception class
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

        // Log using Exception's structured data
        self::logError( $exception );

        // Add security headers
        if ( ! headers_sent() ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: DENY' );
            header( 'X-XSS-Protection: 1; mode=block' );
            header( 'Content-Security-Policy: default-src \'self\'' );
        }

        // Display using ErrorHandler with global debug configuration
        static::create( $exception )
            ->setDebug( self::$debug_mode )
            ->display();

        return true;
    }

    /**
     * Handle exceptions as callback for set_exception_handler()
     *
     * RESPECTS EXCEPTION CLASS - wraps standard exceptions in Exception class
     *
     * Usage:
     *   set_exception_handler( [ ErrorHandler::class, 'handleException' ] );
     *
     * @param \Throwable $exception The uncaught exception
     * @return void
     */
    public static function handleException( \Throwable $exception ) : void {
        // Only handle our Exception class specially
        if ( ! $exception instanceof Exception ) {
            // Wrap standard exceptions in our Exception class for consistency
            $exception = new Exception(
                'uncaught_exception',
                $exception->getMessage(),
                [
                    'status' => 500,
                    'title' => get_class( $exception ),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ]
            );
        }

        // Log using Exception's structured data
        self::logError( $exception );

        // Add security headers
        if ( ! headers_sent() ) {
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: DENY' );
            header( 'X-XSS-Protection: 1; mode=block' );
        }

        // Display using global debug configuration
        static::create( $exception )
            ->setDebug( self::$debug_mode )
            ->display();
    }

    /**
     * Handle shutdown errors as callback for register_shutdown_function()
     *
     * CREATES EXCEPTION FROM SHUTDOWN ERROR - respects Exception class design
     *
     * This catches fatal errors that occur during script execution.
     *
     * Usage:
     *   register_shutdown_function( [ ErrorHandler::class, 'handleShutdown' ] );
     *
     * @return void
     */
    public static function handleShutdown() : void {
        $error = error_get_last();

        // Only handle fatal errors
        if ( ! $error || ! in_array( $error['type'], [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR ], true ) ) {
            return;
        }

        $error_types = [
            E_ERROR => 'Fatal Error',
            E_PARSE => 'Parse Error',
            E_CORE_ERROR => 'Core Fatal Error',
            E_COMPILE_ERROR => 'Compile Error',
        ];

        // Create Exception object from shutdown error - respects Exception class
        $exception = new Exception(
            'fatal_' . $error['type'],
            $error['message'],
            [
                'status' => 500,
                'title' => $error_types[ $error['type'] ] ?? 'Fatal Error',
                'file' => $error['file'] ?? 'unknown',
                'line' => $error['line'] ?? 0,
            ]
        );

        // Log error
        self::logError( $exception );

        // Only display if headers haven't been sent (to avoid output issues)
        if ( ! headers_sent() ) {
            static::create( $exception )
                ->setDebug( false )  // Never debug on shutdown
                ->display();
        }
    }

    /**
     * Register all error handlers
     *
     * Convenience method to set up error, exception, and shutdown handlers
     * with global debug configuration.
     *
     * Usage:
     *   ErrorHandler::registerHandlers();
     *   // or with custom configuration
     *   ErrorHandler::configure( true, $logger_callback );
     *   ErrorHandler::registerHandlers();
     *
     * @param bool $handle_errors Whether to register error handler
     * @param bool $handle_exceptions Whether to register exception handler
     * @param bool $handle_shutdown Whether to register shutdown handler
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
     * Unregister all error handlers
     *
     * Usage:
     *   ErrorHandler::unregisterHandlers();
     *
     * @return void
     */
    public static function unregisterHandlers() : void {
        restore_error_handler();
        restore_exception_handler();
    }
}