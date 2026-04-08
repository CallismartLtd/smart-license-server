<?php
/**
 * Email templates list page.
 *
 * Variables available from OptionsPage::email_template_options():
 *   $templates — array<string, array> from EmailTemplateRegistry::all()
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;
use SmartLicenseServer\Environments\WordPress\AdminMenu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args   = OptionsPage::get_menu_args( $request );
$current_url = smliser_get_current_url()->remove_query_param( 'message', 'provider' );

/**
 * Derive a human-readable domain group label from the template key.
 * Keeps the template class itself free of group metadata.
 */
$group_map = [
    'license_issued'                  => 'License',
    'license_activated'               => 'License',
    'license_deactivated'             => 'License',
    'license_expiry_reminder'         => 'License',
    'license_expired'                 => 'License',
    'license_suspended'               => 'License',
    'license_renewed'                 => 'License',
    'welcome'                         => 'Account',
    'password_reset'                  => 'Account',
    'password_changed'                => 'Account',
    'organization_invite'             => 'Organization',
    'organization_member_removed'     => 'Organization',
    'app_published'                   => 'Apps',
    'app_updated'                     => 'Apps',
    'new_app_version_notification'    => 'Apps',
    'app_status_changed'              => 'Apps',
    'app_ownership_changed'           => 'Apps',
    'payment_received'                => 'Monetization',
    'payment_failed'                  => 'Monetization',
    'subscription_cancelled'          => 'Monetization',
    'bulk_message'                    => 'Messaging',
    'test_email'                      => 'System',
    'system_alert'                    => 'System',
];

$group_colors = [
    'License'      => [ 'bg' => '#eef2ff', 'color' => '#4f46e5' ],
    'Account'      => [ 'bg' => '#f0fdf4', 'color' => '#16a34a' ],
    'Organization' => [ 'bg' => '#fdf4ff', 'color' => '#9333ea' ],
    'Apps'         => [ 'bg' => '#eff6ff', 'color' => '#2563eb' ],
    'Monetization' => [ 'bg' => '#fff7ed', 'color' => '#ea580c' ],
    'Messaging'    => [ 'bg' => '#fefce8', 'color' => '#ca8a04' ],
    'System'       => [ 'bg' => '#f8fafc', 'color' => '#475569' ],
];
?>

<div class="smliser-admin-page">
    <?php AdminMenu::print_admin_top_menu( $menu_args ); ?>

    <!-- Group filter -->
    <div class="smliser-template-filter" style="margin:16px 20px 0;">
        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
            <span style="font-size:13px;font-weight:600;color:#64748b;margin-right:4px;">
                Filter:
            </span>
            <button type="button"
                    class="smliser-filter-btn smliser-filter-btn--active"
                    data-group="all">
                All <span class="smliser-filter-count"><?php echo count( $templates ); ?></span>
            </button>
            <?php
            $groups = array_unique( array_values( $group_map ) );
            foreach ( $groups as $group ) :
                $count = count( array_filter(
                    array_keys( $templates ),
                    fn( $k ) => ( $group_map[ $k ] ?? '' ) === $group
                ) );
                if ( $count === 0 ) continue;
            ?>
                <button type="button"
                        class="smliser-filter-btn"
                        data-group="<?php echo esc_attr( $group ); ?>">
                    <?php echo esc_html( $group ); ?>
                    <span class="smliser-filter-count"><?php echo $count; ?></span>
                </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Templates table -->
    <div class="smliser-table-wrapper">
        <table class="smliser-table widefat striped" id="smliser-email-templates-table">
            <thead>
                <tr>
                    <th style="width:30%;">Template</th>
                    <th>Description</th>
                    <th style="width:110px;">Status</th>
                    <th style="width:100px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $templates as $key => $entry ) :
                    $group       = $group_map[ $key ] ?? 'System';
                    $colors      = $group_colors[ $group ];
                    $detail_url  = $current_url
                        ->add_query_param( 'section', 'templates' )
                        ->add_query_param( 'template', $key );
                    $toggle_url  = $current_url
                        ->add_query_param( 'section', 'templates' )
                        ->add_query_param( 'template', $key )
                        ->add_query_param( 'action', $entry['is_enabled'] ? 'disable' : 'enable' );
                ?>
                    <tr data-group="<?php echo esc_attr( $group ); ?>">

                        <!-- Template name + group badge -->
                        <td>
                            <span style="display:block;font-weight:600;color:#1a1a2e;font-size:13px;
                                         margin-bottom:4px;">
                                <?php echo esc_html( $entry['label'] ); ?>
                            </span>
                            <span style="display:inline-block;font-size:11px;font-weight:700;
                                         padding:2px 8px;border-radius:9999px;
                                         background-color:<?php echo esc_attr( $colors['bg'] ); ?>;
                                         color:<?php echo esc_attr( $colors['color'] ); ?>;">
                                <?php echo esc_html( $group ); ?>
                            </span>
                            <?php if ( $entry['has_custom'] ) : ?>
                                <span style="display:inline-block;font-size:11px;font-weight:700;
                                             padding:2px 8px;border-radius:9999px;margin-left:4px;
                                             background-color:#fdf4ff;color:#9333ea;">
                                    Custom
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Description -->
                        <td style="font-size:13px;color:#64748b;line-height:1.5;">
                            <?php echo esc_html( $entry['description'] ); ?>
                        </td>

                        <!-- Enabled / Disabled status -->
                        <td>
                            <?php if ( $entry['is_enabled'] ) : ?>
                                <span style="display:inline-flex;align-items:center;gap:5px;
                                             font-size:12px;font-weight:700;padding:3px 10px;
                                             border-radius:9999px;background:#f0fdf4;
                                             color:#16a34a;border:1px solid #bbf7d0;">
                                    <span style="width:6px;height:6px;border-radius:50%;
                                                 background:#16a34a;display:inline-block;"></span>
                                    Enabled
                                </span>
                            <?php else : ?>
                                <span style="display:inline-flex;align-items:center;gap:5px;
                                             font-size:12px;font-weight:700;padding:3px 10px;
                                             border-radius:9999px;background:#f8fafc;
                                             color:#94a3b8;border:1px solid #e2e8f0;">
                                    <span style="width:6px;height:6px;border-radius:50%;
                                                 background:#94a3b8;display:inline-block;"></span>
                                    Disabled
                                </span>
                            <?php endif; ?>
                        </td>

                        <!-- Actions -->
                        <td>
                            <div style="display:flex;gap:6px;align-items:center;">

                                <button type="button"
                                        class="smliser-template-toggle"
                                        data-key="<?php echo esc_attr( $key ); ?>"
                                        data-enabled="<?php echo $entry['is_enabled'] ? '1' : '0'; ?>"
                                        title="<?php echo $entry['is_enabled'] ? 'Disable this template' : 'Enable this template'; ?>"
                                        style="display:inline-flex;align-items:center;gap:4px;padding:4px 10px;
                                            font-size:12px;font-weight:600;cursor:pointer;border-radius:6px;
                                            border:1px solid <?php echo $entry['is_enabled'] ? '#fecaca' : '#bbf7d0'; ?>;
                                            background:<?php echo $entry['is_enabled'] ? '#fef2f2' : '#f0fdf4'; ?>;
                                            color:<?php echo $entry['is_enabled'] ? '#991b1b' : '#166534'; ?>;">
                                    <span class="dashicons <?php echo $entry['is_enabled'] ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"
                                        style="font-size:14px;width:14px;height:14px;margin-top:2px;"></span>
                                    <?php echo $entry['is_enabled'] ? 'Disable' : 'Enable'; ?>
                                </button>

                                <a href="<?php echo esc_url( $detail_url->add_query_param( 'noheader', true ) ); ?>"
                                class="button smliser-nav-btn"
                                title="Preview and configure this template">
                                    <span class="dashicons dashicons-admin-generic"></span>
                                    Manage
                                </a>

                            </div>
                        </td>

                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>