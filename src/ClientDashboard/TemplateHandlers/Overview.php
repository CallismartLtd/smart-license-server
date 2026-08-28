<?php

namespace SmartLicenseServer\ClientDashboard\TemplateHandlers;

use SmartLicenseServer\ClientDashboard\DashboardHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Context\Guard;

class Overview implements DashboardHandlerInterface {

    public function __construct( protected Guard $guard ) {}

    public static function slug() : string {
        return 'overview';
    }

    public function guard( Request $request ) : bool|RequestException {
        $principal = $this->guard->get_principal();

        if ( ! $principal ) {
            return new RequestException( 'unauthorized', 'Authentication required.' );
        }

        return true;
    }

    public function handle( Request $request ) : Response {
        $html = smliser_render_template_to_string( 'frontend.sections.index', [
            'principal' => $this->guard->get_principal(),
        ] );

        return ( new Response( 200 ) )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' )
            ->set_body( [ 'html' => $html ] );
    }
}