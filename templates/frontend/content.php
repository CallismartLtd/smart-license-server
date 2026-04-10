<?php
/**
 * Client Dashboard Content Partial
 *
 * Renders the main column: topbar, async content area, loader,
 * error state, and the inline bootstrap script.
 *
 * Opened inside <div class="smlcd-layout"> from frontend.header.
 * Closed by frontend.footer.
 *
 * Expected variables:
 *
 * @var \SmartLicenseServer\Security\Context\Principal $principal
 * @var string $rest_base
 * @var string $active_slug
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$principal   = $principal   ?? null;
$rest_base   = $rest_base   ?? '';
$active_slug = $active_slug ?? '';

?>
    <main class="smlcd-main" id="smlcd-main" role="main">

        <?php /* -----------------------------------------------
         * TOPBAR
         * Sidebar toggle on the left, principal name on right.
         * ----------------------------------------------- */ ?>
        <div class="smlcd-topbar">
            <button
                class="smlcd-sidebar-toggle"
                id="smlcd-sidebar-toggle"
                type="button"
                aria-label="Toggle navigation"
                aria-expanded="true"
                aria-controls="smlcd-sidebar"
            >
                <span class="ti ti-menu-2" aria-hidden="true"></span>
            </button>

            <div class="smlcd-topbar-right">
                <?php if ( $principal ) : ?>
                    <span class="smlcd-principal-name">
                        <?php echo esc_html( $principal->get_display_name() ); ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>

        <?php /* -----------------------------------------------
         * CONTENT AREA
         * Three mutually exclusive states: loader, content, error.
         * JS toggles visibility between them on each fetch.
         * ----------------------------------------------- */ ?>
        <div
            class="smlcd-content-area"
            id="smlcd-content-area"
            aria-live="polite"
            aria-busy="false"
        >
            <?php /* Loading state */ ?>
            <div class="smlcd-loader" id="smlcd-loader" hidden aria-label="Loading">
                <span class="smlcd-loader-spinner" aria-hidden="true"></span>
                <span class="smlcd-loader-text">Loading...</span>
            </div>

            <?php /* Rendered section content injected here by JS */ ?>
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

<?php /* -----------------------------------------------
 * BOOTSTRAP SCRIPT
 *
 * Reads rest_base and active_slug from <meta> tags so the
 * script stays CSP-safe (no inline data from PHP).
 *
 * Depends on window.smliserDashboardFetch being defined by
 * the Set-based cache script loaded before this template.
 * ----------------------------------------------- */ ?>
<script>
( function () {
    'use strict';

    const REST_BASE   = document.querySelector( 'meta[name="smliser-rest-base"]' )?.content   ?? '';
    const ACTIVE_SLUG = document.querySelector( 'meta[name="smliser-active-slug"]' )?.content ?? '';

    const layout   = document.getElementById( 'smlcd-layout' );
    const toggle   = document.getElementById( 'smlcd-sidebar-toggle' );
    const area     = document.getElementById( 'smlcd-content-area' );
    const content  = document.getElementById( 'smlcd-content' );
    const loader   = document.getElementById( 'smlcd-loader' );
    const error    = document.getElementById( 'smlcd-error' );
    const errorMsg = document.getElementById( 'smlcd-error-message' );
    const retryBtn = document.getElementById( 'smlcd-error-retry' );

    /*
    |------------------
    | SIDEBAR TOGGLE
    |------------------
    */
    toggle?.addEventListener( 'click', () => {
        const collapsed = layout.classList.toggle( 'smlcd-layout--collapsed' );
        toggle.setAttribute( 'aria-expanded', String( ! collapsed ) );
    } );

    /*
    |------------------
    | STATE HELPERS
    |------------------
    */

    /**
     * Switch the content area into loading state.
     *
     * @param {boolean} loading
     */
    function setLoading( loading ) {
        area.setAttribute( 'aria-busy', String( loading ) );
        loader.hidden  = ! loading;
        content.hidden = loading;
        error.hidden   = true;
    }

    /**
     * Switch the content area into error state.
     *
     * @param {string} message
     * @param {string} slug     — passed to retry handler
     */
    function showError( message, slug ) {
        area.setAttribute( 'aria-busy', 'false' );
        loader.hidden  = true;
        content.hidden = true;
        error.hidden   = false;
        errorMsg.textContent = message || 'Something went wrong. Please try again.';
        retryBtn.onclick = () => loadSection( slug, true );
    }

    /*
    |------------------
    | ACTIVE MENU ITEM
    |------------------
    */

    /**
     * Toggle the active class and aria-current on all nav items.
     *
     * @param {string} slug
     */
    function setActive( slug ) {
        document.querySelectorAll( '.smlcd-nav-item' ).forEach( item => {
            const active = item.dataset.slug === slug;
            item.classList.toggle( 'smlcd-nav-item--active', active );
            item.setAttribute( 'aria-current', active ? 'page' : 'false' );
        } );
    }

    /*
    |------------------
    | SECTION LOADER
    |------------------
    */

    /**
     * Fetch a dashboard section via the REST API and inject it into
     * the content area. Delegates caching to window.smliserDashboardFetch.
     *
     * @param {string}  slug
     * @param {boolean} [force=false]  Bypass cache when true (e.g. retry).
     */
    function loadSection( slug, force = false ) {
        if ( ! slug || ! REST_BASE ) return;
        console.log( REST_BASE );

        setLoading( true );
        setActive( slug );

        window
            .smliserDashboardFetch( REST_BASE + slug, { force } )
            .then( ( html ) => {
                content.innerHTML = html;
                setLoading( false );
            } )
            .catch( ( err ) => {
                showError( err?.message ?? 'Failed to load section.', slug );
            } );
    }

    /*
    |------------------
    | MENU DELEGATION
    |------------------
    */
    document.getElementById( 'smlcd-sidebar' )?.addEventListener( 'click', ( e ) => {
        const item = e.target.closest( '.smlcd-nav-item[data-slug]' );
        if ( ! item ) return;
        e.preventDefault();
        loadSection( item.dataset.slug );
    } );

    /*
    |------------------
    | INITIAL LOAD
    |------------------
    */
    if ( ACTIVE_SLUG ) {
        loadSection( ACTIVE_SLUG );
    }

} () );
</script>