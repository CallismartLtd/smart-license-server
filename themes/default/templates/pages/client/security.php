<?php
/**
 * Self-service authentication-factor settings.
 *
 * Every form that asks for the current password carries an unnamed, visually hidden
 * username input. It tells password managers which account the password belongs to,
 * so a re-authentication is not treated as a password change. It has no `name`, so
 * it is never submitted and the server contract is unchanged.
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
 * @var string $totp_qr_code
 * @var string $active_tab
 * @var array<string,string> $actions
 */
?>
<section class="panel panel-wide security-page" data-security-tabs data-active-tab="<?php echo escAttr( $active_tab ); ?>">
	<h1 class="panel-heading">Account security</h1>
	<p class="panel-lead">Choose how you protect your account. Each method is verified before it is enabled.</p>

	<div class="security-tablist" role="tablist" aria-label="Account security methods">
		<button class="security-tab" id="security-tab-email" type="button" role="tab" aria-controls="security-panel-email" aria-selected="<?php echo 'email' === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo 'email' === $active_tab ? '0' : '-1'; ?>" data-security-tab="email">Email codes</button>
		<button class="security-tab" id="security-tab-authenticator" type="button" role="tab" aria-controls="security-panel-authenticator" aria-selected="<?php echo 'authenticator' === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo 'authenticator' === $active_tab ? '0' : '-1'; ?>" data-security-tab="authenticator">Authenticator app</button>
		<button class="security-tab" id="security-tab-passkeys" type="button" role="tab" aria-controls="security-panel-passkeys" aria-selected="<?php echo 'passkeys' === $active_tab ? 'true' : 'false'; ?>" tabindex="<?php echo 'passkeys' === $active_tab ? '0' : '-1'; ?>" data-security-tab="passkeys">Passkeys</button>
	</div>

	<section class="security-tab-panel" id="security-panel-email" role="tabpanel" aria-labelledby="security-tab-email" tabindex="0" data-security-panel="email"<?php echo 'email' !== $active_tab ? ' hidden' : ''; ?>>
		<div class="security-method-heading">
			<div><h2>Email verification codes</h2><p>Receive a one-time code at <?php echo escHtml( $email ); ?> when you sign in.</p></div>
			<span class="security-state<?php echo $email_enabled ? ' is-enabled' : ''; ?>"><?php echo $email_enabled ? 'Enabled' : 'Not enabled'; ?></span>
		</div>
		<?php if ( $email_enabled ) : ?>
			<?php if ( '' !== $email_disable_challenge ) : ?>
				<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
					<input type="hidden" name="factor" value="email">
					<input type="hidden" name="challenge" value="<?php echo escAttr( $email_disable_challenge ); ?>">
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
					<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
					<label class="field"><span class="field-label">Email confirmation code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
					<button type="submit" class="btn">Disable email codes</button>
				</form>
			<?php else : ?>
				<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
					<input type="hidden" name="factor" value="email">
					<div class="notice" data-smliser-form-message hidden></div>
					<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
					<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
					<button type="submit" class="btn">Send code to disable</button>
				</form>
			<?php endif; ?>
		<?php elseif ( $email_verified ) : ?>
			<form method="post" action="<?php echo escUrl( $actions['email_start'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<button type="submit" class="btn btn-accent">Send enrollment code</button>
			</form>
		<?php else : ?>
			<p class="security-help">Verify your email address before enabling email codes.</p>
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

	<section class="security-tab-panel" id="security-panel-authenticator" role="tabpanel" aria-labelledby="security-tab-authenticator" tabindex="0" data-security-panel="authenticator"<?php echo 'authenticator' !== $active_tab ? ' hidden' : ''; ?>>
		<div class="security-method-heading">
			<div><h2>Authenticator app</h2><p>Use an app such as 1Password, Google Authenticator, or Microsoft Authenticator.</p></div>
			<span class="security-state<?php echo $totp_enabled ? ' is-enabled' : ''; ?>"><?php echo $totp_enabled ? 'Enabled' : 'Not enabled'; ?></span>
		</div>
		<?php if ( $totp_enabled ) : ?>
			<form method="post" action="<?php echo escUrl( $actions['factor_disable'] ); ?>" data-smliser-form>
				<input type="hidden" name="factor" value="totp">
				<div class="notice" data-smliser-form-message hidden></div>
				<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<label class="field"><span class="field-label">Authenticator or recovery code</span><input type="text" name="code" autocomplete="one-time-code" required></label>
				<button type="submit" class="btn">Disable authenticator app</button>
			</form>
		<?php elseif ( '' !== $totp_challenge && '' !== $totp_secret ) : ?>
			<div class="security-enrollment">
				<div class="security-qr-card">
					<img class="security-qr" src="<?php echo escAttr( $totp_qr_code ); ?>" width="240" height="240" alt="Scan this QR code with your authenticator app">
					<p>Scan this code with your authenticator app.</p>
				</div>
				<div class="security-enrollment-help">
					<p>Or <a href="<?php echo escUrl( $totp_uri ); ?>">open the authenticator app</a> on this device.</p>
					<details>
						<summary>Enter the setup key manually</summary>
						<p class="security-help">Keep this key private. Anyone with it can generate your sign-in codes.</p>
						<code class="security-secret"><?php echo escHtml( $totp_secret ); ?></code>
					</details>
				</div>
			</div>
			<form method="post" action="<?php echo escUrl( $actions['totp_verify'] ); ?>" data-smliser-form>
				<input type="hidden" name="challenge" value="<?php echo escAttr( $totp_challenge ); ?>">
				<div class="notice" data-smliser-form-message style="white-space:pre-wrap" hidden></div>
				<label class="field"><span class="field-label">Authenticator code</span><input type="text" name="code" inputmode="numeric" autocomplete="one-time-code" required></label>
				<button type="submit" class="btn btn-accent">Verify and enable</button>
			</form>
		<?php else : ?>
			<form method="post" action="<?php echo escUrl( $actions['totp_start'] ); ?>" data-smliser-form>
				<div class="notice" data-smliser-form-message hidden></div>
				<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
				<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
				<button type="submit" class="btn btn-accent">Set up authenticator app</button>
			</form>
		<?php endif; ?>
		<?php if ( $recovery_code_count > 0 ) : ?>
			<p class="security-help"><?php echo escHtml( sprintf( '%d recovery codes remain.', $recovery_code_count ) ); ?> Keep them somewhere private.</p>
		<?php endif; ?>
	</section>

	<section class="security-tab-panel" id="security-panel-passkeys" role="tabpanel" aria-labelledby="security-tab-passkeys" tabindex="0" data-security-panel="passkeys"<?php echo 'passkeys' !== $active_tab ? ' hidden' : ''; ?>>
		<div class="security-method-heading">
			<div><h2>Passkeys</h2><p>Sign in with a device, security key, or password manager.</p></div>
			<span class="security-state<?php echo ! empty( $passkeys ) ? ' is-enabled' : ''; ?>"><?php echo ! empty( $passkeys ) ? escHtml( sprintf( '%d registered', count( $passkeys ) ) ) : 'None registered'; ?></span>
		</div>
		<?php if ( empty( $passkeys ) ) : ?>
			<p class="security-help">No passkeys are registered yet.</p>
		<?php else : ?>
			<ul class="security-passkey-list"><?php foreach ( $passkeys as $passkey ) : ?>
				<li>
					<strong><?php echo escHtml( $passkey['label'] ); ?></strong>
					<form method="post" action="<?php echo escUrl( $actions['passkey_revoke'] ); ?>" data-smliser-form>
						<input type="hidden" name="id" value="<?php echo escAttr( (string) $passkey['id'] ); ?>">
						<div class="notice" data-smliser-form-message hidden></div>
						<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
						<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
						<button type="submit" class="btn btn-small">Remove passkey</button>
					</form>
				</li>
			<?php endforeach; ?></ul>
		<?php endif; ?>
		<form method="post" class="security-passkey-form" data-passkey-register data-start-url="<?php echo escUrl( $actions['passkey_start'] ); ?>" data-finish-url="<?php echo escUrl( $actions['passkey_finish'] ); ?>">
			<div class="notice" data-passkey-message hidden></div>
			<label class="field"><span class="field-label">Passkey name</span><input type="text" name="label" maxlength="120" placeholder="This device"></label>
			<input type="text" class="security-username" value="<?php echo escAttr( $email ); ?>" autocomplete="username" tabindex="-1" aria-hidden="true" readonly style="position:absolute;left:-9999px;width:1px;height:1px;opacity:0">
			<label class="field"><span class="field-label">Current password</span><input type="password" name="password" autocomplete="current-password" required></label>
			<button type="submit" class="btn btn-accent">Add a passkey</button>
		</form>
	</section>
</section>