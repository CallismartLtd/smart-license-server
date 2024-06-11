<?php
/**
 *  Admin License details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;
add_filter( 'wp_kses_allowed_html', 'smliser_allowed_html' );

?>
<h1>License Details</h1>
<a href="<?php echo esc_url( smliser_license_admin_action_page( 'edit', $license->get_id() ) ) ?>" class="button action smliser-nav-btn">Edit License</a>
<div class="smliser-admin-view-page-wrapper">
    <div class="smliser-admin-view-page-header"> 
        <div class="smliser-admin-view-page-header-child">
            <h2>Info</h2>
            <p><span class="dashicons dashicons-database-view"></span> License ID: <?php esc_html_e( absint( $license->get_id() ) ) ?></p>
            <p><span class="dashicons dashicons-yes-alt"></span> Status: <?php esc_html_e( $license->get_status() ) ?></p>
            <p><a href="<?php echo esc_url( get_edit_user_link( absint( $license->get_user_id() ) ) ) ?>"><span class="dashicons dashicons-businessman"></span> Client: <?php esc_html_e( $client_full_name ) ?></a></p>
        </div>

        <div class="smliser-admin-view-page-header-child">
            <h2>Statistics</h2>
            <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Sites: <?php esc_html_e( $license->get_allowed_sites() ) ?></p>
            <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Sites Activated: <?php echo $license->get_total_active_sites()?></p>
            <p><span class="dashicons dashicons-plugins-checked"></span> Item ID: <?php esc_html_e( $license->get_item_id() ) ?></p>
        </div>

    </div>
    
    <div class="smliser-admin-view-page-body">
        <div class="smliser-admin-view-page-body-item">
            <p>License ID: <p><?php esc_html_e( absint( $license->get_id() ) ) ?></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Plugin Name: <p><a href="<?php echo esc_url(smliser_repository_admin_action_page( 'view', $licensed_plugin->get_item_id() ) ) ?>"><?php esc_html_e( $licensed_plugin->get_name() )?></a></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Service ID: <p><?php esc_html_e( $license->get_service_id() ) ?></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Start Date: <p><?php esc_html_e( smliser_check_and_format( $license->get_start_date(), true ) ) ?></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>End Date: <p><?php esc_html_e( smliser_check_and_format( $license->get_end_date(), true ) ) ?></p></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>License Key: <p><?php echo wp_kses_post( $license->get_copyable_Lkey() ) ?></p></p>
        </div>
    </div>
</div>