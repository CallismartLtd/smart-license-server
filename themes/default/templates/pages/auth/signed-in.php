<?php
/**
 * Shown on a sign-in page to a visitor who is already signed in.
 *
 * @author Callistus Nwachukwu
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

$principal   = $this->principal();
$destination = $principal->is( 'system_admin' ) ? $this->urls()->admin_url() : $this->urls()->client_dashboard_url();
?>
<div class="panel">
	<h1 class="panel-heading">You are signed in</h1>
	<p class="panel-lead"><?php echo escHtml( sprintf( 'Signed in as %s.', $principal->get_display_name() ) ); ?></p>
	<div class="actions">
		<a class="btn btn-accent" href="<?php echo escUrl( $destination->url() ); ?>">Continue</a>
		<a class="btn" href="<?php echo escUrl( $this->urls()->logout_url()->url() ); ?>">Sign out</a>
	</div>
</div>