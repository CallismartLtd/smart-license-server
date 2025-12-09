<?php
/**
 * Admin bulk message delete confirmation template file
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\templates
 */

defined( 'SMLISER_ABSPATH' ) || exit; ?>

<?php if ( ! $message ) : ?>
    <?php echo wp_kses_post( smliser_not_found_container( 'The specified bulk message could not be found.' ) ); ?>
<?php elseif( $message->delete() ) : ?>

    <?php wp_admin_notice( 'Bulk message deleted successfully.', [ 'type' => 'success', 'dismissible' => false ] ); ?>
    <script>
        setTimeout(() => {
            window.location.href = "<?php echo esc_url( admin_url( 'admin.php?page=smliser-bulk-message&success=1' ) ); ?>";
        }, 5000);
    </script>
<?php else : ?>
    <?php wp_admin_notice( 'An error occurred while deleting the bulk message. Please try again. <a href="' . admin_url( 'admin.php?page=smliser-bulk-message' ) .'">Go back</a>', [ 'type' => 'error', 'dismissible' => false ] ); ?>
    

<?php endif; ?>