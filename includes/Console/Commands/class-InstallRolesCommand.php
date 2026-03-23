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

use SmartLicenseServer\Console\CLIAwareTrait;
use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Installs default permission roles.
 */
class InstallRolesCommand implements CommandInterface {
    use CLIAwareTrait;

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


    public function execute( array $args = [] ): void {
        $this->start_timer();
        $this->info( 'Installing default roles...' );
        $this->newline();

        $this->install_default_roles();

        $this->newline();
        $this->done( 'All roles processed.' );
    }

    /**
     * Install default permission roles.
     *
     * @return void
     */
    public function install_default_roles(): void {
        $default_roles = DefaultRoles::all();
        $headers       = [ 'Role', 'Status' ];
        $rows          = [];

        foreach ( $default_roles as $slug => $roledata ) {
            $role = new Role;
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

        $this->table( $headers, $rows );
    }
}