<?php
/**
 * CSS assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use SmartLicenseServer\Core\URLManager;

/**
 * CSS asset registry.
 */
final class CSS {

    public function __construct( protected URLManager $urlmanager ) {}

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
    public function all( string $suffix = '' ) : array {
        return [
            'smliser-variables' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/variables%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'smliser-admin-styles'  => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/dashboard%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables', 'smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-styles'    => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-styles%s.css', $suffix ) ),
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
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/apps-uploader%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-form-styles' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-forms%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'select2' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/select2%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-tabler-icons' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'icons/tabler-icons%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-role-builder' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/role-builder%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-modal' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-modal%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-json-editor' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/json-editor%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-datetime-picker' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-datetime-picker%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-email-editor' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/email-editor%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-cache-stats' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/cache-stats%s.css', $suffix ) ),
                'dependencies'  => ['smliser-styles'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-client-dashboard' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/client-dashboard%s.css', $suffix ) ),
                'dependencies'  => ['smliser-variables', 'smliser-modal', 'smliser-tabler-icons', 'smliser-utils', 'select2'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-utils' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/utils%s.css', $suffix ) ),
                'dependencies'  => ['smliser-modal', 'smliser-tabler-icons'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-client-auth' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-client-auth%s.css', $suffix ) ),
                'dependencies'  => ['smliser-modal'],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ],
            'smliser-toast' => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/smliser-toast%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'   => SMLISER_VER,
                'media-type' => 'all'
            ]
        ];
    }
}