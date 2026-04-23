<?php
/**
 * CLI Error Handler Class for Smart License Server.
 *
 * Specializes in rendering errors for CLI/terminal environments.
 * Supports ANSI color codes, plain text fallback, and Throwable objects.
 * No HTML output or HTTP headers.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class CliErrorHandler extends AbstractErrorHandler {

    /*
    |------------------------------------------
    | RENDERING
    |------------------------------------------
    */

    /**
     * Render error for CLI environment.
     *
     * Outputs clean, readable format with file, line, and optional trace.
     * Gracefully handles both Throwable objects and string messages.
     *
     * @return string
     */
    public function render() : string {
        if ( ! $this->error_object instanceof \Throwable ) {
            return $this->renderSimpleMessage();
        }

        return $this->renderThrowable();
    }

    /**
     * Render warning/minor error as inline notice.
     *
     * Lightweight output for non-fatal errors in debug mode.
     *
     * @return string
     */
    public function renderWarning() : string {
        $output = '';
        $output .= PHP_EOL;
        $output .= '⚠  ' . $this->getTitle() . ': ' . $this->getMessage() . PHP_EOL;
        
        if ( $this->error_object instanceof \Throwable ) {
            $output .= '   ' . $this->error_object->getFile() . ':' . $this->error_object->getLine() . PHP_EOL;
        }
        
        return $output;
    }

    /**
     * Render simple message-only output.
     *
     * @return string
     */
    private function renderSimpleMessage() : string {
        $output = '';
        $output .= PHP_EOL;
        $output .= str_repeat( '━', 70 ) . PHP_EOL;
        $output .= $this->getTitle() . PHP_EOL;
        $output .= str_repeat( '━', 70 ) . PHP_EOL;
        $output .= $this->getMessage() . PHP_EOL;

        if ( $this->getCode() ) {
            $output .= 'Error Code: ' . $this->getCode() . PHP_EOL;
        }

        $output .= PHP_EOL;

        return $output;
    }

    /**
     * Render Throwable in CLI format.
     *
     * @return string
     */
    private function renderThrowable() : string {
        $output = '';
        $output .= PHP_EOL;
        $output .= str_repeat( '━', 70 ) . PHP_EOL;
        $output .= get_class( $this->error_object ) . PHP_EOL;
        $output .= str_repeat( '━', 70 ) . PHP_EOL;

        $output .= $this->getMessage() . PHP_EOL . PHP_EOL;

        $output .= 'File: ' . $this->error_object->getFile() . PHP_EOL;
        $output .= 'Line: ' . $this->error_object->getLine() . PHP_EOL;

        if ( $this->isDebug() ) {
            $output .= PHP_EOL . 'Stack Trace:' . PHP_EOL;
            $output .= $this->error_object->getTraceAsString() . PHP_EOL;

            $previous = $this->error_object->getPrevious();
            if ( $previous ) {
                $output .= PHP_EOL . 'Caused by: ' . get_class( $previous ) . PHP_EOL;
                $output .= $previous->getMessage() . PHP_EOL;
                $output .= $previous->getTraceAsString() . PHP_EOL;
            }
        }

        $output .= PHP_EOL;

        return $output;
    }

    /*
    |------------------------------------------
    | ABSTRACT METHODS
    |------------------------------------------
    */

    /**
     * Send headers - NO-OP for CLI.
     *
     * CLI environments don't send HTTP headers.
     * Method exists for interface compliance.
     *
     * @return self
     */
    public function sendHeaders() : self {
        return $this;
    }
}