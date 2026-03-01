<?php
/**
 * The bulk messages admin dashboard template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

use SmartLicenseServer\Admin\Menu;
unset( $menu_args['breadcrumbs'][0] ); 
defined( 'SMLISER_ABSPATH' ) || exit; ?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    <div class="smliser-table-wrapper">
        <?php if ( $notice = smliser_get_query_param( 'message' ) ) : ?>
            <?php wp_admin_notice( $notice, ['type' => 'success', 'dismissible' => true] ) ?>
        <?php endif; ?>

        <?php if ( empty( $messages ) ) : ?>
            <?php echo wp_kses_post( smliser_not_found_container( '<span class="dashicons dashicons-email-alt"></span> All bulk messages with be listed here' ) ); ?>
        <?php else : ?>
            <form id="smliser-bulk-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            
                <div class="smliser-actions-wrapper">
                    <div class="smliser-bulk-actions">
                        <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                            <option value=""><?php echo esc_html__( 'Bulk action', 'smliser' ); ?></option>
                            <option value="delete"><?php echo esc_html__( 'Delete', 'smliser' ); ?></option>
                        </select>
                        <button type="submit" class="button action smliser-bulk-action-button"><?php echo esc_html__( 'Apply', 'smliser' ); ?></button>
                    </div>
                    <a href="<?php echo esc_url( $current_url->add_query_param( 'tab', 'search' ) ); ?>" class="smliser-btn smliser-btn-white">Search Messages</a>
                </div>
            
                <input type="hidden" name="action" value="smliser_bulk_action">
                <input type="hidden" name="context" value="bulk-message">
                <?php wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce'); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="smliser-select-all"></th>
                            <th><?php echo esc_html__( 'ID', 'smliser' ); ?></th>
                            <th><?php echo esc_html__( 'Message ID', 'smliser' ); ?></th>
                            <th><?php echo esc_html__( 'Subject', 'smliser' ); ?></th>
                            <th><?php echo esc_html__( 'Body', 'smliser' ); ?></th>
                            <th><?php echo esc_html__( 'Apps', 'smliser' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
            
                        <?php foreach ( $messages as $message ) : ?>        
                            <tr>
                                <td><input type="checkbox" class="smliser-checkbox" name="ids[]" value="<?php echo esc_attr( $message->get_id() ); ?>"> </td>
                                <td class="smliser-edit-row">
                                    <?php echo esc_html( $message->get_id() ); ?>
                                    <p class="smliser-edit-link">
                                        <a href="<?php echo esc_url( $current_url->add_query_params( array( 'tab' => 'edit', 'msg_id' => $message->get_message_id() ) ) ); ?>">Edit</a>
                                        <a href="#" role="button" class="smliser-delete-message">Delete</a>
                                    </p>
                                </td>
                            
                                <td><?php echo esc_html( $message->get_message_id() ); ?></td>
                                <td><?php echo esc_html( $message->get_subject() ); ?></td>
                                <td><?php echo esc_html( wp_trim_words( $message->get_body(), 5 ) ); ?></td>
                                <td><?php echo esc_html( $message->print_associated_apps_summary() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>