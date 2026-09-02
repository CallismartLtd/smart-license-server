<?php
/**
 * Accounts management handler file.
 * 
 * @author Callistus Nwachukwu
 */
declare( strict_types=1 );
namespace SmartLicenseServer\Admin\ActionHandlers;

use SmartLicenseServer\Contracts\AdminRequests\AccessControlHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Security\Owner;
use SmartLicenseServer\Security\RequestController;

class AccountManagements implements AccessControlHandlerInterface {
    public function __construct(
        protected RequestController $request_controller
    ) {}
    
    public function handle_access_control_save_request( Request $request ) : Response {
        $entity = $request->get( 'entity' );

        if ( 'organization_member' === $entity ) {
            $response   = $this->request_controller->save_organization_member( $request );
        } else {
            $response   = $this->request_controller->save_entity( $request );
        }

        return $response;
    }

    public function handle_access_control_delete_request( Request $request ) : Response {
        return $this->request_controller->delete_entity( $request );
    }

    public function handle_admin_security_entity_search_request( Request $request ) : Response {
        $entity = $request->get( 'entity_type', 'user' );

        if ( 'owner_subjects' === $entity ) {
            $response   = $this->request_controller->search_users_orgs( $request );
        } else {
            $response   = $this->request_controller->search_resource_owners( $request );
        }

        return $response;
    }
}