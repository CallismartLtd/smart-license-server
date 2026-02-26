<?php
/**
 * License edit form
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) ||  exit; 

/** @var array $args */
$args   = self::get_menu_args();

if ( 'add-new' !== $tab ) {
    \array_unshift(
        $args['actions'],
        array(
            'title' => 'View License',
            'label' => 'View license',
            'url'   => \smliser_license_admin_action_page( 'view', $license_id ),
            'icon'  => 'dashicons dashicons-edit'
        )
    );
}
?>

<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $args ); ?>

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
