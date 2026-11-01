<?php
/**
 * Complete a pending second-factor sign-in challenge.
 *
 * @var bool   $valid
 * @var string $challenge
 * @var bool   $email_pending
 * @var bool   $email_available
 * @var bool   $totp_available
 * @var string $email_action
 * @var string $verify_action
 */
?>
<div class="panel">
	<h1 class="panel-heading">Verify your sign-in</h1>
	<?php if ( ! $valid ) : ?>
		<p class="panel-lead">This sign-in challenge is invalid or expired. Please sign in again.</p>
		<p><a class="btn btn-accent btn-block" href="<?php echo escUrl( $this->urls()->login_url()->url() ); ?>">Return to sign in</a></p>
	<?php else : ?>
		<p class="panel-lead">Your password was accepted. Complete one of your enabled verification methods to continue.</p>
		<?php if ( $email_pending ) : ?>
			<form method="post" action="<?php echo escUrl( $verify_action ); ?>" data-smliser-form>
				<input type="hidden" name="challenge" value="<?php echo escAttr( $challenge ); ?>">
				<input type="hidden" name="method" value="email">
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field">
					<span class="field-label">Email verification code</span>
					<input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus>
				</label>
				<button type="submit" class="btn btn-accent btn-block">Verify and sign in</button>
			</form>
		<?php else : ?>
			<?php if ( $totp_available ) : ?>
				<form method="post" action="<?php echo escUrl( $verify_action ); ?>" data-smliser-form>
					<input type="hidden" name="challenge" value="<?php echo escAttr( $challenge ); ?>">
					<input type="hidden" name="method" value="totp">
					<div class="notice" data-smliser-form-message hidden></div>
					<label class="field">
						<span class="field-label">Authenticator code or recovery code</span>
						<input type="text" name="code" autocomplete="one-time-code" required autofocus>
					</label>
					<button type="submit" class="btn btn-accent btn-block">Verify and sign in</button>
				</form>
			<?php endif; ?>
			<?php if ( $passkey_available ) : ?>
				<div class="panel-section" data-passkey-authenticate data-login-challenge="<?php echo escAttr( $challenge ); ?>" data-options-url="<?php echo escUrl( $passkey_options_action ); ?>" data-verify-url="<?php echo escUrl( $passkey_verify_action ); ?>">
					<div class="notice" data-passkey-message hidden></div>
					<button type="button" class="btn btn-block" data-passkey-button>Continue with a passkey</button>
				</div>
			<?php endif; ?>
			<?php if ( $email_available ) : ?>
				<form method="post" action="<?php echo escUrl( $email_action ); ?>" data-smliser-form>
					<input type="hidden" name="challenge" value="<?php echo escAttr( $challenge ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<button type="submit" class="btn btn-block">Send a code to my verified email</button>
				</form>
			<?php endif; ?>
		<?php endif; ?>
		<p class="panel-links"><a href="<?php echo escUrl( $this->urls()->login_url()->url() ); ?>">Cancel and sign in again</a></p>
	<?php endif; ?>
</div>
