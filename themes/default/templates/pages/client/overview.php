<?php
/**
 * The client dashboard overview.
 *
 * @author Callistus Nwachukwu
 * @var string $name  The signed-in user's display name.
 * @var string $email The signed-in user's email address.
 * @var string $role  The user's role label.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<p class="dash-lead"><?php echo escHtml( sprintf( 'Welcome back, %s.', $name ) ); ?></p>

<div class="cells">
	<div class="cell">
		<h3>Account</h3>
		<p><?php echo escHtml( $name ); ?></p>
		<?php if ( '' !== $email ) : ?>
			<code><?php echo escHtml( $email ); ?></code>
		<?php endif; ?>
	</div>

	<div class="cell">
		<h3>Role</h3>
		<p><?php echo escHtml( $role ); ?></p>
	</div>

	<div class="cell">
		<h3>API</h3>
		<p>Your software talks to this server through the REST API.</p>
		<code><a href="<?php echo escUrl( $this->url( 'documentation' )->url() ); ?>">Documentation</a></code>
	</div>
</div>