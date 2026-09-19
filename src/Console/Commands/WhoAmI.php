<?php
/**
 * Whoami command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Console\Contracts\InputInterface;
use SmartLicenseServer\Console\Contracts\OutputInterface;
use SmartLicenseServer\Console\ScriptName;
use SmartLicenseServer\Security\Context\Guard;

/**
 * Print the user name associated with the current principal.
 */
class WhoAmI extends AbstractCommand {
    public function __construct(
        protected Guard $guard,
        InputInterface $io,
        OutputInterface $output,
        ScriptName $script_name
    ) {
        
        parent::__construct(
            io: $io,
            output: $output,
            script_name: $script_name
        );
    }

    public static function name(): string {
        return 'whoami';
    }

    public function description(): string {
        return 'Print the user name associated with the current principal.';
    }

    public function help(): string {
        return '';
    }

    public function run( CommandInput $input ): int {
        if ( ! $this->guard->has_principal() ) {
            $this->output->error( 'Guest' );
            return 1;
        }

        $this->output->info( $this->guard->get_principal()->get_display_name() );

        return 0;
    }
}