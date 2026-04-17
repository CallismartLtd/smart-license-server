<?php
/**
 * Dashboard Handler Interface
 *
 * Contract all client dashboard content handlers must implement.
 * Each handler is responsible for a single dashboard section,
 * identified by a unique slug that maps to a registered menu item.
 *
 * @package SmartLicenseServer\ClientDashboard
 */

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;

defined( 'SMLISER_ABSPATH' ) || exit;

interface DashboardHandlerInterface {

    /**
     * Handle a dashboard content request.
     *
     * Called by the router when the REST endpoint path matches
     * this handler's registered menu slug.
     *
     * @param Request $request
     * @return Response
     */
    public static function handle( Request $request ) : Response;

    /**
     * Permission check for this handler.
     *
     * Called before handle(). Return true to allow, or a Response
     * with an error status to reject.
     *
     * @param Request $request
     * @return bool|RequestException
     */
    public static function guard( Request $request ) : bool|RequestException;

    /**
     * Return the menu slug this handler owns.
     *
     * Must match the slug registered in ClientDashboardRegistry.
     *
     * @return string
     */
    public static function slug() : string;
}