<?php
/**
 * File system exception class.
 * 
 * Used for errors related to the file system.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

namespace SmartLicenseServer\Exceptions;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Class FileSystemException
 * 
 * Provides a standard exception for filesystem-related errors.
 * Can carry an optional code and status for structured error handling.
 */
class FileSystemException extends Exception {
    public function __construct( string $message ) {
        parent::__construct( 'file_system_error', $message );
    }
}
