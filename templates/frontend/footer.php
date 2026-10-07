<?php
/**
 * Client Dashboard Footer Partial
 *
 * Closes the tags opened by frontend.header:
 *   - </div> <!-- .smlcd-layout -->
 *   - </body>
 *   - </html>
 *
 * Prints dynamic JavaScript bundles based on auth state.
 *
 * Expected variables (extracted by TemplateLocator):
 *
 * @var \SmartLicenseServer\Assets\AssetsManager $assets_manager
 */

use SmartLicenseServer\Assets\AssetsManager;

defined( 'SMLISER_ROOT' ) || exit; ?>

        </div><!-- /.smlcd-layout -->
        <?php $assets_manager->print_category_scripts( AssetsManager::CATEGORY_CLIENT_DASHBOARD, true ); ?>
    </body>
</html>