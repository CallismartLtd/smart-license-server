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
                    <div class="smliser-search-box">
                        <input type="search" id="smliser-search" name="search_term" class="smliser-search-input" placeholder="<?php echo esc_attr__( 'Search messages', 'smliser' ); ?>">
                    </div>
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
                                        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'edit', 'msg_id' => $message->get_message_id() ), admin_url( 'admin.php?page=smliser-bulk-message' ) ) ); ?>">Edit</a>
                                        <a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'delete', 'msg_id' => $message->get_message_id() ), admin_url( 'admin.php?page=smliser-bulk-message' ) ) ); ?>">Delete</a>
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
            <p class="smliser-table-count"><?php echo absint( count( $messages ) ); ?> item<?php echo ( count( $messages ) > 1 ? 's': '' ); ?></p>
        <?php endif; ?>
    </div>
</div>