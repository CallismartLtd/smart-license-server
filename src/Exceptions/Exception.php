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
    |--------------------
    | CONSTRUCTOR
    |--------------------
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
    |--------------------------
    | ERROR DATA ACCESSORS
    |--------------------------
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
    |-----------------
    | RENDERING
    |-----------------
    */


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