<?php
/**
 * The sign-up page.
 *
 * @author Callistus Nwachukwu
 * @var string $action      The URL the form posts to.
 * @var string $email       Address to fill in (?email=); may be empty.
 * @var string $terms_url   Terms URL; empty when not set.
 * @var string $privacy_url Privacy policy URL; empty when not set.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

if ( null !== $this->principal() ) {
	$this->partial( 'auth.signed-in' );
	return;
}
?>
<div class="panel">
	<h1 class="panel-heading">Create an account</h1>
	<p class="panel-lead">Get access to your licenses and downloads.</p>

	<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form data-pass-redirect>
		<div class="notice" data-smliser-form-message hidden></div>
		<label class="field">
			<span class="field-label">Full name</span>
			<input type="text" name="full_name" autocomplete="name" required autofocus>
		</label>
		<label class="field">
			<span class="field-label">Email</span>
			<input type="email" name="email" autocomplete="email" value="<?php echo escAttr( $email ?? '' ); ?>" required>
		</label>
		<label class="field">
			<span class="field-label">Password</span>
			<input type="password" name="password_1" autocomplete="new-password" minlength="8" required data-password-meter>
			<span class="strength" data-password-strength></span>
			<span class="field-hint">At least 8 characters, with upper and lower case letters and numbers.</span>
		</label>
		<label class="field">
			<span class="field-label">Confirm password</span>
			<input type="password" name="password_2" autocomplete="new-password" minlength="8" required>
		</label>
		<label class="field">
			<span class="field-label">Account type</span>
			<select name="account_type">
				<option value="viewer">Licensee (download access)</option>
				<option value="resource_owner">Resource owner</option>
			</select>
		</label>
		<label class="check">
			<input type="checkbox" name="agree_terms" value="1" required>
			<span>
				I agree to the
				<?php if ( '' !== $terms_url ) : ?>
					<a href="<?php echo escUrl( $terms_url ); ?>" target="_blank" rel="noopener">terms</a>
				<?php else : ?>
					terms
				<?php endif; ?>
				and the
				<?php if ( '' !== $privacy_url ) : ?>
					<a href="<?php echo escUrl( $privacy_url ); ?>" target="_blank" rel="noopener">privacy policy</a>.
				<?php else : ?>
					privacy policy.
				<?php endif; ?>
			</span>
		</label>
		<button type="submit" class="btn btn-accent btn-block">Create account</button>
	</form>

	<div class="panel-links">
		<p>Have an account? <a href="<?php echo escUrl( $this->urls()->login_url()->url() ); ?>">Sign in</a></p>
	</div>
</div>