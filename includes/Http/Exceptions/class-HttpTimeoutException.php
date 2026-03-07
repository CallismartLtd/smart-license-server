<?php
/**
 * HTTP Timeout Exception.
 *
 * Thrown when a request exceeds the configured timeout duration.
 * Extends HttpRequestException so callers can catch either specifically
 * or together.
 *
 * @package SmartLicenseServer\Http\Exceptions
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http\Exceptions;

defined( 'SMLISER_ABSPATH' ) || exit;

class HttpTimeoutException extends HttpRequestException {}