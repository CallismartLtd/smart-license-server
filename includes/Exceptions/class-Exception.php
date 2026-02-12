<?php
/**
 * The base exception class for the Smart License Server.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 * @package SmartLicenseServer\Exceptions
 */
namespace SmartLicenseServer\Exceptions;
use Exception as PHPException;
use WP_Error;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * A model of WP_Error that extends the exception handling capabilities.
 * 
 * @copyright WordPress.org
 * @license GNU General Public License v2 or later
 */
class Exception extends PHPException {
	/**
	 * Stores the list of errors.
	 *
	 * @since 0.1.1
	 * @var array
	 */
	public $errors = array();

	/**
	 * Stores the most recently added data for each error code.
	 *
	 * @since 0.1.1
	 * @var array
	 */
	public $error_data = array();

	/**
	 * Stores previously added data added for error codes, oldest-to-newest by code.
	 *
	 * @since 0.1.1
	 * @var array[]
	 */
	protected $additional_data = array();

    /**
     * Initializes the error and supports throwable chaining.
     *
     * If `$code` is empty, the other parameters will be ignored.
     * When `$code` is not empty, `$message` will be used even if
     * it is empty. The `$data` parameter will be used only if it
     * is not empty.
     *
     * The `$previous` parameter can be used to chain exceptions,
     * allowing this exception to wrap another.
     *
     * @since 0.1.1
     *
     * @param string|int   $code      Error code.
     * @param string       $message   Error message.
     * @param mixed        $data      Optional. Error data. Default empty string.
     * @param ?\Throwable  $previous  Optional. Previous exception. Default null.
     */
    public function __construct( $code = '', $message = '', $data = '', ?\Throwable $previous = null ) {
        // Initialize parent Exception with message and previous throwable.
        parent::__construct( $message, 0, $previous );

        if ( empty( $code ) ) {
            return;
        }

        $this->add( $code, $message, $data );
    }

	/**
	 * Retrieves all error codes.
	 *
	 * @since 0.1.1
	 *
	 * @return array List of error codes, if available.
	 */
	public function get_error_codes() {
		if ( ! $this->has_errors() ) {
			return array();
		}

		return array_keys( $this->errors );
	}

	/**
	 * Retrieves the first error code available.
	 *
	 * @since 0.1.1
	 *
	 * @return string|int Empty string, if no error codes.
	 */
	public function get_error_code() {
		$codes = $this->get_error_codes();

		if ( empty( $codes ) ) {
			return '';
		}

		return $codes[0];
	}

	/**
	 * Retrieves all error messages, or the error messages for the given error code.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code Optional. Error code to retrieve the messages for.
	 *                         Default empty string.
	 * @return string[] Error strings on success, or empty array if there are none.
	 */
	public function get_error_messages( $code = '' ) {
		// Return all messages if no code specified.
		if ( empty( $code ) ) {
			$all_messages = array();
			foreach ( (array) $this->errors as $code => $messages ) {
				$all_messages = array_merge( $all_messages, $messages );
			}

			return $all_messages;
		}

		if ( isset( $this->errors[ $code ] ) ) {
			return $this->errors[ $code ];
		} else {
			return array();
		}
	}

	/**
	 * Gets a single error message.
	 *
	 * This will get the first message available for the code. If no code is
	 * given then the first code available will be used.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code Optional. Error code to retrieve the message for.
	 *                         Default empty string.
	 * @return string The error message.
	 */
	public function get_error_message( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}
		$messages = $this->get_error_messages( $code );
		if ( empty( $messages ) ) {
			return '';
		}
		return $messages[0];
	}

	/**
	 * Retrieves the most recently added error data for an error code.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code Optional. Error code. Default empty string.
	 * @return mixed Error data, if it exists.
	 */
	public function get_error_data( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		if ( isset( $this->error_data[ $code ] ) ) {
			return $this->error_data[ $code ];
		}
	}

	/**
	 * Verifies if the instance contains errors.
	 *
	 * @since 0.1.1
	 *
	 * @return bool If the instance contains errors.
	 */
	public function has_errors() {
		return ! empty( $this->errors );
	}

	/**
	 * Adds an error or appends an additional message to an existing error.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code    Error code.
	 * @param string     $message Error message.
	 * @param mixed      $data    Optional. Error data. Default empty string.
	 */
	public function add( $code, $message, $data = '' ) {
		$this->errors[ $code ][] = $message;

		if ( ! empty( $data ) ) {
			$this->add_data( $data, $code );
		}

		/**
		 * Fires when an error is added to a Exception object.
		 *
		 * @since 0.1.1
		 *
		 * @param string|int $code     Error code.
		 * @param string     $message  Error message.
		 * @param mixed      $data     Error data. Might be empty.
		 * @param self       $error    The Error object.
		 */
		// do_action( 'error_added', $code, $message, $data, $this );
	}

	/**
	 * Adds data to an error with the given code.
	 *
	 * @param mixed      $data Error data.
	 * @param string|int $code Error code.
	 */
	public function add_data( $data, $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		if ( isset( $this->error_data[ $code ] ) ) {
			$this->additional_data[ $code ][] = $this->error_data[ $code ];
		}

		$this->error_data[ $code ] = $data;
	}

	/**
	 * Retrieves all error data for an error code in the order in which the data was added.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code Error code.
	 * @return mixed[] Array of error data, if it exists.
	 */
	public function get_all_error_data( $code = '' ) {
		if ( empty( $code ) ) {
			$code = $this->get_error_code();
		}

		$data = array();

		if ( isset( $this->additional_data[ $code ] ) ) {
			$data = $this->additional_data[ $code ];
		}

		if ( isset( $this->error_data[ $code ] ) ) {
			$data[] = $this->error_data[ $code ];
		}

		return $data;
	}

	/**
	 * Removes the specified error.
	 *
	 * This function removes all error messages associated with the specified
	 * error code, along with any error data for that code.
	 *
	 * @since 0.1.1
	 *
	 * @param string|int $code Error code.
	 */
	public function remove( $code ) {
		unset( $this->errors[ $code ] );
		unset( $this->error_data[ $code ] );
		unset( $this->additional_data[ $code ] );
	}

    /**
     * Merges the errors in the given error object into this one.
     *
     * Compatible with both SmartLicenseServer\Exception and WP_Error.
     *
     * @since 0.1.1
     *
     * @param self|\WP_Error $error Error object to merge.
     */
    public function merge_from( $error ) {
        if ( $error instanceof self || ( class_exists( 'WP_Error' ) && $error instanceof \WP_Error ) ) {
            static::copy_errors( $error, $this );
        }

		return $this;
    }

    /**
     * Exports the errors in this object into the given one.
     *
     * Compatible with both SmartLicenseServer\Exception and WP_Error.
     *
     * @since 0.1.1
     *
     * @param self|\WP_Error $error Error object to export into.
     */
    public function export_to( $error ) {
        if ( $error instanceof self || ( class_exists( 'WP_Error' ) && $error instanceof \WP_Error ) ) {
            static::copy_errors( $this, $error );
        }
    }

    /**
     * Copies errors from one object to another.
     *
     * Works transparently with both SmartLicenseServer\Exception and WP_Error instances.
     *
     * @since 0.1.1
     *
     * @param self|\WP_Error $from The source error object.
     * @param self|\WP_Error $to   The destination error object.
     */
    protected static function copy_errors( $from, $to ) {
        // Get error codes.
        if ( $from instanceof self ) {
            $codes = $from->get_error_codes();
        } elseif ( class_exists( 'WP_Error' ) && $from instanceof \WP_Error ) {
            $codes = $from->get_error_codes();
        } else {
            return;
        }

        foreach ( $codes as $code ) {
            $messages = method_exists( $from, 'get_error_messages' )
                ? $from->get_error_messages( $code )
                : array();

            foreach ( (array) $messages as $error_message ) {
                if ( method_exists( $to, 'add' ) ) {
                    $to->add( $code, $error_message );
                }
            }

            $data = method_exists( $from, 'get_all_error_data' )
                ? $from->get_all_error_data( $code )
                : ( method_exists( $from, 'get_error_data' ) ? array( $from->get_error_data( $code ) ) : array() );

            foreach ( (array) $data as $datum ) {
                if ( method_exists( $to, 'add_data' ) ) {
                    $to->add_data( $datum, $code );
                }
            }
        }
    }

    /**
     * Creates an Exception from a WP_Error instance.
     *
     * @param \WP_Error $wp_error The WordPress error object.
     * @return self
     */
    public static function from_wp_error( \WP_Error $wp_error ): self {
        $exception = new self();
        static::copy_errors( $wp_error, $exception );
        return $exception;
    }

	/**
	 * Converts this exception into a WP_Error instance.
	 *
	 * @since 0.1.1
	 *
	 * @return \WP_Error
	 * @throws \RuntimeException In a non-wp environment.
	 */
	public function to_wp_error() {
		if ( ! \class_exists( WP_Error::class ) ) {
			throw new \RuntimeException( 'WP_Error class is not available.' );
		}
		$wp_error = new \WP_Error();

		foreach ( $this->get_error_codes() as $code ) {
			$messages = $this->get_error_messages( $code );

			foreach ( $messages as $message ) {
				$wp_error->add( $code, $message );
			}

			$data_items = $this->get_all_error_data( $code );
			foreach ( $data_items as $datum ) {
				$wp_error->add_data( $datum, $code );
			}
		}

		// If the WP_Error has no message (rare), fall back to the Exception's own message.
		if ( empty( $wp_error->get_error_messages() ) && $this->getMessage() ) {
			$wp_error->add( $this->get_error_code() ?: 'exception', $this->getMessage() );
		}

		return $wp_error;
	}


    /**
     * Converts the exception to string, including all nested exceptions.
     *
     * @since 0.1.1
     *
     * @return string A formatted string representation of the exception.
     */
    public function __toString() {
        $messages = array();

        foreach ( $this->errors as $code => $items ) {
            foreach ( $items as $message ) {
                $messages[] = sprintf( '[%s] %s', $code, $message );
            }
        }

        // Nicely format messages.
        $formatted = empty( $messages )
            ? '(no structured messages)'
            : implode( "\n", array_map( fn( $msg ) => '  → ' . $msg, $messages ) );

        // Include previous exception info, if any.
        $previous = $this->getPrevious();
        $previous_str = '';
        if ( $previous ) {
            $previous_str = sprintf(
                "\n\nCaused by: %s: %s\nTrace:\n%s\n",
                get_class( $previous ),
                $previous->getMessage(),
                $previous->getTraceAsString()
            );
        }

        return sprintf(
            "%s: %s\n\nAll Errors:\n%s\n\nError Codes: %s\nData: %s\nTrace:\n <div>%s</div>%s\n",
            get_called_class(),
			$this->getMessage(),
            $formatted,
            implode( ', ', $this->get_error_codes() ),
            smliser_safe_json_encode( $this->error_data, JSON_PRETTY_PRINT ),
            $this->getTraceAsString(),
            $previous_str
        );
    }

    /**
     * Converts the exception into an associative array.
     * 
     * @since 0.1.1
     * 
     * @return array Structured error information.
     */
    public function to_array() {
        $errors = array();

        foreach ( $this->get_error_codes() as $code ) {
            $messages = $this->get_error_messages( $code );
            $data     = $this->get_all_error_data( $code );

            $errors[ $code ] = array(
                'messages' => $messages,
                'data'     => $data,
            );
        }

        $array = array(
            'message' => $this->getMessage(),
            'codes'   => $this->get_error_codes(),
            'errors'  => $errors,
            'trace'   => $this->getTrace(),
        );

        // Add WordPress-like context if available
        if ( function_exists( 'smliser_safe_json_encode' ) ) {
            $array['json'] = smliser_safe_json_encode( $array, JSON_PRETTY_PRINT );
        }

        return $array;
    }

}
