<?php
/**
 * Socket HTTP Adapter.
 *
 * Executes HTTP requests using raw PHP fsockopen() streams.
 * Used as the final fallback when both cURL and fopen are unavailable.
 * Supports HTTP/1.1, SSL via ssl:// wrapper, redirects, and cookies.
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

class SocketAdapter implements HttpAdapterInterface {

    protected const SOCKET_READ_LENGTH  = 8192;
    protected const MAX_HEADER_BYTES    = 65536;

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
            // ← Pass sink so execute() can forward it to read_response().
            $response = $this->execute( $request, $current_url, $request->sink );

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
            sink_path        : $response->sink_path,   // ← propagate
            file_size        : $response->file_size,   // ← propagate
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
     * @param HttpRequest $request
     * @param string      $url
     * @param string|null $sink   Absolute path to stream the body into, or null.
     * @return HttpResponse
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    protected function execute( HttpRequest $request, string $url, ?string $sink = null ): HttpResponse {
        $parsed = parse_url( $url );

        if ( $parsed === false || empty( $parsed['host'] ) ) {
            throw new HttpRequestException(
                "SocketAdapter: could not parse URL '{$url}'."
            );
        }

        $scheme      = $parsed['scheme'] ?? 'http';
        $host        = $parsed['host'];
        $port        = $parsed['port'] ?? ( $scheme === 'https' ? 443 : 80 );
        $path        = ( $parsed['path'] ?? '/' )
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

        if ( $sink !== null ) {
            return $this->read_response_to_sink( $socket, $request->timeout, $sink );
        }

        $raw = $this->read_response( $socket, $request->timeout );
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
            'User-Agent'      => 'SmartLicenseServer/0.2',
        ], $request->headers );

        $cookie_header = $request->get_cookie_header();
        if ( $cookie_header !== '' ) {
            $headers['Cookie'] = $cookie_header;
        }

        if ( $request->has_body() ) {
            $headers['Content-Length'] = (string) strlen( $request->body );
        }

        $header_lines = [];

        foreach ( $headers as $name => $value ) {
            $header_lines[] = "{$name}: {$value}";
        };

        $raw  = "{$request->method} {$path} HTTP/1.1{$eol}";
        $raw .= implode( $eol, $header_lines ) . $eol . $eol;

        if ( $request->has_body() ) {
            $raw .= $request->body;
        }

        $length  = strlen( $raw );
        $written = 0;

        while ( $written < $length ) {
            $result = fwrite( $socket, substr( $raw, $written ) );

            if ( $result === false ) {
                throw new HttpRequestException(
                    'SocketAdapter: failed to write request to socket.'
                );
            }

            $written += $result;
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
            if ( microtime( true ) > $deadline ) {
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

    /**
     * Read the HTTP response from the socket, streaming the body to a file.
     *
     * The header block is buffered normally (it is always small).
     * Once the \r\n\r\n separator is found, subsequent bytes are written
     * directly to the sink file in the same 8 KB chunks fread() delivers
     * them — no body string is ever built in memory.
     *
     * @param  resource   $socket
     * @param  int        $timeout
     * @param  string     $sink     Absolute path to write the body into.
     * @return HttpResponse         Body is '' — sink_path / file_size carry the result.
     * @throws HttpTimeoutException
     * @throws HttpRequestException
     */
    protected function read_response_to_sink( $socket, int $timeout, string $sink ): HttpResponse {
        $deadline   = time() + $timeout;
        $header_buf = '';           // accumulates raw bytes until \r\n\r\n
        $separator  = "\r\n\r\n";
        $sep_len    = strlen( $separator );
        $headers_done = false;
        $sink_handle  = null;
        $bytes_written = 0;

        while ( ! feof( $socket ) ) {
            if ( microtime( true ) > $deadline ) {
                fclose( $socket );
                if ( $sink_handle ) { fclose( $sink_handle ); @unlink( $sink ); }
                throw new HttpTimeoutException(
                    'SocketAdapter: timed out while reading server response.'
                );
            }

            $chunk = fread( $socket, self::SOCKET_READ_LENGTH );

            if ( $chunk === false ) {
                $meta = stream_get_meta_data( $socket );
                fclose( $socket );
                if ( $sink_handle ) { fclose( $sink_handle ); @unlink( $sink ); }
                if ( $meta['timed_out'] ) {
                    throw new HttpTimeoutException(
                        'SocketAdapter: stream timed out while reading response.'
                    );
                }
                throw new HttpRequestException(
                    'SocketAdapter: failed to read from socket.'
                );
            }

            if ( ! $headers_done ) {
                $header_buf .= $chunk;

                if ( strlen( $header_buf ) > self::MAX_HEADER_BYTES ) {
                    fclose( $socket );
                    throw new HttpRequestException(
                        'SocketAdapter: header block exceeded maximum allowed size.'
                    );
                }
                
                $pos = strpos( $header_buf, $separator );

                if ( $pos !== false ) {
                    // Everything after the separator is body data.
                    $body_fragment = substr( $header_buf, $pos + $sep_len );
                    $header_buf    = substr( $header_buf, 0, $pos );
                    $headers_done  = true;

                    // Parse status + headers from the buffered header block.
                    $header_lines   = explode( "\r\n", $header_buf );
                    [ $status_code, $reason_phrase ] = $this->extract_status( $header_lines );
                    $parsed_headers = $this->parse_headers( $header_lines );
                    $cookies        = $this->parse_cookies( $header_lines );

                    // Only open the sink for 2xx responses.
                    if ( $status_code >= 200 && $status_code < 300 ) {
                        $sink_handle = @fopen( $sink, 'wb' );
                        if ( $sink_handle === false ) {
                            fclose( $socket );
                            throw new HttpRequestException(
                                "SocketAdapter: could not open sink file for writing: '{$sink}'."
                            );
                        }

                        if ( $body_fragment !== '' ) {
                            fwrite( $sink_handle, $body_fragment );
                            $bytes_written += strlen( $body_fragment );
                        }
                    }
                    // Non-2xx: discard body — continue reading to drain socket cleanly.
                }
                // Haven't found the separator yet — keep buffering.
                continue;
            }

            // Headers are done — write body chunk to sink (if open).
            if ( $sink_handle !== null ) {
                fwrite( $sink_handle, $chunk );
                $bytes_written += strlen( $chunk );
            }
        }

        fclose( $socket );

        if ( $sink_handle !== null ) {
            fclose( $sink_handle );
        }

        // If we never found the header separator something went very wrong.
        if ( ! $headers_done ) {
            throw new HttpRequestException(
                'SocketAdapter: could not locate header/body separator in response.'
            );
        }

        $is_success = $status_code >= 200 && $status_code < 300;

        return new HttpResponse(
            status_code      : $status_code,
            reason_phrase    : $reason_phrase,
            headers          : $parsed_headers,
            body             : '',
            cookies          : $cookies,
            redirect_history : [],
            latency          : 0.0,
            sink_path        : $is_success ? $sink        : null,
            file_size        : $is_success ? $bytes_written : null,
        );
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

        $header_block   = substr( $raw, 0, $pos );
        $body           = substr( $raw, $pos + strlen( $separator ) );
        $header_lines   = explode( "\r\n", $header_block );

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

        $base = parse_url( $current_url );

        if ( ! isset( $base['scheme'], $base['host'] ) ) {
            return $location;
        }

        $root = $base['scheme'] . '://' . $base['host'];

        if ( str_starts_with( $location, '/' ) ) {
            return $root . $location;
        }

        $path = $base['path'] ?? '/';
        $dir  = rtrim( dirname( $path ), '/' );

        return $root . $dir . '/' . $location;
    }
}