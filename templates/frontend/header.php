<?php
/**
 * Client Dashboard Header Template
 *
 * Header layout for the client-facing dashboard.
 * Renders the html head, body and layout opening tags.
 *
 * Expected variables (extracted by TemplateLocator):
 *
 *
 * @var string $rest_base
 *     Full REST base URL for dashboard content requests.
 *     e.g. https://example.com/wp-json/smliser/v1/dashboard/
 *
 * @var string $active_slug
 *     The slug of the initially active menu section.
 * @var \SmartLicenseServer\Security\Context\Principal|null $principal
 * @var array $styles
 */

use SmartLicenseServer\Assets\AssetsManager;

defined( 'SMLISER_ABSPATH' ) || exit;

/*
|------------------
| DEFAULTS
|------------------
*/
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html( $repo_name ); ?> — Dashboard</title>

    <?php AssetsManager::print_styles( ...$styles ); ?>

    <meta name="smliser-rest-base" content="<?php echo esc_attr( $rest_base ); ?>">
    <meta name="smliser-active-slug" content="<?php echo esc_attr( $active_slug ); ?>">

</head>
<body class="smlcd-body">

<div class="smlcd-layout<?php echo $collapsed ? ' smlcd-layout--collapsed' : '' ?>" id="smlcd-layout" data-theme="<?php echo esc_attr( $theme ); ?>">