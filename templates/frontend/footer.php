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
 * @var array $scripts  Scripts array for AssetsManager::print_scripts()
 */

use SmartLicenseServer\Assets\AssetsManager;

defined( 'SMLISER_ABSPATH' ) || exit;

$scripts = $scripts ?? [ 'smliser-client-dashboard' ];

?>
<?php AssetsManager::print_scripts( ...$scripts ); ?>
</div><!-- /.smlcd-layout -->
</body>
</html>