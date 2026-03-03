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
flush_rewrite_rules();

$menu_args  = static::get_menu_args();

unset( $menu_args['breadcrumbs'][0] );
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>

    <?php if ( $message = smliser_get_query_param( 'message' ) ):?>
        <div class="notice notice-success is-dismissible"><p><?php echo esc_html( $message ) ?></p></div>
    <?php endif;?>

    <form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post" >
        <?php wp_nonce_field( 'smliser_options_form', 'smliser_options_form' );?>
        <input type="hidden" name="action" value="smliser_options">

        <div class="smliser-options-form">

        </div>
        <input type="submit" name="smliser_page_setup" class="button action smliser-bulk-action-button" value="Save">

    </form>
</div>
