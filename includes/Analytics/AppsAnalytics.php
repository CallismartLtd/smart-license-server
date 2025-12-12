<?php
/**
 * Apps Analytics class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Analytics
 * @since 0.2.0
 */

namespace SmartLicenseServer\Analytics;

use SmartLicenseServer\HostedApps\AbstractHostedApp;

\defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Apps analytics class is used to track analytical data for the given app instance.
 */
class AppsAnalytics {
    /**
     * The app download count metadata key
     * 
     * @var string
     */
    const DOWNLOAD_COUNT_META_KEY = 'download_count';

    /**
     * The app download timestamp metadata key.
     * 
     * @var string
     */
    const DOWNLOAD_TIMESTAMP_META_KEY = 'download_timestamps';

    /**
     * App acess key is used to track client access to app.
     * 
     * @var string
     */
    const CLIENT_ACCESS_META_KEY = 'client_access_count';

    /**
     * Increment download count for the given app
     * 
     * @param AbstractHostedApp $app
     */
    public static function log_download( AbstractHostedApp $app ) {
        $num   = (int) $app->get_meta( self::DOWNLOAD_COUNT_META_KEY,  0 );
        $num++;
        $app->update_meta( self::DOWNLOAD_COUNT_META_KEY, $num );
    }

}