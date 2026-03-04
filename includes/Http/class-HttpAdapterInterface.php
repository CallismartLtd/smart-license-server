<?php
/**
 * HTTP Adapter Interface.
 *
 * Contract that all HTTP transport adapters must implement.
 * Adapters are responsible for executing an HttpRequest and
 * returning a populated HttpResponse.
 *
 * @package SmartLicenseServer\Http
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http;

use SmartLicenseServer\Http\Exceptions\HttpRequestException;
use SmartLicenseServer\Http\Exceptions\HttpTimeoutException;

defined( 'SMLISER_ABSPATH' ) || exit;

interface HttpAdapterInterface {

    /**
     * Return a unique adapter identifier.
     *
     * Example: "curl", "fopen", "socket"
     *
     * @return string
     */
    public function get_id(): string;

    /**
     * Whether this adapter is available in the current PHP environment.
     *
     * Used by HttpClient during auto-detection to skip unavailable adapters.
     *
     * @return bool
     */
    public function is_available(): bool;

    /**
     * Execute an HTTP request and return the response.
     *
     * @param HttpRequest $request
     * @return HttpResponse
     *
     * @throws HttpTimeoutException  If the request exceeds the configured timeout.
     * @throws HttpRequestException  On any other connection-level failure.
     */
    public function send( HttpRequest $request ): HttpResponse;
}