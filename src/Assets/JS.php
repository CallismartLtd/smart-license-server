<?php
/**
 * JavaScript assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use SmartLicenseServer\Core\URLManager;

final class JS {

    public function __construct( protected URLManager $urlmanager ) {}

    /**
     * Get all registered JavaScript assets and their dependencies.
     *
     * @param string $suffix Optional JavaScript filename suffix, e.g. ".min".
     * @return array<string, array{
     *     url: \SmartLicenseServer\Core\URL,
     *     dependencies: string[],
     *     version: string,
     *     footer: bool
     * }>
     */
    public function all( string $suffix = '' ) : array {
        return [
            'string-utils' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/string-utils%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-admin-scripts' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/dashboard%s.js', $suffix ) ),
                'dependencies'  => [
                    'smliser-script', 'smliser-apps-uploader', 'smliser-chart',
                    'smliser-role-builder'
                ],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-script' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/main-script%s.js', $suffix ) ),
                'dependencies'  => [
                    'string-utils', 'smliser-jquery', 'select2', 'smliser-datetime-picker',
                    'smliser-modal','smliser-toast'
                ],
                'version'   => SMLISER_VER,
                'footer'    => true
            ],
            'smliser-apps-uploader' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/apps-uploader%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-json-editor'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'select2' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/Select2/select2%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-tinymce' => [
                'url'           => $this->urlmanager->assets_url( 'js/tinymce/tinymce.min.js' ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-admin-repository' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/admin-repository%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-role-builder' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/role-builder%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-chart' => [
                'url'           => $this->urlmanager->assets_url( 'js/Chartjs/chart.min.js' ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-modal' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/smliser-modal%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-json-editor' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/json-editor%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-datetime-picker' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/smliser-datetime-picker%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-email-editor' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/email-editor%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-cache-stats' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/cache-stats%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-jquery' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/jQuery/jQuery%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'smliser-client-dashboard' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/client-dashboard%s.js', $suffix ) ),
                'dependencies'  => ['smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'smliser-client-auth' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/smliser-client-auth%s.js', $suffix ) ),
                'dependencies'  => ['smliser-modal', 'smliser-script'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-toast' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/smliser-toast%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
        ];
    }
}