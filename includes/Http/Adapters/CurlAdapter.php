<?php
/**
 * cURL HTTP Adapter.
 *
 * Executes HTTP requests using PHP's cURL extension.
 * Preferred adapter when available — supports all features
 * including SSL verification, redirects, cookies, and timing.
 *
 * @package SmartLicenseServer\Http\Adapters
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http\Adapters;

use SmartLicenseServer\Http\HttpAdapterInterface;
use SmartLicenseServer\Http\HttpRequest;
use SmartLicenseServer\Http\HttpResponse;
use SmartLicenseServer\Http\Exceptions\HttpRequestException;
use SmartLicenseServer\Http\Exceptions\HttpTimeoutException;

defined( 'SMLISER_ABSPATH' ) || exit;

class CurlAdapter implements HttpAdapterInterface {

    public function get_id(): string {
        return 'curl';
    }

    public function is_available(): bool {
        return function_exists( 'curl_init' );
    }

    /**
     * Execute the request via cURL.
     *
     * @param HttpRequest $request
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    public function send( HttpRequest $request ): HttpResponse {
        $start  = microtime( true );
        $handle = curl_init();

        $response_headers = [];

        curl_setopt_array( $handle, $this->build_options( $request, $response_headers ) );

        $body  = curl_exec( $handle );
        $error = curl_error( $handle );
        $errno = curl_errno( $handle );
        $info  = curl_getinfo( $handle );

        curl_close( $handle );

        $latency = microtime( true ) - $start;

        if ( $errno === CURLE_OPERATION_TIMEDOUT ) {
            throw new HttpTimeoutException(
                "CurlAdapter: request to '{$request->url}' timed out after {$request->timeout}s."
            );
        }

        if ( $body === false || $errno !== 0 ) {
            throw new HttpRequestException(
                "CurlAdapter: request to '{$request->url}' failed — {$error} (errno {$errno})."
            );
        }

        [ $status_code, $reason_phrase ] = $this->extract_status( $response_headers );

        $redirect_history = $this->extract_redirect_history( $response_headers, $info );
        $parsed_headers   = $this->parse_headers( $response_headers );
        $cookies          = $this->parse_cookies( $response_headers );

        return new HttpResponse(
            status_code      : $status_code,
            reason_phrase    : $reason_phrase,
            headers          : $parsed_headers,
            body             : (string) $body,
            cookies          : $cookies,
            redirect_history : $redirect_history,
            latency          : $latency,
        );
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Build the cURL options array for a given request.
     *
     * The $response_headers array is passed by reference so the header
     * callback can populate it during execution.
     *
     * @param HttpRequest          $request
     * @param array<int,string>   &$response_headers
     * @return array<int, mixed>
     */
    protected function build_options( HttpRequest $request, array &$response_headers ): array {
        $options = [
            CURLOPT_URL            => $request->url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $request->timeout,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => $request->max_redirects,
            CURLOPT_SSL_VERIFYPEER => $request->verify_ssl,
            CURLOPT_SSL_VERIFYHOST => $request->verify_ssl ? 2 : 0,
            CURLOPT_CUSTOMREQUEST  => $request->method,
            CURLOPT_HEADERFUNCTION => function ( $ch, string $header ) use ( &$response_headers ): int {
                $response_headers[] = $header;
                return strlen( $header );
            },
        ];

        // Headers.
        $headers = $request->headers;

        $cookie_header = $request->get_cookie_header();
        if ( $cookie_header !== '' ) {
            $headers['Cookie'] = $cookie_header;
        }

        if ( ! empty( $headers ) ) {
            $options[ CURLOPT_HTTPHEADER ] = array_map(
                fn( $name, $value ) => "{$name}: {$value}",
                array_keys( $headers ),
                $headers
            );
        }

        // Body.
        if ( $request->has_body() ) {
            $options[ CURLOPT_POSTFIELDS ] = $request->body;
        }

        return $options;
    }

    /**
     * Extract the final HTTP status code and reason phrase from response headers.
     *
     * When redirects are followed, multiple status lines are present.
     * We want the last one — which corresponds to the final response.
     *
     * @param array<int,string> $response_headers
     * @return array{int, string}
     */
    protected function extract_status( array $response_headers ): array {
        $status_code   = 0;
        $reason_phrase = '';

        foreach ( $response_headers as $header ) {
            if ( preg_match( '/^HTTP\/\S+\s+(\d{3})\s*(.*)/i', trim( $header ), $matches ) ) {
                $status_code   = (int) $matches[1];
                $reason_phrase = trim( $matches[2] );
            }
        }

        return [ $status_code, $reason_phrase ];
    }

    /**
     * Build redirect history from curl_getinfo and intermediate status lines.
     *
     * @param array<int,string>    $response_headers
     * @param array<string, mixed> $info  curl_getinfo() result.
     * @return array<int,string>
     */
    protected function extract_redirect_history( array $response_headers, array $info ): array {
        $history      = [];
        $redirect_url = $info['redirect_url'] ?? '';

        // Collect Location headers from intermediate responses.
        foreach ( $response_headers as $header ) {
            if ( preg_match( '/^Location:\s*(.+)/i', trim( $header ), $matches ) ) {
                $history[] = trim( $matches[1] );
            }
        }

        // The effective (final) URL is always appended last.
        $effective_url = $info['url'] ?? '';
        if ( $effective_url !== '' ) {
            array_unshift( $history, $effective_url );
            $history   = array_reverse( $history );
        }

        return $history;
    }

    /**
     * Parse raw header lines into a normalised associative array.
     *
     * Header names are lowercased for case-insensitive access.
     * Multiple values for the same header are joined with ", ".
     *
     * @param array<int,string> $raw_headers
     * @return array<string,string>
     */
    protected function parse_headers( array $raw_headers ): array {
        $parsed = [];

        foreach ( $raw_headers as $line ) {
            if ( strpos( $line, ':' ) === false ) {
                continue;
            }

            [ $name, $value ] = explode( ':', $line, 2 );
            $key = strtolower( trim( $name ) );

            if ( isset( $parsed[ $key ] ) ) {
                $parsed[ $key ] .= ', ' . trim( $value );
            } else {
                $parsed[ $key ] = trim( $value );
            }
        }

        return $parsed;
    }

    /**
     * Parse Set-Cookie headers into a name => value cookie map.
     *
     * Only the cookie name and value are extracted — attributes such as
     * Path, Domain, Expires, and HttpOnly are intentionally discarded
     * as they are not needed for outbound request replay.
     *
     * @param array<int,string> $raw_headers
     * @return array<string,string>
     */
    protected function parse_cookies( array $raw_headers ): array {
        $cookies = [];

        foreach ( $raw_headers as $line ) {
            if ( ! preg_match( '/^Set-Cookie:\s*([^;]+)/i', trim( $line ), $matches ) ) {
                continue;
            }

            $parts = explode( '=', $matches[1], 2 );
            if ( count( $parts ) === 2 ) {
                $cookies[ trim( $parts[0] ) ] = trim( $parts[1] );
            }
        }

        return $cookies;
    }
}