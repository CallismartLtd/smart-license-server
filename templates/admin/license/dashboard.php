<?php
/**
 * The admin license page dashboard template.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\Monetization\License[] $licenses
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

/** @var array $args */
$args   = self::get_menu_args();

unset( $args['breadcrumbs'][0] ); // Remove the home link.

\array_unshift(
    $args['actions'],
    array(
        'title'     => 'Add New License',
        'label'     => 'Add New',
        'url'       => $add_url,
        'icon'      => 'ti ti-plus',
    ),

    array(
        'title'     => 'View Activity Logs',
        'label'     => 'Activity Logs',
        'url'       => admin_url( 'admin.php?page=licenses&tab=logs' ),
        'icon'      => 'ti ti-activity',
    )

);
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $args ); ?>
    <div class="smliser-table-wrapper">
        <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <?php if ( empty( $licenses ) ) : ?>
            <?php echo wp_kses_post( smliser_not_found_container( 'All licenses will appear here' ) ); ?>
        <?php else : ?>
            <form id="smliser-bulk-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            
                <div class="smliser-actions-wrapper">
                    <div class="smliser-bulk-actions">
                        <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                            <option value=""><?php echo esc_html__( 'Bulk Actions', 'smliser' ); ?></option>
                            <option value=""><?php echo esc_html__( 'Auto Calc', 'smliser' ); ?></option>
                            <option value="Deactivated"><?php echo esc_html__( 'Deactivate', 'smliser' ); ?></option>
                            <option value="Suspended"><?php echo esc_html__( 'Suspend', 'smliser' ); ?></option>
                            <option value="Revoked"><?php echo esc_html__( 'Revoke', 'smliser' ); ?></option>
                            <option value="delete"><?php echo esc_html__( 'Delete', 'smliser' ); ?></option>
                        </select>
                        <button type="submit" class="button action smliser-bulk-action-button"><?php echo esc_html__( 'Apply', 'smliser' ); ?></button>
                    </div>
                    <div class="smliser-search-box">
                        <input 
                            type="search" id="smliser-search" class="smliser-search-input"
                            placeholder="<?php echo esc_attr__( 'Search Licenses', 'smliser' ); ?>"
                        />
                    </div>
                </div>
            
                <input type="hidden" name="action" value="smliser_bulk_action">
                <input type="hidden" name="context" value="license">
                <?php wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce'); ?>
                <table class="smliser-table widefat striped">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="smliser-select-all"></th>
                        <th><?php echo esc_html__( 'License ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Licensee Name', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'License Key', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Service ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Licensed App', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Status', 'smliser' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
            
                        <?php foreach ( $licenses as $license ) :
                            ?>        
                            <tr>
                                <td><input type="checkbox" class="smliser-license-checkbox" name="ids[]" value="<?php echo esc_attr( $license->get_id() ); ?>"> </td>
                                <td class="smliser-edit-row">
                                    <?php echo esc_html( $license->get_id() ); ?>
                                    <p class="smliser-edit-link">
                                        <a href="<?php echo esc_url( smliser_license_admin_action_page( 'edit', $license->get_id() ) ); ?>">edit</a> | <a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $license->get_id() ) ); ?>">view</a>
                                    </p>
                                </td>
                            
                                <td><?php echo esc_html( $license->get_licensee_fullname() ); ?></td>
                                <td>
                                    <div class="smliser-license-obfuscation">
                                        <div class="smliser-license-obfuscation_data">
                                            <span class="smliser-license-input">
                                                <input type="text" id="<?php echo esc_html( $license->get_id() ); ?>" value="<?php echo esc_html( $license->get_license_key()) ?>" readonly class="smliser-license-text" />
                                                <span class="dashicons dashicons-admin-page copy-key smliser-tooltip" title="copy license key"></span>
                                            </span>

                                            <span class="smliser-obfuscated-license-text">
                                                <?php echo $license->get_partial_key(); ?>
                                            </span>
                                        </div>
                                        <input type="checkbox" id="<?php echo absint( microtime( true ) ); ?>" class="smliser-licence-key-visibility-toggle smliser-tooltip" title="toggle visibility">
                                    </div>
                                </td>
                                <td><?php echo esc_html( $license->get_service_id() ); ?></td>
                                <td><?php echo esc_html( $license->get_app_prop() ); ?></td>
                                <td><?php echo esc_html( $license->get_status() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>