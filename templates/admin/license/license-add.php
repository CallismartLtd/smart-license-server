<?php
/**
 * License edit form
 */

defined( 'SMLISER_ABSPATH' ) ||  exit;
?>
<h1>Add License <span class="dashicons dashicons-edit"></span></h1>
<div class="smliser-form-container">
    <form id="smliserForm" class="smliser-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="smliser_save_license">
        <?php wp_nonce_field( 'smliser_nonce_field', 'smliser_nonce_field' ); ?>
        
        <div class="smliser-form-row">
            <label for="user_id" class="smliser-form-label">Client</label>
            <span class="smliser-form-description" title="Add User">?</span>
            <?php
            wp_dropdown_users(
                array(
                    'name'              => 'user_id',
                    'show_option_none'  => esc_html__( 'Select a client', 'smliser' ),
                    'option_none_value' => 0,
                    'class'             => 'smliser-form-input',
                    'show'              => 'display_name_with_login',
                )
            );
            ?>
        </div>

        <!-- Service ID -->
        <div class="smliser-form-row">
            <label for="service_id" class="smliser-form-label">Service ID</label>
            <span class="smliser-form-description" title="Service ID uniquely identifies a License key. It is required when accessing the license validation API endpoint">?</span>
            <input type="text" class="smliser-form-input" name="service_id" id="service_id" value="">
        </div>

        <!-- Hosted App linking -->
        <div class="smliser-form-row">
            <label for="item_id" class="smliser-form-label">Target Application</label>
            <span class="smliser-form-description" title="Choose the application to which this license will be issued.">?</span>
            <select class="smliser-select-input license-app-select" name="app_prop" title="<?php esc_html_e( 'Select a hosted application to associate this message with.', 'smliser' ); ?>"></select>

        </div>
        
        <!-- Allowed Websites -->
        <div class="smliser-form-row">
            <label for="allowed_sites" class="smliser-form-label">Max Allowed Domains</label>
            <span class="smliser-form-description" title="Set the maximum number of domains allowed to activate license.">?</span>
            <input type="number" class="smliser-form-input" name="allowed_sites" id="allowed_sites" value="">
        </div>

        <!-- Status -->
        <div class="smliser-form-row">
            <label for="status" class="smliser-form-label">Status</label>
            <span class="smliser-form-description" title="Select the license status">?</span>
            <select name="status" id="status" class="smliser-select-input">
                <option value=""><?php esc_html_e( 'Auto Calculate', 'smliser' ); ?></option>
                <option value="Active"><?php esc_html_e( 'Active', 'smliser' ); ?></option>
                <option value="Deactivated"><?php esc_html_e( 'Deactivated', 'smliser' ); ?></option>
                <option value="Expired"><?php esc_html_e( 'Expired', 'smliser' ); ?></option>
                <option value="Suspended"><?php esc_html_e( 'Suspended', 'smliser' ); ?></option>
            </select>
        </div>

        <!-- Start Date -->
        <div class="smliser-form-row">
            <label for="start_date" class="smliser-form-label">Start Date</label>
            <span class="smliser-form-description" title="Choose the commencement date for this license">?</span>
            <input type="date" class="smliser-form-input" name="start_date" id="start_date" value="">
        </div>

        <!-- End Date -->
        <div class="smliser-form-row">
            <label for="end_date" class="smliser-form-label">End Date</label>
            <span class="smliser-form-description" title="Choose the expiration date for this license">?</span>
            <input type="date" class="smliser-form-input" name="end_date" id="end_date" value="">
        </div>

        <input type="submit" name="smliser_license_new" class="button action smliser-bulk-action-button" value="Add License">
    </form>
</div>
