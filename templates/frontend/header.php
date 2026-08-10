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
 *     e.g. https://example.com/smliser/v1/dashboard/
 *
 * @var string $active_slug
 * @var string $repo_name
 *     The slug of the initially active menu section.
 * @var \SmartLicenseServer\Security\Context\Principal|null $principal
 * @var array $styles
 * @var array $allowed_slugs
 */

use SmartLicenseServer\Assets\AssetsManager;

defined( 'SMLISER_ROOT' ) || exit;

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
    <title><?php echo escHtml( $repo_name ); ?> — Dashboard</title>

    <?php AssetsManager::print_styles( ...$styles ); ?>

    <meta name="smliser-rest-base" content="<?php echo escAttr( $rest_base ); ?>">
    <meta name="smliser-active-slug" content="<?php echo escAttr( $active_slug ); ?>">
    <meta name="smliser-allowed-slugs" content="<?php echo escAttr( implode( '|', $allowed_slugs ) ); ?>">
    <meta name="smliser-repo-name" content="<?php echo escAttr( $repo_name ); ?>">

</head>
<body class="smlcd-body">

<div class="smlcd-layout<?php echo $collapsed ? ' smlcd-layout--collapsed' : '' ?>" id="smlcd-layout" data-theme="<?php echo escAttr( $theme ); ?>">