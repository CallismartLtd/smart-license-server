<?php
/**
 * Dashboard Content.
 *
 * @package Dashboard
 * 
 * @var \SmartLicenseServer\Security\Actors\User $principal
 * @var callable( \SmartLicenseServer\Core\Request ) : void $callback
 * @var \SmartLicenseServer\Core\Request $request
 */

?>

	<div class="dashboard-overlay" id="dashboard-overlay"></div>

	<div class="dashboard-main">

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

		<main class="dashboard-content" id="dashboard-content">
			<?php $callback( $request ) ?>
		</main>

	</div>