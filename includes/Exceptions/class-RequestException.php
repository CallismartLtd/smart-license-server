<?php
/**
 * Specialized Exception for rejecting HTTP requests due to client or access issues.
 *
 * This class abstracts common HTTP status codes for validation, authorization,
 * and rate limiting failures. It is intended to be used by controller layers
 * to fail fast when a request is invalid.
 *
 * @package SmartLicenseServer\Exception
 * @author Callistus
 */

namespace SmartLicenseServer\Exceptions;

use SmartLicenseServer\Exceptions\Exception;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Class RequestException
 */
class RequestException extends Exception {

    /**
     * Map of error slugs to their default data (HTTP Status, Title, and Message).
     *
     * @var array
     */
    protected static $error_map = [

        // 400 Bad Request Errors.
        'invalid_input' => [
            'status'  => 400,
            'title'   => 'Invalid Request',
            'message' => 'One or more required parameters are missing or invalid.'
        ],
        'malformed_json' => [
            'status'  => 400,
            'title'   => 'Invalid Syntax',
            'message' => 'The request body could not be parsed as valid JSON.'
        ],
        'missing_data' => [
            'status'  => 400,
            'title'   => 'Missing Data',
            'message' => 'Required request data (body, header, or query parameter) is missing.'
        ],
        'required_param' => [
            'status'  => 400,
            'title'   => 'Invalid Parameter',
            'message' => 'The please provide the missing request parameter.'
        ],
        'invalid_parameter_type' => [
            'status'  => 400,
            'title'   => 'Type Mismatch',
            'message' => 'A parameter has an unexpected type or format.'
        ],
        'unsupported_media_type' => [
            'status'  => 415,
            'title'   => 'Unsupported Media Type',
            'message' => 'The request content type is not supported by this endpoint.'
        ],
        'validation_failed' => [
            'status'  => 422,
            'title'   => 'Validation Failed',
            'message' => 'Some input fields did not pass validation checks.'
        ],
        'resource_conflict' => [
            'status'  => 409,
            'title'   => 'Conflict',
            'message' => 'The request could not be completed due to a resource conflict.'
        ],
        'duplicate_entry' => [
            'status'  => 409,
            'title'   => 'Duplicate Entry',
            'message' => 'A resource with the same unique identifier already exists.'
        ],
        'email_exists' => [
            'status'  => 409,
            'title'   => 'Email Conflict',
            'message' => 'The provided email is not available.'
        ],

        'app_slug_exists' => [
            'status'  => 409,
            'title'   => 'App Slug Conflict',
            'message' => 'The provided slug for this application is not available.'
        ],
        'precondition_failed' => [
            'status'  => 412,
            'title'   => 'Precondition Failed',
            'message' => 'A required precondition for this request was not met.'
        ],

        // 401 Unauthorized Errors.
        'missing_auth' => [
            'status'  => 401,
            'title'   => 'Unauthorized',
            'message' => 'Authentication is required for this request but was not provided.'
        ],
        'invalid_credentials' => [
            'status'  => 401,
            'title'   => 'Invalid Credentials',
            'message' => 'The provided API key or token is incorrect or expired.'
        ],

        
        'token_expired' => [
            'status'  => 401,
            'title'   => 'Session Expired',
            'message' => 'Your session or authentication token has expired. Please log in again.'
        ],
        'invalid_signature' => [
            'status'  => 401,
            'title'   => 'Invalid Signature',
            'message' => 'The request signature could not be verified.'
        ],
        'unauthorized_scope' => [
            'status'  => 401,
            'title'   => 'Insufficient Scope',
            'message' => 'The provided credentials do not grant access to this resource.'
        ],

        // 403 Forbidden Errors.
        'permission_denied' => [
            'status'  => 403,
            'title'   => 'Forbidden',
            'message' => 'The authenticated user does not have permission to perform this action.'
        ],
        'ip_blocked' => [
            'status'  => 403,
            'title'   => 'Access Denied',
            'message' => 'Access from your IP address has been blocked for security reasons.'
        ],
        'csrf_violation' => [
            'status'  => 403,
            'title'   => 'CSRF Verification Failed',
            'message' => 'Security token verification failed for this request.'
        ],
        'license_suspended' => [
            'status'  => 403,
            'title'   => 'License Suspended',
            'message' => 'The license or subscription for this request has been suspended.'
        ],
        'access_restricted' => [
            'status'  => 403,
            'title'   => 'Restricted Access',
            'message' => 'This operation is restricted under current system settings.'
        ],

        // 404 Not Found Errors.
        'endpoint_not_found' => [
            'status'  => 404,
            'title'   => 'Not Found',
            'message' => 'The requested API endpoint does not exist.'
        ],

        'resource_not_found' => [
            'status'  => 404,
            'title'   => 'Resource Not Found',
            'message' => 'The requested resource could not be located.'
        ],
        'resource_owner_not_found' => [
            'status'  => 404,
            'title'   => 'Resource Owner Not Found',
            'message' => 'The resource owner does not exist.'
        ],

        // 405 Method Not Allowed.
        'method_not_allowed' => [
            'status'  => 405,
            'title'   => 'Method Not Allowed',
            'message' => 'The request method is not supported for this resource.'
        ],

        // 408 Request Timeout
        'request_timeout' => [
            'status'  => 408,
            'title'   => 'Request Timeout',
            'message' => 'The server timed out waiting for the request to complete.'
        ],

        // 409–429 Rate/Quota/Conflict Related.
        'rate_limited' => [
            'status'  => 429,
            'title'   => 'Rate Limit Exceeded',
            'message' => 'You have exceeded the maximum allowed requests in this time window.'
        ],
        'quota_exceeded' => [
            'status'  => 429,
            'title'   => 'Quota Exceeded',
            'message' => 'You have used all available quota for this operation.'
        ],

        // 500 Internal Errors
        'internal_server_error' => [
            'status'  => 500,
            'title'   => 'Internal Server Error',
            'message' => 'An unexpected server error occurred while processing the request.'
        ],
        'database_error' => [
            'status'  => 500,
            'title'   => 'Database Error',
            'message' => 'A database error occurred while handling the request.'
        ],
        'filesystem_error' => [
            'status'  => 500,
            'title'   => 'Filesystem Error',
            'message' => 'A file or directory operation failed unexpectedly.'
        ],
        'service_unavailable' => [
            'status'  => 503,
            'title'   => 'Service Unavailable',
            'message' => 'The service is temporarily unavailable or under maintenance.'
        ],
        'not_implemented' => [
            'status'  => 501,
            'title'   => 'Method not implemented',
            'message' => 'The server does not support the action requested.'
        ],
        'gateway_timeout' => [
            'status'  => 504,
            'title'   => 'Gateway Timeout',
            'message' => 'The upstream service did not respond in time.'
        ],
        'dependency_failure' => [
            'status'  => 502,
            'title'   => 'Bad Gateway',
            'message' => 'An external dependency or microservice failed to respond correctly.'
        ],

    ];

    /**
     * Constructor for RequestException.
     *
     * Creates the exception by passing a known error slug, which automatically
     * loads the correct HTTP status code, title, and default message.
     *
     * @param string $error_slug The known error code (must exist in $error_map keys).
     * @param string|null $custom_message Optional custom message.
     * @param mixed $custom_data Optional custom data array to merge with defaults.
     */
    public function __construct( string $error_slug, ?string $custom_message = null, $custom_data = [] ) {        
        
        if ( ! isset( static::$error_map[ $error_slug ] ) ) {
            $error_slug = 'invalid_input';
        }

        $default_data = static::$error_map[ $error_slug ];
        $message      = $custom_message ?: $default_data['message'];
        
        $data = array_merge( 
            [ 
                'status' => $default_data['status'], 
                'title'  => $default_data['title'],
            ], 
            $custom_data 
        );

        // Pass the constructed data up to the base Exception class.
        parent::__construct( $error_slug, $message, $data );
    }
}