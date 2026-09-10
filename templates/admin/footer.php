<?php
/**
 * Dashboard Footer.
 *
 * Closes the tags opened in header.php. Include this last.
 *
 * @var \SmartLicenseServer\Assets\AssetsManager $assets_manager
 */

use SmartLicenseServer\Assets\AssetsManager;

?>

	<?php $assets_manager->print_category_scripts( AssetsManager::CATEGORY_ADMIN_DASHBOARD, true ); ?>
    </div>
</body>
</html>