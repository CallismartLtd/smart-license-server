<?php
/**
 * HTTP Client.
 *
 * Central HTTP client that auto-detects the best available transport
 * adapter (cURL → fopen → socket) or accepts an injected adapter.
 *
 * Usage — auto-detection:
 *
 *   $client   = new HttpClient();
 *   $request  = HttpRequest::post( 'https://api.example.com/send', $body )
 *                   ->with_header( 'Authorization', 'Bearer ' . $token )
 *                   ->with_json( $data );
 *   $response = $client->send( $request );
 *
 *   if ( $response->is_success() ) {
 *       $data = $response->json();
 *   }
 *
 * Usage — explicit adapter:
 *
 *   $client = new HttpClient( new CurlAdapter() );
 *
 * @package SmartLicenseServer\Http
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http;

use SmartLicenseServer\Http\Adapters\CurlAdapter;
use SmartLicenseServer\Http\Adapters\FopenAdapter;
use SmartLicenseServer\Http\Adapters\SocketAdapter;
use SmartLicenseServer\Http\Exceptions\HttpRequestException;

defined( 'SMLISER_ABSPATH' ) || exit;

class HttpClient {

    /**
     * The resolved adapter used for all requests.
     *
     * @var HttpAdapterInterface
     */
    protected HttpAdapterInterface $adapter;

    /**
     * Default headers merged into every request.
     *
     * @var array<string,string>
     */
    protected array $default_headers = [];

    /**
     * Constructor.
     *
     * If no adapter is provided, the best available adapter is
     * auto-detected in priority order: cURL → fopen → socket.
     *
     * @param HttpAdapterInterface|null $adapter Optional explicit adapter.
     * @throws HttpRequestException If no adapter is available.
     */
    public function __construct( ?HttpAdapterInterface $adapter = null ) {
        $this->adapter = $adapter ?? $this->resolve_adapter();
    }

    /*
    |---------------
    | CONFIGURATION
    |---------------
    */

    /**
     * Set a default header applied to every request.
     *
     * Can be used to set a persistent Authorization header, User-Agent, etc.
     *
     * @param string $name
     * @param string $value
     * @return static Fluent.
     */
    public function with_default_header( string $name, string $value ): static {
        $this->default_headers[ $name ] = $value;
        return $this;
    }

    /**
     * Return the adapter currently in use.
     *
     * @return HttpAdapterInterface
     */
    public function get_adapter(): HttpAdapterInterface {
        return $this->adapter;
    }

    /*
    |---------------------
    | CONVENIENCE METHODS
    |---------------------
    */

    /**
     * Send a GET request.
     *
     * @param string               $url
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function get( string $url, array $headers = [], array $options = [] ): HttpResponse {
        return $this->send( HttpRequest::get( $url, $headers, $options ) );
    }

    /**
     * Send a POST request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function post( string $url, string $body = '', array $headers = [], array $options = [] ): HttpResponse {
        return $this->send( HttpRequest::post( $url, $body, $headers, $options ) );
    }

    /**
     * Send a PUT request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function put( string $url, string $body = '', array $headers = [], array $options = [] ): HttpResponse {
        return $this->send( HttpRequest::put( $url, $body, $headers, $options ) );
    }

    /**
     * Send a PATCH request.
     *
     * @param string               $url
     * @param string               $body
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function patch( string $url, string $body = '', array $headers = [], array $options = [] ): HttpResponse {
        return $this->send( HttpRequest::patch( $url, $body, $headers, $options ) );
    }

    /**
     * Send a DELETE request.
     *
     * @param string               $url
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function delete( string $url, array $headers = [], array $options = [] ): HttpResponse {
        return $this->send( HttpRequest::delete( $url, $headers, $options ) );
    }

    /**
     * Send a POST request with a JSON body.
     *
     * Automatically sets Content-Type and Accept headers.
     *
     * @param string               $url
     * @param array<string,mixed>  $data
     * @param array<string,string> $headers
     * @param array<string,mixed>  $options
     * @return HttpResponse
     */
    public function post_json( string $url, array $data = [], array $headers = [], array $options = [] ): HttpResponse {
        $request = HttpRequest::post( $url, '', $headers, $options )->with_json( $data );
        return $this->send( $request );
    }

    /*
    |--------
    | SEND
    |--------
    */

    /**
     * Send an HttpRequest using the resolved adapter.
     *
     * Default headers are merged in before dispatch, with per-request
     * headers taking precedence over defaults.
     *
     * @param HttpRequest $request
     * @return HttpResponse
     */
    public function send( HttpRequest $request ): HttpResponse {
        $request = $this->apply_default_headers( $request );
        return $this->adapter->send( $request );
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Resolve the best available adapter in priority order.
     *
     * Priority: cURL → fopen → socket
     *
     * @return HttpAdapterInterface
     * @throws HttpRequestException If no adapter is available.
     */
    protected function resolve_adapter(): HttpAdapterInterface {
        $candidates = [
            new CurlAdapter(),
            new FopenAdapter(),
            new SocketAdapter(),
        ];

        foreach ( $candidates as $candidate ) {
            if ( $candidate->is_available() ) {
                return $candidate;
            }
        }

        throw new HttpRequestException(
            'HttpClient: no HTTP adapter is available in this environment. '
            . 'Enable cURL, allow_url_fopen, or fsockopen.'
        );
    }

    /**
     * Merge default headers into the request, preserving per-request overrides.
     *
     * @param HttpRequest $request
     * @return HttpRequest
     */
    protected function apply_default_headers( HttpRequest $request ): HttpRequest {
        if ( empty( $this->default_headers ) ) {
            return $request;
        }

        // Per-request headers take precedence — defaults only fill gaps.
        $merged = array_merge( $this->default_headers, $request->headers );

        return new HttpRequest(
            method        : $request->method,
            url           : $request->url,
            headers       : $merged,
            body          : $request->body,
            timeout       : $request->timeout,
            verify_ssl    : $request->verify_ssl,
            max_redirects : $request->max_redirects,
            cookies       : $request->cookies,
        );
    }
}