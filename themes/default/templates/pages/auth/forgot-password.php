<?php
/**
 * The forgot-password page.
 *
 * @author Callistus Nwachukwu
 * @var string $action The URL the form posts to.
 * @var SmartLicenseServer\Environments\Application\Theming\ThemeContext $this
 */

?>
<div class="panel">
	<h1 class="panel-heading">Reset your password</h1>
	<p class="panel-lead">We will email you a link to choose a new one.</p>

	<form method="post" action="<?php echo escUrl( $action ); ?>" data-smliser-form>
		<div class="notice" data-smliser-form-message hidden></div>
		<label class="field">
			<span class="field-label">Email</span>
			<input type="email" name="email" autocomplete="email" required autofocus>
		</label>
		<button type="submit" class="btn btn-accent btn-block">Send the link</button>
	</form>

	<div class="panel-links">
		<p><a href="<?php echo escUrl( $this->urls()->login_url()->url() ); ?>">Back to sign in</a></p>
	</div>
</div>