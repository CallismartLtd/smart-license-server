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
                'src'    => SetUp::assets_url( sprintf( 'js/main-script%s.js', $suffix ) ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-apps-uploader' => [
                'src'    => SetUp::assets_url( sprintf( 'js/apps-uploader%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'select2' => [
                'src'    => SetUp::assets_url( sprintf( 'js/Select2/select2%s.js', $suffix ) ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-tinymce' => [
                'src'    => SetUp::assets_url( 'js/tinymce/tinymce.min.js' ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-admin-repository' => [
                'src'    => SetUp::assets_url( sprintf( 'js/admin-repository%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-role-builder' => [
                'src'    => SetUp::assets_url( sprintf( 'js/role-builder%s.js', $suffix ) ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-chart' => [
                'src'    => SetUp::assets_url( 'js/Chartjs/chart.min.js' ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-modal' => [
                'src'    => SetUp::assets_url( sprintf( 'js/smliser-modal%s.js', $suffix ) ),
                'deps'   => ['jquery'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-json-editor' => [
                'src'    => SetUp::assets_url( sprintf( 'js/smliser-json-editor%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-datetime-picker' => [
                'src'    => SetUp::assets_url( sprintf( 'js/smliser-datetime-picker%s.js', $suffix ) ),
                'deps'   => [],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-email-editor' => [
                'src'    => SetUp::assets_url( sprintf( 'js/email-editor%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-cache-stats' => [
                'src'    => SetUp::assets_url( sprintf( 'js/cache-stats%s.js', $suffix ) ),
                'deps'   => ['jquery', 'smliser-script', 'smliser-modal'],
                'ver'    => SMLISER_VER,
                'footer' => true
            ],
            'smliser-jquery' => [
                'src'    => SetUp::assets_url( sprintf( 'js/jQuery/jQuery%s.js', $suffix ) ),
                'deps'   => [],
                'ver'    => SMLISER_VER,
                'footer' => true
            ]
        ];
    }
}