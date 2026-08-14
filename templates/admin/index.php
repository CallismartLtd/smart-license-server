<?php
/**
 * Admin Dashboard Shell
 *
 * Pure orchestrator. Resolves the principal once and passes it
 * explicitly to every partial. Contains no HTML of its own.
 * 
 * @var \SmartLicenseServer\Admin\AdminDashboardRegistry|\SmartLicenseServer\ClientDashboard\AuthTemplateRegistry $menu_registry
 * @var SmartLicenseServer\Core\Request $request
 * @var \SmartLicenseServer\Templates\TemplateLocator $this
 */

use SmartLicenseServer\Admin\Page\Shell;
use SmartLicenseServer\Security\Context\Guard;

defined( 'SMLISER_ROOT' ) || exit;

$menus  = $menu_registry->all();

if ( Guard::has_principal() ) {
    $default_dashboard_key  = 'overview';
} else {
    $default_dashboard_key  = 'login';
}

$current_menu   = $menu_registry->get( $request->route_param( 'tab', $default_dashboard_key ) );

/*
|--------------------------------------------------
| RESOLVE PRINCIPAL & USER PREFERENCES
|--------------------------------------------------
*/
$principal = Guard::get_principal();

$theme    = 'dark';
$collapsed = false;


/*
|--------------------------------------------------
| 1. HEADER
|    Auth guard, <head>, <body>
|--------------------------------------------------
*/
$this->render( Shell::HEADER_TEMPLATE, [
    'title' => $current_menu ? $current_menu['title'] : SMLISER_APP_NAME
]);

/*
|--------------------------------------------------
| 2. MENU
|    <aside class="smlcd-sidebar"> ... </aside>
|    Only rendered if authenticated.
|--------------------------------------------------
*/
// if ( Guard::has_principal() ) {
//     $this->render( Shell::MENU_TEMPLATE, [

//     ]);
// }

/*
|--------------------------------------------------
| 3. CONTENT
|    <main class="smlcd-main"> ... </main>
|    OR login form (frontend.auth.login)
|--------------------------------------------------
*/
// $content_template = $principal
//     ? Shell::CONTENT_TEMPLATE
//     : Shell::AUTH_INDEX_TEMPLATE;

$content_template   = Shell::CONTENT_TEMPLATE;

$this->render( $content_template, [
    'principal'   => $principal,
    // 'rest_base'   => $rest_base,
    // 'active_slug' => $active_slug,
    // 'repo_name'   => $repo_name,
] );

/*
|--------------------------------------------------
| 4. FOOTER
|    Closes layout, prints scripts, closes HTML
|--------------------------------------------------
*/
$this->render( Shell::FOOTER_TEMPLATE, [
    // 'scripts' => $scripts,
] );


// dd( $current_menu );