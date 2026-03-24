<?php
/**
 * HTTP Status Utilities Trait
 *
 * Provides HTTP status reason phrases and helpers.
 *
 * @package SmartLicenseServer\Http
 */

namespace SmartLicenseServer\Http;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Trait HttpStatusTrait
 */
trait HttpStatusAwareTrait {

    /**
     * Get default reason phrase for a status code.
     *
     * @param int $code HTTP status code.
     * @return string
     */
    public static function reason_phrase( int $code ) : string {
        static $phrases = array(

            // 1xx Informational.
            100 => 'Continue',
            101 => 'Switching Protocols',

            // 2xx Success.
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // 3xx Redirection.
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect',

            // 4xx Client Error.
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Payload Too Large',
            414 => 'URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Range Not Satisfiable',
            417 => 'Expectation Failed',
            429 => 'Too Many Requests',
            451 => 'Unavailable For Legal Reasons',

            // 5xx Server Error.
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
        );

        return isset( $phrases[ $code ] ) ? $phrases[ $code ] : 'Unknown Status';
    }

    /**
     * Determine if status code is successful.
     *
     * @param int $code
     * @return bool
     */
    public static function is_success( int $code ) : bool {
        return $code >= 200 && $code < 300;
    }

    /**
     * Determine if status code is a redirect.
     *
     * @param int $code
     * @return bool
     */
    public static function is_redirect( int $code ) : bool {
        return $code >= 300 && $code < 400;
    }

    /**
     * Determine if status code is client error.
     *
     * @param int $code
     * @return bool
     */
    public static function is_client_error( int $code ) : bool {
        return $code >= 400 && $code < 500;
    }

    /**
     * Determine if status code is server error.
     *
     * @param int $code
     * @return bool
     */
    public static function is_server_error( int $code ) : bool {
        return $code >= 500 && $code < 600;
    }

    /**
     * Determine if status code is an error.
     *
     * @param int $code
     * @return bool
     */
    public static function is_error( int $code ) : bool {
        return $code >= 400;
    }
}