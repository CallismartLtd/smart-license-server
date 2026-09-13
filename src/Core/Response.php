<?php
/**
 * Core response class file.
 *
 * @package SmartLicenseServer\Core
 * @author  Callistus
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\Exception;
use Callismart\Http\HttpStatusAwareTrait;

use function is_array, array_push, preg_replace, sprintf;

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
	use HttpStatusAwareTrait;

	/**
	 * HTTP protocol protocol.
	 *
	 * @var string
	 */
	protected string $http_protocol;

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
	 * The current request object.
	 * 
	 * @var Request $request
	 */
	protected Request $request;

	/**
	 * Response headers.
	 *
	 * @var array<string,string>
	 */
	protected $headers = [];

    /**
     * Registered callbacks to be executed after file is served.
     *
     * @var array
     */
    protected $after_serve_callbacks = [];

	/**
	 * Response body.
	 *
	 * @var string|array
	 */
	protected $body = '';

	/**
	 * Error instance
	 * 
	 * @var Exception $error
	 */
	protected  $error;

	/**
	 * Constructor.
	 *
	 * @param int    $status_code	Optional. Initial HTTP status code.
	 * @param array  $headers		Optional. Initial headers.
	 * @param string|string $body	Optional. Initial body content.
	 */
	public function __construct( int $status_code = 200, $headers = [], string|array $body = '' ) {
		$this->error	= new Exception();
		
		$this->set_status_code( $status_code );
		
		foreach ( $headers as $name => $value ) {
            if ( is_array( $value ) && isset( $value[0], $value[1] ) ) {
                $this->set_header( (string) $value[0], (string) $value[1] );
            } elseif ( is_string( $name ) ) {
                $this->set_header( $name, (string) $value );
            }
        }

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
    protected function get_default_reason_phrase( int $code ) : string {
        return static::reason_phrase( $code );
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
		$key	= $this->header_canonical( $name );

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
	 * @return string|null
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
	 * Clears all headers.
	 * 
	 * @return true
	 */
	public function remove_headers() : bool {
		$this->headers	= [];
		return true;
	}

	/**
	 * Get all headers.
	 *
	 * @param bool $normalize Whether to nomalize the headers.
	 * @return array<string,string>
	 */
	public function get_headers( bool $normalize = false ) : array {
		if ( ! $normalize ) {
			return $this->headers;
		}

		foreach ( $this->headers as $key => $value ) {
			$new_key	= str_replace( '_', '-', $key );
			$value = trim( preg_replace( '/[\r\n]+/', ' ', $value ) );
			$value = preg_replace( '/\s+/', ' ', $value );

			unset( $this->headers[$key] );
			$this->headers[$new_key]	= $value;
		}

		return $this->headers;
	}

	/*--------------------------------------------------------------
	# Body
	--------------------------------------------------------------*/

	/**
	 * Set the response body.
	 *
	 * @param string|array $content Body content.
	 * @return static
	 */
	public function set_body( string|array $content ) : static {
		$this->body	= $content;
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

	/*
	|---------------
	| PROTOCOL
	|---------------
	*/

	/**
	 * Get the protocol.
	 *
	 * @return string
	 */
	public function get_http_protocol() : string {
		if ( ! isset( $this->http_protocol ) ) {
			$this->http_protocol = smliser_get_server_protocol();
		}

		return $this->http_protocol;
	}

	/**
	 * Set the HTTP protocol.
	 *
	 * @param string $protocol HTTP protocol.
	 * @return static
	 */
	public function set_http_protocol( string $protocol ) : static {
		$this->http_protocol = $protocol;
		return $this;
	}

	/*
	|------------------
	| RESPONSE SENDING
	|------------------
	*/

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
			\smliser_abort_request(
				'Unable to start download. Headers already sent.',
				'Headers Sent',
				['status' => 500]	
			);
			return;
		}

		// Send the status line.
		header(
			sprintf(
				'%s %d %s',
				$this->get_http_protocol(),
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

		if ( 'OPTIONS' === $method || $this->is_redirect() ) {
			$this->stop();
		}
	}

	/**
	 * Send the response body.
	 *
	 * @return void
	 */
	public function send_body() : void {
		echo $this->body;
	}

	/**
	 * Send full response (headers + body).
	 *
	 * @return void
	 */
	public function send() : void {
		if ( $this->has_errors() ) {
			$this->prepare_error_response();
		}
		
		$this->ensure_content_length();
		$this->send_headers();
		$this->send_body();

		$this->trigger_after_serve_callbacks();

		if ( $this->is_json_response() ) {
			$this->stop();
		}
	}

	protected function ensure_content_length() : void {
		if ( is_array( $this->body ) ) {
			$this->body = smliser_safe_json_encode( $this->body );
		}

		if ( ! $this->has_header( 'Content-Length' ) ) {
			$this->set_header( 'Content-Length', (string) strlen( $this->body ) );
		}
	}

	/*
	|---------------
	| ERROR METHODS
	|---------------
	*/
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
     * Useful when converting external errors into the response's error state.
     *
     * @param Exception $exception The new exception object.
     * @return static
     */
    public function set_exception( Exception $exception ): static {
        $this->error	= $exception;
		$error_data		= $this->error->get_error_data();
		$this->set_status_code( (int) ( $error_data['status'] ?? $error_data['response'] ?? 500 ) );
        return $this;
    }

	/**
	 * Prepare error response.
	 * 
	 * @return void
	 */
	protected function prepare_error_response() : void {
		$this->set_header( 'X-Content-Type-Options', 'nosniff' );

		if ( $this->is_json_response() ) {
			$this->set_body([
				'success'	=> false,
				'error'		=> [
					'message'	=> $this->get_error_message(),
					'data'		=> $this->get_error_data(),
					'code'		=> $this->get_error_code()
				]
			]);
		} else {
			$nonce = base64_encode( random_bytes( 16 ) );

			$this->set_header( 'X-Frame-Options', 'DENY' )
				->set_header( 'X-XSS-Protection', '1; mode=block' )
				->set_header(
					'Content-Security-Policy',
					sprintf(
						"default-src %1\$s; style-src %1\$s %2\$s; script-src %1\$s 'nonce-%3\$s'; img-src %1\$s data:; font-src %1\$s;",
						"'self'",
						"'unsafe-inline'",
						$nonce
					)
				)

			->set_body( $this->http_error_document( $this->get_error_message(), $nonce ) );
		}
	}

	/*
	|-----------------
	| UTILITY METHODS
	|-----------------
	*/

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
		$this->headers = [];
		return $this;
	}

	/**
	 * Ends response.
	 * 
	 * @return never
	 */
	public function stop() : never {
		exit;
	}

    /**
     * Register a callback to be executed after the response is sent.
     *
     * @param callable $callback   The function or method to call.
     * @param mixed   ...$args       Optional. Arguments to pass to the callback.
     *
     * @return void
     */
    public function post_response_action( callable $callback, mixed ...$args ) {
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

                smliser_log_error( sprintf(
                    '[%ss] Post-serve callback failed (%s): %s in %s:%d',
					get_class( $this ),
                    $callback_name,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                ) );
            }
        }
    }

	/**
	 * Set the request property.
	 * 
	 * @param Request $request 
	 */
	public function set_request( Request $request ) : static {
		$this->request = $request;

		return $this;
	}

	/**
	 * Get the request object.
	 * 
	 * @return Request|null
	 */
	public function get_request() : ?Request {
		if ( ! isset( $this->request ) ) {
			return null;
		}

		return $this->request;
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

	/**
	 * Make an instance of response object.
	 * 
	 * @param string $body
	 * @param int $status_code
	 * @return static
	 */
	public static function make( string $body, int $status_code = 200, array $headers = [] ) : static {
		return new static( $status_code, $headers, $body );
	}

	/**
	 * Create a new Response instance pre-configured for JSON delivery.
	 *
	 * @param array|string $data        Data payload to encode or pre-encoded JSON string.
	 * @param int          $status_code Optional. HTTP status code. Default 200.
	 * @param array        $headers     Optional. Additional response headers.
	 * @return static
	 */
	public static function json( array|string $data, int $status_code = 200, array $headers = [] ) : static {
		$instance = new static( $status_code, $headers, $data );

		if ( ! $instance->has_header( 'Content-Type' ) ) {
			$instance->set_header( 'Content-Type', 'application/json; charset=utf-8' );
		}

		return $instance;
	}

	/**
	 * Create a new error response.
	 * 
	 * @param Exception $error
	 * @param int $status_code
	 * @return static
	 */
	public static function error( Exception $error, int $status_code = 500, array $headers = [] ) : static {
		return ( new static( $status_code, $headers ) )
			->set_exception( $error );
	}

	/**
	 * Create a redirect response.
	 * 
	 * @param string|URL $url
	 * @param int $status_code
	 */
	public static function redirect( string|URL $url, int $status_code = 307 ) : static {
		return new static( $status_code, [], '' )
			->set_header( 'Location', is_string( $url ) ? $url : $url->url() );
	}

	/**
	 * Render a complete HTML document for an HTTP error response.
	 *
	 * @param string $message User-friendly error message or description.
	 * @param string $nonce CSP nonce for the inline script handling the
	 *                       page's action buttons. Must match the nonce
	 *                       issued in the response's Content-Security-Policy
	 *                       header, or the buttons will not be interactive.
	 * @return string Complete HTML document.
	 */
	protected function http_error_document( string $message, string $nonce ): string {
		$code         = $this->get_status_code();
		$reason       = $this->get_reason_phrase();
		$safe_reason  = htmlspecialchars( $reason, ENT_QUOTES, 'UTF-8' );
		$safe_message = htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
		$safe_nonce   = htmlspecialchars( $nonce, ENT_QUOTES, 'UTF-8' );

		$is_server_error = $code >= 500;

		$accent         = $is_server_error ? '#c2410c' : '#0f766e';
		$accent_bg      = $is_server_error ? '#fff7ed' : '#ecfeff';
		$accent_dark    = $is_server_error ? '#fb923c' : '#2dd4bf';
		$accent_bg_dark = $is_server_error ? '#2a1509' : '#0b2b2b';

		if ( $is_server_error ) {
			$diagram = <<<SVG
				<svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect x="20" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
					<circle cx="34" cy="70" r="3" fill="var(--ink)" />
					<line x1="46" y1="63" x2="66" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
					<line x1="46" y1="77" x2="66" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

					<path class="signal-line" d="M76 70 H164" stroke="var(--accent)" stroke-width="2" stroke-dasharray="4 5" />

					<rect x="164" y="54" width="56" height="32" rx="8" stroke="var(--accent)" stroke-width="1.5" fill="var(--accent-bg)" />
					<path d="M178 54 L188 86 L198 60 L206 86" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
				</svg>
				SVG;
		} else {
			$diagram = <<<SVG
				<svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
					<rect x="20" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
					<circle cx="34" cy="70" r="3" fill="var(--ink)" />
					<line x1="46" y1="63" x2="66" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
					<line x1="46" y1="77" x2="66" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

					<path class="signal-line" d="M76 70 H166" stroke="var(--accent)" stroke-width="2" stroke-dasharray="4 5" />
					<circle cx="178" cy="70" r="11" stroke="var(--accent)" stroke-width="1.5" stroke-dasharray="3 4" />

					<rect x="204" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" stroke-dasharray="3 4" opacity="0.35" />
				</svg>
				SVG;
		}

		$actions = '<button type="button" class="btn" data-action="back">Go back</button>';

		if ( $is_server_error ) {
			$actions = '<button type="button" class="btn btn-accent" data-action="retry">Try again</button>' . $actions;
		}

		return <<<HTML
			<!DOCTYPE html>
			<html lang="en">
			<head>
				<meta charset="UTF-8">
				<meta name="viewport" content="width=device-width, initial-scale=1.0">
				<meta name="color-scheme" content="light dark">
				<title>HTTP {$code} — {$safe_reason}</title>

				<style>
					:root {
						color-scheme: light dark;

						--bg: #f5f6f8;
						--dot: #dde1e6;
						--ink: #12151a;
						--muted: #5b6270;
						--border: #d8dce2;

						--accent: {$accent};
						--accent-bg: {$accent_bg};
					}

					@media (prefers-color-scheme: dark) {
						:root {
							--bg: #0b0e14;
							--dot: #1c212b;
							--ink: #eef1f5;
							--muted: #8b93a1;
							--border: #232935;

							--accent: {$accent_dark};
							--accent-bg: {$accent_bg_dark};
						}
					}

					* {
						box-sizing: border-box;
					}

					html, body {
						height: 100%;
						margin: 0;
					}

					body {
						display: flex;
						flex-direction: column;
						min-height: 100dvh;
						background:
							radial-gradient(var(--dot) 1px, transparent 1px) 0 0 / 24px 24px,
							var(--bg);
						color: var(--ink);
						font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
						font-size: 15px;
						line-height: 1.6;
						-webkit-font-smoothing: antialiased;
						text-rendering: optimizeLegibility;
					}

					main {
						flex: 1;
						display: flex;
						align-items: center;
						justify-content: center;
						padding: 48px 24px;
					}

					.error-shell {
						display: grid;
						grid-template-columns: 260px 1fr;
						align-items: center;
						gap: 48px;
						max-width: 760px;
						width: 100%;
					}

					.error-visual svg {
						width: 100%;
						height: auto;
					}

					.error-copy {
						text-align: left;
					}

					.error-heading {
						margin: 0 0 14px;
						font-size: 2rem;
						font-weight: 600;
						letter-spacing: -0.02em;
						line-height: 1.25;
					}

					.error-message {
						margin: 0 0 28px;
						max-width: 46ch;
						color: var(--muted);
					}

					.error-actions {
						display: flex;
						gap: 10px;
					}

					.btn {
						padding: 9px 18px;
						border: 1px solid var(--border);
						border-radius: 8px;
						background: transparent;
						color: var(--ink);
						font: inherit;
						font-weight: 600;
						font-size: 13.5px;
						cursor: pointer;
					}

					.btn:hover {
						border-color: var(--accent);
						color: var(--accent);
					}

					.btn-accent {
						border-color: var(--accent);
						background: var(--accent-bg);
						color: var(--accent);
					}

					footer {
						padding: 18px 24px;
						border-top: 1px solid var(--border);
						color: var(--muted);
						font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, "Liberation Mono", monospace;
						font-size: 12.5px;
						text-align: center;
					}

					.signal-line {
						stroke-dashoffset: 0;
					}

					@media (prefers-reduced-motion: no-preference) {
						.signal-line {
							animation: signal-travel 1.1s infinite;
						}
					}

					@keyframes signal-travel {
						from { stroke-dashoffset: 90; }
						to { stroke-dashoffset: 0; }
					}

					@media (max-width: 640px) {
						.error-shell {
							grid-template-columns: 1fr;
							gap: 28px;
							text-align: center;
						}

						.error-copy {
							text-align: center;
						}

						.error-message {
							max-width: none;
						}

						.error-actions {
							justify-content: center;
						}

						.error-visual svg {
							max-width: 220px;
							margin: 0 auto;
						}
					}
				</style>
			</head>

			<body>
				<main>
					<div class="error-shell" role="alert" aria-labelledby="error-reason">
						<div class="error-visual">
							{$diagram}
						</div>

						<div class="error-copy">
							<h1 id="error-reason" class="error-heading">{$safe_reason}</h1>
							<p class="error-message">{$safe_message}</p>
							<div class="error-actions">
								{$actions}
							</div>
						</div>
					</div>
				</main>

				<footer>HTTP/1.1 {$code} {$safe_reason}</footer>

				<script nonce="{$safe_nonce}">
					document.querySelectorAll( '[data-action]' ).forEach( function ( button ) {
						button.addEventListener( 'click', function () {
							if ( 'retry' === button.dataset.action ) {
								location.reload();
							} else {
								history.back();
							}
						} );
					} );
				</script>
			</body>
			</html>
			HTML;
	}
}
