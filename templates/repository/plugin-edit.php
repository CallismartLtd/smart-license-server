<?php
/**
 * Template name : Plugin update template.
 */

 defined( 'ABSPATH' ) || exit;
$max_upload_size_bytes = wp_max_upload_size();
$max_upload_size_mb = $max_upload_size_bytes / 1024 / 1024;
?>

<h1>Edit Plugin <span class="dashicons dashicons-plugins-checked"></span></h1>
<p>If you upload a new version of your plugin, please remember to update the relevant inputs with the latest information about your plugin.</p>
<div class="smliser-form-container">
    <?php if ( $message = get_transient( 'smliser_form_validation_message' ) ) :?>
        <?php echo wp_kses_post( smliser_form_message( $message ) ) ;?>
    <?php endif;?>
    <form id="smliser-plugin-upload-form" class="smliser-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ) ; ?>">

    <input type="hidden" name="action" value="smliser_plugin_upload" />
    <input type="hidden" name="id" value="<?php esc_attr_e( $id )?>" />
        <?php wp_nonce_field( 'smliser_plugin_form_nonce', 'smliser_plugin_form_nonce' ); ?>

        <!--Plugin Name -->
        <div class="smliser-form-row">
            <label for="smliser-plugin-name" class="smliser-form-label">Plugin Name:</label>
            <span class="smliser-form-description" title="Add the plugin name, name must match with the name on plugin file header">?</span>
            <input type="text" name="smliser_plugin_name" id="smliser-plugin-name" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_name() )?>" required>
        </div>

        <!--Plugin File Upload-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-file" class="smliser-form-label">Plugin File (.zip):</label>
            <span class="smliser-form-description" title="If you upload a new zip file, the old file will be deleted and replaced with the uploaded version. The older version will not be recoverable, so be sure to use version control to track your plugin update history or skip upload to use current file. Max Upload Size: <?php echo $max_upload_size_mb . 'MB'; ?>">?</span>
            <div class="smliser-form-file-row">
                <input type="file" name="smliser_plugin_file" id="smliser-plugin-file" class="smliser-form-inpu" accept=".zip">
                <p class="smliser-file-size"></p>
            </div>
        </div>

        <!-- Version-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-version" class="smliser-form-label">Version:</label>
            <span class="smliser-form-description" title="Add the latest version of the plugin">?</span>
            <input type="text" name="smliser_plugin_version" id="smliser-plugin-version" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_version() ) ?>">
        </div>

        <!--Plugin Author-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-author" class="smliser-form-label">Author:</label>
            <span class="smliser-form-description" title="Add plugin author">?</span>
            <input type="text" name="smliser_plugin_author" id="smliser-plugin-author" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_author() ) ?>">
        </div>

        <!--Author Profile URL-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-author-profile" class="smliser-form-label">Author Profile:</label>
            <span class="smliser-form-description" title="Author URL">?</span>
            <input type="url" name="smliser_plugin_author_profile" id="smliser-plugin-author-profile" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_author_profile() ) ?>">
        </div>

        <!--Required WordPress Version-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-requires" class="smliser-form-label">Requires WordPress Version:</label>
            <span class="smliser-form-description" title="Minimum required WordPress version for the plugin">?</span>
            <input type="text" name="smliser_plugin_requires" id="smliser-plugin-requires" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_required() )?>">
        </div>

        <!--WordPress Version Tested UpTo-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-tested" class="smliser-form-label">Tested up to WordPress Version:</label>
            <span class="smliser-form-description" title="The WordPres version tested with your plugin">?</span>
            <input type="text" name="smliser_plugin_tested" id="smliser-plugin-tested" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_tested() ) ?>">
        </div>

        <!--Required PHP Version-->
        <div class="smliser-form-row">
            <label for="smliser-plugin-requires-php" class="smliser-form-label">Requires PHP Version:</label>
            <span class="smliser-form-description" title="Minimum required PHP version">?</span>
            <input type="text" name="smliser_plugin_requires_php" id="smliser-plugin-requires-php" class="smliser-form-input" value="<?php esc_attr_e( $plugin->get_required() )?>">
        </div>

        <input type="submit" class="button action smliser-bulk-action-button" name="smliser_plugin_upload_update" value="Update Plugin"/>
    </form>

</div>
