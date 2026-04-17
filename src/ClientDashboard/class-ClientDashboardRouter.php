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

use SmartLicenseServer\ClientDashboard\Handlers\AuthController;
use SmartLicenseServer\ClientDashboard\Handlers\ClientSettingsController;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ABSPATH' ) || exit;

class ClientDashboardRouter {

    /**
     * @param ClientDashboardRegistry $registry
     */
    private function __construct() {}

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
    public static function dispatch( Request $request ) : Response {
        $slug    = (string) $request->get( 'dashboard_slug', '' );
        
        $handler = smliserFrontendTemplate()->get_handler( $slug );

        if ( null === $handler ) {
            return static::not_found( $slug );
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
     * @return bool|RequestException
     */
    public static function guard( Request $request ) : bool|RequestException {
        $slug    = (string) $request->get( 'dashboard_slug', '' );
        $handler = smliserFrontendTemplate()->get_handler( $slug );

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
    protected static function not_found( string $slug ) : Response {
        $response = new Response( 404 );
        $response->add_error(
            'dashboard_not_found',
            sprintf( 'No dashboard section found for slug "%s".', $slug )
        );

        return $response;
    }

    /*
    |------------------------
    | POST REQUEST HANDLERS 
    |------------------------
    */

    /**
     * Handle post request
     * 
     * @param Request $request
     * @return Response
     */
    public static function post_dispatch( Request $request ) : Response {
        $post_action    = $request->get( 'post_action' );
        $status_code    = 401;

        if ( in_array( $post_action, \authTemplateRegistry()->slugs(), true ) ) {
            $action = str_replace( '-', '_', $post_action );
            $method = "handle_{$action}";

            if ( \method_exists( AuthController::class, $method ) ) {
                return AuthController::$method( $request );
            }

            $data           = [ 'success' => false, 'message' => 'Unable to handle authentication request.'];
            $status_code    = 500;

        } else {
            $data           = static::handle_post_action( $request );
            $status_code    = ( $data['success'] ?? false ) ? 200 : $status_code;
        }

        return ( new Response( $status_code ) )
            ->set_body( $data )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
    }

    /**
     * Guard against unauthorized dashboard post requests
     * 
     * @param Request $request
     * @return RequestException|bool
     */
    public static function post_guard( Request $request ) : RequestException|bool {
        if ( ! $request->isPost() ) {
            return new RequestException( 'endpoint_not_found' );
        }

        if ( ! Guard::has_principal() ) {
            $pattern = implode(
                '|',
                array_map(
                    static fn( string $slug ) : string => preg_quote( $slug, '#' ),
                    \authTemplateRegistry()->slugs()
                )
            );

            $is_auth = static::uri_end_matches( "client-dashboard/({$pattern})", $request->uri() );

            if ( $is_auth ) {
                return true;
            }

            return new RequestException( 'missing_auth' );
        }

        return true;
    }

    /**
     * Handles all possible post action a client can perform in the dashboard
     * 
     * @param Request $request
     * @return array{success: bool, message: string} $result
     */
    private static function handle_post_action( Request $request ) : array {
        $post_action    = $request->get( 'post_action' );
        $registry       = ClientDashboardPostRegistry::instance();

        if ( ! $registry->has( $post_action ) ) {
            return [ 'success' => false, 'message' => 'Unknown post action'];
        }

        $result = $registry->dispatch( $post_action, $request );

        return (array) $result;
    }

    private static function uri_end_matches( string $pattern, string $uri ) : bool {
        $namespace = trim( \smliser_envProvider()->rest_namespace(), '/' );

        // Normalize URI
        $uri = ltrim( $uri, '/' );

        // Find namespace position
        $pos = strpos( $uri, $namespace );

        if ( $pos === false ) {
            return false;
        }

        // Slice from namespace onward
        $uri = substr( $uri, $pos );

        $namespace = preg_quote( $namespace, '#' );

        return (bool) preg_match( "#^{$namespace}/{$pattern}(/|$)#", $uri );
    }
}