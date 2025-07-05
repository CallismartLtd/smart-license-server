<?php
/**
 *  Admin License details page.
 * 
 * @author Callistus.
 * @package Smliser\templates.
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;
add_filter( 'wp_kses_allowed_html', 'smliser_allowed_html', 10 , 2 );
?>
<h1>License Details</h1>
<a href="<?php echo esc_url( smliser_license_admin_action_page( 'edit', $license->get_id() ) ); ?>" class="button action smliser-nav-btn">Edit License</a>
<?php if ( $license->has_item() ):?>
    <a data-item-id="<?php echo absint( $license->get_item_id() ); ?>" data-license-key="<?php echo esc_attr( $license->get_license_key() ); ?>" data-plugin-name="<?php echo esc_html( $plugin_name ); ?>" class="button action smliser-nav-btn" id="smliserDownloadTokenBtn">Generate Download Token</a>
<?php endif;?>
<a href="<?php echo esc_url( $delete_link ); ?>" class="button action smliser-nav-btn" id="smliser-license-delete-button">Delete License</a>
<div class="smliser-admin-view-page-wrapper">
    <div class="smliser-admin-view-page-header"> 
        <div class="smliser-admin-view-page-header-child">
            <h2>Info</h2>
            <p><span class="dashicons dashicons-database-view"></span> License ID: <?php echo esc_html( absint( $license->get_id() ) ) ?></p>
            <p><span class="dashicons dashicons-yes-alt"></span> Status: <?php echo esc_html( $license->get_status() ) ?></p>
            <p><a href="<?php echo esc_url( get_edit_user_link( absint( $license->get_user_id() ) ) ) ?>"><span class="dashicons dashicons-businessman"></span> Client: <?php echo esc_html( $client_full_name ) ?></a></p>
        </div>

        <div class="smliser-admin-view-page-header-child">
            <h2>Statistics</h2>
            <p><span class="dashicons dashicons-admin-site-alt"></span> Max Allowed Sites: <?php echo esc_html( $license->get_allowed_sites() ) ?></p>
            <p><span class="dashicons dashicons-admin-site-alt3"></span> Total Sites Activated: <?php echo absint( $license->get_total_active_sites() )?></p>
            <p><span class="dashicons dashicons-plugins-checked"></span> Item ID: <?php echo esc_html( $license->get_item_id() ) ?></p>
        </div>

    </div>
    
    <div class="smliser-loader-container">
        <span class="smliser-loader"></span>
    </div>
    
    <div id="ajaxContentContainer"></div>

    <div class="smliser-admin-view-page-body">
        <div class="smliser-admin-view-page-body-item">
            <p>License ID:</p>
            <p><?php echo esc_html( absint( $license->get_id() ) ) ?></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Plugin Name:</p>
            <a href="<?php echo ! empty( $licensed_plugin ) ? esc_url( smliser_repository_admin_action_page( 'view', $licensed_plugin->get_item_id() ) ) : '#' ?>"><?php echo esc_html( ! empty($licensed_plugin ) ? $licensed_plugin->get_name() : 'N/L' )?></a>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Service ID:</p>
            <p><?php echo esc_html( $license->get_service_id() ) ?></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Start Date:</p>
            <p><?php echo esc_html( smliser_check_and_format( $license->get_start_date() ) ) ?></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>End Date:</p>
            <p><?php echo esc_html( smliser_check_and_format( $license->get_end_date() ) ) ?></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>Activated on:</p>
            <p><?php echo esc_html( $license->get_active_sites() ) ?></p>
        </div>

        <div class="smliser-admin-view-page-body-item">
            <p>License Key:</p>
            <?php echo wp_kses_post( $license->get_copyable_Lkey() ) ?>
        </div>
    </div>

    <h2>API Routes</h2>
</div>