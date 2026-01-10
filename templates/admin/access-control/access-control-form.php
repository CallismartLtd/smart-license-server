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
    <div class="smliser-access-control-form">
        <h2><?php echo esc_html( $title ); ?></h2> <br>

        <div class="smliser-two-rows">
            <div class="smliser-two-rows_left">
                <?php foreach( $form_fields as $field ) : ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>
            </div>
            <div class="smliser-two-rows_right"></div>

        </div>
        
    </div>


</div>