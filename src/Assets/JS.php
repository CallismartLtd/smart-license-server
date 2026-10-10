<?php
/**
 * JavaScript assets registry.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Assets
 */

namespace SmartLicenseServer\Assets;

use SmartLicenseServer\Core\URLManager;
use function sprintf;

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
     *     footer: bool,
     *     category?: string
     * }>
     */
    public function all( string $suffix = '' ) : array {
        return [
            'gobals'    => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/globals%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => false,
            ],
            'theme' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/theme%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => false,
                'category'      => AssetsManager::CATEGORY_ADMIN_DASHBOARD
            ],
            'string-utils'  => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/string-utils%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'admin-dashboard-scripts' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/dashboard%s.js', $suffix ) ),
                'dependencies'  => [
                    'core-admin',
                ],
                'version'       => SMLISER_VER,
                'footer'        => true,
                'category'      => AssetsManager::CATEGORY_ADMIN_DASHBOARD
            ],
            'core-admin' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/core%s.js', $suffix ) ), 
                'dependencies'  => [
                    'gobals', 'string-utils', 'jquery', 'select2', 'datetime-picker',
                    'modal','toast'
                ],
                'version'   => SMLISER_VER,
                'footer'    => true
            ],
            'apps-uploader' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/apps-uploader%s.js', $suffix ) ),
                'dependencies'  => ['jquery', 'core-admin', 'json-editor'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'select2' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/Select2/select2%s.js', $suffix ) ),
                'dependencies'  => ['jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'tinymce' => [
                'url'           => $this->urlmanager->assets_url( 'js/tinymce/tinymce.min.js' ),
                'dependencies'  => ['jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'admin-repository' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/admin-repository%s.js', $suffix ) ),
                'dependencies'  => ['jquery', 'core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'role-builder' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/role-builder%s.js', $suffix ) ),
                'dependencies'  => ['jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'chart' => [
                'url'           => $this->urlmanager->assets_url( 'js/Chartjs/chart.min.js' ),
                'dependencies'  => ['jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'modal' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/modal%s.js', $suffix ) ),
                'dependencies'  => ['jquery'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'json-editor' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/json-editor%s.js', $suffix ) ),
                'dependencies'  => ['jquery', 'core-admin', 'modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'datetime-picker' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/datetime-picker%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'email-editor' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/email-editor%s.js', $suffix ) ),
                'dependencies'  => ['jquery', 'core-admin', 'modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'cache-stats' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/cache-stats%s.js', $suffix ) ),
                'dependencies'  => ['jquery', 'core-admin', 'modal'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'jquery' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/jQuery/jQuery%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'client-dashboard' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/client-dashboard%s.js', $suffix ) ),
                'dependencies'  => ['core-admin', 'modal'],
                'version'       => SMLISER_VER,
                'footer'        => true,
                'category'      => AssetsManager::CATEGORY_CLIENT_DASHBOARD
            ],

            'client-auth' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/client-auth%s.js', $suffix ) ),
                'dependencies'  => ['modal', 'core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],
            'toast' => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/toast%s.js', $suffix ) ),
                'dependencies'  => [],
                'version'       => SMLISER_VER,
                'footer'        => true
            ],

            'site-health'   => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/site-health%s.js', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true,
            ],

            'updates'   => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/updates%s.js', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true,
            ],

            'background'   => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/background%s.js', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true,
            ],
            'support-report'   => [
                'url'           => $this->urlmanager->assets_url( sprintf( 'js/admin/support-report%s.js', $suffix ) ),
                'dependencies'  => ['core-admin'],
                'version'       => SMLISER_VER,
                'footer'        => true,
            ],
        ];
    }
}