<?php
/**
 * Page routing options template.
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = OptionsPage::get_menu_args( $request );
?>

<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

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
            <?php foreach ( OptionsPage::get_routing_fields() as $field ) : ?>
                <?php smliser_render_input_field( $field ); ?>
            <?php endforeach; ?>

            <div class="smliser-form-label-row">
                <span></span>
                <button type="submit" class="smliser-submit-button" style="width: 100px;">Save</button>
            </div>
            <span class="smliser-spinner"></span>
        </div>
    </form>
</div>