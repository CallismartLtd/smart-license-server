<?php
/**
 * A comprehensive HTTP response handler class.
 *
 * @package SmartLicenseServer\Core
 * @author  Callistus
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class HttpResponse
 *
 * Handles HTTP responses in a framework-agnostic way.
 */
class Response {

	/**
	 * HTTP protocol version.
	 *
	 * @var string
	 */
	protected $protocol_version = '1.1';

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	protected $status_code = 200;

	/**
	 * Status reason phrase.
	 *
	 * @var string
	 */
	protected $reason_phrase = 'OK';

	/**
	 * A likely modified version of the request object used to pass message.
	 * 
	 * @var Request $response_data
	 */
	protected $response_data;

	/**
	 * Response headers.
	 *
	 * @var array
	 */
	protected $headers = array();

    /**
     * Registered callbacks to be executed after file is served.
     *
     * @var array
     */
    protected $after_serve_callbacks = array();

	/**
	 * Response body.
	 *
	 * @var string
	 */
	protected $body = '';

	/**
	 * Error instance
	 * 
	 * @param Exception $error
	 */
	protected $error;

	/*--------------------------------------------------------------
	# Constructor
	--------------------------------------------------------------*/

	/**
	 * Constructor.
	 *
	 * @param int    $status_code Optional. Initial HTTP status code.
	 * @param array  $headers     Optional. Initial headers.
	 * @param string $body        Optional. Initial body content.
	 */
	public function __construct( $status_code = 200, $headers = array(), $body = '' ) {
		$this->error	= new Exception();
		
		$this->set_status_code( $status_code );
		$this->headers = array_change_key_case( (array) $headers, CASE_LOWER );
		$this->set_body( $body );
	}

	/*--------------------------------------------------------------
	# Status Code & Reason Phrase
	--------------------------------------------------------------*/

	/**
	 * Set the HTTP status code.
	 *
	 * @param int $code HTTP status code.
	 * @param string|null $reason Custom reason phrase (optional).
	 * @return self
	 */
	public function set_status_code( $code, $reason = null ) {
		$this->status_code  = (int) $code;
		$this->reason_phrase = $reason ?: $this->get_default_reason_phrase( $code );
		return $this;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code() {
		return $this->status_code;
	}

	/**
	 * Get the reason phrase.
	 *
	 * @return string
	 */
	public function get_reason_phrase() {
		return $this->reason_phrase;
	}

	/**
	 * Set custom reason phrase.
	 *
	 * @param string $reason Reason phrase.
	 * @return self
	 */
	public function set_reason_phrase( $reason ) {
		$this->reason_phrase = $reason;
		return $this;
	}

	/**
     * Get default reason phrase for a status code.
     *
     * @param int $code HTTP status code.
     * @return string
     */
    protected function get_default_reason_phrase( $code ) {
        static $phrases = array(
            // --- 1xx Informational ---
            100 => 'Continue',
            101 => 'Switching Protocols',

            // --- 2xx Success ---
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // --- 3xx Redirection ---
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect', // Note: Correct permanent redirect

            // --- 4xx Client Error ---
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            429 => 'Too Many Requests',
            451 => 'Unavailable For Legal Reasons',

            // --- 5xx Server Error ---
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
        );

        return isset( $phrases[ $code ] ) ? $phrases[ $code ] : 'Unknown Status';
    }

	/*--------------------------------------------------------------
	# Headers
	--------------------------------------------------------------*/

	/**
	 * Set or overwrite a response header.
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @return self
	 */
	public function set_header( $name, $value ) {
		$this->headers[ strtolower( $name ) ] = $value;
		return $this;
	}

	/**
	 * Add a header (can coexist with others of same name).
	 *
	 * @param string $name  Header name.
	 * @param string $value Header value.
	 * @return self
	 */
	public function add_header( $name, $value ) {
		$key = strtolower( $name );
		if ( isset( $this->headers[ $key ] ) && is_array( $this->headers[ $key ] ) ) {
			$this->headers[ $key ][] = $value;
		} elseif ( isset( $this->headers[ $key ] ) ) {
			$this->headers[ $key ] = array( $this->headers[ $key ], $value );
		} else {
			$this->headers[ $key ] = $value;
		}
		return $this;
	}

	/**
	 * Get a response header.
	 *
	 * @param string $name Header name.
	 * @return string|array|null
	 */
	public function get_header( $name ) {
		$key = strtolower( $name );
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : null;
	}

	/**
	 * Check if a header exists.
	 *
	 * @param string $name Header name.
	 * @return bool
	 */
	public function has_header( $name ) {
		return isset( $this->headers[ strtolower( $name ) ] );
	}

	/**
	 * Remove a header.
	 *
	 * @param string $name Header name.
	 * @return self
	 */
	public function remove_header( $name ) {
		unset( $this->headers[ strtolower( $name ) ] );
		return $this;
	}

	/**
	 * Get all headers.
	 *
	 * @return array
	 */
	public function get_headers() {
		return $this->headers;
	}

	/*--------------------------------------------------------------
	# Body
	--------------------------------------------------------------*/

	/**
	 * Set the response body.
	 *
	 * @param string $content Body content.
	 * @return self
	 */
	public function set_body( $content ) {
		$this->body = (string) $content;
		$this->set_header( 'Content-Length', strlen( $this->body ) );
		return $this;
	}

	/**
	 * Append content to the body.
	 *
	 * @param string $content Content to append.
	 * @return self
	 */
	public function append_body( $content ) {
		$this->body .= (string) $content;
		return $this;
	}

	/**
	 * Get the response body.
	 *
	 * @return string
	 */
	public function get_body() {
		return $this->body;
	}

	/*--------------------------------------------------------------
	# Protocol
	--------------------------------------------------------------*/

	/**
	 * Get the protocol version.
	 *
	 * @return string
	 */
	public function get_protocol_version() {
		return $this->protocol_version;
	}

	/**
	 * Set the protocol version.
	 *
	 * @param string $version HTTP protocol version.
	 * @return self
	 */
	public function set_protocol_version( $version ) {
		$this->protocol_version = $version;
		return $this;
	}

	/*--------------------------------------------------------------
	# Response Sending
	--------------------------------------------------------------*/

	/**
	 * Send headers to the client.
	 *
	 * Handles multi-word capitalization and respects already sent headers.
	 *
	 * @return void
	 */
	public function send_headers() {
		if ( headers_sent( $file, $line ) ) {
			// Optional: debug log or trigger warning
			error_log( sprintf( 'Headers already sent in %s on line %d', $file, $line ) );
			return;
		}

		// Send the status line
		header(
			sprintf(
				'HTTP/%s %d %s',
				$this->protocol_version,
				$this->status_code,
				$this->reason_phrase
			),
			true,
			$this->status_code
		);

		// Normalize header name for proper capitalization
		$normalize_name = function( $name ) {
			// Converts "x-content-type-options" -> "X-Content-Type-Options"
			$parts = explode( '-', $name );
			$parts = array_map( 'ucfirst', $parts );
			return implode( '-', $parts );
		};

		foreach ( $this->headers as $name => $value ) {
			$name = $normalize_name( $name );

			if ( is_array( $value ) ) {
				foreach ( $value as $v ) {
					header( $name . ': ' . $v, false );
				}
			} else {
				header( $name . ': ' . $value );
			}
		}
		
		if ( 'OPTIONS' === $_SERVER['REQUEST_METHOD'] || self::is_redirect() ) {
			exit;
		}
	}


	/**
	 * Send the response body.
	 *
	 * @return void
	 */
	public function send_body() {
		echo $this->body;
	}

	/**
	 * Send full response (headers + body).
	 *
	 * @return void
	 */
	public function send() {
        if ( $this->has_errors() ) {
			if ( $this->is_json_response() ) {
				smliser_send_json_error( $this->error );
			}
			
            smliser_abort_request( $this->error );
        }
		
		$this->send_headers();
		$this->send_body();

		$this->trigger_after_serve_callbacks();
	}

	/*--------------------------------------------------------------
	# Error Methods
	--------------------------------------------------------------*/
	/**
     * Add an error or append an additional message to an existing error.
     *
     * Delegates to the internal Exception object.
     *
     * @param string|int $code    Error code.
     * @param string     $message Error message.
     * @param mixed      $data    Optional. Error data. Default empty string.
     * @return self
     */
    public function add_error( $code, $message, $data = '' ): self {
        $this->error->add( $code, $message, $data );
        return $this;
    }

    /**
     * Verifies if the response contains accumulated errors.
     *
     * Delegates to the internal Exception object.
     *
     * @return bool If the response contains errors.
     */
    public function has_errors(): bool {
        return $this->error->has_errors();
    }

    /**
     * Retrieves the first error code available.
     *
     * Delegates to the internal Exception object.
     *
     * @return string|int Empty string, if no error codes.
     */
    public function get_error_code() {
        return $this->error->get_error_code();
    }

    /**
     * Retrieves the first error message available.
     *
     * Delegates to the internal Exception object.
     *
     * @param string|int $code Optional. Error code to retrieve the message for.
     * Default empty string (will use the first code).
     * @return string The error message.
     */
    public function get_error_message( $code = '' ) {
        return $this->error->get_error_message( $code );
    }

    /**
     * Retrieves the most recently added error data for an error code.
     *
     * Delegates to the internal Exception object.
     *
     * @param string|int $code Optional. Error code. Default empty string.
     * @return mixed Error data, if it exists.
     */
    public function get_error_data( $code = '' ) {
        return $this->error->get_error_data( $code );
    }

    /**
     * Retrieves the entire internal Exception object.
     *
     * Useful for logging or merging with another Exception instance.
     *
     * @return SmartLicenseServer\Exception
     */
    public function get_exception(): Exception {
        return $this->error;
    }

    /**
     * Overwrites the internal Exception object with a new one.
     *
     * Useful when converting external errors (e.g., WP_Error) into the response's error state.
     *
     * @param SmartLicenseServer\Exception $exception The new exception object.
     * @return self
     */
    public function set_exception( Exception $exception ): self {
        $this->error = $exception;
        return $this;
    }

	/*--------------------------------------------------------------
	# Utility Methods
	--------------------------------------------------------------*/

	/**
	 * Check whether the current request is a json response
	 * 
	 * 
	 * @return bool
	 */
	public function is_json_response() : bool {
		$content_type = $this->get_header( 'Content-Type' );
		if ( is_array( $content_type ) ) {
			$content_type = reset( $content_type );
		}
		return is_string( $content_type ) && stripos( $content_type, 'application/json' ) !== false;
	}

	/**
	 * Check whether the current request is redirect response.
	 * 
	 * 
	 * @return bool
	 */
	public function is_redirect() : bool {
		$redirect_header = $this->get_header( 'Location' );
		if ( is_array( $redirect_header ) ) {
			$redirect_header = reset( $redirect_header );
		}
		return ( $this->status_code >= 300 && $this->status_code < 400 ) 
			&& 
		stripos( $redirect_header, 'http' ) !== false;
	}


	/**
	 * Determines whether a response is okay.
	 * 
	 * @return bool
	 */
	public function ok() : bool {

		return ! $this->has_errors() && ( $this->status_code >= 200 && $this->status_code < 300 );
	}

	/**
	 * Clear headers
	 *
	 * @return self
	 */
	public function clear_headers(): self {
		$this->headers = array();
		return $this;
	}


    /**
     * Register a callback to run after serving the file.
     *
     * @param callable $callback   The function or method to call.
     * @param array    $args       Optional. Arguments to pass to the callback.
     *
     * @return void
     */
    public function register_after_serve_callback( callable $callback, array $args = array() ) {
        $this->after_serve_callbacks[] = array(
            'callback' => $callback,
            'args'     => $args,
        );
    }

    /**
     * Trigger all registered after-serve callbacks.
     *
     * Automatically injects the current FileResponse instance ($this)
     * as the last parameter.
     *
     * @return void
     */
    protected function trigger_after_serve_callbacks() {
        foreach ( $this->after_serve_callbacks as $item ) {
            $callback = $item['callback'];
            $args     = $item['args'];

            array_push( $args, $this );

            try {
                call_user_func_array( $callback, $args );

            } catch ( \Throwable $e ) {
                $callback_name = 'closure';
                if ( is_array( $callback ) ) {
                    $callback_name = ( is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0] ) . '::' . $callback[1];
                } elseif ( is_string( $callback ) ) {
                    $callback_name = $callback;
                }

                error_log( sprintf(
                    '[FileResponse] Post-serve callback failed (%s): %s in %s:%d',
                    $callback_name,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ) );
            }
        }
    }

	/**
	 * Set response data using a modified request object.
	 * 
	 * @param Request $request 
	 */
	public function set_response_data( Request $request ) : self {
		$response_data = clone $request;

		$this->response_data = $response_data;

		return $this;
	}

	/**
	 * Get the response data
	 * 
	 * @return Request
	 */
	public function get_response_data() : Request {
		return $this->response_data;
	}
}
