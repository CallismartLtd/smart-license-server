<?php
/**
 * Product creation page template.
 * This file handles the plugin upload to the repository and crucial data related to the plugin
 * is stored in the database as product.
 */

defined( 'ABSPATH' ) || exit;
?>

<h1>Upload New Plugin <span class="dashicons dashicons-insert"></span></h1>
<div class="smliser-product-form-container">
    <form id="smliserProductForm" class="smliser-product-form" method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
        <div class="smliser-form-group">
            <label for="product-name">Product Name</label>
            <input type="text" id="product-name" name="product_name" class="smliser-input-field" required>
        </div>

        <div class="smliser-form-group">
            <label for="product-description">Product Description</label>
            <textarea id="product-description" name="product_description" class="smliser-textarea-field" rows="4" required></textarea>
        </div>

        <div class="smliser-form-group">
            <label for="plugin-upload">Upload Plugin</label>
            <input type="file" id="plugin-upload" name="plugin_upload" class="smliser-plugin-upload-field" accept="dir" required>
        </div>

        <div class="smliser-form-group">
            <label for="product-price">Product Price</label>
            <input type="number" step="0.01" id="product-price" name="product_price" class="smliser-input-field" required>
        </div>

        <div class="smliser-form-group">
            <label for="product-fee">Product Fee</label>
            <input type="number" step="0.01" id="product-fee" name="product_fee" class="smliser-input-field">
        </div>

        <div class="smliser-form-group">
            <label for="license-key">License Key</label>
            <?php smliser_license_key_dropdown( false, false, true );?>
        </div>

        <div class="smliser-form-group">
            <label for="plugin-basename">Plugin Basename</label>
            <input type="text" id="plugin-basename" name="plugin_basename" class="smliser-input-field" required>
        </div>

        <div class="smliser-form-group">
            <input type="submit" class="smliser-submit-button" value="Publish Product">
        </div>
    </form>
</div>