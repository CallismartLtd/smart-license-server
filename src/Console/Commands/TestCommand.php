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

use SmartLicenseServer\Console\CommandInput;

/**
 * Execute PHPUnit test suites.
 *
 * @since 0.2.0
 */
class TestCommand extends AbstractCommand {

    /**
     * {@inheritDoc}
     */
    public static function name() : string {
        return 'test';
    }

    /**
     * {@inheritDoc}
     */
    public function description() : string {
        return 'Run PHPUnit test suites.';
    }

    /**
     * {@inheritDoc}
     */
    public function synopsis() : string {
        return 'smliser test [phpunit options]';
    }

    /**
     * {@inheritDoc}
     */
    public function help() : string {
        $app_name       = \SMLISER_APP_NAME;
        $script_name    = $this->script_name;
        return <<<HELP
        Run PHPUnit directly through the $app_name CLI.

        Examples:
        $script_name test
        $script_name test --filter SelectionIntentTest
        $script_name test tests/Query
        $script_name test --testdox
        HELP;
    }

    /**
     * {@inheritDoc}
     */
    public function run( CommandInput $input ) : int {

        $binary = SMLISER_RUNTIME_DIR . 'vendor/bin/phpunit';

        if ( ! file_exists( $binary ) ) {
            $this->output->error( 'PHPUnit binary not found.' );

            return 1;
        }

        $command = escapeshellcmd( $binary );

        $args   = \array_merge( $input->get_arguments(), $input->get_options() );
        
        if ( ! empty( $args ) ) {
            $command .= ' ' . implode(
                ' ',
                array_map( 'escapeshellarg', $args )
            );
        }

        passthru( $command, $exit_code );

        return $exit_code;
    }

    public function get_subcommands(): array {
        return [
            'help'          => [$this, 'handle_help'],
            'script-name'   => [$this, 'test_script_name']
        ];
    }

    public function test_script_name() : int {
        $this->output->writeln( $this->script_name->value );
        return 0;
    }
}