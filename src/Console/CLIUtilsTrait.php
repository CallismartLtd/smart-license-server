<?php
/**
 * CLI utility trait file
 */
namespace SmartLicenseServer\Console;

use SmartLicenseServer\Security\Context\Guard;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Provides utility methods for CLI operations
 */
trait CLIUtilsTrait {
    use CLIAwareTrait;
    /**
     * Validate that required positional arguments are present.
     */
    private function require_args( array $args, string $usage ): bool {
        foreach ( $args as $name => $value ) {
            if ( empty( $value ) ) {
                $this->error( sprintf( 'Missing required argument: <%s>', $name ) );
                $this->line( 'Usage: ' . $usage );
                return false;
            }
        }

        return true;
    }

    /**
     * Validate that required options are present.
     * 
     * @param array $opts Parsed options array.
     * @param string[] $required_opts List of required option keys.
     * @param string $usage Usage string to display on error.
     * 
     * @return bool True if all required options are present, false otherwise.
     */
    private function require_options( array $opts, array $required_opts, string $usage ): bool {
        foreach ( $required_opts as $opt ) {
            if ( ! isset( $opts[ $opt ] ) || empty( $opts[ $opt ] ) ) {
                $this->error( sprintf( 'Missing required option: --%s', $opt ) );
                $this->line( 'Usage: ' . $usage );
                return false;
            }
        }

        return true;
    }

    /**
     * Check that a principal is set on Guard.
     */
    private function require_auth(): bool {
        if ( Guard::has_principal() ) {
            return true;
        }

        $this->error( 'Authentication required.' );
        $this->line( 'Set SMLISER_CLI_API_KEY in your .env file and ensure the service account is active.' );
        return false;
    }

    /**
     * Parse --key=value and --key options from an args array.
     */
    private function parse_options( array $args ): array {
        $opts = [];

        foreach ( $args as $k => $arg ) {

            if ( is_string( $k ) ) {
                $opts[$k] = $arg;
                continue;
            }
            
            if ( ! str_starts_with( $arg, '--' ) ) {
                continue;
            }

            $arg = substr( $arg, 2 );

            if ( str_contains( $arg, '=' ) ) {
                [ $key, $value ] = explode( '=', $arg, 2 );
            } else {
                $key   = $arg;
                $value = 'true';
            }

            if ( isset( $opts[ $key ] ) ) {
                $opts[ $key ] = array_merge( (array) $opts[ $key ], [ $value ] );
            } else {
                $opts[ $key ] = $value;
            }
        }

        return $opts;
    }
}