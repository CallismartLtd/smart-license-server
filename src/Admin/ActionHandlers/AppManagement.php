<?php
/**
 * Admin app management handler file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );
namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Contracts\AdminRequests\AppManagementHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\HostedApps\HostingController;

/**
 * Handles admin requests when managing hosted applications.
 */
class AppManagement implements AppManagementHandlerInterface {
    public function __construct(
        protected HostingController $hosting_controller
    ) {}

    public function handle_save_app_request( Request $request ) : Response {
        return $this->hosting_controller->save_app( $request );
    }

    public function handle_app_asset_upload_request( Request $request ) : Response {
        return $this->hosting_controller->app_asset_upload( $request );
    }

    public function handle_app_asset_delete_request( Request $request ) : Response {
        return $this->hosting_controller->app_asset_delete( $request );
    }

    public function handle_app_artifact_upload_request( Request $request ) : Response {
        return $this->hosting_controller->app_artifact_upload( $request );
    }

    public function handle_app_artifact_delete_request( Request $request ) : Response {
        return $this->hosting_controller->app_artifact_delete( $request );
    }

    public function handle_app_status_action_request( Request $request ) : Response {
        return $this->hosting_controller->change_app_status( $request );
    }
}