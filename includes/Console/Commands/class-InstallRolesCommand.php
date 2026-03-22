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

use SmartLicenseServer\Console\CommandInterface;
use SmartLicenseServer\Config;
use SmartLicenseServer\Security\Permission\DefaultRoles;
use SmartLicenseServer\Security\Permission\Role;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Installs default permission roles by delegating to the environment
 * provider's install_default_roles() method.
 */
class InstallRolesCommand implements CommandInterface {

    public static function name(): string {
        return 'install:roles';
    }

    public static function description(): string {
        return 'Install default permission roles.';
    }

    public function execute( array $args = [] ): void {
        $this->install_default_roles();
        echo 'Done.' . PHP_EOL;
    }

    /**
     * Install default permission roles.
     *
     * @return void
     */
    public function install_default_roles(): void {
        $default_roles = DefaultRoles::all();

        foreach ( $default_roles as $slug => $roledata ) {
            $role = new Role;
            $role->set_capabilities( $roledata['capabilities'] );
            $role->set_label( $roledata['label'] );
            $role->set_is_canonical( $roledata['is_canonical'] );
            $role->set_slug( $slug );

            try {
                $role->save();
                echo sprintf( '  Role installed: %s' . PHP_EOL, $slug );
            } catch ( \Throwable $e ) {
                echo sprintf( '  Role skipped (%s): %s' . PHP_EOL, $slug, $e->getMessage() );
            }
        }
    }
}