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

    public static function description(): string {
        return 'Print the user name associated with the current principal.';
    }

    public static function synopsis(): string {
        return 'smliser whoami';
    }

    public static function help(): string {
        return '';
    }

    public function run( CommandInput $input ): int {
        if ( ! Guard::has_principal() ) {
            $this->output->error( 'Guest' );
            return 1;
        }

        $this->output->info( Guard::get_principal()->get_display_name() );

        return 0;
    }
}