<?php
/**
 * HTTP Response Value Object.
 *
 * Immutable representation of a received HTTP response.
 * Returned by all HttpAdapterInterface implementations.
 *
 * @package SmartLicenseServer\Http
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http;

defined( 'SMLISER_ABSPATH' ) || exit;

class HttpResponse {
    use HttpStatusAwareTrait;

    /**
     * @param int                          $status_code    HTTP status code.
     * @param string                       $reason_phrase  HTTP reason phrase (e.g. "OK", "Not Found").
     * @param array<string,string>         $headers        Normalised response headers keyed by lowercase name.
     * @param string                       $body           Raw response body.
     * @param array<string,string>         $cookies        Cookies parsed from Set-Cookie headers.
     * @param array<int,string>            $redirect_history  Ordered list of URLs followed during redirects.
     * @param float                        $latency        Request duration in seconds.
     * @param null|string                  $sink_path      Absolute path where the response body is written to(optional).
     * @param null|int                     $file_size      The file size in bytes.
     */
    public function __construct(
        public readonly int     $status_code,
        public readonly string  $reason_phrase,
        public readonly array   $headers          = [],
        public readonly string  $body             = '',
        public readonly array   $cookies          = [],
        public readonly array   $redirect_history = [],
        public readonly float   $latency          = 0.0,
        public readonly ?string $sink_path        = null,
        public readonly ?int    $file_size        = null,
    ) {}

    /*
    |--------------------
    | STATUS HELPERS
    |--------------------
    */

    /**
     * Tells whether the request was ok.
     * 
     * @return bool
     */
    public function ok() : bool {
        return $this->is_success();
    }

    /**
     * Whether the response indicates success (2xx).
     *
     * @return bool
     */
    public function is_success(): bool {
        return $this->status_code >= 200 && $this->status_code < 300;
    }

    /**
     * Whether the response indicates a client error (4xx).
     *
     * @return bool
     */
    public function is_client_error(): bool {
        return $this->status_code >= 400 && $this->status_code < 500;
    }

    /**
     * Whether the response indicates a server error (5xx).
     *
     * @return bool
     */
    public function is_server_error(): bool {
        return $this->status_code >= 500 && $this->status_code < 600;
    }

    /**
     * Whether the response indicates any error (4xx or 5xx).
     *
     * @return bool
     */
    public function is_error(): bool {
        return $this->status_code >= 400;
    }

    /**
     * Whether the response was a redirect (3xx).
     *
     * @return bool
     */
    public function is_redirect(): bool {
        return $this->status_code >= 300 && $this->status_code < 400;
    }

    /**
     * Whether this response was streamed to a file rather than buffered.
     *
     * True when the originating request carried a sink path and the
     * adapter successfully wrote the body to disk. In this case $body
     * will be an empty string and $sink_path will hold the file path.
     *
     * @return bool
     */
    public function is_download(): bool {
        return $this->sink_path !== null;
    }

    /*
    |--------------------
    | HEADER HELPERS
    |--------------------
    */

    /**
     * Return a single header value by name (case-insensitive).
     *
     * @param string $name
     * @param string|null $default
     * @return string|null
     */
    public function get_header( string $name, ?string $default = null ): ?string {
        return $this->headers[ strtolower( $name ) ] ?? $default;
    }

    /**
     * Whether a header is present (case-insensitive).
     *
     * @param string $name
     * @return bool
     */
    public function has_header( string $name ): bool {
        return array_key_exists( strtolower( $name ), $this->headers );
    }

    /**
     * Return the Content-Type header value, or an empty string if absent.
     *
     * @return string
     */
    public function content_type(): string {
        return $this->get_header( 'content-type' ) ?? '';
    }

    /*
    |--------------------
    | BODY HELPERS
    |--------------------
    */

    /**
     * Decode the body as JSON and return the result.
     *
     * Returns null if the body is empty or is not valid JSON.
     *
     * @param bool $associative Return associative arrays instead of objects.
     * @return mixed
     */
    public function json( bool $associative = true ): mixed {
        if ( empty( $this->body ) ) {
            return null;
        }

        $decoded = json_decode( $this->body, $associative );

        return ( json_last_error() === JSON_ERROR_NONE ) ? $decoded : null;
    }

    /**
     * Whether the response body is valid JSON.
     *
     * @return bool
     */
    public function is_json(): bool {
        if ( empty( $this->body ) ) {
            return false;
        }

        json_decode( $this->body );
        return json_last_error() === JSON_ERROR_NONE;
    }

    /*
    |--------------------
    | COOKIE HELPERS
    |--------------------
    */

    /**
     * Return a single cookie value by name.
     *
     * @param string      $name
     * @param string|null $default
     * @return string|null
     */
    public function get_cookie( string $name, ?string $default = null ): ?string {
        return $this->cookies[ $name ] ?? $default;
    }

    /**
     * Whether a cookie is present in the response.
     *
     * @param string $name
     * @return bool
     */
    public function has_cookie( string $name ): bool {
        return array_key_exists( $name, $this->cookies );
    }

    /*
    |--------------------
    | REDIRECT HELPERS
    |--------------------
    */

    /**
     * Whether any redirects were followed to arrive at this response.
     *
     * @return bool
     */
    public function was_redirected(): bool {
        return ! empty( $this->redirect_history );
    }

    /**
     * Return the original request URL (before any redirects).
     *
     * @return string|null
     */
    public function original_url(): ?string {
        return $this->redirect_history[0] ?? null;
    }

    /**
     * Return the final URL after all redirects.
     *
     * @return string|null
     */
    public function final_url(): ?string {
        return ! empty( $this->redirect_history )
            ? end( $this->redirect_history )
            : null;
    }

    /**
     * Write the in-memory response body to a file on disk.
     *
     * This is a convenience for buffered (non-sink) responses. For large
     * files use HttpRequest::with_sink() instead so the body is never
     * held in memory at all.
     *
     * Returns false if the response was already streamed to disk via a
     * sink (the body is empty — there is nothing to write), if the
     * directory does not exist or is not writable, or if the write fails.
     *
     * Example:
     *   $response = $client->get( $url );
     *   if ( $response->is_success() ) {
     *       $response->save_to( '/var/downloads/file.zip' );
     *   }
     *
     * @param  string $path  Absolute destination path.
     * @return bool          True on success, false on any failure.
     */
    public function save_to( string $path ): bool {
        // A sink response has an empty body — nothing to write.
        if ( $this->is_download() || $this->body === '' ) {
            return false;
        }

        $dir = dirname( $path );
        if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
            return false;
        }

        return file_put_contents( $path, $this->body ) !== false;
    }
}