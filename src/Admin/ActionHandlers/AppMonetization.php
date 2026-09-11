<?php
/**
 * App Monetization handling class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Contracts\AdminRequests\LicenseHandlerInterface;
use SmartLicenseServer\Contracts\AdminRequests\MonetizationHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Monetization\Controller;

/**
 * Handles button click actions, form submissions and other request processing for
 * app monetization.
 */
class AppMonetization implements MonetizationHandlerInterface, LicenseHandlerInterface {
    public function __construct(
        protected Controller $controller
    ) {}

    public function handle_toggle_monetization_request( Request $request ): Response {
        return $this->controller->toggle_monetization( $request );
    }

    public function handle_monetization_provider_product_request( Request $request ): Response {
        return $this->controller->get_provider_product( $request );
    }

    public function handle_monetization_tier_deletion_request( Request $request ): Response {
        return $this->controller->delete_monetization_tier( $request );
    }

    public function handle_monetization_tier_form_request( Request $request ): Response {
        return $this->controller->save_monetization( $request );
    }

    public function handle_save_provider_options_request( Request $request ): Response {
        return $this->controller->save_provider_options( $request );
    }

    public function handle_save_license_request( Request $request ) : Response {
        $app_prop = (string) $request->get( 'app_prop' );

        if ( str_contains( $app_prop, ':' ) ) {
            [ $app_type, $app_slug ] = explode( ':', $app_prop, 2 );
            $request->set( 'app_type', $app_type );
            $request->set( 'app_slug', $app_slug );
        }

        return $this->controller->save_license( $request );
    }

    public function handle_license_delete_request( Request $request ) : Response {
        return $this->controller->delete_license( $request );
    }

    public function handle_licensed_domain_removal_request( Request $request ) : Response {
        return $this->controller->uninstall_domain_from_license( $request );
    }

    function handle_download_token_generation_request( Request $request ) : Response {
        return $this->controller->generate_app_download_token( $request );
    }
}