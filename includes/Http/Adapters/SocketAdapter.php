<?php
/**
 * Socket HTTP Adapter.
 *
 * Executes HTTP requests using raw PHP fsockopen() streams.
 * Used as the final fallback when both cURL and fopen are unavailable.
 * Supports HTTP/1.1, SSL via ssl:// wrapper, redirects, and cookies.
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

class SocketAdapter implements HttpAdapterInterface {

    protected const SOCKET_READ_LENGTH = 8192;

    public function get_id(): string {
        return 'socket';
    }

    public function is_available(): bool {
        return function_exists( 'fsockopen' );
    }

    /**
     * Execute the request via raw PHP socket.
     *
     * @param HttpRequest $request
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    public function send( HttpRequest $request ): HttpResponse {
        $start            = microtime( true );
        $redirect_history = [];
        $current_url      = $request->url;
        $redirects        = 0;

        do {
            $response = $this->execute( $request, $current_url );

            if ( $response->is_redirect() ) {
                $location = $response->get_header( 'location' );

                if ( $location === null || $redirects >= $request->max_redirects ) {
                    break;
                }

                $redirect_history[] = $current_url;
                $current_url        = $this->resolve_url( $location, $current_url );
                $redirects++;
                continue;
            }

            break;
        } while ( true );

        // Append the final URL to complete the history.
        if ( ! empty( $redirect_history ) ) {
            $redirect_history[] = $current_url;
        }

        $latency = microtime( true ) - $start;

        return new HttpResponse(
            status_code      : $response->status_code,
            reason_phrase    : $response->reason_phrase,
            headers          : $response->headers,
            body             : $response->body,
            cookies          : $response->cookies,
            redirect_history : $redirect_history,
            latency          : $latency,
        );
    }

    /*
    |---------
    | EXECUTE
    |---------
    */

    /**
     * Perform a single HTTP request against a given URL.
     *
     * @param HttpRequest $request  Original request (method, headers, body, options).
     * @param string      $url      The URL to connect to (may differ from request->url after redirects).
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    protected function execute( HttpRequest $request, string $url ): HttpResponse {
        $parsed = parse_url( $url );

        if ( $parsed === false || empty( $parsed['host'] ) ) {
            throw new HttpRequestException(
                "SocketAdapter: could not parse URL '{$url}'."
            );
        }

        $scheme = $parsed['scheme'] ?? 'http';
        $host   = $parsed['host'];
        $port   = $parsed['port'] ?? ( $scheme === 'https' ? 443 : 80 );
        $path   = ( $parsed['path'] ?? '/' )
            . ( isset( $parsed['query'] ) ? '?' . $parsed['query'] : '' );

        $socket_host = ( $scheme === 'https' ) ? "ssl://{$host}" : $host;

        $errno  = 0;
        $errstr = '';

        $socket = fsockopen( $socket_host, $port, $errno, $errstr, $request->timeout );

        if ( $socket === false ) {
            if ( $errno === 110 || stripos( $errstr, 'timed out' ) !== false ) {
                throw new HttpTimeoutException(
                    "SocketAdapter: connection to '{$host}:{$port}' timed out."
                );
            }

            throw new HttpRequestException(
                "SocketAdapter: could not connect to '{$host}:{$port}' — {$errstr} ({$errno})."
            );
        }

        stream_set_timeout( $socket, $request->timeout );

        $this->write_request( $socket, $request, $host, $path );

        $raw      = $this->read_response( $socket, $request->timeout );
        fclose( $socket );

        return $this->parse_raw_response( $raw );
    }

    /**
     * Write the HTTP request to the socket.
     *
     * @param resource    $socket
     * @param HttpRequest $request
     * @param string      $host
     * @param string      $path
     * @throws HttpRequestException
     */
    protected function write_request( $socket, HttpRequest $request, string $host, string $path ): void {
        $eol     = "\r\n";
        $headers = array_merge( [
            'Host'            => $host,
            'Connection'      => 'close',
            'Accept'          => '*/*',
            'Accept-Encoding' => 'identity',
        ], $request->headers );

        $cookie_header = $request->get_cookie_header();
        if ( $cookie_header !== '' ) {
            $headers['Cookie'] = $cookie_header;
        }

        if ( $request->has_body() ) {
            $headers['Content-Length'] = (string) strlen( $request->body );
        }

        $header_lines = array_map(
            fn( $name, $value ) => "{$name}: {$value}",
            array_keys( $headers ),
            $headers
        );

        $raw  = "{$request->method} {$path} HTTP/1.1{$eol}";
        $raw .= implode( $eol, $header_lines ) . $eol . $eol;

        if ( $request->has_body() ) {
            $raw .= $request->body;
        }

        if ( fwrite( $socket, $raw ) === false ) {
            throw new HttpRequestException(
                'SocketAdapter: failed to write request to socket.'
            );
        }
    }

    /**
     * Read the full HTTP response from the socket.
     *
     * @param resource $socket
     * @param int      $timeout
     * @return string
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    protected function read_response( $socket, int $timeout ): string {
        $raw       = '';
        $deadline  = time() + $timeout;

        while ( ! feof( $socket ) ) {
            if ( time() > $deadline ) {
                throw new HttpTimeoutException(
                    'SocketAdapter: timed out while reading server response.'
                );
            }

            $chunk = fread( $socket, self::SOCKET_READ_LENGTH );

            if ( $chunk === false ) {
                $meta = stream_get_meta_data( $socket );
                if ( $meta['timed_out'] ) {
                    throw new HttpTimeoutException(
                        'SocketAdapter: stream timed out while reading response.'
                    );
                }
                throw new HttpRequestException(
                    'SocketAdapter: failed to read from socket.'
                );
            }

            $raw .= $chunk;
        }

        return $raw;
    }

    /*
    |----------------
    | RESPONSE PARSE
    |----------------
    */

    /**
     * Parse a raw HTTP response string into an HttpResponse.
     *
     * Separates the header block from the body on the first blank line.
     *
     * @param string $raw
     * @return HttpResponse
     * @throws HttpRequestException
     */
    protected function parse_raw_response( string $raw ): HttpResponse {
        $separator = "\r\n\r\n";
        $pos       = strpos( $raw, $separator );

        if ( $pos === false ) {
            throw new HttpRequestException(
                'SocketAdapter: could not locate header/body separator in response.'
            );
        }

        $header_block = substr( $raw, 0, $pos );
        $body         = substr( $raw, $pos + strlen( $separator ) );
        $header_lines = explode( "\r\n", $header_block );

        [ $status_code, $reason_phrase ] = $this->extract_status( $header_lines );

        $parsed_headers = $this->parse_headers( $header_lines );
        $cookies        = $this->parse_cookies( $header_lines );

        return new HttpResponse(
            status_code      : $status_code,
            reason_phrase    : $reason_phrase,
            headers          : $parsed_headers,
            body             : $body,
            cookies          : $cookies,
            redirect_history : [],
            latency          : 0.0,
        );
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Extract status code and reason phrase from header lines.
     *
     * @param array<int,string> $lines
     * @return array{int, string}
     */
    protected function extract_status( array $lines ): array {
        foreach ( $lines as $line ) {
            if ( preg_match( '/^HTTP\/\S+\s+(\d{3})\s*(.*)/i', trim( $line ), $matches ) ) {
                return [ (int) $matches[1], trim( $matches[2] ) ];
            }
        }
        return [ 0, '' ];
    }

    /**
     * Parse raw header lines into a normalised associative array.
     *
     * @param array<int,string> $lines
     * @return array<string,string>
     */
    protected function parse_headers( array $lines ): array {
        $parsed = [];

        foreach ( $lines as $line ) {
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
     * @param array<int,string> $lines
     * @return array<string,string>
     */
    protected function parse_cookies( array $lines ): array {
        $cookies = [];

        foreach ( $lines as $line ) {
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

    /**
     * Resolve a potentially relative Location header against the current URL.
     *
     * @param string $location    Value from the Location header.
     * @param string $current_url The URL that issued the redirect.
     * @return string
     */
    protected function resolve_url( string $location, string $current_url ): string {
        if ( preg_match( '/^https?:\/\//i', $location ) ) {
            return $location;
        }

        $parsed = parse_url( $current_url );
        $base   = ( $parsed['scheme'] ?? 'http' ) . '://' . ( $parsed['host'] ?? '' );

        return $base . '/' . ltrim( $location, '/' );
    }
}