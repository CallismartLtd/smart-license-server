<?php
/**
 * Client Authentication Index Template (SPA Skeleton)
 *
 * Renders the main container for the authentication SPA.
 * Auth forms (login, signup, forgot-password, 2fa) are fetched
 * dynamically via REST API based on URL fragment.
 *
 * This template provides:
 * - Outer container structure
 * - Content injection point (#smlag-content)
 * - Loading spinner state
 * - Error handling with retry
 * - Meta tags for JS
 *
 * Expected variables (from shell.php):
 *
 * @var string $repo_name    Repository/app name from settings
 * @var string $rest_base    REST API base URL (used by JS)
 * @var string $principal    Null (not authenticated)
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$repo_name = $repo_name ?? 'Dashboard';

?>
<div class="smlag-container">
    <div class="smlag-card">

        <!-- Content area where auth forms are dynamically injected -->
        <div id="smlag-content" aria-live="polite" aria-busy="false"></div>

        <!-- Loading state: shown while fetching form -->
        <div class="smlag-loader" id="smlag-loader" hidden aria-label="Loading">
            <span class="smlag-loader-spinner" aria-hidden="true"></span>
            <span class="smlag-loader-text">Loading...</span>
        </div>

        <!-- Error state: shown if form fetch fails -->
        <div class="smlag-error" id="smlag-error" hidden role="alert">
            <div class="smlag-error-icon" aria-hidden="true">
                <i class="ti ti-alert-circle"></i>
            </div>
            <p class="smlag-error-message" id="smlag-error-message"></p>
            <button class="smlag-button smlag-button--small" id="smlag-error-retry" type="button">
                <i class="ti ti-refresh" aria-hidden="true"></i>
                <span>Retry</span>
            </button>
        </div>

    </div>
</div>

<!-- Meta tags used by smliser-auth.js -->
<meta name="smliser-rest-base" content="<?php echo esc_attr( $rest_base ); ?>">
<meta name="smliser-repo-name" content="<?php echo esc_attr( $repo_name ); ?>">