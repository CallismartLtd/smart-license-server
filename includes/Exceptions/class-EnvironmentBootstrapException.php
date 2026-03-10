<?php
/**
 * Environment bootstrap exception class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Exceptionns
 */
namespace SmartLicenseServer\Exceptions;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Environment bootstrap exception class thrown when a required environment
 * configuration is missing.
 */
class EnvironmentBootstrapException extends Exception {
    /**
     * Errro map
     */
    private array $error_map    = [
        'no_db_adapter_found'   => array(
            'status'    => 500,
            'title'     => 'Database Adapter Not Found',
            'message'   => 'No supported database adapter found or initialized.'
        ),
        
        'mis_configuration'   => array(
            'status'    => 500,
            'title'     => 'Misconfiguration',
            'message'   => 'The Smart License Server is misconfigured.'
        ),
        
        'missing_db_config'   => array(
            'status'    => 500,
            'title'     => 'Database Configuration Error',
            'message'   => 'Database configuration DTO must be initialized.'
        ),

        'unknown_error'   => array(
            'status'    => 500,
            'title'     => 'Unknown Errro',
            'message'   => 'An unknown error has occured in the environment configuration.'
        ),
        
    ];

    /**
     * Class contructor.
     * 
     * @param string $code The error code.
     */
    public function __construct( string $code, $message = '' ) {
        $error  = $this->error_map[$code] ?? $this->error_map['unknown_error'];

        $message    = $message ?: $error['message'];
        if ( ! \function_exists( 'smliser_safe_json_encode' ) ) {
            require_once SMLISER_PATH . 'includes/Utils/functions.php';
        }
        
        parent::__construct( $code, $message, $error );
    }
}