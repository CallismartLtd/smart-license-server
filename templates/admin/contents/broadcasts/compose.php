<?php
/**
 * Compose message template
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * @var array $menu_args
 * @var array $pagination
 * @var \SmartLicenseServer\Core\URL $current_url
 * @var \SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Messaging\BulkMessage|null $message
 */


defined( 'SMLISER_ROOT' ) || exit; ?>

<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <?php if ( empty( $message ) && $request->has( 'msg_id' ) ) : ?>
        <?php echo smliser_not_found_container( __( 'Invalid or deleted message', 'smliser' ) ); // phpcs:ignore ?>
    <?php else : ?>
        <form class="smliser-compose-message-container">
            <div class="smliser-compose-message-container_left">
                <div class="smliser-compose-message-form-row">
                    <label for="subject"><?php echo escHtml( 'Subject' ); ?></label>
                    <input type="text" name="subject" value="<?php echo escAttr( $message?->get_subject()?: '' ); ?>" id="subject" class="smliser-form-input">
                </div>

                <div class="smliser-compose-message-form-row">
                    <textarea name="message_body" id="message-body" class="hidden">
                        <?php echo sanitize_html( $message?->get_body() ?: '' ); ?>
                    </textarea>
                </div>
                
            </div>
            <div class="smliser-compose-message-container_right">
                <div class="smliser-compose-message-form-row">
                    <label for="smliser-app-select"><?php echo escHtml( 'Choose App(s)' ); ?></label>
                    <select id="smliser-app-select" name="associated_apps[]" title="<?php echo escHtml( 'Select a hosted application to associate this message with.' ); ?>" multiple>
                        <?php if ( $message ) : ?>
                            <?php foreach ( $message->get_associated_apps() as $type => $slugs ) : ?>
                                <optgroup label="<?php echo escHtml( ucfirst( $type ) ); ?>">
                                    <?php if ( is_array( $slugs ) ) : ?>
                                        <?php foreach ( $slugs as $slug ) : ?>
                                            <option value="<?php printf( '%s:%s', escAttr( $type ), escAttr( $slug ) ); ?>" selected><?php echo escHtml( $slug ) ?></option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="<?php printf( '%s:%s', escAttr( $type ), escAttr( $slugs ) ); ?>" selected><?php echo escHtml( $slugs ) ?></option>
                                    <?php endif; ?>
                                </optgroup>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <input type="hidden" name="message_id" value="<?php echo escAttr( $message?->get_id() ?: 0 ); ?>">
                <button type="submit" class="button" title="<?php echo escHtml( 'Update this message' ); ?>"><?php printf( '%s', $message ? 'Update' : 'Publish' ); ?></button>
            </div>
        </form>
    <?php endif; ?>
</div>