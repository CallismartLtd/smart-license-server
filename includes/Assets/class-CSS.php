<?php
/**
 * CSS assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use SmartLicenseServer\Environments\WordPress\SetUp;

final class CSS {

    /**
     * Get all CSS files and their dependencies.
     * 
     * @param string $suffix Script suffix (minified or not)
     * @return array<string, array<string, mixed>>
     */
    public static function all( string $suffix = '' ) : array {
        return [
            'smliser-styles'    => [
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-styles%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-apps-uploader' => [
                'src'   => SetUp::assets_url( sprintf( 'css/apps-uploader%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-form-styles' => [
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-forms%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'select2' => [
                'src'   => SetUp::assets_url( sprintf( 'css/select2%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-tabler-icons' => [
                'src'   => SetUp::assets_url( sprintf( 'icons/tabler-icons%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-role-builder' => [
                'src'   => SetUp::assets_url( sprintf( 'css/role-builder%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-modal' => [
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-modal%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-json-editor' => [
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-json-editor%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-datetime-picker' => [
                'src'   => SetUp::assets_url( sprintf( 'css/smliser-datetime-picker%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-email-editor' => [
                'src'   => SetUp::assets_url( sprintf( 'css/email-editor%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-cache-stats' => [
                'src'   => SetUp::assets_url( sprintf( 'css/cache-stats%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ]
        ];
    }
}