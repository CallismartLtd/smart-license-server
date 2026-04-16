<?php
/**
 * CSS assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

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
                'src'   => assetsUrl( sprintf( 'css/smliser-styles%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-apps-uploader' => [
                'src'   => assetsUrl( sprintf( 'css/apps-uploader%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-form-styles' => [
                'src'   => assetsUrl( sprintf( 'css/smliser-forms%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'select2' => [
                'src'   => assetsUrl( sprintf( 'css/select2%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-tabler-icons' => [
                'src'   => assetsUrl( sprintf( 'icons/tabler-icons%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-role-builder' => [
                'src'   => assetsUrl( sprintf( 'css/role-builder%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-modal' => [
                'src'   => assetsUrl( sprintf( 'css/smliser-modal%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-json-editor' => [
                'src'   => assetsUrl( sprintf( 'css/smliser-json-editor%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-datetime-picker' => [
                'src'   => assetsUrl( sprintf( 'css/smliser-datetime-picker%s.css', $suffix ) ),
                'deps'  => [],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-email-editor' => [
                'src'   => assetsUrl( sprintf( 'css/email-editor%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-cache-stats' => [
                'src'   => assetsUrl( sprintf( 'css/cache-stats%s.css', $suffix ) ),
                'deps'  => ['smliser-styles', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-client-dashboard' => [
                'src'   => assetsUrl( sprintf( 'css/client-dashboard%s.css', $suffix ) ),
                'deps'  => ['smliser-modal', 'smliser-tabler-icons', 'smliser-utils'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-utils' => [
                'src'   => assetsUrl( sprintf( 'css/utils%s.css', $suffix ) ),
                'deps'  => ['smliser-modal', 'smliser-tabler-icons'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ],
            'smliser-login' => [
                'src'   => assetsUrl( sprintf( 'css/login%s.css', $suffix ) ),
                'deps'  => ['smliser-client-dashboard', 'smliser-modal'],
                'ver'   => SMLISER_VER,
                'media' => 'all'
            ]
        ];
    }
}