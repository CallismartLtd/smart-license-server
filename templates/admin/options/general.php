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

        <div class="smliser-form-row">
            <label for="smliser-plugin-name" class="smliser-form-label">Option Name:</label>
            <span class="smliser-form-description" title="Add the plugin name, name must match with the name on plugin file header">?</span>
            <input type="text" name="" id="smliser-plugin-name" class="smliser-form-input" required>
        </div>
    </form>
</div>