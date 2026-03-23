<?php
/**
 * DotEnv loader class file.
 *
 * Parses a `.env` file and populates $_ENV, $_SERVER, and the
 * process environment via putenv(). Supports:
 *
 * - Single-line values:          KEY=value
 * - Quoted values:               KEY="hello world"  /  KEY='hello world'
 * - Multiline double-quoted:     KEY="line one\nline two"
 * - Export prefix:               export KEY=value
 * - Inline comments:             KEY=value  # this is ignored
 * - Variable expansion:          KEY=${OTHER_KEY}/suffix  (double-quoted only)
 * - Type casting:                true/false/null → native PHP types
 * - Immutable mode:              existing variables are never overwritten
 * - Required variable assertion: $dotenv->required(['DB_HOST', 'DB_NAME'])
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Core
 * @since   0.2.0
 */

namespace SmartLicenseServer\Core;

defined( 'SMLISER_ABSPATH' ) || exit;

final class DotEnv {

    /*
    |--------------------------------------------
    | PROPERTIES
    |--------------------------------------------
    */

    /**
     * Absolute path to the directory containing the .env file.
     *
     * @var string
     */
    protected string $path;

    /**
     * When true, variables already present in $_ENV are never overwritten.
     *
     * @var bool
     */
    protected bool $immutable = false;

    /**
     * Keys successfully loaded during the most recent load() call.
     *
     * @var string[]
     */
    protected array $loaded = [];

    /*
    |--------------------------------------------
    | CONSTRUCTOR
    |--------------------------------------------
    */

    /**
     * @param string $path      Absolute path to the directory containing the .env file.
     * @param bool   $immutable When true, pre-existing variables are never overwritten.
     */
    public function __construct( string $path, bool $immutable = false ) {
        $this->path      = rtrim( $path, '/' );
        $this->immutable = $immutable;
    }

    /*
    |--------------------------------------------
    | PUBLIC API
    |--------------------------------------------
    */

    /**
     * Parse and load a .env file into the environment.
     *
     * Silently returns when the file does not exist.
     * Throws on a malformed multiline block that is never closed.
     *
     * @param string $file Filename relative to $path. Default '.env'.
     * @throws \RuntimeException When an unclosed multiline block is detected.
     */
    public function load( string $file = '.env' ): void {
        $env_file = $this->path . '/' . $file;

        if ( ! file_exists( $env_file ) ) {
            return;
        }

        // FILE_SKIP_EMPTY_LINES omitted intentionally: empty lines inside a
        // multiline value must be preserved so the buffer accumulates them.
        $lines = file( $env_file, FILE_IGNORE_NEW_LINES );

        if ( $lines === false ) {
            return;
        }

        $key       = null;
        $multiline = false;
        $buffer    = '';

        foreach ( $lines as $line ) {
            $trimmed = trim( $line );

            // Skip blank lines and full-line comments outside multiline blocks.
            if ( ! $multiline && ( $trimmed === '' || str_starts_with( $trimmed, '#' ) ) ) {
                continue;
            }

            /*
            |------------------------------------------
            | Multiline continuation
            |------------------------------------------
            | We are inside a double-quoted multiline value.
            | Accumulate lines until a line ending with " is found.
            */
            if ( $multiline ) {
                if ( str_ends_with( $trimmed, '"' ) ) {
                    // Closing quote found — finalise the buffer.
                    $buffer .= "\n" . rtrim( $trimmed, '"' );
                    $this->set( $key, $this->unescape( $buffer ) );
                    $multiline = false;
                    $buffer    = '';
                    $key       = null;
                } else {
                    // Interior line — preserve as-is (includes empty lines).
                    $buffer .= "\n" . $line;
                }
                continue;
            }

            // Strip leading `export ` keyword (shell-style compatibility).
            if ( str_starts_with( $trimmed, 'export ' ) ) {
                $trimmed = substr( $trimmed, 7 );
            }

            // Lines without `=` cannot be parsed as key–value pairs.
            if ( ! str_contains( $trimmed, '=' ) ) {
                continue;
            }

            [ $raw_key, $raw_value ] = explode( '=', $trimmed, 2 );

            $key   = trim( $raw_key );
            $value = trim( $raw_value );

            // Skip lines with an empty key.
            if ( $key === '' ) {
                continue;
            }

            /*
            |------------------------------------------
            | Multiline double-quoted value start
            |------------------------------------------
            | Detected when the value opens with " but does not close with "
            | on the same line (and the value is not just `""`).
            */
            if (
                str_starts_with( $value, '"' ) &&
                ! ( strlen( $value ) > 1 && str_ends_with( $value, '"' ) )
            ) {
                $multiline = true;
                $buffer    = substr( $value, 1 ); // strip the opening "
                continue;
            }

            $is_double_quoted = str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) && strlen( $value ) >= 2;
            $is_single_quoted = str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) && strlen( $value ) >= 2;

            if ( $is_double_quoted || $is_single_quoted ) {
                // Strip surrounding quotes.
                $value = substr( $value, 1, -1 );
            } else {
                // Unquoted value — strip trailing inline comments.
                $value = (string) preg_replace( '/\s+#.*$/', '', $value );
                $value = trim( $value );
            }

            // Variable expansion: only for double-quoted or unquoted values.
            // Single-quoted values treat ${VAR} as a literal string,
            // consistent with POSIX shell behaviour.
            if ( ! $is_single_quoted ) {
                $value = $this->expand_variables( $value );
            }

            // Unescape escape sequences for double-quoted values.
            if ( $is_double_quoted ) {
                $value = $this->unescape( $value );
            }

            $this->set( $key, $this->cast_value( $value ) );
        }

        // A multiline block that was opened but never closed is a parse error.
        if ( $multiline ) {
            throw new \RuntimeException(
                sprintf(
                    'DotEnv: unclosed multiline value for key "%s" in %s.',
                    $key,
                    $env_file
                )
            );
        }
    }

    /**
     * Assert that a set of keys are present in $_ENV.
     *
     * Call after load() to enforce required configuration.
     *
     * @param string[] $keys
     * @throws \RuntimeException For the first missing key found.
     */
    public function required( array $keys ): void {
        foreach ( $keys as $key ) {
            if ( ! array_key_exists( $key, $_ENV ) ) {
                throw new \RuntimeException(
                    sprintf( 'DotEnv: required environment variable "%s" is missing.', $key )
                );
            }
        }
    }

    /**
     * Return the list of keys loaded during the most recent load() call.
     *
     * @return string[]
     */
    public function loaded(): array {
        return $this->loaded;
    }

    /*
    |--------------------------------------------
    | PROTECTED HELPERS
    |--------------------------------------------
    */

    /**
     * Write a key–value pair into $_ENV, $_SERVER, and the process environment.
     *
     * putenv() only accepts strings, so typed values (bool, null, int, float)
     * are serialised to a string representation for the process environment
     * while the native type is preserved in $_ENV and $_SERVER.
     *
     * @param string|null $key
     * @param mixed       $value
     */
    protected function set( ?string $key, mixed $value ): void {
        if ( $key === null || $key === '' ) {
            return;
        }

        // Immutable mode: never overwrite a variable that already exists.
        if ( $this->immutable && array_key_exists( $key, $_ENV ) ) {
            return;
        }

        $_ENV[ $key ]    = $value;
        $_SERVER[ $key ] = $value;

        // putenv() requires a string — coerce typed values consistently.
        $env_string = match ( true ) {
            $value === true  => 'true',
            $value === false => 'false',
            $value === null  => '',
            default          => (string) $value,
        };

        putenv( "{$key}={$env_string}" );

        $this->loaded[] = $key;
    }

    /**
     * Expand ${VAR} and $VAR references in a value string.
     *
     * Looks up variables in $_ENV first, then falls back to getenv().
     * Unknown variables are replaced with an empty string.
     *
     * @param string $value
     * @return string
     */
    protected function expand_variables( string $value ): string {
        // ${VAR_NAME} syntax.
        $value = preg_replace_callback(
            '/\$\{([A-Z0-9_]+)\}/i',
            fn( $m ) => (string) ( $_ENV[ $m[1] ] ?? getenv( $m[1] ) ?? '' ),
            $value
        );

        // $VAR_NAME syntax (not followed by another word character).
        $value = preg_replace_callback(
            '/\$([A-Z0-9_]+)(?![A-Z0-9_])/i',
            fn( $m ) => (string) ( $_ENV[ $m[1] ] ?? getenv( $m[1] ) ?? '' ),
            $value
        );

        return $value;
    }

    /**
     * Unescape common escape sequences in double-quoted and multiline values.
     *
     * @param string $value
     * @return string
     */
    protected function unescape( string $value ): string {
        return str_replace(
            [ '\n', '\r', '\t', '\\"', "\\\\" ],
            [ "\n", "\r", "\t", '"',   "\\"   ],
            $value
        );
    }

    /**
     * Cast string scalar values to native PHP types.
     *
     * Recognised literals:
     *   true / (true)   → bool true
     *   false / (false) → bool false
     *   null / (null)   → null
     *   empty / (empty) → ''
     *
     * Numeric strings are NOT auto-cast to int/float because doing so
     * would silently corrupt values such as version strings ('1.0'),
     * leading-zero codes ('007'), or phone numbers ('0800123456').
     * Cast numerics explicitly in application code where the type is known.
     *
     * @param string $value
     * @return bool|null|string
     */
    protected function cast_value( string $value ): bool|null|string {
        return match ( strtolower( $value ) ) {
            'true',  '(true)'  => true,
            'false', '(false)' => false,
            'null',  '(null)'  => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}