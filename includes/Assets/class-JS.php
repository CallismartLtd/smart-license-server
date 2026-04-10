<?php
/**
 * JavaScript assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use SmartLicenseServer\Environments\WordPress\SetUp;

final class JS {

    /**
     * Get all JS files and their dependencies.
     * 
     * @param string $suffix Script suffix (minified or not)
     * @return array<string, array<string, mixed>>
     */
    public static function all( string $suffix = '' ) : array {
        return [
            'smliser-script' => [
                'src'    => assetsUrl( sprintf( 'js/main-script%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery', 'select2', 'smliser-datetime-picker', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-apps-uploader' => [
                'src'    => assetsUrl( sprintf( 'js/apps-uploader%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery', 'smliser-script'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'select2' => [
                'src'    => assetsUrl( sprintf( 'js/Select2/select2%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-tinymce' => [
                'src'    => assetsUrl( 'js/tinymce/tinymce.min.js' ),
                'deps'   => ['smliser-jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-admin-repository' => [
                'src'    => assetsUrl( sprintf( 'js/admin-repository%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-role-builder' => [
                'src'    => assetsUrl( sprintf( 'js/role-builder%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-chart' => [
                'src'    => assetsUrl( 'js/Chartjs/chart.min.js' ),
                'deps'   => ['smliser-jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-modal' => [
                'src'    => assetsUrl( sprintf( 'js/smliser-modal%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-json-editor' => [
                'src'    => assetsUrl( sprintf( 'js/smliser-json-editor%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-datetime-picker' => [
                'src'    => assetsUrl( sprintf( 'js/smliser-datetime-picker%s.js', $suffix ) ),
                'deps'   => [],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-email-editor' => [
                'src'    => assetsUrl( sprintf( 'js/email-editor%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-cache-stats' => [
                'src'    => assetsUrl( sprintf( 'js/cache-stats%s.js', $suffix ) ),
                'deps'   => ['smliser-jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-jquery' => [
                'src'    => assetsUrl( sprintf( 'js/jQuery/jQuery%s.js', $suffix ) ),
                'deps'   => [],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],

            'smliser-client-dashboard' => [
                'src'    => assetsUrl( sprintf( 'js/client-dashboard%s.js', $suffix ) ),
                'deps'   => ['smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
        ];
    }
}