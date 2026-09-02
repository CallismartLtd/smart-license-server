<?php
/**
 * Admin actions interface files.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */
namespace SmartLicenseServer\Admin\Contracts;

use SmartLicenseServer\Contracts\AdminRequests\{
    AppManagementHandlerInterface, BulkActionHandlerInterface, AccessControlHandlerInterface
};

/**
 * Contract to handle all admin form submissions, button clicks and other
 * administractive tasks in the admin area.
 */
interface AdminActionsInterface extends AppManagementHandlerInterface, BulkActionHandlerInterface,
    AccessControlHandlerInterface {}