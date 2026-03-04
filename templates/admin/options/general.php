<?php
/**
 * General settings template file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * @since 1.0.0
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args();
unset( $menu_args['breadcrumbs'][0] );

?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    
    <form action="" class="smliser-options-form">
        <input type="hidden" name="action" value="smliser_options" />
        <div class="smliser-options-form_body">
            <?php foreach( static::system_settings_fields() as $field ) : ?>
                <?php smliser_render_input_field( $field ); ?>
            <?php endforeach; ?>

            <label for="save_license" class="smliser-form-label-row">
                <span>Save Settings</span>
                <button type="submit" id="save_license" class="smliser-submit-button"><?php printf( '%s', isset( $license ) ? 'Update' : 'Save' ); ?></button>
            </label>
            <span class="smliser-spinner"></span>
        </div>
    </form>
</div>