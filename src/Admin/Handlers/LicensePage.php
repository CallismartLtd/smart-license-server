<?php
/**
 * Admin license page router class file
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin\Handlers;

use SmartLicenseServer\Admin\Contracts\AdminPageInterface;
use SmartLicenseServer\Analytics\RepositoryAnalytics;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Monetization\License;

use function compact, smliser_render_template;

/**
 * The admin license page class
 */
class LicensePage implements AdminPageInterface {
    /**
     * Page sub-router.
     * 
     * @param Request $request
     */
    public static function sub_router( Request $request ) {
        $tab = $request->get( 'tab' );
        switch ( $tab ) {
            case 'edit':
                self::edit_license_page( $request );
                break;
            case 'view':
                self::view_license_page( $request );
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
    public static function dashboard( Request $request ) : void {
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
        $add_url        = smliser_license_page()->add_query_param( 'tab', 'add-new' );

        $vars   = compact( 'request', 'current_url', 'licenses', 'pagination', 'add_url' );
        smliser_render_template( 'admin-content.license.index', $vars );    
    }

    /**
     * The license page dashbard
     * 
     * @param Request $request
     */
    public static function search_page( Request $request ) : void {
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
        $add_url        = smliser_license_page()->add_query_param( 'tab', 'add-new' );
    
        $vars   = compact( 'request', 'current_url', 'licenses', 'pagination', 'add_url' );
        smliser_render_template( 'admin-content.license.search', $vars );  
    
    }

    /**
     * Add license page
     */
    public static function add_license_page( Request $request ) : void {
        $form_fields    = static::get_form_fields();
        $tab            = $request->get( 'tab' );

        $vars   = compact( 'request', 'form_fields', 'tab' );
        smliser_render_template( 'admin-content.license.form', $vars );
    }

    /**
     * License edit page
     */
    public static function edit_license_page( Request $request ) : void {

        $license_id     = $request->get( 'license_id' );        
        $license        = License::get_by_id( $license_id );
        $tab            = $request->get( 'tab' );
        
        $form_fields    = static::get_form_fields( $license );
        $vars           = compact( 'request', 'form_fields', 'tab', 'license', 'license_id' );
        smliser_render_template( 'admin-content.license.form', $vars );
    
    }

    /**
     * License view page
     */
    public static function view_license_page( Request $request ) : void {
        $license_id     = $request->get( 'license_id' );
        $license        = License::get_by_id( $license_id );
        $licensed_app   = $license?->get_app();

        $vars   = compact( 'request', 'license', 'licensed_app', 'license_id' );
        if ( $license ) {
            $licensee   = $license->get_licensee_fullname();
            $delete_url = \adminUrl( '', [
                'action'        => 'smliser_delete_license',
                'license_id'    => $license_id,
                'smliser_nonce' => wp_create_nonce( 'smliser_delete_license_nonce' )
            ]);

            $vars['licensee']   = $licensee;
            $vars['delete_url'] = $delete_url;
        }
        
        smliser_render_template( 'admin-content.license.view', $vars );
   
    }

    /**
     * License activation log page.
     */
    public static function license_logs_page( Request $request ) : void {
        $logs  = RepositoryAnalytics::get_license_activity_logs();

        if ( $request->has( 'filterBy') ) {
            $logs   = array_filter( 
                $logs, 
                fn( $log ) => ( $log['license_id'] ?? 0 ) === $request->get( 'filterBy' ) 
            );
        }
        
        smliser_render_template( 'admin-content.license.logs', compact( 'logs', 'request' ) );
    }

    /**
     * Get args for admin menu.
     * 
     * @return array
     */
    public static function get_menu_args( Request $request ) : array {
        $tab        = $request->get( 'tab' );
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
                    'url'   => \adminUrl( 'admin.php', ['page' => 'smliser-settings'] ),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        );

        return $args;
    }

    /**
     * Get license form fields.
     * 
     * @param License|null $license
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
    
    /*
    |---------------------------
    | INTERFACE IMPLEMENTATION
    |---------------------------
    */

    public static function index_page_handler() : callable {
        return [static::class, 'sub_router'];
    }

    /**
     * @inheritdoc
     */
    public static function get_submenu() : array {
        return [
            [
                'title'     => 'Add New',
                'slug'      => 'add-new',
                'callback'  => [ static::class, 'add_license_page']
            ],
            [
                'title'     => 'Activity Logs',
                'slug'      => 'logs',
                'callback'  => [static::class, 'license_logs_page']
            ],
            [
                'title'     => 'Search Licenses',
                'slug'      => 'search',
                'callback'  => [static::class, 'search_page']
            ]
        ];
    }

    public static function routing_var() : string {
        return 'tab';
    }
    
    public static function get_menu_key() : string {
        return 'licenses';
    }

    public static function get_menu_data() : array {
        return [
            'title'   => 'Licenses',
            'slug'    => 'licenses',
            'handler' => static::class,
            'icon'    => 'ti ti-license',
        ];
    }
}