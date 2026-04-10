<?php
/**
 * Client Dashboard Footer Partial
 *
 * Closes the tags opened by frontend.header:
 *   - </div> <!-- .smlcd-layout -->
 *   - </body>
 *   - </html>
 *
 * No variables required.
 */

use SmartLicenseServer\Assets\AssetsManager;

defined( 'SMLISER_ABSPATH' ) || exit;

?>
<?php AssetsManager::print_scripts( 'smliser-client-dashboard' ); ?>
</div><!-- /.smlcd-layout -->
</body>
</html>