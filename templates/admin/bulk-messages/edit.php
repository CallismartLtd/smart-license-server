<?php
/**
 * Compose message template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

defined( 'ABSPATH' ) || exit; ?>

<div class="smliser-admin-page">
    <h1><?php esc_html_e( 'Edit Bulk Message', 'smliser' ); ?></h1>

    <?php if ( empty( $message ) ) : ?>
        <?php echo smliser_not_found_container( __( 'Invalid or deleted message', 'smliser' ) ); // phpcs:ignore ?>
    <?php else : ?>

        <form class="smliser-compose-message-container">
            <div class="smliser-compose-message-container_left">
                <div class="smliser-compose-message-form-row">
                    <label for="subject"><?php esc_html_e( 'Subject', 'smliser' ); ?></label>
                    <input type="text" name="subject" value="<?php echo esc_attr( $message->get_subject() ); ?>" id="subject" class="smliser-form-input">
                </div>

                <div class="smliser-compose-message-form-row">
                    <textarea name="message_body" id="message-body" class="hidden">
                        <?php echo wp_kses_post( $message->get_body() ); ?>
                    </textarea>
                </div>
                
            </div>
            <div class="smliser-compose-message-container_right">
                <div class="smliser-compose-message-form-row">
                    <label for="smliser-app-select"><?php esc_html_e( 'Choose App(s)', 'smliser' ); ?></label>
                    <select id="smliser-app-select" name="associated_apps[]" title="<?php esc_html_e( 'Select a hosted application to associate this message with.', 'smliser' ); ?>" multiple>
                        <?php foreach ( $message->get_associated_apps() as $type => $slugs ) : ?>
                            <optgroup label="<?php echo esc_html( ucfirst( $type ) ); ?>">
                                <?php if ( is_array( $slugs ) ) : ?>
                                    <?php foreach ( $slugs as $slug ) : ?>
                                        <option value="<?php printf( '%s:%s', esc_attr( $type ), esc_attr( $slug ) ); ?>" selected><?php echo esc_html( $slug ) ?></option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="<?php printf( '%s:%s', esc_attr( $type ), esc_attr( $slugs ) ); ?>" selected><?php echo esc_html( $slugs ) ?></option>
                                <?php endif; ?>
                            </optgroup>

                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="message_id" value="<?php echo esc_attr( $message_id ); ?>">
                <button type="submit" class="button" title="<?php esc_html_e( 'Update this message', 'smliser' ); ?>"><?php esc_html_e( 'Update', 'smliser' ); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php wp_enqueue_script( 'smliser-tinymce' );