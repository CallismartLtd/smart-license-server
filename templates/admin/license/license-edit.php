<?php
/**
 * License edit form
 */

defined( 'ABSPATH' ) ||  exit;
?>


<?php if ( get_transient( 'smliser_form_success' ) ):?>
    <div class="notice notice-success is-dismissible"><p>Saved!</p></div>
<?php endif;?>
<h1>Edit License <span class="dashicons dashicons-edit"></span></h1>
<?php if ( empty( $license ) ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'Invalid or deleted license <a href="' . smliser_license_page() . '">Back</a>' ) ); ?>
<?php else: ?>
    <a href="<?php echo esc_url( smliser_license_admin_action_page( 'view', $license->get_id() ) ) ?>" class="button action smliser-nav-btn">View License</a>
    <div class="smliser-form-container">
        <?php if ( get_transient( 'smliser_form_validation_message' ) ) : ?>
            <?php echo wp_kses_post( smliser_form_message( get_transient( 'smliser_form_validation_message' ) ) ) ;?>
        <?php endif;?>
        <form id="smliserForm" class="smliser-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="smliser_license_update">
            <input type="hidden" name="license_id" value="<?php echo esc_attr( $license->get_id() ) ?>">
            <?php wp_nonce_field( 'smliser_nonce_field', 'smliser_nonce_field' ); ?>
            
            <div class="smliser-form-row">
                <label for="user_id" class="smliser-form-label">Client</label>
                <span class="smliser-form-description" title="Add User">?</span>
                <?php
                $selected_user = ( $user_id ) ? get_user_by( 'ID', $user_id ) : false;
                wp_dropdown_users(
                    array(
                        'name'              => 'user_id',
                        'selected'          => $selected_user ? $selected_user->ID : '',
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

            <!-- Item ID -->
            <div class="smliser-form-row">
                <label for="item_id" class="smliser-form-label">Item ID</label>
                <span class="smliser-form-description" title="Item ID uniquely identifies a License key. It is required when accessing the license validation API endpoint">?</span>
                <input type="number" class="smliser-form-input" name="item_id" id="item_id" value="<?php echo esc_attr( $license->get_item_id() ); ?>">
            </div>
            
            <!-- Allowed Websites -->
            <div class="smliser-form-row">
                <label for="allowed_sites" class="smliser-form-label">Allowed Websites</label>
                <span class="smliser-form-description" title="Item ID uniquely identifies a License key. It is required when accessing the license validation API endpoint">?</span>
                <input type="number" class="smliser-form-input" name="allowed_sites" id="allowed_sites" value="<?php echo esc_attr( $license->get_allowed_sites() ); ?>">
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