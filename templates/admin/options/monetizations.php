<?php
/**
 * Hosted application monetization provider settings page.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicensseServer\templates
 * @see \SmartLicenseServer\Admin\OptionsPage::provider_settings()
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ABSPATH' ) || exit; 

$args   = OptionsPage::get_menu_args( $request );

$current_label  = end( $args['breadcrumbs'] )['label'];
$args['breadcrumbs'][1]  = array(
    'label' => $current_label,
    'url'   => smliser_get_current_url()->remove_query_param( 'provider' ),
    'icon'  => 'ti ti-cash-register'

);

$args['breadcrumbs'][2]['label']   = $name;
$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'provider' );

?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $args ); ?>

    <?php if ( ! $provider ) : ?>
        <?php printf(
            smliser_not_found_container( 'The email provider "%s" does not exists. <a href="%s">Go Back</a>' ),
            $provider_key,
            $current_url
        ); ?>
    <?php else: ?>

        <?php if ( $saved = $request->get( 'message' ) ):?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved ); ?></p></div>
        <?php endif;?>

        <form action="" class="smliser-options-form">
            <span> <a href="<?php echo esc_url( $current_url->get_href() ) ?>" class="smliser-btn"> <i class="ti ti-arrow-back"></i></a></span>
            <div class="smliser-spinner"></div>
            <input type="hidden" name="action" value="smliser_save_monetization_provider_options">
            <input type="hidden" name="provider_id" value="<?php echo esc_html( $id ); ?>">
            <div class="smliser-options-form_body">
                <?php foreach( $settings as $option ) : ?>
                    <?php smliser_render_input_field( $option ); ?>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="button">Save</button>
        </form>
    <?php endif; ?>
</div>
