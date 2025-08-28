<?php
/**
 * The admin repository page template
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\templates
 */

defined( 'ABSPATH' ) || exit; ?>
<div class="smliser-table-wrapper">
    <h1>Plugin Repository</h1>    
    <a href="<?php echo esc_url( $add_url ); ?>" class="button action smliser-nav-btn">Upload New Plugin</a>
    <?php if ( empty( $plugins ) ) : ?>
        <?php echo wp_kses_post( smliser_not_found_container( 'All uploaded plugins will appear here.' ) ); ?>           
    <?php endif; ?>
    
    <form id="smliser-bulk-action-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
    
        <div class="smliser-actions-wrapper">
    
            <div class="smliser-search-box">
                <input type="search" id="smliser-search" class="smliser-search-input" placeholder="<?php echo esc_attr__( 'Search Plugins', 'smliser' ); ?>">
            </div>
        </div>

        <table class="smliser-table">
            <thead>
                <tr>
                    <th><?php echo esc_html__( 'Item ID', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Plugin Name', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Plugin Author', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Version', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Slug', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Created at', 'smliser' ); ?></th>
                    <th><?php echo esc_html__( 'Last Updated', 'smliser' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $plugins as $plugin ) : ?>
                    <tr>
                        <td class="smliser-edit-row">
                            <?php echo absint( $plugin->get_item_id() ); ?>
                            <div class="smliser-edit-link"><p><a href="<?php echo esc_url( smliser_admin_repo_tab( 'edit', $plugin->get_item_id() ) ); ?>">edit</a> | <a href="<?php echo esc_url( smliser_admin_repo_tab( 'view', $plugin->get_item_id() ) ); ?>">view</a> </p></div>
                        </td>
                        <td><?php echo esc_html( $plugin->get_name() ); ?></td>
                        <td><?php echo $plugin->get_author(); ?></td>
                        <td><?php echo esc_html( $plugin->get_version() ); ?></td>
                        <td><?php echo esc_html( $plugin->get_slug() ); ?></td>
                        <td><?php echo esc_html( smliser_check_and_format( $plugin->get_date_created(), true ) ); ?></td>
                        <td><?php echo esc_html( smliser_check_and_format( $plugin->get_last_updated(), true ) ); ?></td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    </form>
    <p class="smliser-table-count"><?php echo absint( count( $plugins ) ); ?> item<?php echo ( count( $plugins ) > 1 ? 's': '' ); ?></p>
</div>