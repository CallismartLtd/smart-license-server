<?php
/**
 * The download request class file
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\Filesystem\DownloadsApi;
use SmartLicenseServer\Core\Request;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Holds the incoming request for downloading a resource from this server.
 */
class FileRequest extends Request {

    /**
     * Constructor.
     *
     * @param array $args Optional initial property values.
     */
    public function __construct( array $args = [] ) {
        parent::__construct( $args );
    }

}
