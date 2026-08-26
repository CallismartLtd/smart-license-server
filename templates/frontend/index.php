<?php
/**
 * Client Dashboard Shell
 *
 * Pure orchestrator. Resolves the principal once and passes it
 * explicitly to every partial. Contains no HTML of its own.
 *
 * Render order:
 *   1. frontend.header        — <head>, <body>, opens .smlcd-layout
 *   2. frontend.menu          — left sidebar (conditional, only if authenticated)
 *   3. frontend.content OR
 *      frontend.auth.login    — <main> with topbar or login form
 *   4. frontend.footer        — closes layout, prints scripts, </body>, </html>
 *
 * @var array<string, array{title: string, slug: string, handler: callable, icon: string}> $menu
 * @var string $rest_base
 * @var string $active_slug
 * @var \SmartLicenseServer\Templates\TemplateLocator $this
 * @var \SmartLicenseServer\Security\Context\Guard $guard
 * @var \SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ROOT' ) || exit;

/*
|--------------------------------------------------
| VARIABLES & DEFAULTS
|--------------------------------------------------
*/
$menu        = $menu        ?? [];
$rest_base   = $rest_base   ?? '';
$active_slug = $active_slug ?? array_key_first( $menu ) ?? '';
$repo_name   = (string) smliser_settings()->get( 'smliser_repository_name', SMLISER_APP_NAME );

/*
|--------------------------------------------------
| RESOLVE PRINCIPAL & USER PREFERENCES
|--------------------------------------------------
*/
$principal = $guard->get_principal();

$theme    = 'dark';
$collapsed = false;

/*
|--------------------------------------------------
| DYNAMIC ASSET LOADING
|--------------------------------------------------
*/
$styles  = [ 'smliser-client-dashboard' ];
$scripts = [ 'smliser-client-dashboard' ];

if ( $principal ) {
    $settings  = UserSettings::for( $principal->get_actor() );
    $theme     = (string) $settings->get( 'theme', 'dark' );
    $collapsed = (bool) $settings->get( 'sidebar_collapsed', false );
} else {
    $styles     = ['smliser-client-auth', 'smliser-client-dashboard'];
    $scripts    = ['smliser-client-auth'];
}

/*
|--------------------------------------------------
| 1. HEADER
|    Auth guard, <head>, <body class="smlcd-body">,
|    opens <div class="smlcd-layout" id="smlcd-layout">
|--------------------------------------------------
*/
$title  = $repo_name;
$title  = ! empty( $menu )
    ? sprintf( '%s — %s', $menu[$active_slug]['title'], $title ) ?? $title
    : $title;

$template   = $guard->has_principal() ? clientDashboardRegistry() : authTemplateRegistry();

$this->render( ClientDashboardRenderer::HEADER_TEMPLATE, [
    'menu'          => $menu,
    'rest_base'     => $rest_base,
    'active_slug'   => $active_slug,
    'principal'     => $principal,
    'styles'        => $styles,
    'title'         => $title,
    'repo_name'     => $repo_name,
    'theme'         => $theme,
    'collapsed'     => $collapsed,
    'allowed_slugs' => $template->slugs()
] );

/*
|--------------------------------------------------
| 2. MENU
|    <aside class="smlcd-sidebar"> ... </aside>
|    Only rendered if authenticated.
|--------------------------------------------------
*/
if ( $principal ) {
    $this->render( ClientDashboardRenderer::MENU_TEMPLATE, [
        'menu'        => $menu,
        'active_slug' => $active_slug,
        'principal'   => $principal,
        'repo_name'   => $repo_name,
    ] );
}

/*
|--------------------------------------------------
| 3. CONTENT
|    <main class="smlcd-main"> ... </main>
|    OR login form (frontend.auth.login)
|--------------------------------------------------
*/
$content_template = $principal
    ? ClientDashboardRenderer::CONTENT_TEMPLATE
    : ClientDashboardRenderer::AUTH_INDEX_TEMPLATE;

$this->render( $content_template, [
    'principal'     => $principal,
    'rest_base'     => $rest_base,
    'active_slug'   => $active_slug,
    'repo_name'     => $repo_name,
    'request'       => $request
] );

/*
|--------------------------------------------------
| 4. FOOTER
|    Closes layout, prints scripts, closes HTML
|--------------------------------------------------
*/
$this->render( ClientDashboardRenderer::FOOTER_TEMPLATE, [
    'scripts' => $scripts,
] );