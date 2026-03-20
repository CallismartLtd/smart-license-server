<?php
/**
 * Admin license page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\RESTAPI\Versions\V1;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * The admin license page class
 */
class LicensePage {
    /**
     * Page router.
     * 
     * @param Request $request
     */
    public static function router( Request $request ) {
        $tab = $request->get( 'tab' );
        switch ( $tab ) {
            case 'add-new':
                self::add_license_page( $request );
                break;
            case 'edit':
                self::edit_license_page( $request );
                break;
            case 'view':
                self::view_license_page( $request );
                break;
            case 'logs':
                self::license_logs_page( $request );
                break;
            case 'search':
                self::search_page( $request );
                break;    
            default:
            self::dashboard( $request );
        }
    
    }

    /**
     * The license page dashbard
     * 
     * @param Request $request
     */
    private static function dashboard( Request $request ) : void {
        $current_url    = smliser_get_current_url();
        $limit          = $request->get( 'limit', 20 );
        $page           = $request->get( 'paged', 1 );

        if ( $search_term = $request->get( 'search_term' ) ) {
            $license_data   = License::search([
                'search_term'   => $search_term,
                'limit'         => $limit,
                'page'          => $page
            ]);
        } else {
            $license_data   = License::get_all( $page, $limit );
        }
        
        $licenses       = $license_data['items'] ?? [];
        $pagination     = $license_data['pagination'] ?? [];
        $add_url        = smliser_license_admin_action_page( 'add-new' );
    
        include_once SMLISER_PATH . 'templates/admin/license/dashboard.php';
    
    }

    /**
     * The license page dashbard
     * 
     * @param Request $request
     */
    private static function search_page( Request $request ) : void {
        $current_url    = smliser_get_current_url();
        $limit          = $request->get( 'limit', 20 );
        $page           = $request->get( 'paged', 1 );
        $search_term    = $request->get( 'search_term' );

        $license_data   = License::search([
            'search_term'   => $search_term,
            'limit'         => $limit,
            'page'          => $page
        ]);
        $licenses       = $license_data['items'] ?? [];
        $pagination     = $license_data['pagination'] ?? [];
        $add_url        = smliser_license_admin_action_page( 'add-new' );
    
        include_once SMLISER_PATH . 'templates/admin/license/search.php';
    
    }

    /**
     * Add license page
     */
    private static function add_license_page( Request $request ) : void {
        $form_fields    = static::get_form_fields();
        $tab            = $request->get( 'tab' );
        include_once SMLISER_PATH . 'templates/admin/license/license-form.php';
    }

    /**
     * License edit page
     */
    private static function edit_license_page( Request $request ) : void {

        $license_id     = $request->get( 'license_id' );        
        $license        = License::get_by_id( $license_id );
        $tab            = $request->get( 'tab' );
        
        $form_fields    = static::get_form_fields( $license );
        include_once SMLISER_PATH . 'templates/admin/license/license-form.php';
    
    }

    /**
     * License view page
     */
    private static function view_license_page( Request $request ) : void {
        $license_id         = $request->get( 'license_id' );
        $route_descriptions = V1::describe_routes('license');   

        $license    = License::get_by_id( $license_id );
        $licensed_app   = $license?->get_app();
        if ( $license ) {
            $client_fullname    = $license->get_licensee_fullname();
            $delete_url         = ( new URL( admin_url() ) )
                ->add_query_params([
                    'action'        => 'smliser_delete_license',
                    'license_id'    => $license_id,
                    'smliser_nonce' => wp_create_nonce( 'smliser_delete_license_nonce' )
                ]);    
        }

        include_once SMLISER_PATH . 'templates/admin/license/view-license.php';    
    }

    /**
     * License activation log page.
     */
    private static function license_logs_page( Request $request ) : void {
        $all_tasks  = RepositoryAnalytics::get_license_activity_logs();

        include_once SMLISER_PATH . 'templates/admin/license/logs.php';
    }

    /**
     * Get args for admin menu.
     * 
     * @return array
     */
    protected static function get_menu_args( Request $request ) : array {
        $tab        = $request->get( 'tab' );
        $license_id = $request->get( 'license_id' );
        $title  = match ( $tab ) {
            'logs'      => 'License Activity Logs',
            'add-new'   => 'Add new license',
            'edit'      => 'Edit license',
            'view'      => 'License Details',
            'search'    => 'Search Licenses',
            default     => 'Licenses'
        };

        $args   = array(
            'breadcrumbs'   => array(
                array(
                    'label' => 'Licenses',
                    'url'   => smliser_license_page(),
                    'icon'  => 'dashicons dashicons-admin-home'
                ),
                array(
                    'label' => $title,
                )
            ),
            'actions'   => array(
                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        );

        return $args;
    }

    /**
     * Get license form fields.
     * 
     * @param License|null
     * @return array
     */
    protected static function get_form_fields( ?License $license = null ) : array {
        $app_prop           = $license && $license->get_app() ? sprintf( '%s', str_replace( '/', ':', $license->get_app_prop() ) ) : '';
        $_license_statuses  = License::get_allowed_statuses();
        $_status_titles     = array_map( 'ucwords', array_values( $_license_statuses ) );
        $_status_keys       = array_values( $_license_statuses );
        $_statuses          = array_combine( $_status_keys, $_status_titles );
        
        return array(
            array(
                'label' => 'License ID',
                'input' => array(
                    'type'  => 'hidden',
                    'name'  => 'license_id',
                    'value' => $license?->get_id() ?? 0,
                )
            ),
            array(
                'label' => 'Licensee Name',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'licensee_fullname',
                    'value' => $license?->get_licensee_fullname() ?? '',
                    'attr'  => array(
                        'aria-label'    => 'Licensee full name'
                    )

                )
            ),

            array(
                'label' => 'Service ID',
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'service_id',
                    'value' => $license?->get_service_id() ?? '',
                    'attr'  => array(
                        'aria-label'    => 'Enter Service ID'
                    )
                )
            ),
            array(
                'label' => 'Hosted Application',
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'app_prop',
                    'value' => $app_prop,
                    'attr'  => array(
                        'aria-label'    => 'Select hosted application.',
                        'class'         => 'license-app-select'
                    ),
                    'options'   => $app_prop ?[$app_prop => $license->get_app()->get_name()] : []
                )
            ),
            array(
                'label' => 'Status',
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'status',
                    'value' => $license?->get_status( 'edit' ) ?? '',
                    'attr'  => array(
                        'aria-label'    => 'Select license status.'
                    ),
                    'options'   => ['' => 'Automatic Calculation'] + $_statuses
                )
            ),
            array(
                'label' => 'Maximum Domains',
                'input' => array(
                    'type'  => 'number',
                    'name'  => 'max_allowed_domains',
                    'value' => $license?->get_max_allowed_domains( 'edit' ) ?? '',
                    'attr'  => array(
                        'aria-label'    => 'Maximum domains',
                        'title'         => 'Enter the maximum number of domains allowed to install this license.'
                    )
                )
            ),
            array(
                'label' => 'Start Date',
                'input' => array(
                    'type'  => 'datetime-local',
                    'name'  => 'start_date',
                    'value' => $license?->get_start_date()?->format( 'Y-m-d H:i:s' ) ?? '',
                    'attr'  => array(
                        'aria-label'    => 'Enter license start date.',
                        'smliser-date-picker'   => 'datetime'
                    )
                )
            ),

            array(
                'label' => 'End Date',
                'input' => array(
                    'type'  => 'datetime-local',
                    'name'  => 'end_date',
                    'value' => $license?->get_end_date()?->format( 'Y-m-d H:i:s' ) ?? '',
                    'attr'  => array(
                        'aria-label'            => 'Enter license end date.',
                        'smliser-date-picker'   => 'datetime'
                    )
                )
            ),
        );
    }
    
}