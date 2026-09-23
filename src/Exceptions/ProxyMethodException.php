<?php

namespace SmartLicenseServer\Exceptions;

use ErrorException;
use Throwable;

/**
 * Exception thrown when a proxied method call fails because the target 
 * method does not exist or is not callable on the underlying adapter.
 *
 * Extends ErrorException to preserve origin file and line number context
 * where the undefined method call was made.
 */
class ProxyMethodException extends ErrorException {

    /**
     * Create a new proxy method exception.
     *
     * @param string         $callerClass The class receiving the proxy call.
     * @param string         $method      The method name attempted.
     * @param int            $traceDepth  Backtrace level to identify file/line of origin. Default is 0.
     * @param Throwable|null $previous    Previous exception for chaining.
     */
    public function __construct( 
        string $callerClass, 
        string $method, 
        int $traceDepth = 0, 
        ?Throwable $previous = null 
    ) {
        $message = sprintf(
            'Method %s::%s() does not exist or is not callable on the adapter.',
            $callerClass,
            $method
        );

        $trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $traceDepth + 2 );
        $file  = $trace[ $traceDepth ]['file'] ?? __FILE__;
        $line  = $trace[ $traceDepth ]['line'] ?? __LINE__;

        parent::__construct(
            $message,
            0,
            E_ERROR,
            $file,
            $line,
            $previous
        );
    }
}