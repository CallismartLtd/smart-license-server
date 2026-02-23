<?php
/**
 * License edit form.
 * 
 * @var SmartLicenseServer\Monetization\License $license
 */

defined( 'SMLISER_ABSPATH' ) ||  exit;
?>


<?php if ( $saved = smliser_get_query_param( 'message' ) ):?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved ); ?></p></div>
<?php endif;?>
<h1>Edit License <span class="dashicons dashicons-edit"></span></h1>
<?php if ( empty( $license ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'Invalid or deleted license <a href="' . smliser_license_page() . '">Back</a>' ) ); ?>
<?php else: ?>
    <a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $license->get_id() ) ) ?>" class="button action smliser-nav-btn">View License</a>
    <div class="smliser-form-container">
        <form id="smliserForm" class="smliser-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <input type="hidden" name="action" value="smliser_save_license">
            <input type="hidden" name="license_id" value="<?php echo esc_attr( $license->get_id() ) ?>">
            <?php wp_nonce_field( 'smliser_nonce_field', 'smliser_nonce_field' ); ?>
            
            <div class="smliser-form-row">
                <label for="user_id" class="smliser-form-label">Client</label>
                <span class="smliser-form-description" title="Add User">?</span>
                <?php
                $selected_user = ( $user_id ) ? get_user_by( 'ID', $user_id ) : false;
                wp_dropdown_users(
                    array(
                        'name'              => 'user_i',
                        'selected'          => '',
                        'show_option_none'  => esc_html__( 'Select a client', 'smliser' ),
                        'option_none_value' => -1,
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
                <input type="text" class="smliser-form-input" name="service_id" id="service_id" value="<?php echo esc_attr( $license->get_service_id() ); ?>">
            </div>

            <!-- Hosted App linking -->
            <div class="smliser-form-row">
                <label for="app_prop" class="smliser-form-label">Target Application</label>
                <span class="smliser-form-description" title="Choose the application to which this license will be issued.">?</span>
                <select class="smliser-select-input license-app-select" id="app_prop" name="app_prop" title="<?php esc_html_e( 'Select a hosted application to associate this message with.', 'smliser' ); ?>">
                    <?php if ( $license->is_issued() ) : ?>
                        <option value="<?php printf( '%s', str_replace( '/', ':', $license->get_app_prop() ) ); ?>" selected><?php echo esc_html( $license->get_app()->get_name() ); ?></option>
                    <?php endif; ?>
                </select>

            </div>
            
            <!-- Allowed Domains -->
            <div class="smliser-form-row">
                <label for="allowed_sites" class="smliser-form-label">Max Allowed Domains</label>
                <span class="smliser-form-description" title="Set the maximum number of domains allowed to activate license.">?</span>
                <input type="number" class="smliser-form-input" name="allowed_sites" id="allowed_sites" value="<?php echo esc_attr( $license->get_max_allowed_domains( 'edit' ) ); ?>">
            </div>

            <!-- Status -->
            <div class="smliser-form-row">
                <label for="status" class="smliser-form-label">Status</label>
                <span class="smliser-form-description" title="Select the license status">?</span>
                <select name="status" id="status" class="smliser-select-input">
                    <option value="" <?php selected( '', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Auto Calculate', 'smliser' ); ?></option>
                    <option value="Active" <?php selected( 'Active', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Active', 'smliser' ); ?></option>
                    <option value="Deactivated" <?php selected( 'Deactivated', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Deactivated', 'smliser' ); ?></option>
                    <option value="Revoked" <?php selected( 'Revoked', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Revoked', 'smliser' ); ?></option>
                    <option value="Expired" <?php selected( 'Expired', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Expired', 'smliser' ); ?></option>
                    <option value="Suspended" <?php selected( 'Suspended', $license->get_status( 'edit' ) ); ?>><?php esc_html_e( 'Suspended', 'smliser' ); ?></option>
                </select>
            </div>

            <!-- Start Date -->
            <div class="smliser-form-row">
                <label for="start_date" class="smliser-form-label">Start Date</label>
                <span class="smliser-form-description" title="Choose the commencement date for this license">?</span>
                <input type="date" class="smliser-form-input" name="start_date" id="start_date" value="<?php echo esc_attr( $license->get_start_date() ); ?>">
            </div>

            <!-- End Date -->
            <div class="smliser-form-row">
                <label for="end_date" class="smliser-form-label">End Date</label>
                <span class="smliser-form-description" title="Choose the expiration date for this license">?</span>
                <input type="date" class="smliser-form-input" name="end_date" id="end_date" value="<?php echo esc_attr( $license->get_end_date( 'edit' ) ); ?>">
            </div>

            <input type="submit" name="smliser_license_edit" class="button action smliser-bulk-action-button" value="Update">
        </form>
    </div>
<?php endif;?>