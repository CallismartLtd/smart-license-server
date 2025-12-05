<?php
/**
 * Specialized Exception for all FileRequest and FileResponse related errors.
 *
 * This class abstracts common HTTP status codes related to file access, 
 * authentication, availability, and processing failures, providing clear
 * documentation for all file-serving endpoints.
 *
 * @package SmartLicenseServer\Exception
 * @author Callistus
 */

namespace SmartLicenseServer\Exceptions;

use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Class FileRequestException
 */
class FileRequestException extends Exception {

    /**
     * Map of error slugs to their default data (HTTP Status, Title, and Message).
     *
     * @var array
     */
    protected static $error_map = [

        // --- 400 Bad Request Errors (Client-side input problems) ---
        'invalid_app_type_method' => [
            'status'  => 400,
            'title'   => 'Invalid App Type',
            'message' => 'The application type specified in the request is not supported.'
        ],
        'unsupported_repo_type' => [
            'status'  => 400,
            'title'   => 'Unsupported Type',
            'message' => 'The associated repository/filesystem for this application type is not available.'
        ],
        'missing_file_parameter' => [
            'status'  => 400,
            'title'   => 'Missing Parameter',
            'message' => 'Required file identifier (slug or ID) is missing from the request.'
        ],
        'malformed_request' => [
            'status'  => 400,
            'title'   => 'Malformed Request',
            'message' => 'The request format or parameters provided are improperly structured.'
        ],
        'invalid_file_extension' => [
            'status'  => 400,
            'title'   => 'Invalid File Type',
            'message' => 'The uploaded or requested file has an unsupported or mismatched file extension.'
        ],
        'invalid_mime_type' => [
            'status'  => 400,
            'title'   => 'Invalid MIME Type',
            'message' => 'The file MIME type does not match the expected type or is disallowed.'
        ],
        'file_too_large' => [
            'status'  => 400,
            'title'   => 'File Too Large',
            'message' => 'The uploaded file exceeds the maximum size allowed by the server configuration.'
        ],
        'file_upload_error' => [
            'status'  => 400,
            'title'   => 'Upload Failed',
            'message' => 'The file upload process encountered an unexpected error.'
        ],

        // --- 401 Unauthorized Errors ---
        'invalid_token' => [
            'status'  => 401,
            'title'   => 'Unauthorized',
            'message' => 'The provided download token is invalid, expired, or does not match the file.'
        ],
        'auth_header_missing' => [
            'status'  => 401,
            'title'   => 'Unauthorized',
            'message' => 'Authentication credentials are required for this resource but were not provided.'
        ],

        // --- 402 Payment Required Errors ---
        'payment_required' => [
            'status'  => 402,
            'title'   => 'Payment Required',
            'message' => 'This is a monetized application; a valid license or token is required to access the file.'
        ],
        'license_payment_required' => [
            'status'  => 402,
            'title'   => 'Payment Required',
            'message' => 'A valid token is required to access the license document file.'
        ],

        // --- 403 Forbidden Errors ---
        'user_not_authorized' => [
            'status'  => 403,
            'title'   => 'Forbidden',
            'message' => 'The authenticated user does not have permission to access this resource.'
        ],
        'invalid_param_license_id' => [
            'status'  => 400,
            'title'   => 'License Invalid',
            'message' => 'Please privide the license ID in the request parameter.'
        ],
        'license_revoked' => [
            'status'  => 403,
            'title'   => 'License Invalid',
            'message' => 'Access denied because the associated license has been revoked or suspended.'
        ],
        'file_permission_denied' => [
            'status'  => 403,
            'title'   => 'Permission Denied',
            'message' => 'The file exists but cannot be read or written due to insufficient permissions.'
        ],

        // --- 404 Not Found Errors ---
        'app_not_found' => [
            'status'  => 404,
            'title'   => 'App Not Found',
            'message' => 'The requested application (slug or ID) was not found in the repository.'
        ],
        
        'file_not_found' => [
            'status'  => 404,
            'title'   => 'File Not Found',
            'message' => 'The corresponding file could not be located on the server.'
        ],
        'http_404' => [
            'status'  => 404,
            'title'   => 'File Not Found',
            'message' => 'The corresponding file could not be located on the server.'
        ],
        'directory_not_found' => [
            'status'  => 404,
            'title'   => 'Directory Not Found',
            'message' => 'The specified directory or repository path does not exist on the server.'
        ],

        // --- 409 Conflict Errors ---
        'file_already_exists' => [
            'status'  => 409,
            'title'   => 'Conflict',
            'message' => 'A file with the same name already exists in the target directory.'
        ],
        'repo_sync_conflict' => [
            'status'  => 409,
            'title'   => 'Repository Conflict',
            'message' => 'File synchronization conflict: repository or metadata version mismatch detected.'
        ],

        // --- 422 Unprocessable Entity Errors ---
        'file_integrity_failure' => [
            'status'  => 422,
            'title'   => 'File Integrity Error',
            'message' => 'The file failed integrity checks (e.g., corrupted or tampered).'
        ],
        'file_validation_failed' => [
            'status'  => 422,
            'title'   => 'File Validation Error',
            'message' => 'The file does not meet the required validation criteria (e.g., missing manifest or invalid structure).'
        ],

        // --- 500 Internal Server Errors ---
        'file_reading_error' => [
            'status'  => 500,
            'title'   => 'File Reading Error',
            'message' => 'Internal server error: The file exists but cannot be opened or read by the server.'
        ],
        'file_write_error' => [
            'status'  => 500,
            'title'   => 'File Write Error',
            'message' => 'An error occurred while writing the file to disk.'
        ],
        'disk_space_exhausted' => [
            'status'  => 500,
            'title'   => 'Disk Space Error',
            'message' => 'The server has insufficient storage space to complete the requested file operation.'
        ],
        'filesystem_unavailable' => [
            'status'  => 500,
            'title'   => 'Filesystem Error',
            'message' => 'The underlying filesystem or I/O handler is unavailable or not initialized properly.'
        ],
        'unexpected_repo_failure' => [
            'status'  => 500,
            'title'   => 'Repository Error',
            'message' => 'An unexpected failure occurred while communicating with the application repository.'
        ],
        'file_corrupted' => [
            'status'  => 500,
            'title'   => 'Corrupt File',
            'message' => 'The file appears to be corrupted, incomplete, or unreadable.'
        ],
        'unknown_file_error' => [
            'status'  => 500,
            'title'   => 'Unknown File Error',
            'message' => 'An unexpected or undefined file error occurred during processing.'
        ],
    ];

    /**
     * Constructor for FileRequestException.
     *
     * @param string $error_slug The known error code (must exist in $error_map keys).
     * @param string|null $custom_message Optional custom message.
     * @param mixed $custom_data Optional custom data array to merge with defaults.
     */
    public function __construct( string $error_slug, ?string $custom_message = null, $custom_data = [] ) {
        // Fallback to a generic 500 error if the slug is unknown
        if ( ! isset( self::$error_map[ $error_slug ] ) ) {
            $error_slug = 'unknown_file_error';
        }

        $default_data = self::$error_map[ $error_slug ];
        $message      = $custom_message ?: $default_data['message'];
        
        $data = array_merge(
            [
                'status' => $default_data['status'],
                'title'  => $default_data['title'],
            ],
            $custom_data
        );

        parent::__construct( $error_slug, $message, $data );
    }
}
