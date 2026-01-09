<?php
/**
 * Admin bulk message page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Security\Organization;
use SmartLicenseServer\Security\Owner;

use function defined, smliser_get_query_param, array_unshift;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin bulk message page class
 */
class AccessControlPage {
    /**
     * Page router.
     */
    public static function router() {
        $tab     = smliser_get_query_param( 'tab' );
        $section = smliser_get_query_param( 'section' );

        $routes = [
            'users' => [
                'default' => [__CLASS__, 'users_page'],
                'add-new' => [__CLASS__, 'add_new_users_page'],
            ],
            'organizations' => [
                'default' => [__CLASS__, 'organizations_page'],
                'add-new' => [__CLASS__, 'add_new_organizations_page'],
            ],
            'owners' => [
                'default' => [__CLASS__, 'owners_page'],
                'add-new' => [__CLASS__, 'add_new_owners_page'],
            ],
            'rest-api' => [
                'default' => [__CLASS__, 'rest_api_page'],
                'add-new' => [__CLASS__, 'add_new_rest_api_page'],
            ],
        ];

        if ( isset( $routes[ $tab ] ) ) {
            $handler = $routes[ $tab ][ $section ] ?? $routes[ $tab ]['default'];
            call_user_func( $handler );
            return;
        }

        self::dashboard();
    }


    /**
     * Access control page dashbard.
     */
    public static function dashboard() {
          
        include_once SMLISER_PATH . 'templates/admin/access-control/dashboard.php';
    
    }

    /**
     * Users page.
     */
    private static function users_page() {
        include_once SMLISER_PATH . 'templates/admin/access-control/users.php';
    }

    /**
     * Organizations page.
     */
    private static function organizations_page() {

   
        include_once SMLISER_PATH . 'templates/admin/access-control/organizations.php';
    }

    /**
     * Resource owners.
     */
    private static function owners_page() {
        $page   = (int) smliser_get_query_param( 'paged', 1 );
        $limit  = (int) smliser_get_query_param( 'limit', 25 );
        $owners = Owner::get_all( $page, $limit );

        include_once SMLISER_PATH . 'templates/admin/access-control/owners.php';
       
    }

    /**
     * REST API setting page.
     */
    private static function rest_api_page() {

        include_once SMLISER_PATH . 'templates/admin/access-control/rest-api.php';
       
    }

    /**
     * Print admin header
     */
    protected static function print_header() {
        $tab        = smliser_get_query_param( 'tab' );
        $title      = match( $tab ) {
            'users'         => 'Users',
            'organizations' => 'Organization',
            'owners'        => 'Resource Owners',
            'rest-api'      => 'REST API Credentials',
            default         => 'Security & Access Control'

        };

        $args   = array(
            'breadcrumbs' => array(
                array(
                    'label' => $title,
                ),
            ),
            'actions'   => array(
                array(
                    'title' => 'Resource Owners',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=owners'),
                    'icon'  => 'ti ti-source-code'
                ),

                array(
                    'title' => 'Users',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=users'),
                    'icon'  => 'ti ti-user'
                ),

                array(
                    'title' => 'Organizations',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=organizations'),
                    'icon'  => 'ti ti-users-group'
                ),
                array(
                    'title' => 'REST API',
                    'url'   => admin_url( 'admin.php?page=smliser-access-control&tab=rest-api'),
                    'icon'  => 'ti ti-api'
                ),
            )
        );

        if ( $tab ) {
            $home = array(
                'title' => 'Security & Access Control',
                'url'   => admin_url( 'admin.php?page=smliser-access-control' ),
                'icon'  => 'ti ti-home'
            );
            
            array_unshift( $args['actions'], $home );
        }

        Menu::print_admin_top_menu( $args );
    }
}
