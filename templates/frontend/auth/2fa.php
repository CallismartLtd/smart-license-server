<?php
/**
 * Two-Factor Authentication Form Template
 *
 * Rendered by TwoFactorAuthHandler and injected into auth SPA container.
 *
 * Shown after initial login, requires user to verify:
 * - TOTP code (6-digit)
 * - OR backup code
 *
 * Form submission via AJAX to {rest_base}auth/2fa
 */

defined( 'SMLISER_ABSPATH' ) || exit;

?>
<div class="smlag-header">
    <span class="smlag-subtitle">Two-Factor Authentication</span>
    <span class="smlag-description">Enter the verification code from your authenticator app</span>
</div>

<!-- Form submission errors shown here by JS -->
<div class="smlag-alert smlag-alert-error" role="alert" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-alert-circle"></i>
    </div>
    <div id="smlag-2fa-error"></div>
</div>

<!-- Form -->
<form class="smlag-form" method="post" id="smlag-2fa-form">

    <!-- TOTP Code Input (6 digits) -->
    <div class="smlag-form-group">
        <label class="smlag-label">
            <i class="ti ti-shield-check" aria-hidden="true"></i>
            Verification Code
        </label>
        <div class="smlag-otp-group" id="smlag-otp-group">
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_1"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="0"
            />
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_2"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="1"
            />
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_3"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="2"
            />
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_4"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="3"
            />
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_5"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="4"
            />
            <input
                type="text"
                class="smlag-otp-input"
                name="otp_6"
                maxlength="1"
                inputmode="numeric"
                pattern="[0-9]"
                placeholder="0"
                required
                data-otp-index="5"
            />
        </div>
        <p class="smlag-help-text">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            Enter the 6-digit code from your authenticator app
        </p>
    </div>

    <!-- Hidden field to hold the full OTP code -->
    <input type="hidden" name="verification_code" id="smlag-verification-code" />

    <!-- Submit button -->
    <button type="submit" class="smlag-button" id="smlag-2fa-submit">
        <span class="smlag-button-text">
            <i class="ti ti-check" aria-hidden="true"></i>
            Verify
        </span>
    </button>

</form>

<!-- Divider -->
<div class="smlag-divider-with-text">
    <span class="smlag-divider-text">Can't access your app?</span>
</div>

<!-- Backup code section -->
<form class="smlag-form" id="smlag-backup-code-form" hidden>
    <div class="smlag-form-group">
        <label for="smlag-backup-code" class="smlag-label">
            <i class="ti ti-key" aria-hidden="true"></i>
            Backup Code
        </label>
        <input
            type="text"
            id="smlag-backup-code"
            name="backup_code"
            class="smlag-input"
            placeholder="XXXX-XXXX-XXXX"
            autocomplete="off"
        />
        <p class="smlag-help-text">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            Use one of your backup codes instead
        </p>
    </div>

    <!-- CSRF Nonce -->
    <?php if ( function_exists( 'wp_nonce_field' ) ) : ?>
        <?php wp_nonce_field( 'smliser_auth_2fa', '_wpnonce_2fa' ); ?>
    <?php endif; ?>

    <button type="submit" class="smlag-button smlag-button--secondary" id="smlag-backup-submit">
        <span class="smlag-button-text">
            <i class="ti ti-check" aria-hidden="true"></i>
            Verify Backup Code
        </span>
    </button>
</form>

<!-- Toggle backup code form -->
<div style="text-align: center; margin-top: 1rem;">
    <button
        type="button"
        class="smlag-button smlag-button--small smlag-button--secondary"
        id="smlag-backup-toggle"
        aria-expanded="false"
    >
        <i class="ti ti-key" aria-hidden="true"></i>
        <span>Use backup code</span>
    </button>
</div>

<!-- Footer links -->
<div class="smlag-footer" style="margin-top: 1.5rem;">
    <p>
        <a href="#login">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Try different account
        </a>
    </p>
</div>