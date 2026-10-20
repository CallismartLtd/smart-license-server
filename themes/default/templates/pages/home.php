<?php
/**
 * The home page.
 *
 * @author Callistus Nwachukwu
 * @var string $app_version The running app version.
 * @var string $php_version The running PHP version.
 * @var SmartLicenseServer\Environments\Application\Theming\View $view
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$principal = $this->principal();
$actions   = [ [ 'label' => 'API documentation', 'url' => $this->url( 'documentation' )->url(), 'accent' => true ] ];

if ( null === $principal ) {
	$actions[] = [ 'label' => 'Sign in', 'url' => $this->urls()->login_url()->url() ];
} else {
	$actions[] = $principal->is( 'system_admin' )
		? [ 'label' => 'Admin', 'url' => $this->urls()->admin_url()->url() ]
		: [ 'label' => 'Dashboard', 'url' => $this->urls()->client_dashboard_url()->url() ];
}

$this->partial(
	'status',
	[
		'diagram' => 'connected',
		'heading' => SMLISER_APP_NAME,
		'message' => $view->get_description(),
		'actions' => $actions,
		'detail'  => sprintf( 'v%s · PHP %s', $app_version, $php_version ),
	]
);
?>
<div class="cells">
	<div class="cell">
		<h3>Command line</h3>
		<p>Manage applications, licenses and the installation from the terminal.</p>
		<code>smliser status</code>
	</div>

	<div class="cell">
		<h3>Any database</h3>
		<p>MySQL, MariaDB, PostgreSQL and SQLite through one abstraction layer.</p>
		<code>callismart/dbprism</code>
	</div>

	<div class="cell">
		<h3>REST API</h3>
		<p>Activate licenses, check updates and download releases from your software.</p>
		<code>/smliser/v1/</code>
	</div>
</div>