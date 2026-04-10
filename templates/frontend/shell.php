<?php
/**
 * Client Dashboard Shell
 *
 * Pure orchestrator. Resolves the principal once and passes it
 * explicitly to every partial. Contains no HTML of its own.
 *
 * Render order:
 *   1. frontend.header  — auth guard, <head>, <body>, opens .smlcd-layout
 *   2. frontend.menu    — left sidebar inside .smlcd-layout
 *   3. frontend.content — <main>, topbar, content area, bootstrap JS
 *   4. frontend.footer  — closes .smlcd-layout, </body>, </html>
 *
 * @var array<string, array{title: string, slug: string, handler: callable, icon: string}> $menu
 * @var string $rest_base
 * @var string $active_slug
 */

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\Security\Context\Guard;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu        = $menu        ?? [];
$rest_base   = $rest_base   ?? '';
$active_slug = $active_slug ?? array_key_first( $menu ) ?? '';

// Resolve once — all partials share this instance.
// header.php will redirect and exit if null.
$principal = Guard::get_principal();

/*
|-------------------------------------------------------------
| 1. HEADER
|    Auth guard, <head>, <body class="smlcd-body">,
|    opens <div class="smlcd-layout" id="smlcd-layout">
|-------------------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::HEADER_TEMPLATE, [
    'menu'        => $menu,
    'rest_base'   => $rest_base,
    'active_slug' => $active_slug,
    'principal'   => $principal,
] );

/*
|-------------------------------------------------------------
| 2. MENU
|    <aside class="smlcd-sidebar"> ... </aside>
|    Rendered inside .smlcd-layout opened by header.
|-------------------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::MENU_TEMPLATE, [
    'menu'        => $menu,
    'active_slug' => $active_slug,
    'principal'   => $principal,
] );

/*
|-------------------------------------------------------------
| 3. CONTENT
|    <main class="smlcd-main"> topbar + content area </main>
|    + inline bootstrap <script>
|-------------------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::CONTENT_TEMPLATE, [
    'principal'   => $principal,
    'rest_base'   => $rest_base,
    'active_slug' => $active_slug,
] );

/*
|-------------------------------------------------------------
| 4. FOOTER
|    Closes </div><!-- .smlcd-layout -->, </body>, </html>
|-------------------------------------------------------------
*/
smliser_render_template( ClientDashboardRenderer::FOOTER_TEMPLATE );