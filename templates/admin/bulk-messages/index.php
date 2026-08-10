<?php
/**
 * The bulk messages admin dashboard template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

unset( $menu_args['breadcrumbs'][0] ); 
defined( 'SMLISER_ROOT' ) || exit; ?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <div class="smliser-table-wrapper">
        <?php if ( $notice = smliser_get_query_param( 'message' ) ) : ?>
            <?php wp_admin_notice( $notice, ['type' => 'success', 'dismissible' => true] ) ?>
        <?php endif; ?>

        <?php if ( empty( $messages ) ) : ?>
            <?php echo wp_kses_post( smliser_not_found_container( '<span class="dashicons dashicons-email-alt"></span> All bulk messages with be listed here' ) ); ?>
        <?php else : ?>
            <form id="smliser-bulk-action-form" method="post" action="<?php echo escUrl( adminUrl( 'admin-post.php' ) ); ?>">
            
                <div class="smliser-actions-wrapper">
                    <div class="smliser-bulk-actions">
                        <select name="bulk_action" id="smliser-bulk-action" class="smliser-bulk-action-select" required>
                            <option value=""><?php echo escHtml( 'Bulk action' ); ?></option>
                            <option value="delete"><?php echo escHtml( 'Delete' ); ?></option>
                        </select>
                        <button type="submit" class="button action smliser-bulk-action-button"><?php echo escHtml( 'Apply' ); ?></button>
                    </div>
                    <a href="<?php echo escUrl( $current_url->add_query_param( 'tab', 'search' ) ); ?>" class="smliser-btn smliser-btn-white">Search Messages</a>
                </div>
            
                <input type="hidden" name="action" value="smliser_bulk_action">
                <input type="hidden" name="context" value="bulk-message">
                <?php wp_nonce_field( 'smliser_table_nonce', 'smliser_table_nonce'); ?>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="smliser-select-all"></th>
                            <th><?php echo escHtml( 'ID' ); ?></th>
                            <th><?php echo escHtml( 'Message ID' ); ?></th>
                            <th><?php echo escHtml( 'Subject' ); ?></th>
                            <th><?php echo escHtml( 'Body' ); ?></th>
                            <th><?php echo escHtml( 'Apps' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
            
                        <?php foreach ( $messages as $message ) : ?>        
                            <tr>
                                <td><input type="checkbox" class="smliser-checkbox" name="ids[]" value="<?php echo escAttr( $message->get_id() ); ?>"> </td>
                                <td class="smliser-edit-row">
                                    <?php echo escHtml( $message->get_id() ); ?>
                                    <p class="smliser-edit-link">
                                        <a href="<?php echo escUrl( $current_url->add_query_params( array( 'tab' => 'edit', 'msg_id' => $message->get_message_id() ) ) ); ?>">Edit</a>
                                    </p>
                                </td>
                            
                                <td><?php echo escHtml( $message->get_message_id() ); ?></td>
                                <td><?php echo escHtml( $message->get_subject() ); ?></td>
                                <td><?php echo escHtml( smliser_trim_words( $message->get_body(), 5 ) ); ?></td>
                                <td><?php echo escHtml( $message->print_associated_apps_summary() ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </form>
            <?php smliser_render_pagination( $pagination ); ?>
        <?php endif; ?>
    </div>
</div>