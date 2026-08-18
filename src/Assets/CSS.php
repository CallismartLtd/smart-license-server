<?php
/**
 * CSS assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use function assetsUrl;

/**
 * CSS asset registry.
 */
final class CSS {

    /**
     * Get all registered CSS assets and their dependencies.
     *
     * @param string $suffix Optional CSS filename suffix, e.g. ".min".
     * @return array<string, array{
     *     url: \SmartLicenseServer\Core\URL,
     *     dependencies: string[],
     *     version: string,
     *     media-type: string
     * }>
     */
    public static function all( string $suffix = '' ) : array {
        return [
            'smliser-variables' => [
                'url'   => assetsUrl( sprintf( 'css/variables%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'smliser-admin-styles'  => [
                'url'   => assetsUrl( sprintf( 'css/admin/dashboard%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables', 'smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-styles'    => [
                'url'   => assetsUrl( sprintf( 'css/smliser-styles%s.css', $suffix ) ),
                'dependencies'  => [
                    'smliser-variables',
                    'smliser-toast',
                    'smliser-modal',
                    'smliser-datetime-picker',
                    'smliser-role-builder',
                    'smliser-apps-uploader'
                ],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-apps-uploader' => [
                'url'   => assetsUrl( sprintf( 'css/admin/apps-uploader%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-form-styles' => [
                'url'   => assetsUrl( sprintf( 'css/smliser-forms%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'select2' => [
                'url'   => assetsUrl( sprintf( 'css/select2%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-tabler-icons' => [
                'url'   => assetsUrl( sprintf( 'icons/tabler-icons%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-role-builder' => [
                'url'   => assetsUrl( sprintf( 'css/admin/role-builder%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-modal' => [
                'url'   => assetsUrl( sprintf( 'css/smliser-modal%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-json-editor' => [
                'url'   => assetsUrl( sprintf( 'css/admin/json-editor%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-datetime-picker' => [
                'url'   => assetsUrl( sprintf( 'css/smliser-datetime-picker%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-email-editor' => [
                'url'   => assetsUrl( sprintf( 'css/admin/email-editor%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-cache-stats' => [
                'url'   => assetsUrl( sprintf( 'css/admin/cache-stats%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-client-dashboard' => [
                'url'   => assetsUrl( sprintf( 'css/client-dashboard%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables', 'smliser-modal', 'smliser-tabler-icons', 'smliser-utils', 'select2'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-utils' => [
                'url'   => assetsUrl( sprintf( 'css/utils%s.css', $suffix ) ),
                'dependencies'  => ['smliser-modal', 'smliser-tabler-icons'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-client-auth' => [
                'url'   => assetsUrl( sprintf( 'css/smliser-client-auth%s.css', $suffix ) ),
                'dependencies'  => ['smliser-client-dashboard', 'smliser-modal'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-toast' => [
                'url'   => assetsUrl( sprintf( 'css/smliser-toast%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ]
        ];
    }
}