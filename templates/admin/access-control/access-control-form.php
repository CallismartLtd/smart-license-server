<?php
/**
 * Access control dashboard template.
 *
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\AccessControlPage
 */

defined( 'SMLISER_ABSPATH' ) || exit; ?>

<div class="smliser-admin-repository-template">
    <?php self::print_header(); ?>
    <form class="smliser-access-control-form">
        <h2><?php echo esc_html( $title ); ?></h2>
        <div class="smliser-two-rows">
            <div class="smliser-two-rows_left">
                <?php foreach( $form_fields as $field ) : ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>
            </div>
            <div class="smliser-two-rows_right">
                <div class="smliser-avatar-upload">
                    <div class="smliser-avatar-upload_image-holder">
                        <img class="smliser-avatar-upload_image-preview" src="<?php echo esc_url(  $avatar_url ); ?>" title="<?php echo esc_attr( basename( $avatar_url ) ); ?>" alt="avatar">
                    </div>
                    <div class="smliser-avatar-upload_data">
                        <input type="file" name="avatar" id="smliser-avatar-input" accept="image/*" class="hidden">
                        <div class="smliser-avatar-upload_buttons-row">
                            <button type="button" class="button add-file"><i class="ti ti-camera"></i> Upload Image</button>
                            <button type="button" class="button clear hidden"><i class="ti ti-x"></i> Clear</button>
                        </div>
                        <em>Upload an image(170 x 170 pixels recommended).</em>

                        <span class="smliser-avatar-upload_data-filename"><?php echo esc_html( basename( $avatar_url ) ) ?></span>
                    </div>

                </div>
            </div>
        </div>
        
        <?php if ( ! empty( $roles ) ) : ?>
            <h2 class="smliser-access-control-role-deading"><?php echo esc_html( $roles_title ); ?></h2>
            <!-- Mounted dynamically - @see role-builder.js -->
            <div id="smliser-role-builder"></div>
        <?php endif; ?>
        <button type="submit" class="button smliser-save-button">Save</button>
    </form>


</div>