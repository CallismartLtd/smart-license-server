<?php
/**
 * The admin repository page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\RESTAPI\Versions\V1;

/**
 * The Admin repository page handler
 */
class RepositoryPage {
    /**
     * Page router
     */
    public static function router() {
        $tab    = smliser_get_query_param( 'tab' );
        
        switch( $tab ) {
            case 'add-new':
                self::upload_page();
                break;
            case 'edit':
                self::edit_page();
                break;
            case 'view':
                self::view_page();
                break;
            case 'monetization':
                self::monetization_page();
                break;
            case 'search':
                self::search_page();
                break;
            default:
            self::dashboard();
        }
    }

    /**
     * The repository dashboard page
     */
    private static function dashboard() {
        $args = array(
            'page'      => smliser_get_query_param( 'paged', 1 ),
            'limit'     => smliser_get_query_param( 'limit', 10 ),
        );

        $type   = smliser_get_query_param( 'type', null );
        $tab    = smliser_get_query_param( 'tab', '' );
        if ( $type ) {
            $args['types']   = $type;
        }

        $status     = \smliser_get_query_param( 'status', AbstractHostedApp::STATUS_ACTIVE );
        $has_status = (bool) smliser_get_query_param( 'status', false );

        if ( $status ) {
            $args['status'] = $status;
        }

        $result     = HostedApplicationService::get_apps( $args );

        $apps           = $result['items'];
        $pagination     = $result['pagination'];
        $current_url    = smliser_get_current_url()->remove_query_param( 'message', 'tab' );
        $add_url        = $current_url
        ->add_query_param( 'tab', 'add-new' )
        ->remove_query_param( 'status' );

        $page_title = \sprintf( '%s Repository', ucfirst( (string) $type ) );

        if ( $has_status ) {
            $page_title = sprintf( 'Status: %s', $status );
        }

        include SMLISER_PATH . 'templates/admin/repository/dashboard.php';

    }

    /**
     * Page to search the entire repository.
     */
    private static function search_page() {
        $illigal        = ['app_search', 'tab', 'search_status', 's', 'app_types', 'message', 'type', 'status'];
        $current_url    = smliser_get_current_url()->remove_query_param( ...$illigal );
        $add_url        = $current_url
        ->add_query_param( 'tab', 'add-new' )
        ->remove_query_param( 'status' );
        $post_url   = $current_url->add_query_param( 'tab', 'search' );
        $app_types  = HostedApplicationService::get_allowed_app_types();
        $limit      = smliser_get_query_param( 'limit', 10 );
        $page       = smliser_get_query_param( 'paged', 1 );
        $app_data   = [
            'items' => [], 
            'pagination' => [
                'page'        => $page,
                'limit'       => $limit,
                'total'       => 0,
                'total_pages' => 0,
            ]
        ];
        
        if ( \smliser_get_query_param( 'app_search', false ) ) {
           
            $types          = (string) \smliser_get_request_param( 'app_types', '' );
            $types          = str_contains( $types, '|' ) ? explode( '|', $types ) : (array) $types;
            $search_term    = \smliser_get_request_param( 'app_search', '' );
            $search_status  = \smliser_get_request_param( 'search_status', 'active' );
            $post_url       = $post_url->add_query_param( 's', $search_term );

            $app_data   = HostedApplicationService::search_apps([
                'page'  => $page,
                'term'  => $search_term,
                'status'    => $search_status,
                'types'     => $types,
                'limit'     => $limit
            ]);
        }

        $apps       = $app_data['items'];
        $pagination = $app_data['pagination'];

        $menu_args  = array(
            'breadcrumbs'   => array(
                array(
                    'title' => 'Repository',
                    'label' => 'Repository',
                    'url'   => $current_url ->remove_query_param( 'tab', 'type', 'status' )->get_href()
                ),

                array(
                    'label' => 'Search Repository'
                )
            ),

            'actions'   => array(
                array(
                    'title'     => 'Upload New Application',
                    'label'     => 'Upload New',
                    'url'       => $add_url->get_href(),
                    'icon'      => 'ti ti-upload',
                ),

                array(
                    'title'     => 'Plugin Repository',
                    'label'     => 'Plugins',
                    'url'       => $current_url->add_query_param( 'type', 'plugin' )->get_href(),
                    'icon'      => 'ti ti-plug',
                ),
                
                array(
                    'title'     => 'Theme Repository',
                    'label'     => 'Themes',
                    'url'       => $current_url->add_query_param( 'type', 'theme' )->get_href(),
                    'icon'      => 'ti ti-palette',
                ),
                
                array(
                    'title'     => 'Software Repository',
                    'label'     => 'Software',
                    'url'       => $current_url->add_query_param( 'type', 'software' )->get_href(),
                    'icon'      => 'ti ti-device-desktop-code',
                ),
            )
        );
        
        include SMLISER_PATH . 'templates/admin/repository/search.php';
    }

    /**
     * The upload page
     */
    private static function upload_page() {
        $type = smliser_get_query_param( 'type', null );

        $app_upload_dashboard   = SMLISER_PATH . 'templates/admin/repository/upload.php';
        $app_upload_template    = SMLISER_PATH . 'templates/admin/repository/uploader.php';
        $app_upload_page        = $type ? $app_upload_template : $app_upload_dashboard;

        $type_title = $type ? ucfirst( $type ) : '';
        $title      = \sprintf( 'Upload New %s', $type_title );

        $essential_fields = self::prepare_essential_app_fields();
        $app_action = array(
            'title' => 'Repository',
            'label' => 'Repository',
            'url'   => smliser_get_current_url()->remove_query_param(  'app_id', 'type', 'tab' ),
            'icon'  => 'ti ti-arrow-back'
        );
        include_once $app_upload_page;
    }

    /**
     * The edit page
     */
    private static function edit_page() {
        $id     = smliser_get_query_param( 'app_id' );
        $type   = smliser_get_query_param( 'type' );
    
        if ( ! HostedApplicationService::app_type_is_allowed( $type ) ) {
            echo smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) );
            return;
        }

        $app = HostedApplicationService::get_app_by_id( $type, $id );
        
        if ( ! $app ) {
            echo smliser_not_found_container( sprintf( 'Invalid or deleted application! <a href="%s">Go Back</a>', esc_url( smliser_repo_page() ) ) );
            return;
        }

        $app_action = array(
            'title' => 'View App',
            'label' => 'View App',
            'url'   => smliser_get_current_url()->add_query_params( 
                array(
                    'app_id'    => $app->get_id(), 
                    'type'      => $app->get_type(),
                    'tab'       => 'view' 
                ) 
            ),
            'icon'  => 'ti ti-eye'
        );
        $essential_fields   = self::prepare_essential_app_fields( $app );
        $type_title         = ucfirst( $type );
        $file               = sprintf( SMLISER_PATH . 'templates/admin/repository/edit-%s.php', $type );
        include_once $file;
    }

    /**
     * View hosted application page
     */
    private static function view_page() {
        $id     = smliser_get_query_param( 'app_id' );
        $type   = smliser_get_query_param( 'type' );
        
        if ( ! HostedApplicationService::app_type_is_allowed( $type ) ) {
            echo smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) );
            return;
        }

        $file   = \sprintf( '%s/templates/admin/repository/view-%s.php', SMLISER_PATH, $type );

        if ( ! file_exists( $file ) ) {
            echo smliser_not_found_container( sprintf( 'This application type "%s" edit file does not exist! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) );
            return;
        }

        $app = HostedApplicationService::get_app_by_id( $type, $id );

        if ( ! $app ) {
            echo smliser_not_found_container( sprintf( 'This "%s" does not exist! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) );
            return;
        }

        $repo_class = HostedApplicationService::get_app_repository_class( $app->get_type() );

        $url                    = new URL( admin_url( 'admin.php?page=repository' ) );
        $download_actions       = [
            'action' => 'smliser_admin_download',
            'type'   => $app->get_type(),
            'id'     => $app->get_id(),
            'download_token' => wp_create_nonce( 'smliser_download_token' )
        ];
        $download_url           = ( new URL( admin_url( 'admin-post.php' ) ) )->add_query_params( $download_actions);
        $last_updated_string    = sprintf( '%s ago', smliser_readable_duration( time() - strtotime( $app->get_last_updated() ) ) );
        $file_size              = FileSystemHelper::format_file_size( $repo_class->filesize( $app->get_file() ) );

        $template_header    = [
            'icon'              => $app->get_icon(),
            'name'              => $app->get_name(),
            'badges'            => [ $app->get_status(), $app->get_type(), $app->get_version() ],
            'short_description' => $app->get_short_description(),
            'buttons'           => [
                [
                    'text'  => 'Repository',
                    'url'   => $url->get_href(),
                    'icon'  => 'ti ti-home',
                    'class' => ['smliser-btn', 'smliser-btn-glass'],
                    'attr'  => []
                ],

                [
                    'text'  => 'Monetization',
                    'url'   => $url->add_query_params( [ 'tab' => 'monetization', 'app_id' => $app->get_id(), 'type' => $app->get_type()] )->get_href(),
                    'icon'  => 'ti ti-cash-register',
                    'class' => ['smliser-btn', 'smliser-btn-glass'],
                    'attr'  => []
                ],

                [
                    'text'  => sprintf( 'Edit %s', ucfirst( $app->get_type() ) ),
                    'url'   => $url->add_query_params( ['tab' => 'edit', 'app_id' => $app->get_id(), 'type' => $app->get_type()] )->get_href(),
                    'icon'  => 'ti ti-edit',
                    'class' => ['smliser-btn', 'smliser-btn-glass'],
                    'attr'  => []
                ],

                [
                    'text'  => sprintf( 'Download %s', ucfirst( $app->get_type() ) ),
                    'url'   => $download_url->__toString(),
                    'icon'  => 'ti ti-download',
                    'class' => ['smliser-btn', 'smliser-btn-glass'],
                    'attr'  => []
                ]
            ]
        ];

        if ( $app->can_be_restored() ) {
            $template_header['buttons'][] = [
                'text'  => sprintf( 'Restore %s', ucfirst( $app->get_type() ) ),
                'url'   => '',
                'icon'  => 'ti ti-arrow-back-up',
                'class' => ['smliser-btn', 'smliser-btn-glass', 'smliser-app-restore-button'],
                'attr'  => ['data-action-args' => smliser_json_encode_attr( ['slug' => $app->get_slug(), 'type' => $app->get_type(), 'status' => AbstractHostedApp::STATUS_ACTIVE] )]
            ];
        } else if ( $app->can_be_trashed() ) {
            $template_header['buttons'][] = [
                'text'  => sprintf( 'Trash %s', ucfirst( $app->get_type() ) ),
                'url'   => '',
                'icon'  => 'ti ti-trash',
                'class' => ['smliser-btn', 'smliser-btn-danger', 'smliser-app-delete-button'],
                'attr'  => ['data-action-args' => smliser_json_encode_attr( ['slug' => $app->get_slug(), 'type' => $app->get_type(), 'status' => AbstractHostedApp::STATUS_TRASH] )]
            ];
        }

        $template_content = [
            'Installation'  => $app->get_installation(),
            'Changelog'     => $app->get_changelog(),
        ];

        $template_sidebar   = [
            'Author'    => [
                'icon'      => 'ti ti-user',
                'content'   => ''
            ],
            'Performance Metrics (30 days)'   => [
                'icon'      => 'ti ti-chart-histogram',
                'content'   => self::build_analytics_html( $app )
            ], 
            'Application Details'   => [
                'icon'      => 'ti ti-info-circle',
                'content'   => self::build_info_html( $app, $file_size, $last_updated_string )
            ],
            'Technical Details'     => [
                'icon'      => 'ti ti-cpu',
                'content'   => self::build_tech_details( $app->get_manifest() )
            ],
        ];

        
        $route_descriptions     = V1::describe_routes( 'repository' );   

        include_once $file;
    }

    /**
     * Manage plugin monetization page
     */
    private static function monetization_page() {
        $url    = new URL( admin_url( 'admin.php?page=repository' ) );
        $url->remove_query_param( 'message' );

        include_once SMLISER_PATH . 'templates/admin/monetization.php';
    }

    /**
     * Prepare essential application fields
     * 
     * @param AbstractHostedApp|null $app
     */
    private static function prepare_essential_app_fields( ?AbstractHostedApp $app = null ) {
        $type               = \smliser_get_query_param( 'type' );
        $manifest_filename  = match( $type ) {
            'plugin'    => 'readme.txt',
            'theme'     => 'style.css',
            default     => 'app.json'
        };

        $owner_option  = [];

        if ( $app && $owner = $app->get_owner() ) {
            $owner_option  = [
                sprintf( '%s:%s', $owner->get_type(), $owner->get_id() ) => $owner->get_name()
                
            ];
        }

        $statuses   = array_filter( AbstractHostedApp::STATUSES, fn( $v ) => $v !== AbstractHostedApp::STATUS_TRASH );

        return array(
            array(
                'label' => __( 'Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_name',
                    'value' => $app ? $app->get_name() : '',
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'required'      => true
                    )
                )
            ),
            array(
                'label' => __( 'Version', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_version',
                    'value' => $app ? $app->get_version() : '',
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'readonly'      => true,
                        'title'         => \sprintf( 'Use %s file to edit %s version', $manifest_filename, $type )
                    )
                )
            ),
            array(
                'label' => __( 'Author Name'),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author',
                    'value' => $app ? $app->get_author() : '',
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Author Profile URL', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author_url',
                    'value' => $app ? $app->get_author_profile() : '',
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Owner', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'app_owner_id',
                    'value' => key( $owner_option ),
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    ),
                    'options'   => $owner_option
                ),
            ),
            array(
                'label' => __( 'Status', 'smliser' ),
                'input' => array(
                    'type'  => 'select',
                    'name'  => 'app_status',
                    'value' => $app ? $app->get_status() : 'active',
                    'class' => 'app-uploader-form-row',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    ),
                    'options'   => array_map( 'ucfirst', $statuses )
                )
            ),
        );
    }

    /**
     * Get page menu args
     * 
     * @param HostedAppsInterface|null $app
     * @return array
     */
    protected static function get_menu_args( ?HostedAppsInterface $app = null ) : array {
        $tab        = smliser_get_query_param( 'tab', '' );
        $app_type   = \ucfirst( $app?->get_type() ?? smliser_get_query_param( 'type', '' ) );
        $app_name   = $app?->get_name() ?? '';

        $title  = match( $tab ) {
            'monetization'  => sprintf( '%s Monetization', $app_type ),
            'edit'          => sprintf( 'Edit %s: %s', $app_type, $app_name ),
            'view'          => $app_name,
            'add-new'       => 'Add New',
            default         => ''
        };
        
        return [
            'breadcrumbs'   => array(
                array(
                    'label' => 'Repository',
                    'url'   => admin_url( 'admin.php?page=repository' ),
                    'icon'  => 'ti ti-home-filled'
                ),

                array(
                    'label' => smliser_pluralize( $app?->get_type() ?? $app_type ),
                    'url'   => admin_url( 'admin.php?page=repository&type=' . $app?->get_type() ?? '' ),
                    'icon'  => 'ti ti-folder-open'
                ),
                array(
                    'label' => $title
                )
            ),
            'actions'   => array(
                array(
                    'title' => 'Edit App',
                    'label' => 'Edit',
                    'url'   => smliser_get_current_url()->add_query_params( 
                        array(
                            'app_id'    => $app?->get_id() ?? 0, 
                            'type'      => $app?->get_type() ?? $app_type,
                            'tab'       => 'edit' 
                        ) 
                    ),
                    'icon'  => 'ti ti-edit',
                    'active'    => 'edit' === $tab
                ),

                array(
                    'title' => 'View App',
                    'label' => 'View',
                    'url'   => smliser_get_current_url()->add_query_params( 
                        array(
                            'app_id'    => $app?->get_id() ?? 0, 
                            'type'      => $app?->get_type() ?? '',
                            'tab'       => 'view' 
                        ) 
                    ),
                    'icon'  => 'ti ti-eye',
                    'active'    => 'view' === $tab
                ),

                array(
                    'title' => 'App monetization',
                    'label' => 'Monetization',
                    'url'   => smliser_get_current_url()->add_query_params( 
                        array(
                            'app_id'    => $app?->get_id() ?? 0, 
                            'type'      => $app?->get_type() ?? '',
                            'tab'       => 'monetization' 
                        ) 
                    ),
                    'icon'  => 'ti ti-eye',
                    'active'    => 'monetization' === $tab
                ),

                array(
                    'title' => 'Settings',
                    'label' => 'Settings',
                    'url'   => admin_url( 'admin.php?page=smliser-options'),
                    'icon'  => 'dashicons dashicons-admin-generic'
                )
            )
        ];
    }

    /**
     * Build the INFO list (common to all hosted apps)
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param string            $file_size Human-readable size.
     * @param string            $last_updated_string Time string.
     * @return string
     */
    private static function build_info_html( $app, $file_size, $last_updated_string ) {

        $license_uri    = $app->get_license()['license_uri'] ?? '';
        $license        = $app->get_license()['license'] ?? '';

        return sprintf(
            '<ul class="smliser-app-meta">
                <li><span>%1$s</span> <span>#%2$s</span></li>
                <li><span>%3$s</span>
                    <span class="smliser-click-to-copy"
                    data-copy-value="%4$s" 
                        title="copy">%4$s</span>
                </li>
                <li><span>%5$s</span> <a href="%17$s">%6$s</a></li>
                <li><span>%7$s</span> <span>%8$s</span></li>
                <li><span>%9$s</span> <i class="ti ti-circle-%10$s-filled"></i></li>
                <li title="%12$s"><span>%11$s</span> 
                    <i class="ti ti-copy smliser-click-to-copy" 
                        data-copy-value="%12$s" 
                        title="copy"></i>
                </li>
                <li><span>%13$s</span> <span>%14$s</span></li>
                <li><span>%15$s</span> <span>%16$s</span></li>
            </ul>',
            __( 'APP ID', 'smliser' ),
            $app->get_id(),
            __( 'Slug', 'smliser' ),
            $app->get_slug(),
            __( 'License', 'smliser' ),
            $license,
            __( 'Status', 'smliser' ),
            $app->get_status(),
            __( 'Monetization', 'smliser' ),
            $app->is_monetized() ? 'check' : 'x',
            __( 'Public Download URL', 'smliser' ),//11
            $app->is_monetized() ? $app->monetized_url_sample() : $app->get_download_url(),
            __( 'File Size', 'smliser' ),
            $file_size,
            __( 'Last Updated', 'smliser' ),
            $last_updated_string,
            $license_uri
        );
    }

    /**
     * Build the Technical Details section
     * 
     * @param array $data
     */
    private static function build_tech_details( array $data ) : string {

        unset( $data['name'], $data['slug'], $data['version'] );
        $details = '<ul class="smliser-app-meta">';

        foreach( $data as $key => $value ) {

            if ( is_array( $value ) ) {
                // Detect if associative array
                $is_assoc = array_keys( $value) !== range(0, count( $value ) - 1 );

                $value = smliser_implode_deep_smart( $value );
            }

            $details .= sprintf(
                '<li><span class="list-title">%1$s</span> <span class="list-value">%2$s</span></li>',
                ucwords(str_replace('_', ' ', $key)),
                $value
            );
        }

        $details .= '</ul>';

        return $details;
    }

    /**
     * Build analytics HTML payload for an app.
     *
     * @param AbstractHostedApp $app
     * @return string
     */
    private static function build_analytics_html( AbstractHostedApp $app ) {

        // Downloads.
        $downloads_daily   = AppsAnalytics::get_downloads_per_day( $app, 30 );
        ksort( $downloads_daily );

        $download_days     = array_keys( $downloads_daily );
        $download_values   = array_values( $downloads_daily );

        // Client access.
        $access_daily      = AppsAnalytics::get_client_access_per_day( $app, 30 );
        ksort( $access_daily );

        $access_days       = array_keys( $access_daily );
        $access_values     = array_values( $access_daily );

        // Aggregate KPIs.
        $analytics = array(
            'downloads' => array(
                'total'        => AppsAnalytics::get_total_downloads( $app ),
                'today'        => AppsAnalytics::get_todays_downloads( $app ),
                'average'      => AppsAnalytics::get_average_daily_downloads( $app, 30 ),
                'growth'       => AppsAnalytics::get_download_growth_percentage( $app, 30 ),
                'peak_day'     => AppsAnalytics::get_peak_download_day( $app, 365 ),
                'timeline'     => array(
                    'days'   => $download_days,
                    'values' => $download_values,
                ),
            ),
            'client_access' => array(
                'total'        => AppsAnalytics::get_total_client_accesses( $app ),
                'average'      => AppsAnalytics::get_average_daily_client_accesses( $app, 30 ),
                'growth'       => AppsAnalytics::get_client_access_growth_percentage( $app, 30 ),
                'peak_day'     => AppsAnalytics::get_peak_client_access_day( $app, 365 ),
                'active_installs' => AppsAnalytics::get_estimated_active_installations( $app, 30 ),
                'timeline'     => array(
                    'days'   => $access_days,
                    'values' => $access_values,
                ),
            ),
        );

        $json = smliser_json_encode_attr( $analytics );

        $html = '<div class="smliser-app-analytics">';
        
        $html .= '<div class="smliser-chart-container">';
        $html .= sprintf(
            '<canvas class="smliser-app-mini-analytics" data-analytics="%s"></canvas>',
            esc_attr( $json )
        );
        $html .= '</div>';

        // Stats Footer
        $html .= self::build_stats_footer( $analytics );
        
        $html .= '</div>';

        return $html;
    }

    /**
     * Build stats footer HTML.
     *
     * @param array $analytics Analytics data array.
     * @return string HTML for stats footer.
     */
    private static function build_stats_footer( array $analytics ) {
        $stats = array();

        // Downloads stats
        if ( ! empty( $analytics['downloads'] ) ) {
            $downloads = $analytics['downloads'];

            if ( isset( $downloads['total'] ) ) {
                $stats[] = array(
                    'icon'  => '📥',
                    'label' => 'Total Downloads',
                    'value' => number_format( $downloads['total'] ),
                    'color' => '#3b82f6',
                );
            }

            if ( isset( $downloads['today'] ) ) {
                $stats[] = array(
                    'icon'  => '📊',
                    'label' => 'Today\'s Downloads',
                    'value' => number_format( $downloads['today'] ),
                    'color' => '#3b82f6',
                );
            }

            if ( isset( $downloads['average'] ) ) {
                $stats[] = array(
                    'icon'  => '📈',
                    'label' => 'Avg Downloads/Day',
                    'value' => number_format( $downloads['average'], 1 ),
                    'color' => '#3b82f6',
                );
            }

            if ( isset( $downloads['growth'] ) ) {
                $growth = (float) $downloads['growth'];
                $stats[] = array(
                    'icon'      => $growth >= 0 ? '🚀' : '📉',
                    'label'     => 'Download Growth',
                    'value'     => ( $growth >= 0 ? '+' : '' ) . number_format( $growth, 1 ) . '%',
                    'color'     => $growth >= 0 ? '#10b981' : '#ef4444',
                    'highlight' => true,
                );
            }

            if ( ! empty( $downloads['peak_day'] ) && isset( $downloads['peak_day']['count'] ) ) {
                $stats[] = array(
                    'icon'     => '🏆',
                    'label'    => 'Peak Day',
                    'value'    => number_format( $downloads['peak_day']['count'] ),
                    'subtitle' => date( 'M j, Y', strtotime( $downloads['peak_day']['date'] ) ),
                    'color'    => '#8b5cf6',
                );
            }
        }

        // Client Access stats
        if ( ! empty( $analytics['client_access'] ) ) {
            $access = $analytics['client_access'];

            if ( isset( $access['total'] ) ) {
                $stats[] = array(
                    'icon'  => '🌐',
                    'label' => 'Total Client Access',
                    'value' => number_format( $access['total'] ),
                    'color' => '#10b981',
                );
            }

            if ( isset( $access['average'] ) ) {
                $stats[] = array(
                    'icon'  => '📊',
                    'label' => 'Avg Access/Day',
                    'value' => number_format( $access['average'], 1 ),
                    'color' => '#10b981',
                );
            }

            if ( isset( $access['growth'] ) ) {
                $growth = (float) $access['growth'];
                $stats[] = array(
                    'icon'      => $growth >= 0 ? '🚀' : '📉',
                    'label'     => 'Access Growth',
                    'value'     => ( $growth >= 0 ? '+' : '' ) . number_format( $growth, 1 ) . '%',
                    'color'     => $growth >= 0 ? '#10b981' : '#ef4444',
                    'highlight' => true,
                );
            }

            if ( isset( $access['active_installs'] ) ) {
                $stats[] = array(
                    'icon'      => '⚡',
                    'label'     => 'Active Installs',
                    'value'     => '~' . number_format( $access['active_installs'] ),
                    'color'     => '#f59e0b',
                    'highlight' => true,
                );
            }

            if ( ! empty( $access['peak_day'] ) && isset( $access['peak_day']['count'] ) ) {
                $stats[] = array(
                    'icon'     => '🏆',
                    'label'    => 'Peak Access Day',
                    'value'    => number_format( $access['peak_day']['count'] ),
                    'subtitle' => date( 'M j, Y', strtotime( $access['peak_day']['date'] ) ),
                    'color'    => '#8b5cf6',
                );
            }
        }

        // No stats to display
        if ( empty( $stats ) ) {
            return '';
        }

        // Build HTML
        $html = '<div class="analytics-stats-footer">';

        foreach ( $stats as $stat ) {
            $highlight = ! empty( $stat['highlight'] ) ? ' highlight' : '';
            
            $html .= sprintf( '<div class="stat-card%s">', $highlight );
            $html .= '<div class="stat-card_header">';
            $html .= sprintf( '<span class="icon">%s</span>', $stat['icon'] );
            $html .= sprintf( '<span class="stat-card_header-title">%s</span>', esc_html( $stat['label'] ) );
            $html .= '</div>';
            $html .= '<div class="stat-card_content">';
            $html .= sprintf( '<span class="stat-card_content-value">%s</span>', esc_html( $stat['value'] ) );
            
            if ( ! empty( $stat['subtitle'] ) ) {
                $html .= sprintf( '<span class="stat-card_content-subtitle">%s</span>', esc_html( $stat['subtitle'] ) );
            }
            $html .= '</div>';
            
            $html .= '</div>'; // Stat card.
        }

        $html .= '</div>'; // Close footer

        return $html;
    }
}