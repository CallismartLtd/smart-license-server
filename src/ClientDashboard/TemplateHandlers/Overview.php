<?php

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Context\Guard;

class Overview implements DashboardHandlerInterface {

    public static function slug() : string {
        return 'overview';
    }

    public static function guard( Request $request ) : bool|RequestException {
        $principal = Guard::get_principal();

        if ( ! $principal ) {
            return new RequestException( 'unauthorized', 'Authentication required.' );
        }

        return true;
    }

    public static function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( 'frontend.sections.index', [
            'principal' => Guard::get_principal(),
        ] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [ 'html' => $html ] );
    }
}