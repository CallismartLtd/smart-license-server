<?php
/**
 * JavaScript assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use function assetsUrl;
final class JS {

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
    public static function all( string $suffix = '' ) : array {
        return [
            'string-utils' => [
                'url'           => assetsUrl( sprintf( 'js/string-utils%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-admin-scripts' => [
                'url'           => assetsUrl( sprintf( 'js/admin/dashboard%s.js', $suffix ) ),
                'dependencies'  => [
                    'smliser-script', 'smliser-apps-uploader', 'smliser-chart',
                ],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-script' => [
                'url'           => assetsUrl( sprintf( 'js/main-script%s.js', $suffix ) ),
                'dependencies'  => [
                    'string-utils', 'smliser-jquery', 'select2', 'smliser-datetime-picker',
                    'smliser-modal','smliser-toast', 'smliser-role-builder'
                ],
                'version'   => SMLISER_VER,
                'footer'    => true
            ],
            'smliser-apps-uploader' => [
                'url'           => assetsUrl( sprintf( 'js/admin/apps-uploader%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'select2' => [
                'url'           => assetsUrl( sprintf( 'js/Select2/select2%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-tinymce' => [
                'url'           => assetsUrl( 'js/tinymce/tinymce.min.js' ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-admin-repository' => [
                'url'           => assetsUrl( sprintf( 'js/admin/admin-repository%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-role-builder' => [
                'url'           => assetsUrl( sprintf( 'js/admin/role-builder%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-chart' => [
                'url'           => assetsUrl( 'js/Chartjs/chart.min.js' ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-modal' => [
                'url'           => assetsUrl( sprintf( 'js/smliser-modal%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-json-editor' => [
                'url'           => assetsUrl( sprintf( 'js/admin/json-editor%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-datetime-picker' => [
                'url'           => assetsUrl( sprintf( 'js/smliser-datetime-picker%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-email-editor' => [
                'url'           => assetsUrl( sprintf( 'js/admin/email-editor%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-cache-stats' => [
                'url'           => assetsUrl( sprintf( 'js/admin/cache-stats%s.js', $suffix ) ),
                'dependencies'  => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-jquery' => [
                'url'           => assetsUrl( sprintf( 'js/jQuery/jQuery%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'smliser-client-dashboard' => [
                'url'           => assetsUrl( sprintf( 'js/client-dashboard%s.js', $suffix ) ),
                'dependencies'  => ['smliser-script', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'smliser-client-auth' => [
                'url'           => assetsUrl( sprintf( 'js/smliser-client-auth%s.js', $suffix ) ),
                'dependencies'  => ['smliser-client-dashboard', 'smliser-modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'smliser-toast' => [
                'url'           => assetsUrl( sprintf( 'js/smliser-toast%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
        ];
    }
}