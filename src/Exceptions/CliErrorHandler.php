<?php
/**
 * CLI Error Handler Class for Smart License Server.
 *
 * Specializes in rendering errors for CLI/terminal environments.
 * Uses Exception's built-in render() method which supports ANSI color codes.
 * No HTML output, HTTP headers, or web-specific features.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class CliErrorHandler extends AbstractErrorHandler {

    /**
     * Render error for CLI environment.
     *
     * Delegates to Exception's render() method which:
     * - Outputs plain text for CLI environments.
     * - Includes ANSI color codes when stdout supports them.
     * - Falls back to plain text when output is piped.
     * - Respects NO_COLOR environment variable.
     *
     * @return string
     */
    public function render() : string {
        // If rendering an Exception, use its render() method.
        if ( $this->error_object instanceof Exception ) {
            // For CLI, include trace based on debug configuration.
            $include_trace = static::$global_debug_mode;
            return $this->error_object->render( $include_trace );
        }

        // Fallback for string messages - simple CLI output.
        $output = '';
        $output .= \PHP_EOL;
        $output .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
        $output .= htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . \PHP_EOL;
        $output .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" . PHP_EOL;
        $output .= htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' ) . PHP_EOL;

        if ( $this->config['back_link'] ) {
            $output .= "Error Code: " . htmlspecialchars( $this->getCode(), ENT_QUOTES, 'UTF-8' ) . \PHP_EOL;
        }

        $output .= \PHP_EOL;

        return $output;
    }

    /**
     * Send headers - NO-OP for CLI.
     *
     * CLI environments don't send HTTP headers.
     * This method exists for interface compliance.
     *
     * @return self
     */
    public function sendHeaders() : self {
        // No-op. CLI doesn't send HTTP headers.
        return $this;
    }

    /**
     * Static factory method.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return static
     */
    public static function create( $message = '', $title = '', $args = [] ) : static {
        return new static( $message, $title, $args );
    }
}