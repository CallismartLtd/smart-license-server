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

		<main class="dashboard-content" id="dashboard-content">
			<?php $callback( $request ) ?>
		</main>

	</div>