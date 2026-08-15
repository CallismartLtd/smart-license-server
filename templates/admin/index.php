<?php
/**
 * Admin Dashboard Shell
 *
 * Pure orchestrator. Resolves the principal once and passes it
 * explicitly to every partial. Contains no HTML of its own.
 * 
 * @var \SmartLicenseServer\Admin\AdminDashboardRegistry|\SmartLicenseServer\ClientDashboard\AuthTemplateRegistry $registry
 * @var SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Templates\TemplateLocator $this
 */

use SmartLicenseServer\Admin\Page\Shell;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ROOT' ) || exit;

$menus  = $registry->all();

// if ( Guard::has_principal() ) {
    $default_dashboard_key  = 'overview';
// } else {
//     $default_dashboard_key  = 'login';
// }

/*
|--------------------------------------------------
| RESOLVE PRINCIPAL & USER PREFERENCES
|--------------------------------------------------
*/
$principal = Guard::get_principal();

$theme    = ''; // 'dark';
$collapsed = false;

if ( $principal ) {
    $settings   = UserSettings::for( $principal->get_actor() );
    $theme      = (string) $settings->get( UserSettings::DASHBOARD_THEME_NAME, $theme );
    $collapsed  = (bool) $settings->get( UserSettings::DASHBOARD_SIDEBAR_COLLAPSED_NAME, $collapsed );
}

$current_slug   = $request->route_param( 'tab', $default_dashboard_key );
$current_menu   = $registry->get( $current_slug );

// Guarenteed current menu for logged in users.
if ( null === $current_menu ) { // @TODO add && Guard::has_principal().
    $current_menu = $registry->get( 'overview' );
}

/*
|--------------------------------------------------
| 1. HEADER
|    Auth guard, <head>, <body>
|--------------------------------------------------
*/
$this->render( Shell::HEADER_TEMPLATE, [
    'title'     => $current_menu ? $current_menu['title'] : SMLISER_APP_NAME,
    'theme'     => $theme,
    'collapsed'  => $collapsed
]);

/*
|--------------------------------------------------
| 2. MENU
|    <nav class="dashboard-left-menu" ... </nav>
|    Only rendered if authenticated.
|--------------------------------------------------
*/
$submenu_slug       = $request->route_param( 'submenu' );
$current_submenu    = null;

if ( $submenu_slug ) {
    $current_submenu    = $registry->get_submenu_by_slug( $current_slug, $submenu_slug );
}

// if ( Guard::has_principal() ) { // still developing...
    $this->render( Shell::MENU_TEMPLATE, [
        'registry'          => $registry,
        'current_menu'      => $current_menu,
        'current_submenu'   => $current_submenu
    ]);
// }

/*
|--------------------------------------------------
| 3. CONTENT
|    <div class="dashboard-main"> ... </div>
|    OR login form (frontend.auth.login)
|--------------------------------------------------
*/
// $content_template = $principal
//     ? Shell::CONTENT_TEMPLATE
//     : Shell::AUTH_INDEX_TEMPLATE;

$content_template   = Shell::CONTENT_TEMPLATE;

if ( $current_submenu ) {
    $callback    = $current_submenu['callback'];
} else {
    $callback   = $current_menu['handler']::index_page_handler();
}
$this->render( $content_template, [
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