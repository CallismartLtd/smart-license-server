<?php
/**
 * Admin request dispatcher class file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Admin\Page;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * The admin request dispatcher class.
 * 
 * Controls and handles all request to the admin routes.
 */
final class Dispatcher {
    /**
     * Renders the admin dashboard.
     * 
     * The route callback to handle the admin dashboard page.
     */
    public static function render_admin_dashboard( Request $request ) : Response {
        $registry       = smliserAdminTemplate();
        $locator        = smliser_template_locator();

        $renderer       = new Shell( $registry, $locator, \smliser_request() );
        $rest_base      = restAPIUrl( 'client-dashboard' );
        
        return $renderer->asResponse ( $rest_base->url() );
    }
}