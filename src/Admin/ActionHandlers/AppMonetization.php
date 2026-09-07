<?php
/**
 * App Monetization handling class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Contracts\AdminRequests\MonetizationHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Monetization\Controller;

/**
 * Handles button click actions, form submissions and other request processing for
 * app monetization.
 */
class AppMonetization implements MonetizationHandlerInterface {
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
}