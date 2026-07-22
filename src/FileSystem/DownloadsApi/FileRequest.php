<?php
/**
 * The download request class file
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;
use SmartLicenseServer\Core\Request;

/**
 * Holds the incoming request for downloading a resource from this server.
 */
class FileRequest extends Request {

    /**
     * Constructor.
     *
     * @param array $params The request params, defaults to $_REQUEST array.
     * @param array $headers The request headers, defaults to all headers.
     * @param string $method The HTTP method, defaults to $_SERVER['REQUEST_METHOD'].
     * @param string $uri The request URI, defaults to $_SERVER['REQUEST_URI'].
     */
    public function __construct( 
        array $params   = [],
        array $headers  = [],
        string $method  = '',
        string $uri     = ''
        
    ) {
        parent::__construct( $params, $headers, $method, $uri );
    }

}
