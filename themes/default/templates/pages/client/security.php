<?php
/**
 * Self-service authentication-factor settings.
 *
 * @var string $email
 * @var bool $email_verified
 * @var bool $email_enabled
 * @var bool $totp_enabled
 * @var array $passkeys
 * @var int $recovery_code_count
 * @var string $email_challenge
 * @var string $email_disable_challenge
 * @var string $totp_challenge
 * @var string $totp_secret
 * @var string $totp_uri
 * @var array<string,string> $actions
 */
?>
<section class="panel">
	<h1 class="panel-heading">Account security</h1>
	<p class="panel-lead">Add a second verification method to protect your account. Enrollments are verified before they are enabled.</p>

	<section class="panel-section">
		<h2>Email verification codes</h2>
		<p><?php echo $email_enabled ? 'Enabled' : ( $email_verified ? 'Not enabled' : 'Verify your email address first' ); ?></p>
		<?php if ( $email_enabled ) : ?>
			<?php if ( '' !== $email_disable_challenge ) : ?>
				<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
					<input type="hidden" name="factor" value="email">
					<input type="hidden" name="challenge" value="<?php echo escAttr( $email_disable_challenge ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
					<label class="field"><span class="field-label">Email confirmation code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
					<button type="submit" class="btn">Disable email codes</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
					<input type="hidden" name="factor" value="email">
					<div class="notice" data-smliser-form-message hidden></div>
					<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
					<button type="submit" class="btn">Send code to disable</button>
				</form>
			<?php endif; ?>
		<?php elseif ( $email_verified ) : ?>
			<form method="post" action="<?php echo escUrl( $actions['email_start'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<button type="submit" class="btn btn-accent">Send enrollment code</button>
			</form>
		<?php endif; ?>
		<?php if ( '' !== $email_challenge ) : ?>
			<form method="post" action="<?php echo escUrl( $actions['email_verify'] ); ?>" data-smliser-form>
				<input type="hidden" name="challenge" value="<?php echo escAttr( $email_challenge ); ?>">
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field"><span class="field-label">Email verification code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
				<button type="submit" class="btn btn-accent">Verify and enable email codes</button>
			</form>
		<?php endif; ?>
	</section>

	<section class="panel-section">
		<h2>Authenticator app</h2>
		<p><?php echo $totp_enabled ? 'Enabled' : 'Not enabled'; ?></p>
		<?php if ( $totp_enabled ) : ?>
			<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
				<input type="hidden" name="factor" value="totp">
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<label class="field"><span class="field-label">Authenticator or recovery code</span><input type="text" name="code" autocomplete="one-time-code" required></label>
				<button type="submit" class="btn">Disable authenticator app</button>
			</form>
		<?php elseif ( '' !== $totp_challenge && '' !== $totp_secret ) : ?>
			<p>In your authenticator app, add an account using this setup key:</p>
			<p><code><?php echo escHtml( $totp_secret ); ?></code></p>
			<p><a href="<?php echo escUrl( $totp_uri ); ?>">Open authenticator app</a></p>
			<form method="post" action="<?php echo escUrl( $actions['totp_verify'] ); ?>" data-smliser-form>
				<input type="hidden" name="challenge" value="<?php echo escAttr( $totp_challenge ); ?>">
				<div class="notice" data-smliser-form-message style="white-space:pre-wrap" hidden></div>
				<label class="field"><span class="field-label">Current authenticator code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
				<button type="submit" class="btn btn-accent">Verify and enable</button>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo escUrl( $actions['totp_start'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<button type="submit" class="btn btn-accent">Set up authenticator app</button>
			</form>
		<?php endif; ?>
		<?php if ( $recovery_code_count > 0 ) : ?>
			<p><?php echo escHtml( sprintf( '%d recovery codes remain.', $recovery_code_count ) ); ?> Keep them somewhere private.</p>
		<?php endif; ?>
	</section>

	<section class="panel-section">
		<h2>Passkeys</h2>
		<?php if ( empty( $passkeys ) ) : ?>
			<p>No passkeys are registered yet.</p>
		<?php else : ?>
			<ul><?php foreach ( $passkeys as $passkey ) : ?>
				<li>
					<?php echo escHtml( $passkey['label'] ); ?>
					<form method="post" action="<?php echo escUrl( $actions['passkey_revoke'] ); ?>" data-smliser-form>
						<input type="hidden" name="id" value="<?php echo escAttr( (string) $passkey['id'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
						<button type="submit" class="btn">Remove passkey</button>
					</form>
				</li>
			<?php endforeach; ?></ul>
		<?php endif; ?>
		<form method="post" data-passkey-register data-start-url="<?php echo escUrl( $actions['passkey_start'] ); ?>" data-finish-url="<?php echo escUrl( $actions['passkey_finish'] ); ?>">
			<div class="notice" data-passkey-message hidden></div>
			<label class="field"><span class="field-label">Passkey name</span><input type="text" name="label" maxlength="120" placeholder="This device"></label>
			<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
			<button type="submit" class="btn btn-accent">Add a passkey</button>
		</form>
	</section>
</section>
