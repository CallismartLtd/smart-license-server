<?php
/**
 * Admin Dashboard Shell
 *
 * Pure orchestrator. Resolves the principal once and passes it
 * explicitly to every partial. Contains no HTML of its own.
 * 
 * @var \SmartLicenseServer\Admin\AdminDashboardRegistry $registry
 * @var SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Templates\TemplateLocator $this
 * @var \SmartLicenseServer\Security\Context\Guard $guard
 * @var \SmartLicenseServer\Assets\AssetsManager $assets_manager
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 */

use SmartLicenseServer\Admin\Page\Shell;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ROOT' ) || exit;
$principal = $guard->get_principal();

if ( ! $principal ) {
    smliser_abort_request(
        'Access Denied. You must be logged into a valid administrator account to view this page.',
        '🛑 401 - Authentication Required',
        [
            'status'    => 401,
            'link_url'  => url( 'login/' )->add_query_param( 'redirect_url', smliser_get_current_url()->url() )->url(),
            'link_text' => 'Login',
        ]    
    );
}

if ( ! $principal->is( 'system_admin' ) ) {
    smliser_abort_request(
        'Access Denied. You must be logged into a valid administrator account to view this page.[Log In Again][Return to Homepage]',
        '🚫 403 - Access Denied',
        [
            'status' => 403,
            'back_link' => true
        ]
    );
}

/*
|--------------------------------------------------
| RESOLVE USER PREFERENCES
|--------------------------------------------------
*/

$settings   = UserSettings::for( $principal->get_actor() );
$theme      = (string) $settings->get( UserSettings::DASHBOARD_THEME_NAME, 'auto' );
$collapsed  = (bool) $settings->get( UserSettings::DASHBOARD_SIDEBAR_COLLAPSED_NAME, false );


$current_slug   = $request->route_param( 'page', 'overview' );
$current_menu   = $registry->get( $current_slug );

/*
|--------------------------------------------------
| 1. HEADER
|    <head>, <body>, 
|       <div class="dashboard-wrapper>,
|           <header class="dashboard-top-menu" ... </header>
|--------------------------------------------------
*/
$this->render( Shell::HEADER_TEMPLATE, [
    'title'             => $current_menu ? $current_menu['title'] : SMLISER_APP_NAME,
    'theme'             => $theme,
    'collapsed'         => $collapsed,
    'registry'          => $registry,
    'assets_manager'    => $assets_manager
]);

/*
|--------------------------------------------------
| 2. MENU
|    <nav class="dashboard-left-menu" ... </nav>
|--------------------------------------------------
*/
$submenu_slug       = $request->route_param( 'tab' );
$current_submenu    = null;

if ( $submenu_slug ) {
    $current_submenu    = $registry->get_submenu_by_slug( $current_slug, $submenu_slug );
}

$this->render( Shell::MENU_TEMPLATE, [
    'registry'          => $registry,
    'current_menu'      => $current_menu,
    'current_submenu'   => $current_submenu,
    'urlmanager'        => $urlmanager
]);

/*
|--------------------------------------------------
| 3. CONTENT
|    <div class="dashboard-main"> ... </div>
|--------------------------------------------------
*/

if ( $current_submenu ) {
    $callback    = $current_submenu['callback'];
} else {
    $callback   = $current_menu['handler']?->index_page_handler() ?? null;
}

if ( ! $callback ) {
    $callback   = function() {
        echo smliser_not_found_container( 'No registered handler for this page.' );
    };
}

$this->render( Shell::CONTENT_TEMPLATE, [
    'principal' => $principal,
    'callback'  => $callback,
    'request'   => $request
]);

/*
|--------------------------------------------------
| 4. FOOTER
|    Closes layout, prints scripts, closes HTML
|--------------------------------------------------
*/
$this->render( Shell::FOOTER_TEMPLATE, [] );