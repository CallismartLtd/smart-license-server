<?php
/**
 * Client Dashboard Header Template
 *
 * Header layout for the client-facing dashboard.
 * Renders the html head, body and layout opening tags.
 *
 * Expected variables (extracted by TemplateLocator):
 *
 * @var array<string, array{title: string, slug: string, handler: callable, icon: string}> $menu
 *     Ordered menu items from ClientDashboardRegistry.
 *
 * @var string $rest_base
 *     Full REST base URL for dashboard content requests.
 *     e.g. https://example.com/wp-json/smliser/v1/dashboard/
 *
 * @var string $active_slug
 *     The slug of the initially active menu section.
 */

use SmartLicenseServer\Assets\AssetsManager;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;

defined( 'SMLISER_ABSPATH' ) || exit;

/*
|------------------
| AUTH GUARD
|------------------
|
| Verify the principal is set before rendering anything.
| Guard::get_principal() returns null when no authenticated
| session exists for this request.
|
*/
$principal = Guard::get_principal();

if ( ! $principal ) {
    $login_url = smliser_resolve_template( 'auth.login' )
        ? url( 'auth/login' )
        : url( '' );

    header( 'Location: ' . esc_url( $login_url ) );
    exit;
}

/*
|------------------
| DEFAULTS
|------------------
*/
$menu           = $menu ?? [];
$rest_base      = $rest_base   ?? '';
$active_slug    = $active_slug ?? array_key_first( $menu ) ?? '';
$app_name       = defined( 'SMLISER_APP_NAME' ) ? SMLISER_APP_NAME : 'Dashboard';
$settings       = UserSettings::for( $principal->get_actor() );
$theme          = $settings->get( 'theme' );
$collapsed      = (bool) $settings->get( 'sidebar_collapsed' );

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $app_name ); ?> — Dashboard</title>

    <?php AssetsManager::print_styles( 'smliser-client-dashboard' ); ?>

    <meta name="smliser-rest-base" content="<?php echo esc_attr( $rest_base ); ?>">
    <meta name="smliser-active-slug" content="<?php echo esc_attr( $active_slug ); ?>">

</head>
<body class="smlcd-body">

<div class="smlcd-layout<?php echo $collapsed ? ' smlcd-layout--collapsed' : '' ?>" id="smlcd-layout" data-theme="<?php echo esc_attr( $theme ); ?>">