<?php
/**
 * Application upload page
 */

defined( 'ABSPATH' ) || exit;?>

<div class="application-uploader-page">
    <h1><?php echo esc_html( $title ); ?> Upload</h1>
    
    <form action="" class="app-uploader-form" id="newAppUploaderForm">
        <input type="hidden" name="action" value="smliser_save_<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_type" value="<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_id" value="<?php printf( '%s', esc_html( smliser_get_query_param( 'item_id' ) ) ) ?>">
        <div class="app-uploader-top-section">
            <div class="app-uploader-left">
                <h3><?php echo esc_html( $title ) ?> Details</h3>
                <?php foreach( $essential_fields as $field ) : ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>
            </div>
            <div class="app-uploader-right">
                <h3>File Upload</h3>
                <em>Max Upload Size: <?php echo esc_html( $max_upload_size_mb ) . 'MB'; ?></em>
                <div class="smliser-form-file-row">
                    <input type="file" name="app_file" id="smliser-file-input"  style="display: none;">
                    <div class="smliser-file-info" wp-max-upload-size= "<?php echo absint( $max_upload_size_mb ) ?>">
                        <span>No file selected.</span>
                    </div>
                    <button type="button" class="smliser-upload-btn button"><span class="dashicons dashicons-media-archive"></span> Upload File</button>
                    <button type="button" class="smliser-file-remove button smliser-hide"><span class="dashicons dashicons-remove" title="remove file"></span> Clear</button>
                </div>
            </div>
        </div>

        <?php if ( ! empty( $other_fields ) ) : ?>
            <div class="app-uploader-below-section">
                <?php foreach( $other_fields as $extra ) : ?>
                    <?php smliser_render_input_field( $extra ); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <button type="submit" class="button"><?php printf( 'Save %s', esc_html( $title ) ); ?></button>
    </form>
</div>
