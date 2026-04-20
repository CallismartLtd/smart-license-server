<?php
/**
 * The base exception class for the Smart License Server.
 *
 * A structured, throwable error container modelled after WP_Error.
 * Supports multiple error codes, per-code messages and data, exception
 * chaining, WP_Error interop, and environment-aware rendering for both
 * HTTP and CLI contexts.
 *
 * @author  Callistus Nwachukwu <admin@callismart.com.ng>
 * @package SmartLicenseServer\Exceptions
 * @since   0.1.1
 */

namespace SmartLicenseServer\Exceptions;

use Exception as PHPException;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Structured, throwable error container with WP_Error compatibility.
 */
class Exception extends PHPException {

    /*
    |--------------------------------------------
    | ANSI COLOUR CONSTANTS (CLI rendering)
    |--------------------------------------------
    */

    private const ANSI_RED     = "\033[0;31m";
    private const ANSI_YELLOW  = "\033[0;33m";
    private const ANSI_CYAN    = "\033[0;36m";
    private const ANSI_BOLD    = "\033[1m";
    private const ANSI_DIM     = "\033[2m";
    private const ANSI_RESET   = "\033[0m";

    /*
    |--------------------------------------------
    | ERROR STORAGE
    |--------------------------------------------
    */

    /**
     * Error messages keyed by error code.
     *
     * @var array<string|int, string[]>
     */
    public array $errors = [];

    /**
     * Most recently added data for each error code.
     *
     * @var array<string|int, mixed>
     */
    public array $error_data = [];

    /**
     * Previously added data for each error code, oldest-to-newest.
     *
     * @var array<string|int, mixed[]>
     */
    protected array $additional_data = [];

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    /**
     * Initialise the exception with optional structured error info.
     *
     * When $code is empty all other parameters are ignored.
     * When $code is present, $message is always stored (even if empty).
     * $data is stored only when non-empty.
     *
     * @param string|int      $code     Error code.
     * @param string          $message  Error message.
     * @param mixed           $data     Optional error data. Default ''.
     * @param \Throwable|null $previous Optional previous exception for chaining.
     */
    public function __construct(
        string|int   $code     = '',
        string       $message  = '',
        mixed        $data     = '',
        ?\Throwable  $previous = null
    ) {
        parent::__construct( $message, 0, $previous );

        if ( ! empty( $code ) ) {
            $this->add( $code, $message, $data );
        }
    }

    /*
    |--------------------------------------------
    | ERROR CODE ACCESSORS
    |--------------------------------------------
    */

    /**
     * Return all registered error codes.
     *
     * @return array<string|int>
     */
    public function get_error_codes(): array {
        return $this->has_errors() ? array_keys( $this->errors ) : [];
    }

    /**
     * Return the first registered error code, or an empty string.
     *
     * @return string|int
     */
    public function get_error_code(): string|int {
        $codes = $this->get_error_codes();
        return $codes[0] ?? '';
    }

    /*
    |--------------------------------------------
    | ERROR MESSAGE ACCESSORS
    |--------------------------------------------
    */

    /**
     * Return all messages for a given code, or all messages if no code given.
     *
     * @param string|int $code Optional error code.
     * @return string[]
     */
    public function get_error_messages( string|int $code = '' ): array {
        if ( empty( $code ) ) {
            $all = [];
            foreach ( $this->errors as $messages ) {
                $all = array_merge( $all, $messages );
            }
            return $all;
        }

        return $this->errors[ $code ] ?? [];
    }

    /**
     * Return the first message for a given code, or the first message overall.
     *
     * @param string|int $code Optional error code.
     * @return string
     */
    public function get_error_message( string|int $code = '' ): string {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }

        return $this->get_error_messages( $code )[0] ?? '';
    }

    /*
    |--------------------------------------------
    | ERROR DATA ACCESSORS
    |--------------------------------------------
    */

    /**
     * Return the most recently added data for a code.
     *
     * @param string|int $code Optional error code.
     * @return mixed
     */
    public function get_error_data( string|int $code = '' ): mixed {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }

        return $this->error_data[ $code ] ?? null;
    }

    /**
     * Return all data items for a code in insertion order.
     *
     * @param string|int $code Optional error code.
     * @return mixed[]
     */
    public function get_all_error_data( string|int $code = '' ): array {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }

        $data = $this->additional_data[ $code ] ?? [];

        if ( isset( $this->error_data[ $code ] ) ) {
            $data[] = $this->error_data[ $code ];
        }

        return $data;
    }

    /*
    |--------------------------------------------
    | MUTATION METHODS
    |--------------------------------------------
    */

    /**
     * Add an error message (and optionally data) for a given code.
     *
     * @param string|int $code    Error code.
     * @param string     $message Error message.
     * @param mixed      $data    Optional error data.
     */
    public function add( string|int $code, string $message, mixed $data = '' ): void {
        $this->errors[ $code ][] = $message;

        if ( ! empty( $data ) ) {
            $this->add_data( $data, $code );
        }
    }

    /**
     * Add data for a given code, preserving previously set data in $additional_data.
     *
     * @param mixed      $data Error data.
     * @param string|int $code Optional error code.
     */
    public function add_data( mixed $data, string|int $code = '' ): void {
        if ( empty( $code ) ) {
            $code = $this->get_error_code();
        }

        if ( isset( $this->error_data[ $code ] ) ) {
            $this->additional_data[ $code ][] = $this->error_data[ $code ];
        }

        $this->error_data[ $code ] = $data;
    }

    /**
     * Remove all messages and data for a given error code.
     *
     * @param string|int $code Error code.
     */
    public function remove( string|int $code ): void {
        unset(
            $this->errors[ $code ],
            $this->error_data[ $code ],
            $this->additional_data[ $code ]
        );
    }

    /**
     * Whether the instance contains at least one error.
     *
     * @return bool
     */
    public function has_errors(): bool {
        return ! empty( $this->errors );
    }

    /*
    |--------------------------------------------
    | MERGE / EXPORT
    |--------------------------------------------
    */

    /**
     * Merge errors from another exception or WP_Error into this instance.
     *
     * @param self|\WP_Error $error
     * @return static Fluent.
     */
    public function merge_from( self|\WP_Error $error ): static {
        static::copy_errors( $error, $this );
        return $this;
    }

    /**
     * Export errors from this instance into another exception or WP_Error.
     *
     * @param self|\WP_Error $error
     */
    public function export_to( self|\WP_Error $error ): void {
        static::copy_errors( $this, $error );
    }

    /**
     * Create an Exception from a WP_Error.
     *
     * @param \WP_Error $wp_error
     * @return static
     */
    public static function from_wp_error( \WP_Error $wp_error ): static {
        $exception = new static();
        static::copy_errors( $wp_error, $exception );
        return $exception;
    }

    /**
     * Convert this exception to a WP_Error.
     *
     * @return \WP_Error
     * @throws \RuntimeException In non-WordPress environments.
     */
    public function to_wp_error(): \WP_Error {
        if ( ! class_exists( \WP_Error::class ) ) {
            throw new \RuntimeException( 'WP_Error class is not available.' );
        }

        $wp_error = new \WP_Error();

        foreach ( $this->get_error_codes() as $code ) {
            foreach ( $this->get_error_messages( $code ) as $message ) {
                $wp_error->add( $code, $message );
            }

            foreach ( $this->get_all_error_data( $code ) as $datum ) {
                $wp_error->add_data( $datum, $code );
            }
        }

        // Fallback: if no structured message was set, use PHP exception message.
        if ( empty( $wp_error->get_error_messages() ) && $this->getMessage() ) {
            $wp_error->add( $this->get_error_code() ?: 'exception', $this->getMessage() );
        }

        return $wp_error;
    }

    /*
    |--------------------------------------------
    | RENDERING
    |--------------------------------------------
    */

    /**
     * Render the exception for the current runtime environment.
     *
     * CLI  → plain-text with optional ANSI colour.
     * HTTP → HTML-safe output (no raw stack trace exposed by default).
     *
     * @param bool $include_trace Whether to include the stack trace. Default true.
     * @return string
     */
    public function render( bool $include_trace = true ): string {
        return $this->is_cli()
            ? $this->render_cli( $include_trace )
            : $this->render_http( $include_trace );
    }

    /**
     * Return a structured array representation of the exception.
     *
     * @return array{
     *     message: string,
     *     codes: array,
     *     errors: array,
     *     trace: array
     * }
     */
    public function to_array(): array {
        $errors = [];

        foreach ( $this->get_error_codes() as $code ) {
            $errors[ $code ] = [
                'messages' => $this->get_error_messages( $code ),
                'data'     => $this->get_all_error_data( $code ),
            ];
        }

        return [
            'message' => $this->getMessage(),
            'codes'   => $this->get_error_codes(),
            'errors'  => $errors,
            'trace'   => $this->getTrace(),
        ];
    }

    /**
     * Delegate __toString() to render() for the current environment.
     *
     * @return string
     */
    public function __toString(): string {
        return $this->render();
    }

    /*
    |--------------------------------------------
    | PRIVATE RENDERING HELPERS
    |--------------------------------------------
    */

    /**
     * Render for a CLI environment.
     *
     * Uses ANSI colour codes when stdout is an interactive terminal.
     * Falls back to plain text when output is piped or redirected.
     *
     * @param bool $include_trace
     * @return string
     */
    private function render_cli( bool $include_trace ): string {
        $ansi  = $this->supports_ansi();
        $nl    = PHP_EOL;
        $class = get_class( $this );
        $out   = '';

        // Header.
        $out .= $this->cli_colour( "✖ {$class}", self::ANSI_RED . self::ANSI_BOLD, $ansi ) . $nl;

        if ( $this->getMessage() ) {
            $out .= $this->cli_colour( '  ' . $this->getMessage(), self::ANSI_RED, $ansi ) . $nl;
        }

        $out .= $nl;

        // Structured errors.
        if ( $this->has_errors() ) {
            $out .= $this->cli_colour( 'Errors:', self::ANSI_YELLOW . self::ANSI_BOLD, $ansi ) . $nl;

            foreach ( $this->errors as $code => $messages ) {
                $out .= $this->cli_colour( "  [{$code}]", self::ANSI_CYAN, $ansi ) . $nl;

                foreach ( $messages as $message ) {
                    $out .= "    → {$message}" . $nl;
                }

                $data = $this->get_all_error_data( $code );
                if ( ! empty( $data ) ) {
                    foreach ( $data as $datum ) {
                        $encoded = is_string( $datum )
                            ? $datum
                            : json_encode( $datum, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                        $out .= $this->cli_colour( "    data: {$encoded}", self::ANSI_DIM, $ansi ) . $nl;
                    }
                }
            }

            $out .= $nl;
        }

        // Chained exception.
        $previous = $this->getPrevious();
        if ( $previous ) {
            $out .= $this->cli_colour( 'Caused by:', self::ANSI_YELLOW . self::ANSI_BOLD, $ansi ) . $nl;
            $out .= $this->cli_colour( '  ' . get_class( $previous ) . ': ' . $previous->getMessage(), self::ANSI_DIM, $ansi ) . $nl;
            $out .= $nl;
        }

        // Stack trace.
        if ( $include_trace ) {
            $out .= $this->cli_colour( 'Stack trace:', self::ANSI_BOLD, $ansi ) . $nl;
            $out .= $this->cli_colour( $this->getTraceAsString(), self::ANSI_DIM, $ansi ) . $nl;
        }

        return $out;
    }

    /**
     * Render for an HTTP environment.
     *
     * Stack traces are intentionally excluded by default — never expose
     * raw server paths or source code to a browser response.
     *
     * @param bool $include_trace
     * @return string
     */
    private function render_http( bool $include_trace ): string {
        $nl    = "\n";
        $class = get_called_class();
        $out   = '';

        // Header line.
        $out .= sprintf( '%s: %s', $class, $this->getMessage() ) . $nl . $nl;

        // Structured errors.
        if ( $this->has_errors() ) {
            $out .= 'All Errors:' . $nl;

            foreach ( $this->errors as $code => $messages ) {
                foreach ( $messages as $message ) {
                    $out .= sprintf( '  → [%s] %s', $code, $message ) . $nl;
                }
            }

            $out .= $nl;
            $out .= 'Error Codes: ' . implode( ', ', $this->get_error_codes() ) . $nl;
            $out .= 'Data: ' . smliser_safe_json_encode( $this->error_data, JSON_PRETTY_PRINT ) . $nl;
            
        }

        // Chained exception.
        $previous = $this->getPrevious();
        if ( $previous ) {
            $out .= $nl . sprintf(
                'Caused by: %s: %s',
                get_class( $previous ),
                $previous->getMessage()
            ) . $nl;
        }

        // Stack trace — HTML-wrapped, opt-in only.
        if ( $include_trace ) {
            $out .= $nl . 'Trace:' . $nl;
            $out .= '<div>' . $this->getTraceAsString() . '</div>' . $nl;

            if ( $previous ) {
                $out .= $nl . 'Previous Trace:' . $nl;
                $out .= '<div>' . $previous->getTraceAsString() . '</div>' . $nl;
            }
        }

        return $out;
    }

    /**
     * Wrap a string in an ANSI colour sequence when ANSI is supported.
     *
     * @param string $text
     * @param string $colour One or more ANSI_* constants concatenated.
     * @param bool   $ansi   Whether ANSI is supported in this context.
     * @return string
     */
    private function cli_colour( string $text, string $colour, bool $ansi ): string {
        return $ansi ? $colour . $text . self::ANSI_RESET : $text;
    }

    /**
     * Detect whether the current runtime is a CLI process.
     *
     * @return bool
     */
    private function is_cli(): bool {
        return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
    }

    /**
     * Detect whether the current stdout supports ANSI escape codes.
     *
     * Returns false when output is piped or redirected so plain text
     * is used in log files, CI output, and other non-interactive contexts.
     *
     * @return bool
     */
    private function supports_ansi(): bool {
        if ( ! $this->is_cli() ) {
            return false;
        }

        // Explicit override via environment variable (e.g. CI systems).
        if ( isset( $_SERVER['NO_COLOR'] ) || getenv( 'NO_COLOR' ) !== false ) {
            return false;
        }

        if ( getenv( 'TERM' ) === 'dumb' ) {
            return false;
        }

        return function_exists( 'stream_isatty' ) && stream_isatty( STDOUT );
    }

    /*
    |--------------------------------------------
    | STATIC HELPERS
    |--------------------------------------------
    */

    /**
     * Copy all errors and data from one error object to another.
     *
     * Works transparently across Exception and WP_Error instances.
     *
     * @param self|\WP_Error $from
     * @param self|\WP_Error $to
     */
    protected static function copy_errors( self|\WP_Error $from, self|\WP_Error $to ): void {
        $codes = method_exists( $from, 'get_error_codes' ) ? $from->get_error_codes() : [];

        foreach ( $codes as $code ) {
            $messages = method_exists( $from, 'get_error_messages' )
                ? $from->get_error_messages( $code )
                : [];

            foreach ( (array) $messages as $message ) {
                if ( method_exists( $to, 'add' ) ) {
                    $to->add( $code, $message );
                }
            }

            $data = method_exists( $from, 'get_all_error_data' )
                ? $from->get_all_error_data( $code )
                : ( method_exists( $from, 'get_error_data' )
                    ? [ $from->get_error_data( $code ) ]
                    : [] );

            foreach ( (array) $data as $datum ) {
                if ( method_exists( $to, 'add_data' ) ) {
                    $to->add_data( $datum, $code );
                }
            }
        }
    }
}