<?php

namespace SmartLicenseServer\ClientDashboard\Handlers;

use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Context\Guard;

class OverviewHandler implements DashboardHandlerInterface {

    public static function slug() : string {
        return 'overview';
    }

    public static function guard( Request $request ) : bool|Response {
        $principal = Guard::get_principal();

        if ( ! $principal ) {
            $response = new Response( 401 );
            $response->add_error( 'unauthorized', 'Authentication required.' );
            return $response;
        }

        return true;
    }

    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( 'client-dashboard.sections.overview', [
            'principal' => Guard::get_principal(),
        ] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'text/html; charset=utf-8' )
            ->set_body( $html );
    }
}