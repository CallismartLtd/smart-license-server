<?php
/**
 * Admin request dispatcher class file.
 * 
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Admin\Page;

use SmartLicenseServer\Admin\AdminDashboardRegistry;
use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Templates\TemplateLocator;

/**
 * The admin request dispatcher class.
 * 
 * Controls and handles all request to the admin routes.
 */
final class Dispatcher {
    public function __construct(
        protected Guard $guard,
        protected URLManager $urlmanager,
        protected AdminDashboardRegistry $registry,
        protected TemplateLocator $locator,
        protected AssetsManager $assets_manager
    ) {}

    /**
     * Renders the admin dashboard.
     * 
     * The route callback to handle the admin dashboard page.
     */
    public function render_admin_dashboard( Request $request ) : Response {

        $renderer       = new Shell(
            $this->registry,
            $this->locator,
            $request, $this->guard,
            $this->assets_manager,
            $this->urlmanager
        );
        $rest_base      = $this->urlmanager->client_dashboard_url();
        
        return $renderer->asResponse ( $rest_base->url() );
    }
}