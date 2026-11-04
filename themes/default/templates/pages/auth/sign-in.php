<?php
/**
 * The sign-in page.
 *
 * @author Callistus Nwachukwu
 * @var string $action       The URL the form posts to.
 * @var string $redirect_url Where to return after signing in (?redirect_url=, this site only); may be empty.
 * @var string $passkey_options_url Starts a passkey sign-in.
 * @var string $passkey_verify_url  Finishes a passkey sign-in.
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
			<input type="text" name="email" autocomplete="email" required autofocus>
		</label>
		<label class="field">
			<span class="field-label">Password</span>
			<input type="password" name="password" autocomplete="current-password" required>
		</label>
		<label class="check">
			<input type="checkbox" name="remember" value="1" data-passkey-remember>
			<span>Keep me signed in for 7 days</span>
		</label>
		<button type="submit" class="btn btn-accent btn-block">Sign in</button>
	</form>

	<?php // A passkey replaces the password. Shown by passkeys.js only where the browser supports passkeys. ?>
	<div class="passkey-login" data-passkey-login data-options-url="<?php echo escUrl( $passkey_options_url ); ?>" data-verify-url="<?php echo escUrl( $passkey_verify_url ); ?>" hidden>
		<p class="passkey-login-or">or</p>
		<div class="notice" data-passkey-message hidden></div>
		<button type="button" class="btn btn-block" data-passkey-button>Sign in with a passkey</button>
	</div>

	<div class="panel-links">
		<p><a href="<?php echo escUrl( $this->urls()->login_url( 'forgot-password' )->url() ); ?>">Forgot your password?</a></p>
		<p>No account? <a href="<?php echo escUrl( $this->urls()->login_url( 'signup', '' !== $redirect_url ? [ 'redirect_url' => $redirect_url ] : [] )->url() ); ?>">Create one</a></p>
	</div>
</div>