<?php
/**
 * SmartLicenseServer Admin Menu Configuration
 *
 * @package SmartLicenseServer\Admin
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Admin\ContentHandlers\AccessControlPage;
use SmartLicenseServer\Admin\ContentHandlers\BulkMessagePage;
use SmartLicenseServer\Admin\ContentHandlers\DashboardPage;
use SmartLicenseServer\Admin\ContentHandlers\LicensePage;
use SmartLicenseServer\Admin\ContentHandlers\OptionsPage;
use SmartLicenseServer\Admin\ContentHandlers\RepositoryPage;
use SmartLicenseServer\Contracts\AbstractDashboardRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Core\URLManager;

final class AdminDashboardRegistry extends AbstractDashboardRegistry {

    public function __construct(
        protected URLManager $urlmanager,
        protected Container $container
    ) {}

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

        foreach( $core_menu as $id ) {
            /** @var AdminPageInterface */
            $class   = $this->container->get( $id );
            $this->register( $class->get_menu_key(), $class->get_menu_data() );
            
            foreach( $class->get_submenu() as $value ) {
                $this->add_submenu( $class->get_menu_key(), $value );
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

        $this->add_top_menu( 'logout_button', [
            'title'         => 'Logout',
            'type'          => 'link',
            'href'          => $this->urlmanager->logout_url(),
            'visibility'    => true,
            'icon'          => 'ti ti-logout',
            'attributes'    => [
                'class' => 'smliser-logout-link-btn'
            ]
        ]);
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