<?php
/**
 * SmartLicenseServer Admin Menu Configuration
 *
 * @package SmartLicenseServer\Admin
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Admin\Handlers\AccessControlPage;
use SmartLicenseServer\Admin\Handlers\BulkMessagePage;
use SmartLicenseServer\Admin\Handlers\DashboardPage;
use SmartLicenseServer\Admin\Handlers\LicensePage;
use SmartLicenseServer\Admin\Handlers\OptionsPage;
use SmartLicenseServer\Admin\Handlers\RepositoryPage;
use SmartLicenseServer\Contracts\AbstractDashboardRegistry;

final class AdminDashboardRegistry extends AbstractDashboardRegistry {

    protected function boot() : void {
        if ( $this->booted ) {
            return;
        }

        $this->booted   = true;
   
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

        $this->add_top_menu( 'theme_toggle', [
            'type'  => 'button',
            'icons' => [
                [ 'class' => 'dashboard-theme-icon dashboard-theme-icon-light ti ti-moon', 'attributes' => [ 'aria-hidden' => 'true' ] ],
                [ 'class' => 'dashboard-theme-icon dashboard-theme-icon-dark ti ti-sun', 'attributes' => [ 'aria-hidden' => 'true' ] ],
            ],
            'attributes' => [
                'id'         => 'dashboard-theme-toggle',
                'class'      => 'dashboard-theme-toggle',
                'aria-label' => 'Toggle theme',
            ],
            'visibility' => true,
        ] );
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