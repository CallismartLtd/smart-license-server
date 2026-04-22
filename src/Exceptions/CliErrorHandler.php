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

use SmartLicenseServer\Console\CLIUtilsTrait;

class CliErrorHandler extends AbstractErrorHandler {
    use CLIUtilsTrait;
    
    private function outputError() : string {
        $class = get_class( $this->error_object );

        // Header.
        $this->line(
            $this->colorize( self::ANSI_RED . self::ANSI_BOLD, "✖ {$class}" ),
        );
        

        if ( $this->getMessage() ) {
            $this->error( $this->getMessage() );
        }

        // Structured errors.
        if ( $this->error_object->has_errors() ) {
            $this->info( 'Errors:' );

            foreach ( $this->error_object->errors as $code => $messages ) {
                $this->line(
                    $this->colorize( static::ANSI_CYAN, "  [{$code}]" )
                );

                foreach ( $messages as $message ) {
                    $this->info( "    → {$message}" );
                }

                $data = $this->error_object->get_all_error_data( $code );
                if ( ! empty( $data ) ) {
                    foreach ( $data as $datum ) {
                        $encoded = is_string( $datum )
                            ? $datum
                            : json_encode( $datum, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
                        $this->line( 
                            $this->colorize( self::ANSI_DIM, "    data: {$encoded}" )
                        );
                    }
                }
            }

        }

        // Chained exception.
        $previous = $this->error_object->getPrevious();
        if ( $previous ) {
            $this->line(
                $this->colorize(
                    self::ANSI_YELLOW . self::ANSI_BOLD,
                    'Caused by:'
                )
            );
            
            $this->line(
                $this->colorize(
                    self::ANSI_DIM,
                    '  ' . get_class( $previous ) . ': ' . $previous->getMessage()
                )
            );

        }

        // Stack trace.
        if ( $this->isDebug() ) {
            $this->info( 'Stack trace:' );
            $this->error( $this->error_object->getTraceAsString() );
        }

        return '';
    }

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
            return $this->outputError();
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
}