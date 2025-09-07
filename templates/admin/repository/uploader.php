<?php
/**
 * Application upload page
 */

defined( 'ABSPATH' ) || exit;

$max_upload_size_bytes = wp_max_upload_size();
$max_upload_size_mb = $max_upload_size_bytes / 1024 / 1024;
?>

<div class="application-uploader-page">
    <h1><?php echo esc_html( $title ); ?> Upload</h1>
    
    <form action="" class="app-uploader-form" id="newAppUploaderForm">
        <input type="hidden" name="action" value="smliser_save_<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_type" value="<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_id" value="<?php printf( '%s', esc_html( smliser_get_query_param( 'item_id' ) ) ) ?>">
        <div class="app-uploader-top-section">
            <div class="app-uploader-left">
                <h3><?php echo esc_html( $type_title ) ?> Details</h3>
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

        <div class="smliser-spinner"></div>

        <div class="app-uploader-below-section">
            <?php if ( ! empty( $other_fields ) ) : ?>
                <div class="app-uploader-below-section_extras">
                    <?php foreach( $other_fields as $extra ) : ?>
                        <?php smliser_render_input_field( $extra ); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ( ! empty( $assets ) ) : ?>
                <div class="app-uploader-below-section_assets">
                    <h2><?php printf( '%s Assets', esc_html( $type_title ) ) ?></h2>
                    <?php foreach( $assets as $key => $asset ) : ?>
                        <div class="app-uploader-asset-container">
                            <h3><?php echo esc_html( ucfirst( $key ) ) ?></h3>
                            <div class="app-uploader-asset-container_images">
                                <?php foreach( $asset as $index => $file_name ) : ?>
                                    <?php if ( ! empty( $file_name ) ) : 
                                        $image_title = is_int( $index ) ? 'asset-'. $index : $index;    
                                    ?>
                                        <div class="app-uploader-image-preview">
                                            <img src="<?php echo esc_url( smliser_get_app_asset_url( $type, $app->get_slug(), $file_name ) ); ?>" alt="<?php echo esc_attr( $image_title ) ?>" title="<?php echo esc_attr( $image_title ) ?>">
                                            <div class="app-uploader-image-preview_edit">
                                                <span class="dashicons dashicons-edit"></span>
                                                <span class="dashicons dashicons-dismiss"></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach;
                                $config = wp_json_encode([
                                    'asset_prefix'  => $key,
                                    'app_slug'      => $app->get_slug(),
                                    'app_type'      => $app->get_type()
                                ])
                                ?>    
                                <div class="smliser-uploader-add-image" data-action="openModal" data-config="<?php echo esc_attr( $config ) ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                </div>                            
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>        
        <button type="submit" class="button"><?php printf( 'Save %s', esc_html( $type_title ) ); ?></button>
    </form>
</div>
<?php if( 'edit' === smliser_get_query_param( 'tab' ) ) : ?>
    <div class="smliser-admin-modal app-asset-uploader hidden">
        <div class="smliser-admin-modal_content">
            <span class="dashicons dashicons-dismiss remove-modal" title="remove" data-action="closeModal"></span>
            <h2 id="modal-header">Asset Uploader</h2>
            <em id="modal-description">You can choose to upload from your device, Wordpress gallery or a URL</em>

            <div class="app-asset-uploader-body">
                <div class="app-asset-uploader-body_uploaded-asset">
                    <div class="app-asset-uploader-placeholder">
                        <span class="dashicons dashicons-format-image"></span>
                    </div>

                    <div class="app-asset-uploader-uploaded-image">
                        <span class="dashicons dashicons-dismiss clear-uploaded" title="Clear selected image" data-action="resetModal"></span>
                        <img src="" alt="uploaded image" id="currentImage">
                    </div>
                    <button type="button" class="button smliser-nav-btn" id="upload-image" data-action="uploadToRepository"><span class="dashicons dashicons-cloud-upload"></span> Upload to repository</button>
                </div>

                <div class="smliser-spinner modal"></div>
                <input type="url" id="app-uploader-asset-url-input" placeholder="Enter image url">
                <input type="file" id="app-uploader-asset-file-input" accept="image/png, image/jpeg, .png, .jpg, .jpeg, .gif" class="hidden">
                
                <div class="app-asset-uploader-buttons-container">
                    <button type="button" class="button smliser-nav-btn" id="upload-from-device" data-action="uploadFromDevice"><span class="dashicons dashicons-open-folder"></span> Choose from device</button>
                    <button type="button" class="button smliser-nav-btn" id="upload-from-wp" data-action="uploadFromWpGallery"><span class="dashicons dashicons-format-gallery"></span> Choose from Gallery</button>
                    <button type="button" class="button smliser-nav-btn" id="upload-from-url" data-action="uploadFromUrl"><span class="dashicons dashicons-admin-links"></span> Choose from URL</button>
                </div>

            </div>
        </div>
    </div>
<?php endif; ?>
<?php
    wp_enqueue_media();
    wp_enqueue_script( 'smliser-apps-uploader' ); 
?>
