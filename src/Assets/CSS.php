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
     *     media-type: string,
     *     category?: string
     * }>
     */
    public function all( string $suffix = '' ) : array {
        return [
            'variables' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/variables%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'view-transition' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/view-transition%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'admin-styles'  => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/dashboard%s.css', $suffix ) ),
                'dependencies'  => [
                    'variables', 'tabler-icons', 'core-admin', 'apps-uploader', 'cache-stats'
                ],
                'version'       => SMLISER_VER,
                'media-type'    => 'all',
                'category'      => AssetsManager::CATEGORY_ADMIN_DASHBOARD
            ],
            'core-admin'    => [
                'url'   => $this->urlmanager->assets_url( sprintf( 'css/admin/core%s.css', $suffix ) ),
                'dependencies'  => [
                    'variables',
                    'toast',
                    'modal',
                    'datetime-picker',
                ],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'apps-uploader'     => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/apps-uploader%s.css', $suffix ) ),
                'dependencies'  => ['json-editor'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'form-styles' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/forms%s.css', $suffix ) ),
                'dependencies'  => ['variables', 'select2'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all',
                'category'      => AssetsManager::CATEGORY_ADMIN_DASHBOARD
            ],
            'select2' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/select2%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'tabler-icons' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'icons/tabler-icons%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'role-builder' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/role-builder%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'modal' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/modal%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'json-editor' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/json-editor%s.css', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'datetime-picker' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/datetime-picker%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'email-editor'  => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/email-editor%s.css', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'cache-stats' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/admin/cache-stats%s.css', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'client-dashboard' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/client-dashboard%s.css', $suffix ) ),
                'dependencies'  => ['variables', 'modal', 'tabler-icons', 'select2'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all',
                'category'      => AssetsManager::CATEGORY_CLIENT_DASHBOARD
            ],
            'client-auth' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/client-auth%s.css', $suffix ) ),
                'dependencies'  => ['modal'],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ],
            'toast' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'css/toast%s.css', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'media-type'    => 'all'
            ]
        ];
    }
}