<?php
/**
 * Login Form Template
 *
 * Rendered by LoginHandler and injected into auth SPA container.
 *
 * This template renders ONLY the form content (no wrapper/card).
 * The auth index provides the container structure.
 *
 * Form data submitted via AJAX to {rest_base}auth/login
 */

defined( 'SMLISER_ABSPATH' ) || exit;

?>
<div class="smlag-header">
    <span class="smlag-subtitle">Sign in to your account</span>
</div>

<!-- Form submission errors shown here by JS -->
<div class="smlag-alert smlag-alert-error" role="alert" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-alert-circle"></i>
    </div>
    <div id="smlag-login-error"></div>
</div>

<!-- Form -->
<form class="smlag-form" method="post" id="smlag-login-form">

    <!-- Username/Email field -->
    <div class="smlag-form-group">
        <label for="smlag-login-username" class="smlag-label">
            <i class="ti ti-user" aria-hidden="true"></i>
            Username or Email
        </label>
        <input
            type="text"
            id="smlag-login-username"
            name="username"
            class="smlag-input"
            placeholder="you@example.com"
            required
            autocomplete="username"
            autofocus
        />
    </div>

    <!-- Password field -->
    <div class="smlag-form-group">
        <label for="smlag-login-password" class="smlag-label">
            <i class="ti ti-lock" aria-hidden="true"></i>
            Password
        </label>
        <input
            type="password"
            id="smlag-login-password"
            name="password"
            class="smlag-input"
            placeholder="••••••••"
            required
            autocomplete="current-password"
        />
    </div>

    <!-- Remember me -->
    <div class="smlag-checkbox-wrapper">
        <input
            type="checkbox"
            id="smlag-login-remember"
            name="remember"
            class="smlag-checkbox"
            value="1"
        />
        <label for="smlag-login-remember">Keep me signed in for 7 days</label>
    </div>

    <!-- CSRF Nonce -->
    <?php if ( function_exists( 'wp_nonce_field' ) ) : ?>
        <?php wp_nonce_field( 'smliser_auth_login', '_wpnonce_login' ); ?>
    <?php endif; ?>

    <!-- Submit button -->
    <button type="submit" class="smlag-button" id="smlag-login-submit">
        <span class="smlag-button-text">
            <i class="ti ti-login-2" aria-hidden="true"></i>
            Sign In
        </span>
    </button>

</form>

<!-- Divider -->
<div class="smlag-divider"></div>

<!-- Footer links -->
<div class="smlag-footer">
    <p>
        <a href="#forgot-password">
            <i class="ti ti-help-circle" aria-hidden="true"></i>
            Forgot your password?
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