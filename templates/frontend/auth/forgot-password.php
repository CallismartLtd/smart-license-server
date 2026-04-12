<?php
/**
 * Forgot Password Form Template
 *
 * Rendered by ForgotPasswordHandler and injected into auth SPA container.
 *
 * Two-step process:
 * 1. Request password reset (email)
 * 2. Check email for reset link
 *
 * Form submission via AJAX to {rest_base}auth/forgot-password
 */

defined( 'SMLISER_ABSPATH' ) || exit;

?>
<div class="smlag-header">
    <span class="smlag-subtitle">Reset your password</span>
    <span class="smlag-description">Enter your email address and we'll send you a link to reset your password</span>
</div>

<!-- Form submission errors shown here by JS -->
<div class="smlag-alert smlag-alert-error" role="alert" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-alert-circle"></i>
    </div>
    <div id="smlag-forgot-error"></div>
</div>

<!-- Success message -->
<div class="smlag-alert smlag-alert-success" role="status" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-check"></i>
    </div>
    <div id="smlag-forgot-success"></div>
</div>

<!-- Form -->
<form class="smlag-form" method="post" id="smlag-forgot-password-form">

    <!-- Email field -->
    <div class="smlag-form-group">
        <label for="smlag-forgot-email" class="smlag-label">
            <i class="ti ti-mail" aria-hidden="true"></i>
            Email Address
        </label>
        <input
            type="email"
            id="smlag-forgot-email"
            name="email"
            class="smlag-input"
            placeholder="you@example.com"
            required
            autocomplete="email"
            autofocus
        />
        <p class="smlag-help-text">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            Enter the email address associated with your account
        </p>
    </div>

    <!-- CSRF Nonce -->
    <?php if ( function_exists( 'wp_nonce_field' ) ) : ?>
        <?php wp_nonce_field( 'smliser_auth_forgot_password', '_wpnonce_forgot' ); ?>
    <?php endif; ?>

    <!-- Submit button -->
    <button type="submit" class="smlag-button" id="smlag-forgot-submit">
        <span class="smlag-button-text">
            <i class="ti ti-mail-forward" aria-hidden="true"></i>
            Send Reset Link
        </span>
    </button>

</form>

<!-- Info box -->
<div class="smlag-alert smlag-alert-info" role="note">
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-info-circle"></i>
    </div>
    <div>
        <strong>Check your email</strong>
        <p style="margin: 0.25rem 0 0 0; font-size: 0.8125rem;">
            Reset links expire after 1 hour. If you don't receive an email, check your spam folder.
        </p>
    </div>
</div>

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
    <p style="color: #94a3b8;">
        Don't have an account?
        <a href="#signup">
            <i class="ti ti-user-plus" aria-hidden="true"></i>
            Create one
        </a>
    </p>
</div>