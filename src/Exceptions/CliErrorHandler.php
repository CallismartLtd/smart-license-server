<?php
/**
 * CLI Error Handler Class for Smart License Server.
 *
 * Specializes in rendering errors for CLI/terminal environments.
 * Supports ANSI color codes, POSIX color detection, exception chains,
 * and clean terminal formatting without HTTP dependencies.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class CliErrorHandler extends AbstractErrorHandler {

    /*
    |------------------------------------------
    | ANSI COLOR CODES
    |------------------------------------------
    */

    private const COLOR_RESET   = "\033[0m";
    private const COLOR_BOLD    = "\033[1m";
    private const COLOR_DIM     = "\033[2m";
    private const COLOR_RED     = "\033[31m";
    private const COLOR_GREEN   = "\033[32m";
    private const COLOR_YELLOW  = "\033[33m";
    private const COLOR_CYAN    = "\033[36m";
    private const COLOR_GRAY    = "\033[90m";

    private const BG_RED        = "\033[41m";
    private const BG_YELLOW     = "\033[43m";

    /*
    |------------------------------------------
    | STATE & TERMINAL DETECTION
    |------------------------------------------
    */

    /**
     * Cache for terminal color capability.
     *
     * @var bool|null
     */
    private ?bool $supports_color = null;

    /**
     * Check if the current terminal environment supports ANSI color codes.
     *
     * @return bool
     */
    public function supportsColor() : bool {
        if ( null !== $this->supports_color ) {
            return $this->supports_color;
        }

        // 1. Explicit override in configuration
        if ( isset( $this->config['colors'] ) ) {
            return $this->supports_color = (bool) $this->config['colors'];
        }

        // 2. Check NO_COLOR environment standard
        if ( getenv( 'NO_COLOR' ) !== false ) {
            return $this->supports_color = false;
        }

        // 3. Check FORCE_COLOR environment override
        if ( getenv( 'FORCE_COLOR' ) !== false ) {
            return $this->supports_color = true;
        }

        // 4. Windows VT100 / ANSICON / ConEmu support check
        if ( \DIRECTORY_SEPARATOR === '\\' ) {
            return $this->supports_color = (
                0 === strcasecmp( '10.0.10586', sprintf( '%s.%s.%s', ...explode( '.', php_uname( 'r' ) ) ) )
                || getenv( 'ANSICON' ) !== false
                || getenv( 'ConEmuANSI' ) === 'ON'
                || getenv( 'TERM' ) === 'xterm'
            );
        }

        // 5. Standard POSIX TTY check
        return $this->supports_color = ( \function_exists( 'posix_isatty' ) && @posix_isatty( STDOUT ) );
    }

    /**
     * Toggle ANSI color support explicitly.
     *
     * @param bool $enable
     * @return static
     */
    public function setColors( bool $enable ) : static {
        $this->config['colors'] = $enable;
        $this->supports_color   = $enable;
        return $this;
    }

    /*
    |------------------------------------------
    | FORMATTING HELPERS
    |------------------------------------------
    */

    /**
     * Wrap text with ANSI color sequences if color is supported.
     *
     * @param string $text
     * @param string $colorCode
     * @return string
     */
    private function style( string $text, string $colorCode ) : string {
        if ( ! $this->supportsColor() ) {
            return $text;
        }
        return $colorCode . $text . self::COLOR_RESET;
    }

    /**
     * Render a horizontal rule matching terminal width (defaults to 72 chars).
     *
     * @param string $char Rule character.
     * @param int $length Width length.
     * @return string
     */
    private function rule( string $char = '━', int $length = 72 ) : string {
        return $this->style( str_repeat( $char, $length ), self::COLOR_GRAY ) . PHP_EOL;
    }

    /*
    |------------------------------
    | PUBLIC RENDERING METHODS
    |------------------------------
    */

    /**
     * Render complete error for CLI environment.
     *
     * Outputs styled block headers, metadata, and stack traces with color accents.
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
     * Render warning or minor error as an inline terminal notice.
     *
     * @return string
     */
    public function renderWarning() : string {
        $output  = PHP_EOL;
        $badge   = $this->style( ' WARNING ', self::BG_YELLOW . self::COLOR_BOLD );
        $title   = $this->style( $this->getTitle() . ':', self::COLOR_YELLOW . self::COLOR_BOLD );
        $message = $this->getMessage();

        $output .= sprintf( "%s %s %s%s", $badge, $title, $message, PHP_EOL );

        if ( $this->error_object instanceof \Throwable ) {
            $fileInfo = sprintf( '%s:%d', $this->error_object->getFile(), $this->error_object->getLine() );
            $output  .= '   ' . $this->style( 'at ' . $fileInfo, self::COLOR_GRAY ) . PHP_EOL;
        }

        return $output;
    }

    /*
    |------------------------------------------
    | PRIVATE RENDERING HELPERS
    |------------------------------------------
    */

    /**
     * Render non-Throwable simple error message.
     *
     * @return string
     */
    private function renderSimpleMessage() : string {
        $output  = PHP_EOL;
        $badge   = $this->style( ' ERROR ', self::BG_RED . self::COLOR_BOLD );
        $title   = $this->style( $this->getTitle(), self::COLOR_BOLD );

        $output .= sprintf( "%s %s%s", $badge, $title, PHP_EOL );
        $output .= $this->rule();
        $output .= $this->getMessage() . PHP_EOL;

        if ( $code = $this->getCode() ) {
            $output .= PHP_EOL . $this->style( 'Code: ', self::COLOR_GRAY ) . $code . PHP_EOL;
        }

        $output .= $this->rule() . PHP_EOL;

        return $output;
    }

    /**
     * Render Throwable object with environment-aware debug trace and chain resolution.
     *
     * @return string
     */
    private function renderThrowable() : string {
        $exception = $this->error_object;
        $output    = PHP_EOL;

        // Banner Header
        $badge = $this->style( ' EXCEPTION ', self::BG_RED . self::COLOR_BOLD );
        $class = $this->style( get_class( $exception ), self::COLOR_BOLD . self::COLOR_RED );
        
        $output .= sprintf( "%s %s%s", $badge, $class, PHP_EOL );
        $output .= $this->rule();

        // Message
        $output .= $this->style( $this->getMessage(), self::COLOR_BOLD ) . PHP_EOL . PHP_EOL;

        // Location Metadata
        $output .= $this->style( 'File: ', self::COLOR_GRAY ) . $this->style( $exception->getFile(), self::COLOR_CYAN ) . PHP_EOL;
        $output .= $this->style( 'Line: ', self::COLOR_GRAY ) . $this->style( (string) $exception->getLine(), self::COLOR_YELLOW ) . PHP_EOL;

        // Debug mode: Stack Trace & Exception Chain
        if ( $this->isDebug() ) {
            $output .= PHP_EOL . $this->style( 'Stack Trace:', self::COLOR_BOLD . self::COLOR_YELLOW ) . PHP_EOL;
            $output .= $this->formatStackTrace( $exception->getTraceAsString() ) . PHP_EOL;

            // Render Previous Exception Chain
            $previous = $exception->getPrevious();
            while ( $previous ) {
                $output .= $this->rule( '-' );
                $output .= $this->style( 'Caused by: ', self::COLOR_YELLOW ) . $this->style( get_class( $previous ), self::COLOR_BOLD ) . PHP_EOL;
                $output .= $previous->getMessage() . PHP_EOL;
                $output .= $this->style( sprintf( 'at %s:%d', $previous->getFile(), $previous->getLine() ), self::COLOR_GRAY ) . PHP_EOL;
                
                if ( ! empty( $previous->getTraceAsString() ) ) {
                    $output .= PHP_EOL . $this->formatStackTrace( $previous->getTraceAsString() ) . PHP_EOL;
                }

                $previous = $previous->getPrevious();
            }
        }

        $output .= $this->rule() . PHP_EOL;

        return $output;
    }

    /**
     * Apply syntax accents to stack trace lines.
     *
     * @param string $rawTrace
     * @return string
     */
    private function formatStackTrace( string $rawTrace ) : string {
        if ( ! $this->supportsColor() ) {
            return $rawTrace;
        }

        $lines  = explode( "\n", trim( $rawTrace ) );
        $parsed = [];

        foreach ( $lines as $line ) {
            // Highlight frame numbers (#0, #1, etc.) and file locations
            $line     = preg_replace( '/^#(\d+)/', self::COLOR_GRAY . '#$1' . self::COLOR_RESET, $line );
            $parsed[] = preg_replace( '/\(([\d]+)\):/', '(' . self::COLOR_YELLOW . '$1' . self::COLOR_RESET . '):', $line );
        }

        return implode( PHP_EOL, $parsed );
    }

    /*
    |------------------------------------------
    | ABSTRACT METHOD IMPLEMENTATIONS
    |------------------------------------------
    */

    /**
     * Send headers - NO-OP for CLI environments.
     *
     * Maintains interface compatibility across concrete error handlers.
     *
     * @return static
     */
    public function sendHeaders() : static {
        return $this;
    }
}