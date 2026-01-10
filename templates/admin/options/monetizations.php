<?php
/**
 * Application monetization providers settings page.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicensseServer\templates
 * @see \SmartLicenseServer\Admin\OptionsPage::provider_settings()
 */

defined( 'SMLISER_ABSPATH' ) || exit; ?>

<h2><?php echo esc_html( $name ); ?> Settings</h2>

<?php if ( $saved = smliser_get_query_param( 'message' ) ):?>
    <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $saved ); ?></p></div>
<?php endif;?>

<form action="" class="smliser-options-form">
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
