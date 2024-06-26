<?php
/**
 * File name options.php
 * 
 * @author Callistus
 * @package Smliser\templates
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;
?>
<h1>Dashboard</h1>
<?php if ( get_transient( 'smliser_form_validation_message' ) ) :?>
    <?php echo wp_kses_post( smliser_form_message( get_transient( 'smliser_form_validation_message' ) ) ) ;?>
<?php endif;?>
<form action="" class="smliser-form">
    <div class="smliser-form-container">
        <input type="hidden" name="action" value="smliser_options" />

        <div class="smliser-form-row">
            <label for="smliser-plugin-name" class="smliser-form-label">Option Name:</label>
            <span class="smliser-form-description" title="Add the plugin name, name must match with the name on plugin file header">?</span>
            <input type="text" name="" id="smliser-plugin-name" class="smliser-form-input" required>
        </div>
    </div>
</form>