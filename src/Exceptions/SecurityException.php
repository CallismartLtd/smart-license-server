<?php
/**
 * Security exception class.
 * 
 * Used for errors related to the security context, authentication, or authorization.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Exceptions;

/**
 * Class SecurityException
 * 
 * Provides a standard exception for security-related errors.
 * Can carry an optional code and status for structured error handling.
 */
class SecurityException extends Exception {}
