<?php
/**
 * Installer class file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Handles installation processes through the console.
 */
class Installer extends AbstractCommand {
    /**
     * {@inheritdoc}
     */
    public static function name() : string {
        return 'installer';
    }

    /**
     * {@inheritdoc}
     */
    public static function description() : string {
        return 'Handle installation.';
    }

    public static function synopsis() : string {
        $name   = static::name();
        return "smliser {$name} <subcommands> [arguments]";
    }

    public static function help() : string {
        $name   = static::name();
        return \implode( PHP_EOL, [
            "smliser {$name}  check                 Performs environment sanity checks.",
            "smliser {$name}  make:roles            Install default roles.",
            "smliser {$name}  make:admin            Create a human administrator account.",
            "smliser {$name}  make:dir              Creates all required directories.",
            "smliser {$name}  make:tables           Creates all registered database tables.",
            "smliser {$name}  make:dotenv           Create a .env file if missing.",
            "smliser {$name}  make:htaccess         Creates or updates the .htaccess file.",
        ]);
    }

    public function run( CommandInput $input ) : int {
        $this->output->info(
            \sprintf(
                'Start or fix the installation of %s',
                SMLISER_APP_NAME
            )
            
        );

        $prefix = \is_interactive_shell() ? static::name() : $this->script_name . ' ' . static::name();

        $this->output->info(
            sprintf(
                "Run `{$prefix} check` to know whether %s can run on this server.",
                SMLISER_APP_NAME
            )
            
        );
        
        $this->output->info( "Run `{$prefix} help` to see available subcommands." );
        $this->output->writeln('');

        return 0;
    }

    /**
     * Install default permission roles.
     *
     * @param CommandInput $input
     * @return int
     */
    public function install_default_roles( CommandInput $input ): int {
        $default_roles = DefaultRoles::all();
        $rows          = [];

        foreach ( $default_roles as $slug => $roledata ) {
            $role = new Role();
            $role->set_capabilities( $roledata['capabilities'] );
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );
            $role->set_slug( $slug );

            try {
                if ( $role->save() ) {
                    $rows[] = [ $slug, '✔ Installed' ];
                } else {
                    $rows[] = [ $slug, '⚠ Skipped — unable to save' ];
                }
            } catch ( \Throwable $e ) {
                $rows[] = [ $slug, '✖ ' . $e->getMessage() ];
            }
        }

        $this->output->table( [ 'Role', 'Result' ], $rows );

        return 0;
    }
}