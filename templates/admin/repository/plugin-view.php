<?php
/**
 *  Admin Plugin details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit; ?>

<?php if ( empty( $plugin ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'Invalid or deleted plugin <a href="' . smliser_repo_page() . '">Back</a>' ) ); ?>
<?php else : ?>
    <h1><?php echo esc_html( $plugin->get_name() ) ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=repository' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-database"></span> Repository</a>
    <a href="<?php echo esc_url( smliser_repository_admin_action_page( 'edit', $plugin->get_item_id() ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-edit"></span> Edit Plugin</a>
    <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=smliser_admin_download_plugin&item_id=' . $plugin->get_item_id() ), 'smliser_download_token', 'download_token' ) ) ?>" download class="button action smliser-nav-btn"><span class="dashicons dashicons-download"></span> Download Plugin</a>
    <a class="button action smliser-nav-btn" id="smliser-plugin-delete-button" item-id="<?php echo absint( $plugin->get_item_id() ); ?>"><span class="dashicons dashicons-trash"></span> Delete Plugin</a>
    <div class="smliser-admin-view-page-wrapper">
        <div class="smliser-admin-view-page-header"> 
            <div class="smliser-admin-view-page-header-child">
                <h2><span class="dashicons dashicons-media-archive"></span> Info</h2>
                <p><span class="dashicons dashicons-database-view"></span> Plugin ID: <?php echo esc_html( absint( $plugin->get_item_id() ) ) ?></p>
                <p><span class="dashicons dashicons-update-alt"></span> Version: <?php echo esc_html( $plugin->get_version() ) ?></p>
                <p><span class="dashicons dashicons-businessman"></span> Author: <?php echo esc_html( $plugin->get_author() ) ?></p>
            </div>

            <div class="smliser-admin-view-page-header-child">
                <h2><span class="dashicons dashicons-chart-bar"></span> Statistics</h2>
                <p><span class="dashicons dashicons-download"></span> Downloads: <?php echo absint( $stats->get_downloads( $plugin->get_item_id() ) ) ?></p>
                <p><span class="dashicons dashicons-cloud-saved"></span></span> Active Installations: <?php echo absint( $stats->estimate_active_installations( $plugin->get_item_id() ) ) ?></p>
                <p><span class="dashicons dashicons-chart-line"></span> Average Daily Download: <?php echo absint( $stats->get_average_daily_downloads( $plugin->get_item_id() ) ) ?></p>
            </div>

        </div>
        
        <div class="smliser-admin-view-page-body">
            <div class="smliser-admin-view-page-body-item">
                <p>Plugin ID: <p><?php echo esc_html( absint( $plugin->get_item_id() ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Plugin Name: <p><?php echo esc_html( $plugin->get_name() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Slug: <p><?php echo esc_html( $plugin->get_slug() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Author: <p><?php echo esc_html( $plugin->get_author() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
            <p>Author URL: <p><a href="<?php echo esc_url( $plugin->get_author_profile() ) ?>" target="framename"><?php echo esc_html( $plugin->get_author_profile() )?></a></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>License Status: <p><?php echo esc_html( $plugin->is_licensed() ? 'Licensed' : 'Not Licensed' );?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Requires WordPress: <p> <?php echo esc_html( $plugin->get_required() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Requires PHP: <p> <?php echo esc_html( $plugin->get_required_php() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Tested WP version: <p> <?php echo esc_html( $plugin->get_tested() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Date Created: <p><?php echo esc_html( smliser_check_and_format( $plugin->get_date_created(), true ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Last Updated: <p><?php echo esc_html( smliser_check_and_format( $plugin->get_last_updated(), true ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Public Download URL: <p><?php echo $plugin->is_licensed() ? $plugin->licensed_download_url() : esc_html( $plugin->get_download_url()  ); ?></p></p>
            </div>
        </div>
    </div>
<?php endif; ?>