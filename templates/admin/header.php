<?php
/**
 * Dashboard Header.
 *
 * Renders the HTML document opening, <head> section (title, meta tags,
 * styles, scripts placeholders) and opens the <body> tag.
 *
 * Define DASHBOARD_PAGE_TITLE before including this file to set a custom
 * page title. Falls back to a default when not set.
 *
 * @package Dashboard
 * @var string $title
 * @var string $theme
 * @var bool $collapsed
 */

use SmartLicenseServer\Assets\AssetsManager;

define( 'SCRIPT_DEBUG', TRUE );
?>
<!DOCTYPE html>
<html lang="en"<?php echo $theme ? ' data-theme="' . escAttr( $theme ) . '"' : ''; ?>>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo htmlspecialchars( $title ?? 'Dashboard', ENT_QUOTES, 'UTF-8' ); ?></title>

	

	<?php AssetsManager::instance()->print_group( AssetsManager::GROUP_ADMIN_DASHBOARD ); ?>

</head>
<body>
    <div class="dashboard-wrapper<?php echo $collapsed ? ' is-collapsed' : '' ?>" id="dashboard-wrapper">