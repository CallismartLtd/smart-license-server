<?php
/**
 * Authentication index file.
 * 
 * Pure orchestrator.
 *
 * @var array<string, array{title: string, slug: string, handler: callable, icon: string}> $menu
 * @var string $rest_base
 * @var string $active_slug
 * @var \SmartLicenseServer\Templates\TemplateLocator $this
 * @var \SmartLicenseServer\Security\Context\Guard $guard
 * @var \SmartLicenseServer\Core\Request $request
 */

use SmartLicenseServer\ClientDashboard\ClientDashboardRenderer;
use SmartLicenseServer\ClientDashboard\TemplateHandlers\AuthForms;

defined( 'SMLISER_ROOT' ) || exit;

/*
|--------------------------------------------------
| VARIABLES & DEFAULTS
|--------------------------------------------------
*/
$rest_base      = $rest_base   ?? '';
$active_slug    = $active_slug ?? array_key_first( $menu ) ?? '';
$repo_name      = (string) smliser_settings()->get( 'smliser_repository_name', SMLISER_APP_NAME );

/*
|--------------------------------------------------
| RESOLVE PRINCIPAL & USER PREFERENCES
|--------------------------------------------------
*/

$theme    = 'dark';
$collapsed = false;

/*
|--------------------------------------------------
| DYNAMIC ASSET LOADING
|--------------------------------------------------
*/
$styles  = [ 'smliser-client-dashboard' ];
$scripts = [ 'smliser-client-dashboard' ];

$styles     = ['smliser-client-auth', 'smliser-client-dashboard'];
$scripts    = ['smliser-client-auth'];

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

$template   = authTemplateRegistry();

$this->render( ClientDashboardRenderer::HEADER_TEMPLATE, [
    'menu'          => $menu,
    'rest_base'     => $rest_base,
    'active_slug'   => $active_slug,
    'styles'        => $styles,
    'title'         => $title,
    'repo_name'     => $repo_name,
    'theme'         => $theme,
    'collapsed'     => $collapsed,
    'allowed_slugs' => $template->slugs()
] );

/*
|--------------------------------------------------
| 2. CONTENT
|    <div class="smlag-container">... </div>
|--------------------------------------------------
*/

$this->render( AuthForms::INDEX_CONTENT_TEMPLATE, [
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