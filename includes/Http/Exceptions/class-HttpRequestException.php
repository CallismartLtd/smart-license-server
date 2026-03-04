<?php
/**
 * HTTP Request Exception.
 *
 * Thrown when a connection-level failure occurs during an HTTP request,
 * such as a DNS resolution failure, refused connection, or socket error.
 *
 * @package SmartLicenseServer\Http\Exceptions
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Http\Exceptions;

use RuntimeException;

defined( 'SMLISER_ABSPATH' ) || exit;

class HttpRequestException extends RuntimeException {}