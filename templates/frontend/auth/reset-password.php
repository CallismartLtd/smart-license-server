<?php
/**
 * Reset Password Form Template
 *
 * User lands here via link in reset email.
 * Contains token in query string: ?token=XXX&email=user@example.com
 * User enters new password, token is sent with form.
 *
 * Form submission via AJAX to {rest_base}auth/reset-password
 */

defined( 'SMLISER_ABSPATH' ) || exit;

?>
<div class="smlag-header">
    <span class="smlag-subtitle">Create a new password</span>
    <span class="smlag-description">Enter a strong password to secure your account</span>
</div>

<!-- Form submission errors shown here by JS -->
<div class="smlag-alert smlag-alert-error" role="alert" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-alert-circle"></i>
    </div>
    <div id="smlag-reset-error"></div>
</div>

<!-- Success message -->
<div class="smlag-alert smlag-alert-success" role="status" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-check"></i>
    </div>
    <div id="smlag-reset-success"></div>
</div>

<!-- Form -->
<form class="smlag-form" method="post" id="smlag-reset-password-form">

    <!-- Token field (hidden) -->
    <input
        type="hidden"
        id="smlag-reset-token"
        name="token"
        value=""
    />

    <!-- Email field (hidden) -->
    <input
        type="hidden"
        id="smlag-reset-email"
        name="email"
        value=""
    />

    <!-- New password field -->
    <div class="smlag-form-group">
        <label for="smlag-reset-password" class="smlag-label">
            <i class="ti ti-lock" aria-hidden="true"></i>
            New Password
        </label>
        <input
            type="password"
            id="smlag-reset-password"
            name="password"
            class="smlag-input"
            placeholder="Enter new password"
            required
            minlength="8"
            data-password-meter
            autocomplete="new-password"
            autofocus
        />
        <p class="smlag-help-text">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            Minimum 8 characters, should include uppercase, lowercase, numbers
        </p>

        <!-- Password strength meter -->
        <div class="smlag-password-strength">
            <div class="smlag-strength-bar">
                <div id="smlag-strength-fill" class="smlag-strength-fill"></div>
            </div>
            <span id="smlag-strength-text" class="smlag-strength-text"></span>
        </div>
    </div>

    <!-- Confirm password field -->
    <div class="smlag-form-group">
        <label for="smlag-reset-password-confirm" class="smlag-label">
            <i class="ti ti-lock-check" aria-hidden="true"></i>
            Confirm Password
        </label>
        <input
            type="password"
            id="smlag-reset-password-confirm"
            name="password_confirm"
            class="smlag-input"
            placeholder="Confirm password"
            required
            minlength="8"
            autocomplete="new-password"
        />
    </div>

    <!-- CSRF Nonce -->
    <input
        type="hidden"
        id="smlag-reset-nonce"
        name="_wpnonce_reset"
        value=""
    />

    <!-- Submit button -->
    <button type="submit" class="smlag-button" id="smlag-reset-submit">
        <span class="smlag-button-text">
            <i class="ti ti-check" aria-hidden="true"></i>
            Reset Password
        </span>
    </button>

</form>

<!-- Divider -->
<div class="smlag-divider"></div>

<!-- Footer links -->
<div class="smlag-footer">
    <p>
        <a href="#login">
            <i class="ti ti-arrow-left" aria-hidden="true"></i>
            Back to sign in
        </a>
    </p>
</div>