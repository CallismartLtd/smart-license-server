<?php
/**
 * SmartLicenseServer Admin Menu Configuration
 *
 * @package SmartLicenseServer\Admin
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Contracts\AbstractDashboardRegistry;

defined( 'SMLISER_ABSPATH' ) || exit;

final class AdminDashboardRegistry extends AbstractDashboardRegistry {

    protected function boot() : void {
        if ( $this->booted ) {
            return;
        }

        $this->booted = true;

        $this->menu = [
            'overview' => [
                'title'   => 'Overview',
                'slug'    => '',
                'handler' => [ DashboardPage::class, 'router' ],
                'icon'    => 'ti ti-home',
            ],
            'repository' => [
                'title'   => 'Repository',
                'slug'    => 'repository',
                'handler' => [ RepositoryPage::class, 'router' ],
                'icon'    => 'ti ti-folder',
            ],
            'licenses' => [
                'title'   => 'Licenses',
                'slug'    => 'licenses',
                'handler' => [ LicensePage::class, 'router' ],
                'icon'    => 'ti ti-license',
            ],
            'bulk_messages' => [
                'title'   => 'Bulk Messages',
                'slug'    => 'bulk-messages',
                'handler' => [ BulkMessagePage::class, 'router' ],
                'icon'    => 'ti ti-envelop',
            ],
            'accounts' => [
                'title'   => 'Accounts',
                'slug'    => 'accounts',
                'handler' => [ AccessControlPage::class, 'router' ],
                'icon'    => 'ti ti-users-group',
            ],
            'settings' => [
                'title'   => 'Settings',
                'slug'    => 'settings',
                'handler' => [ OptionsPage::class, 'router' ],
                'icon'    => 'ti ti-generic',
            ],
        ];
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