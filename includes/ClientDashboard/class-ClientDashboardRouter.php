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
        $handler = \smliser_envProvider()->clientDashboardRegistry()->get_handler( $slug );

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
     * @return bool|Response
     */
    public static function guard( Request $request ) : bool|Response {
        $slug    = (string) $request->get( 'dashboard_slug', '' );
        $handler = \smliser_envProvider()->clientDashboardRegistry()->get_handler( $slug );

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
        $data   = static::handle_post_action( $request );

        return ( new Response( 200 ) )
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
            return new RequestException( 'missing_auth' );
        }

        return true;
    }

    /**
     * Handles all possible post action a client can perform in the dashboard
     * 
     * @param Request $request
     * @return array
     */
    private static function handle_post_action( Request $request ) : array {
        $post_action    = $request->get( 'post_action' );

        switch( $post_action ) {
            case 'user-preference':
                $key    = $request->get( 'key', null );
                if ( ! $key ) {
                    $result = [ 'success' => false, 'message' => 'User preference key must be set.'];
                    break;
                }

                $value  = $request->get( 'value', '' );
                $result = static::set_user_preference( $key, $value );
                break;
            default:
            $result = ['success' => false, 'message' => 'Unknown post action'];
        }

        return $result;
    }


    /**
     * Set the user dashboard preference.
     * 
     * @param $key The prefernce key.
     * @param $value The preference value
     * @return array
     */
    private static function set_user_preference( string $key, string $value ) : array {
        $principal      = Guard::get_principal();
        $user_settings  = UserSettings::for( $principal->get_actor() );

        $saved  = $user_settings->set( $key, $value );

        return [ 'success' => $saved, 'message' => ( $saved ? 'Saved' : 'Something went wrong' )];
    }
}