<?php
/**
 * InstallRoles command class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Console\Commands
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Console\Commands;

use SmartLicenseServer\Console\CommandInput;
use SmartLicenseServer\Utils\Stopwatch;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

/**
 * Installs default permission roles.
 */
class InstallRolesCommand extends AbstractCommand {

    public static function name(): string {
        return 'install:roles';
    }

    public static function description(): string {
        return 'Install default permission roles.';
    }

    public static function synopsis(): string {
        return 'smliser install:roles';
    }

    public static function help(): string {
        return '';
    }

    /**
     * {@inheritdoc}
     *
     * No subcommands here, so get_subcommands()/definition() stay at
     * AbstractCommand's defaults (both empty arrays) — no need to
     * redeclare them just to return the same thing.
     */
    public function run( CommandInput $input ): int {
        $stopwatch = new Stopwatch();
        $stopwatch->start();

        $this->output->info( 'Installing default roles...' );
        $this->output->newline();

        $this->install_default_roles();

        $this->output->newline();
        $this->output->success( sprintf( 'All roles processed. Completed in %ss.', $stopwatch->elapsed() ) );

        return 0;
    }

    /**
     * Install default permission roles.
     *
     * @return void
     */
    private function install_default_roles(): void {
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
    }
}