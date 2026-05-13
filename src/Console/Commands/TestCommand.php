<?php
/**
 * PHPUnit test command.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInterface;

/**
 * Execute PHPUnit test suites.
 *
 * @since 0.2.0
 */
class TestCommand implements CommandInterface {

    /**
     * {@inheritDoc}
     */
    public static function name() : string {
        return 'test';
    }

    /**
     * {@inheritDoc}
     */
    public static function description() : string {
        return 'Run PHPUnit test suites.';
    }

    /**
     * {@inheritDoc}
     */
    public static function synopsis() : string {
        return 'smliser test [phpunit options]';
    }

    /**
     * {@inheritDoc}
     */
    public static function help() : string {
        return <<<HELP
        Run PHPUnit directly through the Smart License Server CLI.

        Examples:
        smliser test
        smliser test --filter SelectionIntentTest
        smliser test tests/Query
        smliser test --testdox
        HELP;
    }

    /**
     * {@inheritDoc}
     */
    public function execute( array $args = [] ) : void {

        $binary = SMLISER_PATH . 'vendor/bin/phpunit';

        if ( ! file_exists( $binary ) ) {
            fwrite( STDERR, "PHPUnit binary not found.\n" );
            exit( 1 );
        }

        $command = escapeshellcmd( $binary );

        if ( ! empty( $args ) ) {
            $command .= ' ' . implode(
                ' ',
                array_map( 'escapeshellarg', $args )
            );
        }

        passthru( $command, $exit_code );

        exit( (int) $exit_code );
    }
}