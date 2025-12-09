<?php
/**
 * Compose message template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

defined( 'SMLISER_ABSPATH' ) || exit; ?>

<div class="smliser-admin-page">
    <h1><?php esc_html_e( 'Compose Bulk Message', 'smliser' ); ?></h1>

    <form class="smliser-compose-message-container">
        <div class="smliser-compose-message-container_left">
            <div class="smliser-compose-message-form-row">
                <label for="subject"><?php esc_html_e( 'Subject', 'smliser' ); ?></label>
                <input type="text" name="subject" id="subject" class="smliser-form-input">
            </div>

            <div class="smliser-compose-message-form-row">
                <textarea name="message_body" id="message-body" class="hidden"></textarea>
            </div>
            
        </div>
        <div class="smliser-compose-message-container_right">
            <div class="smliser-compose-message-form-row">
                <label for="smliser-app-select"><?php esc_html_e( 'Choose App(s)', 'smliser' ); ?></label>
                <select id="smliser-app-select" name="associated_apps[]" title="<?php esc_html_e( 'Select a hosted application to associate this message with.', 'smliser' ); ?>" multiple></select>
            </div>
            
            <button type="submit" class="button" title="<?php esc_html_e( 'Publish this message', 'smliser' ); ?>"><?php esc_html_e( 'Publish', 'smliser' ); ?></button>
        </div>
    </form>
</div>
<?php wp_enqueue_script( 'smliser-tinymce' );