<?php
/**
 * Request finisher class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Core
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Core;

/**
 * Sends the response, closes the connection, and keeps PHP running.
 *
 * For endpoints that answer at once and do their work afterwards, such
 * as the cron URL: the caller gets its response immediately and learns
 * nothing from how long the work takes, and the work is not cut short
 * when the caller disconnects.
 *
 * Uses every mechanism the server offers, best first:
 *
 *  1. fastcgi_finish_request()  PHP-FPM: ends the request at the web
 *                               server; PHP carries on.
 *  2. litespeed_finish_request() LiteSpeed's LSAPI equivalent.
 *  3. Headers and flushing      Everywhere else (mod_php, CGI, built-in
 *                               server): an exact Content-Length (or
 *                               204), "Connection: close", compression
 *                               and proxy buffering turned off, then
 *                               every output buffer flushed, so the
 *                               client sees a complete response and
 *                               hangs up.
 *
 * The headers in step 3 are sent in every case: they cost nothing and
 * help when a proxy sits in front of FPM or LiteSpeed.
 *
 * After finish(), output is discarded and headers can no longer be sent.
 *
 * Safe where the host disables functions: every call is checked first
 * (see PhpRuntime).
 */
final class RequestFinisher {

	/**
	 * How the connection was released.
	 */
	public const METHOD_FASTCGI   = 'fastcgi_finish_request';
	public const METHOD_LITESPEED = 'litespeed_finish_request';
	public const METHOD_FLUSH     = 'flush';
	public const METHOD_NONE      = 'none';

	/**
	 * Whether finish() has run in this request.
	 *
	 * @var bool
	 */
	private static bool $finished = false;

	/**
	 * Send a response, close the connection and keep running.
	 *
	 * @param int                   $status      HTTP status. 204 and 304 never carry a body.
	 * @param string                $body        Response body.
	 * @param array<string, string> $headers     Extra headers, name => value.
	 * @param int                   $time_limit  Seconds PHP may keep running afterwards; 0 for no limit.
	 * @return string One of the METHOD_* constants: how the client was released.
	 *                METHOD_NONE means headers were already sent, so the
	 *                client may wait until the script ends.
	 */
	public static function finish( int $status = 204, string $body = '', array $headers = array(), int $time_limit = 0 ) : string {
		if ( self::$finished ) {
			return self::METHOD_NONE;
		}

		self::$finished = true;

		// The work after the response must survive the client hanging up.
		PhpRuntime::allow_long_work( $time_limit );

		// Anything already printed (stray output, warnings) must not become the body.
		self::discard_buffers();

		// A held session lock would block the user's next request until the work ends.
		if ( function_exists( 'session_status' ) && PHP_SESSION_ACTIVE === session_status() ) {
			session_write_close();
		}

		$bodyless = 204 === $status || 304 === $status || ( $status >= 100 && $status < 200 );
		$body     = $bodyless ? '' : $body;

		if ( headers_sent() ) {
			echo $body;
			self::flush_all();

			return self::release( self::METHOD_NONE );
		}

		self::disable_compression();

		http_response_code( $status );

		foreach ( $headers as $name => $value ) {
			header( $name . ': ' . $value );
		}

		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
		header( 'Connection: close' );
		header( 'X-Accel-Buffering: no' );   // nginx: do not hold the response in its buffer.
		header( 'Content-Encoding: none' );  // Stops servers that compress whole responses from waiting for the end.

		// A 204 must not carry Content-Length (RFC 9110); every other response gets an exact one.
		if ( $bodyless ) {
			header_remove( 'Content-Type' );
		} else {
			header( 'Content-Length: ' . strlen( $body ) );
		}

		echo $body;

		return self::release( self::METHOD_FLUSH );
	}

	/**
	 * Whether this request's response has already been finished.
	 *
	 * @return bool
	 */
	public static function finished() : bool {
		return self::$finished;
	}

	/**
	 * Hand the response to the client with the best mechanism available.
	 *
	 * @param string $fallback Method to report when only flushing was possible.
	 * @return string
	 */
	private static function release( string $fallback ) : string {
		self::flush_all();

		if ( function_exists( 'fastcgi_finish_request' ) && fastcgi_finish_request() ) {
			$method = self::METHOD_FASTCGI;
		} elseif ( function_exists( 'litespeed_finish_request' ) && litespeed_finish_request() ) {
			$method = self::METHOD_LITESPEED;
		} else {
			$method = $fallback;
		}

		// From here on the client is gone; keep any later output out of the closed stream.
		ob_start( static fn() : string => '' );

		return $method;
	}

	/**
	 * Turn off compression that would make the server wait for the whole response.
	 *
	 * @return void
	 */
	private static function disable_compression() : void {
		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', '1' );
			@apache_setenv( 'dont-vary', '1' );
		}

		PhpRuntime::ini_set( 'zlib.output_compression', 'Off' );
		PhpRuntime::ini_set( 'implicit_flush', '1' );
	}

	/**
	 * Throw away every open output buffer.
	 *
	 * @return void
	 */
	private static function discard_buffers() : void {
		while ( ob_get_level() > 0 ) {
			if ( ! @ob_end_clean() ) {
				break;
			}
		}
	}

	/**
	 * Flush every open output buffer, then PHP's own output.
	 *
	 * @return void
	 */
	private static function flush_all() : void {
		while ( ob_get_level() > 0 ) {
			if ( ! @ob_end_flush() ) {
				break;
			}
		}

		flush();
	}
}