<?php
/**
 * CLI utility trait file
 */
namespace SmartLicenseServer\Console\Traits;

use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\OptionParser;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Provides utility methods for CLI operations
 */
trait CLIUtilsTrait {    
    protected OutputInterface $output;
    protected InputInterface $io;
    /**
     * Validate that required positional arguments are present.
     */
    private function require_args( array $args, string $usage ): bool {
        foreach ( $args as $name => $value ) {
            if ( empty( $value ) ) {
                $this->output->error( sprintf( 'Missing required argument: <%s>', $name ) );
                $this->output->writeln( 'Usage: ' . $usage );
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
                $this->output->error( sprintf( 'Missing required option: --%s', $opt ) );
                $this->output->writeln( 'Usage: ' . $usage );
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

        $this->output->error( 'Authentication required.' );
        $this->output->writeln( 'Set SMLISER_CLI_API_KEY in your .env file and ensure the service account is active.' );
        return false;
    }

    /**
     * Parse options.
     */
    private function parse_options( array $args ): array {
        return $this->parse_args_opts( $args )['options'];
    }

    /**
     * Parse Arguments.
     */
    private function parse_arguments( array $args ): array {
        return $this->parse_args_opts( $args )['arguments'];
    }

    /**
     * Parse arguments and flags from the CLI input...
     * 
     * @param array $args
     * @return array{arguments: array, options: array}
     */
    private function parse_args_opts( array $args ) : array {
        return ( new OptionParser )->parse( $args );
    }
}