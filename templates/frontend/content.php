<?php
/**
 * Client Dashboard Content Partial
 *
 * Renders the main column: mobile backdrop, topbar (three pluggable
 * slots), async content area, loader, and error state.
 *
 *
 * Opened inside <div class="smlcd-layout"> from frontend.header.
 * Closed by frontend.footer.
 *
 * Topbar slots:
 *   smlcd-topbar-left   — sidebar toggle (always present)
 *   smlcd-topbar-center — page title / breadcrumb (optional via $topbar_center)
 *   smlcd-topbar-right  — theme toggle + principal name + custom actions
 *                         Extend via $topbar_actions (HTML string) injected
 *                         before the theme button.
 *
 * Expected variables:
 *
 * @var \SmartLicenseServer\Security\Context\Principal $principal
 * @var string      $rest_base
 * @var string      $active_slug
 * @var string|null $topbar_center   Optional HTML for the center slot.
 * @var string|null $topbar_actions  Optional HTML injected into the right slot
 *                                   before the theme toggle button.
 *                                   Use this to add notification bells, links, etc.
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$principal      = $principal      ?? null;
$rest_base      = $rest_base      ?? '';
$active_slug    = $active_slug    ?? '';
$topbar_center  = $topbar_center  ?? null;
$topbar_actions = $topbar_actions ?? null;

?>

<?php /* -----------------------------------------------
 * MOBILE BACKDROP
 * Sits between the sidebar overlay and page content.
 * JS adds .smlcd-backdrop--visible when sidebar opens
 * on mobile. Clicking it closes the sidebar.
 * ----------------------------------------------- */ ?>
<div class="smlcd-backdrop" id="smlcd-backdrop" aria-hidden="true"></div>

<main class="smlcd-main" id="smlcd-main" role="main">

    <?php /* -----------------------------------------------
     * TOPBAR
     *
     * Three slots: left / center / right.
     * Inject custom content via $topbar_center and
     * $topbar_actions without modifying this template.
     * ----------------------------------------------- */ ?>
    <div class="smlcd-topbar" role="banner">

        <?php /* LEFT SLOT — sidebar toggle */ ?>
        <div class="smlcd-topbar-left">
            <button
                class="smlcd-icon-btn"
                id="smlcd-sidebar-toggle"
                type="button"
                aria-label="Toggle navigation"
                aria-expanded="true"
                aria-controls="smlcd-sidebar"
            >
                <span class="ti ti-menu-2" aria-hidden="true"></span>
            </button>
        </div>

        <?php /* CENTER SLOT — page title or custom breadcrumb */ ?>
        <div class="smlcd-topbar-center">
            <?php if ( $topbar_center ) : ?>
                <?php echo $topbar_center; /* Caller is responsible for escaping */ ?>
            <?php endif; ?>
        </div>

        <?php /* RIGHT SLOT — custom actions + theme toggle + principal name */ ?>
        <div class="smlcd-topbar-right">

            <?php /* Pluggable action area: notification bell, links, etc. */ ?>
            <?php if ( $topbar_actions ) : ?>
                <?php echo $topbar_actions; /* Caller is responsible for escaping */ ?>
                <div class="smlcd-topbar-divider" aria-hidden="true"></div>
            <?php endif; ?>

            <?php /* Theme toggle */ ?>
            <button
                class="smlcd-icon-btn"
                id="smlcd-theme-toggle"
                type="button"
                aria-label="Switch to light mode"
            >
                <span class="ti ti-sun" id="smlcd-theme-icon" aria-hidden="true"></span>
            </button>

            <?php if ( $principal ) : ?>
                <div class="smlcd-topbar-divider" aria-hidden="true"></div>
                <span class="smlcd-principal-name">
                    <?php echo esc_html( $principal->get_display_name() ); ?>
                </span>
            <?php endif; ?>

        </div>

    </div>

    <?php /* -----------------------------------------------
     * CONTENT AREA
     * Three mutually exclusive states managed by JS:
     *   loading  — spinner visible
     *   content  — rendered HTML from REST response
     *   error    — error message + retry button
     * ----------------------------------------------- */ ?>
    <div
        class="smlcd-content-area"
        id="smlcd-content-area"
        aria-live="polite"
        aria-busy="false"
    >
        <?php /* Loading state */ ?>
        <div class="smlcd-loader" id="smlcd-loader" hidden aria-label="Loading content">
            <span class="smlcd-loader-spinner" aria-hidden="true"></span>
            <span class="smlcd-loader-text">Loading...</span>
        </div>

        <?php /* Section content injected by JS */ ?>
        <div class="smlcd-content" id="smlcd-content"></div>

        <?php /* Error state */ ?>
        <div class="smlcd-error" id="smlcd-error" hidden role="alert">
            <span class="ti ti-alert-circle" aria-hidden="true"></span>
            <p class="smlcd-error-message" id="smlcd-error-message"></p>
            <button class="smlcd-error-retry" id="smlcd-error-retry" type="button">
                Retry
            </button>
        </div>

    </div>

</main>