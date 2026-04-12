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
 */

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ABSPATH' ) || exit;

/*
|--------------------------------------------------
| VARIABLES & DEFAULTS
|--------------------------------------------------
*/
$menu        = $menu        ?? [];
$rest_base   = $rest_base   ?? '';
$active_slug = $active_slug ?? array_key_first( $menu ) ?? '';
$repo_name   = smliser_settings()->get( 'smliser_repository_name' ) ?? 'Dashboard';

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
    $styles[]       = 'smliser-login';
    $scripts[]      = 'smliser-login';
    $active_slug    = '';
}

/*
|--------------------------------------------------
| 1. HEADER
|    Auth guard, <head>, <body class="smlcd-body">,
|    opens <div class="smlcd-layout" id="smlcd-layout">
|--------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::HEADER_TEMPLATE, [
    'menu'        => $menu,
    'rest_base'   => $rest_base,
    'active_slug' => $active_slug,
    'principal'   => $principal,
    'styles'      => $styles,
    'repo_name'   => $repo_name,
    'theme'       => $theme,
    'collapsed'   => $collapsed,
] );

/*
|--------------------------------------------------
| 2. MENU
|    <aside class="smlcd-sidebar"> ... </aside>
|    Only rendered if authenticated.
|--------------------------------------------------
*/
if ( $principal ) {
    smliser_render_template( ClientDashboardRenderer::MENU_TEMPLATE, [
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

smliser_render_template( $content_template, [
    'principal'   => $principal,
    'rest_base'   => $rest_base,
    'active_slug' => $active_slug,
    'repo_name'   => $repo_name,
] );

/*
|--------------------------------------------------
| 4. FOOTER
|    Closes layout, prints scripts, closes HTML
|--------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::FOOTER_TEMPLATE, [
    'scripts' => $scripts,
] );