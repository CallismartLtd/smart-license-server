<?php
/**
 * Client Dashboard Configuration
 *
 * @package SmartLicenseServer\Client
 */

namespace SmartLicenseServer\Frontend;

use SmartLicenseServer\Contracts\AbstractDashboardRegistry;

final class ClientDashboardConfiguration extends AbstractDashboardRegistry {

    protected function boot() : void {

        $this->menu = [
            'overview' => [
                'title'   => 'Dashboard',
                'slug'    => '',
                'handler' => [ DashboardPage::class, 'render' ],
                'icon'    => 'ti ti-home',
            ],
            'services' => [
                'title'   => 'My Services',
                'slug'    => 'services',
                'handler' => [ ServicesPage::class, 'render' ],
                'icon'    => 'ti ti-briefcase',
            ],
            'licenses' => [
                'title'   => 'Licenses',
                'slug'    => 'licenses',
                'handler' => [ LicensePage::class, 'render' ],
                'icon'    => 'ti ti-key',
            ],
            'billing' => [
                'title'   => 'Billing',
                'slug'    => 'billing',
                'handler' => [ BillingPage::class, 'render' ],
                'icon'    => 'ti ti-credit-card',
            ],
            'account' => [
                'title'   => 'Account',
                'slug'    => 'account',
                'handler' => [ AccountPage::class, 'render' ],
                'icon'    => 'ti ti-user',
            ],
        ];
    }
}