<?php
/**
 * Core response class file.
 *
 * @package SmartLicenseServer\Core
 * @author  Callistus
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\Exception;

use function smliser_safe_json_encode, defined, is_array, array_push, preg_replace, sprintf;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The core HTTP response class used to deliver responses to client.
 * 
 * @example 
 * ```php 
 * $data		= "Some strings or array";
 * $headers		= array( 
 *	['Content-Type', 'application/json; charset=utf-8;'],
 *	['X-Custom-Header', 'custom header value']
 * );
 * $response	= new \SmartLicenseServer\Core\Response( 200, $headers, $data );
 * $response->send();
 * ```
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
	 * @var array<string,string>
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
	 * @var string|array
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
	public function __construct( int $status_code = 200, $headers = array(), $body = '' ) {
		$this->error	= new Exception();
		
		$this->set_status_code( $status_code );
		$this->headers = array_map( [$this, 'set_header'], $headers );
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
	 * @return static
	 */
	public function set_status_code( int $code, ?string $reason = null ) : static {
		$this->status_code		= $code;
		$this->reason_phrase	= $reason ?: $this->get_default_reason_phrase( $code );
		return $this;
	}

	/**
	 * Get the HTTP status code.
	 *
	 * @return int
	 */
	public function get_status_code() : int {
		return $this->status_code;
	}

	/**
	 * Get the reason phrase.
	 *
	 * @return string
	 */
	public function get_reason_phrase() : string {
		return $this->reason_phrase;
	}

	/**
	 * Set custom reason phrase.
	 *
	 * @param string $reason Reason phrase.
	 * @return static
	 */
	public function set_reason_phrase( string $reason ) : static {
		$this->reason_phrase = $reason;
		return $this;
	}

	/**
     * Get default reason phrase for a status code.
     *
     * @param int $code HTTP status code.
     * @return string
     */
    protected function get_default_reason_phrase( $code ) : string {
        static $phrases = array(
            // 1xx Informational.
            100 => 'Continue',
            101 => 'Switching Protocols',

            // 2xx Success.
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // 3xx Redirection.
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // 4xx Client Error.
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

            // 5xx Server Error.
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
	 * @param bool $override Whether or not to override existing value.
	 * @return static
	 */
	public function set_header( string $name, string $value, bool $override = true  ) {
		$key					= $this->header_canonical( $name );

		if ( ! $this->has_header( $key ) || $override ) {
			$this->headers[ $key ]	= $value;
		} else {
			$this->headers[ $key ]	.= ', ' . $value;
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
		$key = $this->header_canonical( $name );

		return $this->headers[ $key ] ?? null;
	}

	/**
	 * Check if a header exists.
	 *
	 * @param string $name Header name.
	 * @return bool
	 */
	public function has_header( $name ) : bool {
		$key	= $this->header_canonical( $name );
		return array_key_exists( $key, $this->headers );
	}

	/**
	 * Remove a header.
	 *
	 * @param string $name Header name.
	 * @return static
	 */
	public function remove_header( string $name ) : static {
		$key	= $this->header_canonical( $name );
		unset( $this->headers[$key] );
		return $this;
	}

	/**
	 * Get all headers.
	 *
	 * @return array<string,string>
	 */
	public function get_headers() : array {
		return $this->headers;
	}

	/*--------------------------------------------------------------
	# Body
	--------------------------------------------------------------*/

	/**
	 * Set the response body.
	 *
	 * @param mixed $content Body content.
	 * @return static
	 */
	public function set_body( mixed $content ) : static {
		$this->body = $content;
		$this->remove_header( 'Content-Length' );
		return $this;
	}

	/**
	 * Append content to the body.
	 *
	 * @param string $content Content to append.
	 * @return static
	 */
	public function append_body( $content ) {
		if ( is_array( $this->body ) ) {
			array_push( $this->body, $content );
		} else {
			$this->body .= (string) $content;
		}

		return $this;
	}

	/**
	 * Get the response body.
	 *
	 * @return string|array
	 */
	public function get_body() : string|array {
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
	public function get_protocol_version() : string {
		return $this->protocol_version;
	}

	/**
	 * Set the protocol version.
	 *
	 * @param string $version HTTP protocol version.
	 * @return static
	 */
	public function set_protocol_version( $version ) : static {
		$this->protocol_version = $version;
		return $this;
	}

	/*--------------------------------------------------------------
	# Response Sending
	--------------------------------------------------------------*/

	/**
	 * Send HTTP response headers to the client.
	 *
	 * This method implements header transmission in accordance with:
	 *
	 * - RFC 7230: Hypertext Transfer Protocol (HTTP/1.1): Message Syntax and Routing
	 *   - §3.1.2 – Status Line format: "HTTP-version status-code reason-phrase"
	 *   - §3.2 – Header Fields: case-insensitive names and token formatting
	 *   - §3.2.4 – Field Parsing: prevention of CRLF injection
	 *
	 * - RFC 7231: Hypertext Transfer Protocol (HTTP/1.1): Semantics and Content
	 *   - §6 – Response Status Codes
	 *   - §7.1.2 – Location header semantics for redirects
	 *
	 * Behavior:
	 * - Ensures headers are not re-sent if output has already begun.
	 * - Sends a properly formatted HTTP status line.
	 * - Normalizes header names to hyphenated form as recommended by RFC 7230.
	 * - Sanitizes header values to prevent CRLF injection and invalid whitespace.
	 * - Sends each header using PHP’s native header() function.
	 * - Terminates execution for OPTIONS requests or redirect responses,
	 *   as no message body is expected in these cases.
	 *
	 * @return void
	 */
	public function send_headers() : void {
		if ( headers_sent( $file, $line ) ) {
			return;
		}

		// Send the status line.
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

		foreach ( $this->headers as $name => $value ) {
			$name	= str_replace( '_', '-', $name );
					
			$value = trim( preg_replace( '/[\r\n]+/', ' ', $value ) );
			$value = preg_replace( '/\s+/', ' ', $value );
			
			header( $name . ': ' . $value );
		}
		
		$method	= $_SERVER['REQUEST_METHOD'] ?? '';

		if ( 'OPTIONS' === $method || static::is_redirect() ) {
			exit;
		}
	}

	/**
	 * Send the response body.
	 *
	 * @return void
	 */
	public function send_body() : void {
		if ( is_array( $this->body ) ) {
			$this->body = smliser_safe_json_encode( $this->body );
		}

		if ( ! $this->has_header( 'Content-Length' ) ) {
			$this->set_header( 'Content-Length', strlen( $this->body ) );
		}

		echo $this->body;
	}

	/**
	 * Send full response (headers + body).
	 *
	 * @return void
	 */
	public function send() : void {
		
        if ( $this->has_errors() ) {
			if ( $this->is_json_response() ) {
				smliser_send_json_error( $this->error );
			}
			
            smliser_abort_request( $this->error );
        }
		
		$this->send_headers();
		$this->send_body();

		$this->trigger_after_serve_callbacks();

		if ( $this->is_json_response() ) {
			exit;
		}
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
     * @return static
     */
    public function add_error( $code, $message, $data = '' ) : static {
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
     * @return \SmartLicenseServer\Exceptions\Exception The internal Exception object.
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
     * @return static
     */
    public function set_exception( Exception $exception ): static {
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
		&& ! empty( $redirect_header );

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
	 * @return static
	 */
	public function clear_headers(): static {
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
     * Automatically injects the current Response instance ($this)
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

				$class_name	= get_class( $this );
                error_log( sprintf(
                    '[%ss] Post-serve callback failed (%s): %s in %s:%d',
					$class_name,
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
	public function set_response_data( Request $request ) : static {
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

	/**
	 * Ensures that header names are always treated the same regardless of
	 * source. Header names are always case-insensitive.
	 *
	 *
	 * @param string $key Header name.
	 * @return string Canonicalized name.
	 */
	public static function header_canonical( $key ) : string {
		$key = strtolower( $key );
		$key = str_replace( '-', '_', $key );

		return $key;
	}
}
