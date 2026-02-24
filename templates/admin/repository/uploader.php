<?php
/**
 * Application upload page.
 * 
 * @author Callistus Nwachukwu
 * @see \SmartLicenseServer\Admin\RepositoryPage::upload_page()
 * @see \SmartLicenseServer\Admin\RepositoryPage::edit_page()
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$max_upload_size_bytes = wp_max_upload_size();
$max_upload_size_mb = $max_upload_size_bytes / 1024 / 1024;
?>

<div class="application-uploader-page">
    <?php Menu::print_admin_top_menu(
        [
            'breadcrumbs'   => array(
                array(
                    'label' => 'Repository',
                    'url'   => admin_url( 'admin.php?page=repository' ),
                    'icon'  => 'ti ti-home-filled'
                ),

                array(
                    'label' => smliser_pluralize( $type ),
                    'url'   => admin_url( 'admin.php?page=repository&type=' . $type ),
                    'icon'  => 'ti ti-folder-open'
                ),
                array(
                    'label' => $title
                )
            ),
            'actions'   => array(
                $app_action,
                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        ]
    ); ?>
 

    <form action="" class="app-uploader-form" id="appUploaderForm">
        <input type="hidden" name="action" value="smliser_save_<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_type" value="<?php printf( '%s', esc_html( $type ) ) ?>">
        <input type="hidden" name="app_id" value="<?php printf( '%s', esc_html( smliser_get_query_param( 'app_id' ) ) ) ?>">
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
                    <input type="file" name="app_zip_file" id="smliser-file-input"  style="display: none;">
                    <div class="smliser-file-info" wp-max-upload-size= "<?php echo absint( $max_upload_size_mb ) ?>">
                        <span>No file selected.</span>
                    </div>
                    <button type="button" class="smliser-upload-btn button">Drag over or click to upload file</button>
                    <button type="button" class="smliser-file-remove button smliser-hide"><span class="dashicons dashicons-no-alt" title="remove file"></span> Clear</button>
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
                    <?php foreach( $assets as $key => $data ) : ?>
                        <div class="app-uploader-asset-container <?php echo esc_html( $key ) ?>">
                            <h3><?php echo esc_html( $data['title'] ?? '' ) ?></h3>
                            <div class="app-uploader-asset-container_images">
                                <?php foreach( $data['images'] as $url ) : ?>
                                    <?php if ( ! empty( $url ) ) : 
                                        $asset_name = basename( $url );
                                        $json_data = smliser_safe_json_encode([
                                            'asset_type'    => $key,
                                            'app_slug'      => $app->get_slug(),
                                            'app_type'      => $app->get_type(),
                                            'asset_name'    => $asset_name,
                                            'asset_url'     => $url
                                        ]);  
                                    ?>
                                        <div class="app-uploader-image-preview">
                                            <img src="<?php echo esc_url( $url ); ?>" id="<?php echo esc_attr( explode( '.', $asset_name )[0] ); ?>" alt="<?php echo esc_attr( $asset_name ) ?>" loading="lazy" title="<?php echo esc_attr( $asset_name ) ?>">
                                            <div class="app-uploader-image-preview_edit">
                                                <span class="dashicons dashicons-edit edit-image" data-config="<?php echo urlencode( $json_data ) ?>" data-action="openModal" title="Edit"></span>
                                                <span class="dashicons dashicons-trash delete-image" data-config="<?php echo urlencode( $json_data ) ?>" data-action="deleteImage" title="Delete"></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; 
                                $config = smliser_safe_json_encode([
                                    'asset_type'    => $key,
                                    'app_slug'      => $app->get_slug(),
                                    'app_type'      => $app->get_type(),
                                    'limit'         => $data['limit']
                                    
                                ])
                                ?>    
                                <div class="smliser-uploader-add-image <?php echo esc_attr( ( $data['total'] < $data['limit'] ) ? '' : 'smliser-hide' ) ?>" data-action="openModal" data-config="<?php echo urlencode( $config ) ?>">
                                    <span class="dashicons dashicons-plus"></span>
                                </div>             
                            </div>
                        </div>
                        
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>        
        <button type="submit" class="button authoritatively"><?php printf( 'Save %s', esc_html( $type_title ) ); ?></button>
    </form>
</div>
<?php if ( 'edit' === smliser_get_query_param( 'tab' ) ) : ?>
    <div class="smliser-admin-modal app-asset-uploader hidden" role="dialog" aria-modal="true" aria-labelledby="modal-header" aria-describedby="modal-description">
        <div class="smliser-admin-modal_content">

            <span
                class="dashicons dashicons-dismiss remove-modal"
                title="Close"
                data-action="closeModal"
                role="button"
                aria-label="Close modal"
                tabindex="0"
            ></span>

            <h2 id="modal-header">Asset Uploader</h2>
            <em id="modal-description">
                Upload from your device, WordPress gallery, or a URL.
                Multiple files are supported where the asset limit allows.
            </em>

            <div class="app-asset-uploader-body">

                <!-- {{-- Single-image preview (shown when exactly one image is staged) --}} -->
                <div class="app-asset-uploader-body_uploaded-asset">
                    <div class="app-asset-uploader-placeholder">
                        <span class="dashicons dashicons-format-image"></span>
                        <p class="app-asset-uploader-placeholder_hint">No image selected</p>
                    </div>

                    <div class="app-asset-uploader-uploaded-image">
                        <span
                            class="dashicons dashicons-dismiss clear-uploaded"
                            title="Clear selected image"
                            data-action="resetModal"
                            role="button"
                            aria-label="Clear selected image"
                            tabindex="0"
                        ></span>
                        <img src="" alt="Uploaded image preview" id="currentImage">
                    </div>

                    <button
                        type="button"
                        class="button smliser-nav-btn"
                        id="upload-image"
                        data-action="uploadToRepository"
                    >
                        <span class="dashicons dashicons-cloud-upload"></span>
                        Upload to repository
                    </button>
                </div>

                <!-- {{-- Multi-file thumbnail strip (injected by JS when > 1 file is staged) --}} -->
                <div class="smliser-multi-preview"></div>

                <div class="smliser-spinner modal"></div>

                <input
                    type="url"
                    id="app-uploader-asset-url-input"
                    placeholder="Enter image URL"
                    aria-label="Image URL"
                >

                <!-- {{--
                    Multiple attribute is toggled at runtime by AppUploader.openModal()
                    based on the remaining slot count for the current asset type.
                --}} -->
                <input
                    type="file"
                    id="app-uploader-asset-file-input"
                    accept="image/png, image/jpeg, image/gif, .png, .jpg, .jpeg, .gif"
                    class="hidden"
                    aria-label="Select image file(s)"
                >

                <div class="app-asset-uploader-buttons-container">
                    <button type="button" class="button smliser-nav-btn" id="upload-from-device" data-action="uploadFromDevice">
                        <span class="dashicons dashicons-open-folder"></span>
                        Upload from device
                    </button>
                    <button type="button" class="button smliser-nav-btn" id="upload-from-wp" data-action="uploadFromWpGallery">
                        <span class="dashicons dashicons-format-gallery"></span>
                        Upload from Gallery
                    </button>
                    <button type="button" class="button smliser-nav-btn" id="upload-from-url" data-action="uploadFromUrl">
                        <span class="dashicons dashicons-admin-links"></span>
                        Upload from URL
                    </button>
                </div>

            </div><!-- /.app-asset-uploader-body -->
        </div><!-- /.smliser-admin-modal_content -->
    </div><!-- /.smliser-admin-modal -->
<?php endif;