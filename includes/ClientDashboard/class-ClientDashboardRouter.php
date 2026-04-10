<?php
/**
 * Client Dashboard Router
 *
 * Matches an incoming request path against registered dashboard slugs,
 * runs the guard, and dispatches to the matched handler.
 *
 * Fits into the existing REST API pattern: this class is the callable
 * that V1::get_routes() points to for the dashboard endpoint.
 *
 * URL pattern: /{namespace}/dashboard/{slug}
 *
 * @package SmartLicenseServer\ClientDashboard
 */

namespace SmartLicenseServer\ClientDashboard;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;

defined( 'SMLISER_ABSPATH' ) || exit;

class ClientDashboardRouter {

    /**
     * @param ClientDashboardRegistry $registry
     */
    public function __construct(
        protected ClientDashboardRegistry $registry
    ) {}

    /*
    |-----------
    | DISPATCH
    |-----------
    */

    /**
     * Main dispatch entry point — registered as the REST route handler.
     *
     * Extracts the slug from the request, resolves the handler,
     * runs the guard, and calls handle().
     *
     * @param Request $request
     * @return Response
     */
    public function dispatch( Request $request ) : Response {
        $slug    = (string) $request->get( 'dashboard_slug', '' );
        $handler = $this->registry->get_handler( $slug );

        if ( null === $handler ) {
            return $this->not_found( $slug );
        }

        return $handler->handle( $request );
    }

    /**
     * Guard entry point — registered as the REST route guard.
     *
     * Runs the matched handler's own guard. Falls through to true
     * if no slug is matched (dispatch will return 404 anyway).
     *
     * @param Request $request
     * @return bool|Response
     */
    public function guard( Request $request ) : bool|Response {
        $slug    = (string) $request->get( 'dashboard_slug', '' );
        $handler = $this->registry->get_handler( $slug );

        if ( null === $handler ) {
            return true;
        }

        return $handler->guard( $request );
    }

    /*
    |-----------
    | RESPONSES
    |-----------
    */

    /**
     * Return a 404 response for an unmatched slug.
     *
     * @param string $slug
     * @return Response
     */
    protected function not_found( string $slug ) : Response {
        $response = new Response( 404 );
        $response->add_error(
            'dashboard_not_found',
            sprintf( 'No dashboard section found for slug "%s".', $slug )
        );

        return $response;
    }
}