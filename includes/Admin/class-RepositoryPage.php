<?php
/**
 * The admin repository page handler class.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 */

namespace SmartLicenseServer\Admin;

use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\SmliserSoftwareCollection;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\FileSystem\FileSystemHelper;
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
            default:
            self::dashboard();
        }
    }

    /**
     * The repository dashboard page
     */
    private static function dashboard() {
        $args = array(
            'page'  => smliser_get_query_param( 'paged', 1 ),
            'limit' => smliser_get_query_param( 'limit', 25 )
        );

        $type   = smliser_get_query_param( 'type', null );
        if ( $type ) {
            $args['types']   = $type;
        }

        $result     = SmliserSoftwareCollection::get_apps( $args );
        $apps       = $result['items'];
        $pagination = $result['pagination'];
        
        $add_url    = smliser_admin_repo_tab( 'add-new' );
        include SMLISER_PATH . 'templates/admin/repository/dashboard.php';

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
        
        include_once $app_upload_page;
    }

    /**
     * The edit page
     */
    private static function edit_page() {
        $id     = smliser_get_query_param( 'item_id' );
        $type   = smliser_get_query_param( 'type' );
        $class  = SmliserSoftwareCollection::get_app_class( $type );
        $method = "get_{$type}";

        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }

        $app = $class::$method( $id );
        
        if ( ! $app ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'Invalid or deleted application! <a href="%s">Go Back</a>', esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }
        $essential_fields   = self::prepare_essential_app_fields( $app );
        $type_title         = ucfirst( $type );
        $file               = sprintf( SMLISER_PATH . 'templates/admin/repository/edit-%s.php', $type );
        include_once $file;
    }

    /**
     * View hosted application page
     */
    private static function view_page() {
        $id     = smliser_get_query_param( 'item_id' );
        $type   = smliser_get_query_param( 'type' );
        $class  = SmliserSoftwareCollection::get_app_class( $type );
        $method = "get_{$type}";

        if ( ! class_exists( $class ) || ! method_exists( $class, $method ) ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }

        $file   = \sprintf( '%s/templates/admin/repository/view-%s.php', SMLISER_PATH, $type );

        if ( ! file_exists( $file ) ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'This application type "%s" is not supportd! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }

        /** @var AbstractHostedApp|null */
        $app        = $class::$method( $id );
        $repo_class = SmliserSoftwareCollection::get_app_repository_class( $app->get_type() );
        if ( ! $app ) {
            smliser_abort_request( smliser_not_found_container( sprintf( 'This "%s" does not exist! <a href="%s">Go Back</a>', esc_html( $type ), esc_url( smliser_repo_page() ) ) ), 'Invalid App Type' );
        }

        $url            = new URL( admin_url( 'admin.php?page=repository' ) );
        $download_url   = new URL( admin_url( 'admin-post.php' ) );
        $download_url->add_query_params([ 'action' => 'smliser_admin_download', 'type' => $app->get_type(), 'id' => $app->get_id(), 'download_token' => wp_create_nonce( 'smliser_download_token' )] );
        $meta                   = $repo_class->get_metadata( $app->get_slug() );
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
                    'url'   => $url->__toString(),
                    'icon'  => 'ti ti-home',
                    'attr'  => []
                ],

                [
                    'text'  => 'Monetization',
                    'url'   => $url->add_query_params( [ 'tab' => 'monetization', 'item_id' => $app->get_id(), 'type' => $app->get_type()] )->__toString(),
                    'icon'  => 'ti ti-cash-register',
                    'attr'  => []
                ],

                [
                    'text'  => sprintf( 'Edit %s', ucfirst( $app->get_type() ) ),
                    'url'   => $url->add_query_param( 'tab', 'edit' )->__toString(),
                    'icon'  => 'ti ti-edit',
                    'attr'  => []
                ],

                [
                    'text'  => sprintf( 'Download %s', ucfirst( $app->get_type() ) ),
                    'url'   => $download_url->__toString(),
                    'icon'  => 'ti ti-download',
                    'attr'  => []
                ],

                [
                    'text'  => sprintf( 'Delete %s', ucfirst( $app->get_type() ) ),
                    'url'   => '',
                    'icon'  => 'ti ti-trash',
                    'attr'  => ['data-app-info' => smliser_json_encode_attr( ['slug' => $app->get_slug(), 'type' => $app->get_type()] )]
                ],
            ]
        ];
        $template_content = [
            'Installation'          => $app->get_installation(),
            'Changelog'             => $app->get_changelog(),
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
                'content'   => self::build_info_html( $app, $meta, $file_size, $last_updated_string )
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

        include_once SMLISER_PATH . 'templates/admin/monetization.php';
        
    }

    /**
     * Prepare essential application fields
     * 
     * @param AbstractHostedApp|null $app
     */
    private static function prepare_essential_app_fields( ?AbstractHostedApp $app = null ) {
        return array(
            array(
                'label' => __( 'Name', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_name',
                    'value' => $app ? $app->get_name() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
            array(
                'label' => __( 'Version', 'smliser' ),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_version',
                    'value' => $app ? $app->get_version() : '',
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off',
                        'readonly'      => true,
                        'title'         => 'Use app manifest file to edit version'
                    )
                )
            ),
            array(
                'label' => __( 'Author Name'),
                'input' => array(
                    'type'  => 'text',
                    'name'  => 'app_author',
                    'value' => $app ? $app->get_author() : '',
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
                    'attr'  => array(
                        'autocomplete'  => 'off',
                        'spellcheck'    => 'off'
                    )
                )
            ),
        );
    }


    /**
     * Build the INFO list (common to all hosted apps)
     *
     * @param AbstractHostedApp $app Hosted app instance.
     * @param array             $meta Metadata array.
     * @param string            $file_size Human-readable size.
     * @param string            $last_updated_string Time string.
     * @return string
     */
    private static function build_info_html( $app, $meta, $file_size, $last_updated_string ) {

        $license_uri = $meta['license_uri'] ?? '';

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
            __( 'APP ID', 'smliser' ),             //1
            $app->get_id(),                         //2
            __( 'Slug', 'smliser' ),           //3
            $app->get_slug(),          //4
            __( 'License', 'smliser' ),            //5
            $meta['license'] ?? '',                //6
            __( 'Status', 'smliser' ),             //7
            $app->get_status(),                    //8
            __( 'Monetization', 'smliser' ),       //9
            $app->is_monetized() ? 'check' : 'x',  //10
            __( 'Public Download URL', 'smliser' ),//11
            $app->is_monetized() ? $app->monetized_url_sample() : $app->get_download_url(), //12
            __( 'File Size', 'smliser' ),          //13
            $file_size,                             //14
            __( 'Last Updated', 'smliser' ),        //15
            $last_updated_string,                   //16
            $license_uri                             //17
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
                $is_assoc = array_keys($value) !== range(0, count($value) - 1);

                if ( $is_assoc ) {
                    $parts = [];
                    foreach( $value as $k => $v ) {
                        if ( is_array( $v ) ) {
                            $v = implode(', ', $v);
                        }
                        $parts[] = "$k: $v";
                    }
                    $value = implode(', ', $parts);
                } else {
                    // Sequential array: just implode values
                    $value = implode(', ', $value);
                }
            }

            $details .= sprintf(
                '<li><span>%1$s</span> <span>%2$s</span></li>',
                ucwords(str_replace('_', ' ', $key)),
                $value
            );
        }

        $details .= '</ul>';

        return $details;
    }

    /**
     * Build analytics html
     * 
     * @param AbstractHostedApp $app
     */
    private static function build_analytics_html( AbstractHostedApp $app ) {

    }
}