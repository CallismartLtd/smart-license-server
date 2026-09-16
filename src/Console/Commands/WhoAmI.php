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
use SmartLicenseServer\Security\Context\Guard;

/**
 * Print the user name associated with the current principal.
 */
class WhoAmI extends AbstractCommand {

    public static function name(): string {
        return 'whoami';
    }

    public function description(): string {
        return 'Print the user name associated with the current principal.';
    }

    public function synopsis(): string {
        return 'smliser whoami';
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