<?php
/**
 * Admin Dashboard Footer.
 *
 * Closes the tags opened in header.php. Include this last.
 *
 * @var \SmartLicenseServer\Assets\AssetsManager $assets_manager
 */

use SmartLicenseServer\Assets\AssetsManager;

?>
    </div>
    <?php $assets_manager->print_category_scripts( AssetsManager::CATEGORY_ADMIN_DASHBOARD, true ); ?>
</body>
</html>