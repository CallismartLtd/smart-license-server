<?php
/**
 * SmartLicenseServer Admin Menu Configuration
 *
 * @package SmartLicenseServer\Admin
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Contracts\AbstractDashboardRegistry;

final class AdminDashboardRegistry extends AbstractDashboardRegistry {

    protected function boot() : void {
        if ( $this->booted ) {
            return;
        }

        $this->booted   = true;

        $this->menu = [

        //     'settings' => [
        //         'title'   => 'Settings',
        //         'slug'    => 'settings',
        //         'handler' => [ OptionsPage::class, 'router' ],
        //         'icon'    => 'ti ti-generic',
        //     ],
        ];

        
        $core_menu  = [
            DashboardPage::class,
            RepositoryPage::class,
            LicensePage::class,
            BulkMessagePage::class,
            AccessControlPage::class,
            OptionsPage::class
        ];
        
        /** @var class-string<AdminPageInterface>[] $core_menu */

        foreach( $core_menu as $class ) {
            $this->register( $class::get_menu_key(), $class::get_menu_data() );
            
            foreach( $class::get_submenu() as $value ) {
                $this->add_submenu( $class::get_menu_key(), $value );
            }
        }
    }

    /**
     * Determine whether a key represents the root/overview menu item.
     *
     * @param string $key Already canonicalized.
     * @return bool
     */
    public function is_root_menu( string $key ) : bool {
        $key    = $this->canonical_key( $key );
        return 'overview' === $key;
    }
}