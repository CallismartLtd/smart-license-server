<?php
/**
 * Hosted Application preview file.
 * 
 * @author Callistus Nwachukwu
 * 
 * @var \SmartLicenseServer\HostedApps\AbstractHostedApp $app
 * @var SmliserStats $stats The stats object.
 */

defined( 'SMLISER_PATH' ) || exit; ?>

<?php if ( empty( $app ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( sprintf( 'Invalid or deleted %s <a href="%s">Back</a>', $type, smliser_repo_page() ) ) ); ?>
<?php else : ?>
    <h1><?php echo esc_html( $app->get_name() ) ?></h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=repository' ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-database"></span> Repository</a>
    <a href="<?php echo esc_url( add_query_arg( array( 'type' => $type ), smliser_admin_repo_tab( 'monetization', $app->get_id() ) ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-money-alt"></span> Monetization</a>
    <a href="<?php echo esc_url( add_query_arg( array( 'type' => $type ), smliser_admin_repo_tab( 'edit', $app->get_id() ) ) ) ?>" class="button action smliser-nav-btn"><span class="dashicons dashicons-edit"></span> Edit <?php echo esc_html( $type ) ?></a>
    <a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'action' => 'smliser_admin_download', 'type' => $app->get_type(), 'id' => $app->get_id() ), admin_url( 'admin-post.php' ) ), 'smliser_download_token', 'download_token' ) ) ?>" download class="button action smliser-nav-btn"><span class="dashicons dashicons-download"></span> Download Plugin</a>
    <a class="button action smliser-nav-btn smliser-app-delete-button" data-app-info="<?php echo esc_attr( smliser_json_encode_attr( ['slug' => $app->get_slug(), 'type' => $app->get_type()] ) ); ?>"><span class="dashicons dashicons-trash"></span> Delete <?php echo esc_html( $type ) ?></a>
    <div class="smliser-admin-view-page-wrapper">
        <div class="smliser-admin-view-page-header"> 
            <div class="smliser-admin-view-page-header-child">
                <h2><span class="dashicons dashicons-media-archive"></span> Info</h2>
                <p><span class="dashicons dashicons-database-view"></span> Plugin ID: <?php echo esc_html( absint( $app->get_id() ) ) ?></p>
                <p><span class="dashicons dashicons-update-alt"></span> Version: <?php echo esc_html( $app->get_version() ) ?></p>
                <p><span class="dashicons dashicons-businessman"></span> Author: <?php echo esc_html( $app->get_author() ) ?></p>
            </div>

            <div class="smliser-admin-view-page-header-child">
                <h2><span class="dashicons dashicons-chart-bar"></span> Statistics</h2>
                <p><span class="dashicons dashicons-download"></span> Downloads: <?php echo absint( $stats->get_downloads( $app->get_id() ) ) ?></p>
                <p><span class="dashicons dashicons-cloud-saved"></span></span> Active Installations: <?php echo absint( $stats->estimate_active_installations( $app->get_id() ) ) ?></p>
                <p><span class="dashicons dashicons-chart-line"></span> Average Daily Download: <?php echo absint( $stats->get_average_daily_downloads( $app->get_id() ) ) ?></p>
            </div>

        </div>
        
        <div class="smliser-admin-view-page-body">
            <div class="smliser-admin-view-page-body-item">
                <p>Plugin ID: <p><?php echo esc_html( absint( $app->get_id() ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Plugin Name: <p><?php echo esc_html( $app->get_name() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Slug: <p><?php echo esc_html( $app->get_slug() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Author: <p><?php echo esc_html( $app->get_author() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
            <p>Author URL: <p><a href="<?php echo esc_url( $app->get_author_profile() ) ?>" target="framename"><?php echo esc_html( $app->get_author_profile() )?></a></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Monetization Status: <p><?php echo esc_html( $app->is_monetized() ? 'Monetized' : 'Not Monetized' );?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Requires WordPress: <p> <?php echo esc_html( $app->get_required() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Requires PHP: <p> <?php echo esc_html( $app->get_required_php() ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Tested WP version: <p> <?php echo esc_html( $app->get_tested_up_to() ) ?></p></p>
            </div>
            <div class="smliser-admin-view-page-body-item">
                <p>Support URL: <p><?php echo esc_html( $app->get_support_url() ?: 'N/A' ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Date Created: <p><?php echo esc_html( smliser_check_and_format( $app->get_date_created(), true ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Last Updated: <p><?php echo esc_html( smliser_check_and_format( $app->get_last_updated(), true ) ) ?></p></p>
            </div>

            <div class="smliser-admin-view-page-body-item">
                <p>Public Download URL: <p><?php echo $app->is_monetized() ? $app->monetized_url_sample() : esc_html( $app->get_download_url()  ); ?></p></p>
            </div>
        </div>
    </div>
<?php endif;
