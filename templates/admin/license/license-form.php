<?php
/**
 * License edit form
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) ||  exit; ?>

<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( self::get_menu_args() ); ?>

    <form class="smliser-form-container smliser-license-form">
        <?php foreach( $form_fields as $field ) : ?>
            <?php smliser_render_input_field( $field ); ?>
        <?php endforeach; ?>

        <label for="save_license" class="smliser-form-label-row">
            <span>Save License</span>
            <button type="submit" id="save_license" class="smliser-submit-button"><?php printf( '%s', isset( $license ) ? 'Update' : 'Save' ); ?></button>
        </label>
        <span class="smliser-spinner"></span>
    </form>
</div>
