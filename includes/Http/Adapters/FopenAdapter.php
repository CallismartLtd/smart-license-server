<?php
/**
 * fopen HTTP Adapter.
 *
 * Executes HTTP requests using PHP's file_get_contents() with a
 * stream context. Used as the first fallback when cURL is unavailable.
 * Requires allow_url_fopen = On in php.ini.
 *
 * @package SmartLicenseServer\Http\Adapters
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http\Adapters;

use SmartLicenseServer\Http\HttpAdapterInterface;
use SmartLicenseServer\Http\HttpRequest;
use SmartLicenseServer\Http\HttpResponse;
use SmartLicenseServer\Http\Exceptions\HttpRequestException;
use SmartLicenseServer\Http\Exceptions\HttpTimeoutException;

defined( 'SMLISER_ABSPATH' ) || exit;

class FopenAdapter implements HttpAdapterInterface {

    public function get_id(): string {
        return 'fopen';
    }

    public function is_available(): bool {
        return (bool) ini_get( 'allow_url_fopen' ) && function_exists( 'fopen' );;
    }

    /**
     * Execute the request via file_get_contents() stream context.
     *
     * @param HttpRequest $request
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    public function send( HttpRequest $request ): HttpResponse {
        if ( $request->has_sink() ) {
            return $this->send_to_sink( $request );
        }

        $start      = microtime( true );
        $context    = stream_context_create( $this->build_context( $request ) );
        $body       = @file_get_contents( $request->url, false, $context );
        $latency    = microtime( true ) - $start;

        if ( $body === false ) {
            $error = error_get_last()['message'] ?? 'Unknown error';

            if ( stripos( $error, 'timed out' ) !== false ) {
                throw new HttpTimeoutException(
                    "FopenAdapter: request to '{$request->url}' timed out after {$request->timeout}s."
                );
            }

            throw new HttpRequestException(
                "FopenAdapter: request to '{$request->url}' failed — {$error}."
            );
        }

        $raw_headers    = isset( $http_response_header ) ? $http_response_header : [];

        [ $status_code, $reason_phrase ] = $this->extract_status( $raw_headers );
        $redirect_history                = $this->extract_redirect_history( $raw_headers );
        $parsed_headers                  = $this->parse_headers( $raw_headers );
        $cookies                         = $this->parse_cookies( $raw_headers );

        return new HttpResponse(
            status_code      : $status_code,
            reason_phrase    : $reason_phrase,
            headers          : $parsed_headers,
            body             : $body,
            cookies          : $cookies,
            redirect_history : $redirect_history,
            latency          : $latency,
        );
    }

    /**
     * Execute the request and stream the response body directly to the sink file.
     *
     * Uses fopen() + stream_copy_to_stream() so the response body is never
     * held entirely in PHP memory regardless of file size.
     *
     * @param  HttpRequest $request  Must have a non-null sink.
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    private function send_to_sink( HttpRequest $request ): HttpResponse {
        $start   = microtime( true );
        $context = stream_context_create( $this->build_context( $request ) );

        // Open the remote URL as a readable stream.
        // $http_response_header is populated by fopen() just like file_get_contents().
        $remote = @fopen( $request->url, 'rb', false, $context );

        $latency = microtime( true ) - $start;

        if ( $remote === false ) {
            $error = error_get_last()['message'] ?? 'Unknown error';

            if ( stripos( $error, 'timed out' ) !== false ) {
                throw new HttpTimeoutException(
                    "FopenAdapter: request to '{$request->url}' timed out after {$request->timeout}s."
                );
            }

            throw new HttpRequestException(
                "FopenAdapter: request to '{$request->url}' failed — {$error}."
            );
        }

        // $http_response_header is a PHP magic variable populated by fopen().
        $raw_headers = $http_response_header ?? [];

        [ $status_code, $reason_phrase ] = $this->extract_status( $raw_headers );
        $redirect_history                = $this->extract_redirect_history( $raw_headers );
        $parsed_headers                  = $this->parse_headers( $raw_headers );
        $cookies                         = $this->parse_cookies( $raw_headers );

        // Only stream to disk on 2xx — discard error-response bodies.
        $file_size = null;
        $sink_path = null;

        if ( $status_code >= 200 && $status_code < 300 ) {
            $local = @fopen( $request->sink, 'wb' );

            if ( $local === false ) {
                fclose( $remote );
                throw new HttpRequestException(
                    "FopenAdapter: could not open sink file for writing: '{$request->sink}'."
                );
            }

            $bytes = stream_copy_to_stream( $remote, $local );
            fclose( $local );

            if ( $bytes === false ) {
                @unlink( $request->sink );
                fclose( $remote );
                throw new HttpRequestException(
                    "FopenAdapter: failed to write response body to sink '{$request->sink}'."
                );
            }

            $sink_path = $request->sink;
            $file_size = $bytes;
        }

        fclose( $remote );

        return new HttpResponse(
            status_code      : $status_code,
            reason_phrase    : $reason_phrase,
            headers          : $parsed_headers,
            body             : '',
            cookies          : $cookies,
            redirect_history : $redirect_history,
            latency          : $latency,
            sink_path        : $sink_path,
            file_size        : $file_size,
        );
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Build the stream context options array.
     *
     * @param HttpRequest $request
     * @return array<string, mixed>
     */
    protected function build_context( HttpRequest $request ): array {
        $headers = $request->headers;

        $cookie_header = $request->get_cookie_header();
        if ( $cookie_header !== '' ) {
            $headers['Cookie'] = $cookie_header;
        }

        $header_string = implode( "\r\n", array_map(
            fn( $name, $value ) => "{$name}: {$value}",
            array_keys( $headers ),
            $headers
        ) );

        $http = [
            'method'          => $request->method,
            'timeout'         => $request->timeout,
            'follow_location' => 1,
            'max_redirects'   => $request->max_redirects,
            'ignore_errors'   => true,
            'header'          => $header_string,
        ];

        if ( $request->has_body() ) {
            $http['content'] = $request->body;
        }

        $ssl = [
            'verify_peer'       => $request->verify_ssl,
            'verify_peer_name'  => $request->verify_ssl,
            'SNI_enabled'       => true,
        ];

        return [ 'http' => $http, 'ssl' => $ssl ];
    }

    /**
     * Extract the final HTTP status code and reason phrase.
     *
     * @param array<int,string> $raw_headers
     * @return array{int, string}
     */
    protected function extract_status( array $raw_headers ): array {
        $status_code   = 0;
        $reason_phrase = '';

        foreach ( $raw_headers as $line ) {
            if ( preg_match( '/^HTTP\/\S+\s+(\d{3})\s*(.*)/i', trim( $line ), $matches ) ) {
                $status_code   = (int) $matches[1];
                $reason_phrase = trim( $matches[2] );
            }
        }

        return [ $status_code, $reason_phrase ];
    }

    /**
     * Extract redirect history from Location headers.
     *
     * @param array<int,string> $raw_headers
     * @return array<int,string>
     */
    protected function extract_redirect_history( array $raw_headers ): array {
        $history = [];

        foreach ( $raw_headers as $line ) {
            if ( preg_match( '/^Location:\s*(.+)/i', trim( $line ), $matches ) ) {
                $history[] = trim( $matches[1] );
            }
        }

        return $history;
    }

    /**
     * Parse raw header lines into a normalised associative array.
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
     * Parse Set-Cookie headers into a name => value map.
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