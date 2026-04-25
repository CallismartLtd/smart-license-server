<?php
/**
 * Signup Form Template
 *
 * Rendered by SignupHandler and injected into auth SPA container.
 *
 * Features:
 * - Email verification
 * - Password strength meter
 * - Terms acceptance checkbox
 * - Form submission via AJAX to {rest_base}auth/signup
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$settings = smliser_settings();

?>
<div class="smlag-header">
    <span class="smlag-subtitle">Create your account</span>
    <span class="smlag-description">Join us to get started managing your licenses</span>
</div>

<!-- Form submission errors shown here by JS -->
<div class="smlag-alert smlag-alert-error" role="alert" hidden>
    <div class="smlag-alert-icon" aria-hidden="true">
        <i class="ti ti-alert-circle"></i>
    </div>
    <div id="smlag-signup-error"></div>
</div>

<!-- Form -->
<form class="smlag-form" method="post" id="smlag-signup-form">

    <!-- Full Name field -->
    <div class="smlag-form-group">
        <label for="smlag-signup-name" class="smlag-label">
            <i class="ti ti-user" aria-hidden="true"></i>
            Full Name
        </label>
        <input
            type="text"
            id="smlag-signup-name"
            name="full_name"
            class="smlag-input"
            placeholder="John Doe"
            required
            autocomplete="name"
        />
    </div>

    <!-- Email field -->
    <div class="smlag-form-group">
        <label for="smlag-signup-email" class="smlag-label">
            <i class="ti ti-mail" aria-hidden="true"></i>
            Email Address
        </label>
        <input
            type="email"
            id="smlag-signup-email"
            name="email"
            class="smlag-input"
            placeholder="you@example.com"
            required
            autocomplete="email"
        />
        <p class="smlag-help-text">
            <i class="ti ti-info-circle" aria-hidden="true"></i>
            We'll send a verification link to this address
        </p>
    </div>

    <!-- Password field -->
    <div class="smlag-form-group">
        <label for="smlag-signup-password" class="smlag-label">
            <i class="ti ti-lock" aria-hidden="true"></i>
            Password
        </label>
        <input type="password"id="smlag-signup-password"
            name="password_1" class="smlag-input" placeholder="••••••••••••••••" required
            autocomplete="new-password"
            data-password-meter
        />
        <div class="smlag-password-strength" id="smlag-password-strength">
            <div class="smlag-password-strength-bar">
                <div class="smlag-password-strength-fill" id="smlag-strength-fill"></div>
            </div>
            <p class="smlag-password-strength-text" id="smlag-strength-text"></p>
        </div>
        <p class="smlag-help-text">
            <i class="ti ti-lock-check" aria-hidden="true"></i>
            At least 8 characters, mix of letters and numbers
        </p>
    </div>

    <!-- Confirm Password field -->
    <div class="smlag-form-group">
        <label for="smlag-signup-confirm" class="smlag-label">
            <i class="ti ti-lock" aria-hidden="true"></i>
            Confirm Password
        </label>
        <input type="password" id="smlag-signup-confirm" name="password_2"
            class="smlag-input" placeholder="••••••••••••••••" required 
            autocomplete="new-password"
        />
    </div>

    <!-- Confirm Password field -->
    <div class="smlag-form-group">
        <label for="smlag-account-type" class="smlag-label">
            <i class="ti ti-lock" aria-hidden="true"></i>
            Account Type
        </label>
        <select name="account_type" id="smlag-account-type" class="smlag-select">
            <option value="resource_owner">Resource Owner</option>
            <option value="viewer">Licensee (Download Access)</option>
        </select>
    </div>

    <!-- Terms checkbox -->
    <div class="smlag-checkbox-wrapper">
        <input type="checkbox" id="smlag-signup-terms" name="agree_terms"
            class="smlag-checkbox" value="1" required />

        <label for="smlag-signup-terms">
            I agree to the
            <a href="<?php echo escUrl( $settings->get( 'terms_url', '/', true ) ); ?>" 
                target="_blank">Terms of Service</a>
            and
            <a href="<?php echo escUrl( $settings->get( 'privacy_policy_url', '/', true ) ); ?>"
                target="_blank">Privacy Policy</a>
        </label>
    </div>

    <!-- Submit button -->
    <button type="submit" class="smlag-button" id="smlag-signup-submit">
        <span class="smlag-button-text">
            <i class="ti ti-user-plus" aria-hidden="true"></i>
            Create Account
        </span>
    </button>

</form>

<!-- Divider -->
<div class="smlag-divider"></div>

<!-- Footer links -->
<div class="smlag-footer">
    <p style="color: #94a3b8;">
        Already have an account?
        <a href="#login">
            <i class="ti ti-login-2" aria-hidden="true"></i>
            Sign in
        </a>
    </p>
</div>