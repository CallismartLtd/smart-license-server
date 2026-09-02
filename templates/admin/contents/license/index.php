<?php
/**
 * The admin license page dashboard template.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 * @var SmartLicenseServer\Monetization\License[] $licenses
 * @var SmartLicenseServer\Core\Request $request
 * @var SmartLicenseServer\Core\URL $add_url
 * @var SmartLicenseServer\Core\URL $current_url
 * @var array $pagination
 * @var \SmartLicenseServer\Admin\ContentHandlers\LicensePage $page_handler
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

defined( 'SMLISER_ROOT' ) || exit;

/** @var array $args */
$args   = $page_handler->get_menu_args( $request );

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
        'url'       => $urlmanager->admin_license_page_url( 'logs' ),
        'icon'      => 'ti ti-activity',
    )

);
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $args ); ?>
    <div class="smliser-table-wrapper">
        <?php if ( $message = $request->get( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo escHtml( $message ); ?></p></div>
        <?php endif; ?>

        <?php if ( empty( $licenses ) ) : ?>
            <?php echo sanitize_html( smliser_not_found_container( 'All licenses will appear here' ) ); ?>
        <?php else : ?>
            <form id="smliser-bulk-action-form" method="post">
            
                <div class="smliser-actions-wrapper">
                    <div class="smliser-bulk-actions">
                        <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                            <option value=""><?php echo escHtml( 'Bulk Actions' ); ?></option>
                            <option value=""><?php echo escHtml( 'Auto Calc' ); ?></option>
                            <option value="Deactivated"><?php echo escHtml( 'Deactivate' ); ?></option>
                            <option value="Suspended"><?php echo escHtml( 'Suspend' ); ?></option>
                            <option value="Revoked"><?php echo escHtml( 'Revoke' ); ?></option>
                            <option value="delete"><?php echo escHtml( 'Delete' ); ?></option>
                        </select>
                        <button type="submit" class="button action smliser-bulk-action-button"><?php echo escHtml( 'Apply' ); ?></button>
                    </div>
                    <a href="<?php echo escUrl( $current_url->add_query_param( 'tab', 'search' )->url() ); ?>" class="smliser-btn smliser-btn-white">Search Licenses</a>
                </div>
            
                <input type="hidden" name="action" value="smliser_bulk_action">
                <input type="hidden" name="context" value="license">
                <?php wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce'); ?>
                <table class="smliser-table widefat striped">
                    <thead>
                    <tr>
                        <th><input type="checkbox" id="smliser-select-all"></th>
                        <th><?php echo escHtml( 'License ID' ); ?></th>
                        <th><?php echo escHtml( 'Licensee Name' ); ?></th>
                        <th><?php echo escHtml( 'License Key' ); ?></th>
                        <th><?php echo escHtml( 'Service ID' ); ?></th>
                        <th><?php echo escHtml( 'Licensed App' ); ?></th>
                        <th><?php echo escHtml( 'Status' ); ?></th>
                    </tr>
                    </thead>
                    <tbody>
            
                        <?php foreach ( $licenses as $license ) :
                            ?>        
                            <tr>
                                <td><input type="checkbox" class="smliser-license-checkbox" name="ids[]" value="<?php echo escAttr( $license->get_id() ); ?>"> </td>
                                <td class="smliser-edit-row">
                                    <?php echo escHtml( $license->get_id() ); ?>
                                    <p class="smliser-edit-link">
                                        <a href="<?php echo 
                                            escUrl( $urlmanager->admin_license_page_url(
                                                'edit',['id' => $license->get_id()]
                                            )->url() ); ?>">edit</a>|
                                        <a href="<?php echo 
                                            escUrl( $urlmanager->admin_license_page_url(
                                                'view', ['id' => $license->get_id()]
                                            )->url() ); ?>">view</a>
                                    </p>
                                </td>
                            
                                <td><?php echo escHtml( $license->get_licensee_fullname() ); ?></td>
                                <td>
                                    <div class="smliser-license-obfuscation">
                                        <div class="smliser-license-obfuscation_data">
                                            <span class="smliser-license-input">
                                                <input type="text" id="<?php echo escHtml( $license->get_id() ); ?>" value="<?php echo escHtml( $license->get_license_key()) ?>" readonly class="smliser-license-text" />
                                                <span class="ti ti-copy copy-key smliser-tooltip" title="copy license key"></span>
                                            </span>

                                            <span class="smliser-obfuscated-license-text">
                                                <?php echo $license->get_partial_key(); ?>
                                            </span>
                                        </div>
                                        <input type="checkbox" id="<?php echo intval( microtime( true ) ); ?>" class="smliser-licence-key-visibility-toggle smliser-tooltip" title="toggle visibility">
                                    </div>
                                </td>
                                <td><?php echo escHtml( $license->get_service_id() ); ?></td>
                                <td><?php echo escHtml( $license->get_app_prop() ); ?></td>
                                <td><?php echo escHtml( $license->get_status() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>