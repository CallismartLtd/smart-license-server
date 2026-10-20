<?php
/**
 * The sign-in page.
 *
 * @author Callistus Nwachukwu
 * @var string $action The URL the form posts to.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

if ( null !== $this->principal() ) {
	$this->partial( 'auth.signed-in' );
	return;
}
?>
<div class="panel">
	<h1 class="panel-heading">Sign in</h1>
	<p class="panel-lead"><?php echo escHtml( sprintf( 'to your %s account.', SMLISER_APP_NAME ) ); ?></p>

	<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form data-pass-redirect>
		<div class="notice" data-smliser-form-message hidden></div>
		<label class="field">
			<span class="field-label">Username or email</span>
			<input type="text" name="username" autocomplete="username" required autofocus>
		</label>
		<label class="field">
			<span class="field-label">Password</span>
			<input type="password" name="password" autocomplete="current-password" required>
		</label>
		<label class="check">
			<input type="checkbox" name="remember" value="1">
			<span>Keep me signed in for 7 days</span>
		</label>
		<button type="submit" class="btn btn-accent btn-block">Sign in</button>
	</form>

	<div class="panel-links">
		<p><a href="<?php echo escUrl( $this->urls()->login_url( 'forgot-password' )->url() ); ?>">Forgot your password?</a></p>
		<p>No account? <a href="<?php echo escUrl( $this->urls()->login_url( 'signup' )->url() ); ?>">Create one</a></p>
	</div>
</div>