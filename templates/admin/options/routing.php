<?php
/**
 * Page set up options template.
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args();
?>

<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>

    <?php if ( $message = smliser_get_query_param( 'message' ) ) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php echo esc_html( $message ); ?></p>
        </div>
    <?php endif; ?>

    <form class="smliser-options-form" action="">
        <div class="notice notice-warning">
            <p>
                <i class="ti ti-alert-hexagon-filled"></i>
                <strong>Caution:</strong> Changing these values will update the public URLs of your hosted applications. Any existing links shared with users may stop working until they are updated.
            </p>
        </div>

        <input type="hidden" name="action" value="smliser_save_route_options">

        <div class="smliser-options-form_body">
            <?php foreach ( static::get_routing_fields() as $field ) : ?>
                <?php smliser_render_input_field( $field ); ?>
            <?php endforeach; ?>

            <div class="smliser-form-label-row">
                <span>Routing Settings</span>
                <button type="submit" class="smliser-submit-button">Save</button>
            </div>
            <span class="smliser-spinner"></span>
        </div>
    </form>
</div>