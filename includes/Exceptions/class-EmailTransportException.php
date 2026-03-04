<?php
/**
 * Email exception class.
 * 
 * Used for errors related to the security context, authentication, or authorization.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Exceptions;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Email transport exception class.
 * 
 * Provides a standard exception for security-related errors.
 * Can carry an optional code and status for structured error handling.
 */
class EmailTransportException extends Exception {
    function __construct( string $message ) {
        parent::__construct( 'email_error', $message );

    }
}
