<?php
/**
 * The reset-password page: the link of the reset email.
 *
 * @author Callistus Nwachukwu
 * @var string $action The URL the form posts to.
 * @var bool   $valid  Whether the key is valid.
 * @var string $token  The key; empty when invalid.
 * @var string $email  The account's email address; empty when invalid.
 * @var string $reason Why the key cannot be used; empty when valid.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="panel">
	<h1 class="panel-heading">Choose a new password</h1>

	<?php if ( $valid ) : ?>
		<p class="panel-lead"><?php echo escHtml( sprintf( 'For %s.', $email ) ); ?></p>

		<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form>
			<div class="notice" data-smliser-form-message hidden></div>
			<input type="hidden" name="token" value="<?php echo escAttr( $token ); ?>">
			<label class="field">
				<span class="field-label">New password</span>
				<input type="password" name="password_1" autocomplete="new-password" minlength="8" required autofocus data-password-meter>
				<span class="strength" data-password-strength></span>
			</label>
			<label class="field">
				<span class="field-label">Confirm password</span>
				<input type="password" name="password_2" autocomplete="new-password" minlength="8" required>
			</label>
			<button type="submit" class="btn btn-accent btn-block">Change password</button>
		</form>
	<?php else : ?>
		<div class="notice" data-state="error" role="alert"><?php echo escHtml( $reason ); ?></div>
		<a class="btn btn-block" href="<?php echo escUrl( $this->urls()->login_url( 'forgot-password' )->url() ); ?>">Ask for a new link</a>
	<?php endif; ?>

	<div class="panel-links">
		<p><a href="<?php echo escUrl( $this->urls()->login_url()->url() ); ?>">Back to sign in</a></p>
	</div>
</div>