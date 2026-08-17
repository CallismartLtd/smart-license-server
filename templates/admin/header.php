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

	<?php AssetsManager::instance()->print_js_constants(); ?>
	<?php AssetsManager::instance()->print_group( AssetsManager::GROUP_ADMIN_DASHBOARD ); ?>

</head>
<body>

    <div class="dashboard-wrapper<?php echo $collapsed ? ' is-collapsed' : '' ?>" id="dashboard-wrapper">
		<header class="dashboard-top-menu">
			<div class="dashboard-top-menu-left">
				<button type="button" class="dashboard-menu-toggle" id="dashboard-menu-toggle" aria-label="Collapse menu" aria-controls="dashboard-wrapper">
					<span></span><span></span><span></span>
				</button>
				<button type="button" class="dashboard-mobile-menu-toggle" id="dashboard-mobile-menu-toggle" aria-label="Open menu" aria-controls="dashboard-wrapper">
					<span></span><span></span><span></span>
				</button>
			</div>

			<div class="dashboard-top-menu-right">
				<button type="button" class="dashboard-theme-toggle" id="dashboard-theme-toggle" aria-label="Toggle theme">
					<span class="dashboard-theme-icon dashboard-theme-icon-light" aria-hidden="true">&#9728;&#65039;</span>
					<span class="dashboard-theme-icon dashboard-theme-icon-dark" aria-hidden="true">&#127769;</span>
				</button>

				Add notifications, user menu, or other app-specific top menu items here.
			</div>
		</header>