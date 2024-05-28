<?php
/**
 * Product creation page template.
 * This file handles the plugin upload to the repository and crucial data related to the plugin
 * is stored in the database as product.
 */

defined( 'ABSPATH' ) || exit;
?>
<h1>Add License Product <span class="dashicons dashicons-insert"></span></h1>
<div class="smliser-product-form-container">
    <form id="smliserProductForm" class="smliser-product-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) )?>">
        <div class="smliser-in-form-contaner">
            
        </div>

    </form>
</div>