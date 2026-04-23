<?php
/**
 * General settings template file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 * @since 0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = OptionsPage::get_menu_args( $request );
unset( $menu_args['breadcrumbs'][0] );

?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    
    <form action="" class="smliser-options-form">
        <input type="hidden" name="action" value="smliser_save_system_options" />
        <div class="smliser-options-form_body">
            <span class="smliser-spinner"></span>
            <?php foreach( OptionsPage::system_settings_fields() as $field ) : ?>
                <?php smliser_render_input_field( $field ); ?>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="smliser-submit-button">Save</button>
    </form>
</div>