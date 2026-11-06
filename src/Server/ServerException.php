<?php
/**
 * Server exception class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Exceptions
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Exceptions;

/**
 * Thrown when the development server cannot be started, stopped or managed.
 *
 * The message is written for the operator and is safe to print as it is.
 */
class ServerException extends Exception {

	/**
	 * Constructor.
	 *
	 * @param string $message What went wrong, and what to do about it.
	 */
	public function __construct( string $message = '' ) {
		$code = $message ? 'server_error' : '';
		parent::__construct( $code, $message );
	}
}
