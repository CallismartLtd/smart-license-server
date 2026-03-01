<?php
/**
 * Admin bulk message search file.
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * 
 * @var \SmartLicenseServer\Core\URL $current_url 
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit; ?>

<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    <div class="smliser-table-wrapper">
      
        <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
            <div class="notice notice-info is-dismissible"><p><?php echo esc_html( $message ); ?></p></div>
        <?php endif; ?>

        <div class="smliser-app-search-page smliser-table-wrapper">
            <form class="smliser-admin-search" method="GET" action="<?php echo esc_url( $current_url->get_href() ) ?>">
                <input type="hidden" name="page" value="smliser-bulk-message">
                <input type="hidden" name="tab" value="search">
                
                <input type="search" name="msg_search" value="<?php echo smliser_get_query_param( 'msg_search' ) ?>" id="smliser-msg-search-input" placeholder="Search messages">
                <button type="submit" class="button smliser-btn">Search</button>
            </form>

            <table class="smliser-table widefat striped">
                <thead class="<?php printf( '%s', empty( $messages ) ? 'smliser-hide' : '' ) ?>">
                    <tr>
                        <th><?php echo esc_html__( 'ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Message ID', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Subject', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Body', 'smliser' ); ?></th>
                        <th><?php echo esc_html__( 'Apps', 'smliser' ); ?></th>
                    </tr>
                </thead>
                <tbody>

                    <?php if ( empty( $messages ) ) : ?>
                        <tr><td colspan="6" class="align-center bg-white"><?php echo esc_html__( 'No messages found.', 'smliser' ); ?></td></tr>
                    <?php else: ?>
                    
                        <?php foreach ( $messages as $message ) : ?>        
                            <tr>
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
                    <?php endif; ?>
                </tbody>
            </table>
            <?php smliser_render_pagination( $pagination ); ?>
        </div>
    </div>
</div>