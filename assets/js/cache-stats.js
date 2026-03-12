/**
 * Cache statistics dashboard — client-side behaviour.
 *
 * Responsibilities:
 *  1. Refresh button — live stat card reload via AJAX (no full page reload).
 *  2. Confirm-action buttons — intercept .smlc-confirm-btn before dispatching
 *     to the global smliserActionBtns handler.
 *  3. Input-action buttons (.smlc-input-action-btn) — read value from a
 *     companion input, validate it, then fire the AJAX action.
 *  4. Top-keys browser — load APCu key table on demand.
 *  5. Re-fetch stat cards after any successful cache action.
 *
 * Depends on: smliserActionBtns (global), smliser_var.ajaxURL, smliser_var.nonce,
 *             SmliserModal.confirm / .success / .error, smliserFetchJSON.
 *
 * @package SmartLicenseServer
 * @since   0.2.0
 */

( function () {
    'use strict';

    const dash = document.querySelector( '.smlc-dash' );
    if ( ! dash ) return;

    const adapter = dash.dataset.adapter ?? '';

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    /**
     * Build a WordPress AJAX URL with the given action and nonce appended.
     *
     * @param {string} action - wp_ajax_{action} hook name.
     * @param {string} nonce  - Security nonce.
     * @returns {URL}
     */
    function ajaxUrl( action, nonce ) {
        const url = new URL( smliser_var.ajaxURL, window.location.origin );
        url.searchParams.set( 'action',   action );
        url.searchParams.set( 'security', nonce  );
        return url;
    }

    /**
     * Set a button into a loading state and return a restore function.
     *
     * @param {HTMLButtonElement} btn
     * @returns {() => void} Call to restore the button.
     */
    function setLoading( btn ) {
        const original = btn.innerHTML;
        btn.disabled   = true;
        btn.innerHTML  = original + ' <span class="ti ti-loader-2 smlc-spin-inline"></span>';
        return () => {
            btn.disabled  = false;
            btn.innerHTML = original;
        };
    }

    /* ── 1. Refresh button ────────────────────────────────────────────────── */

    const refreshBtn = dash.querySelector( '.smlc-refresh-btn' );

    if ( refreshBtn ) {
        refreshBtn.addEventListener( 'click', async () => {
            refreshBtn.classList.add( 'smlc-spinning' );
            refreshBtn.disabled = true;

            try {
                await refreshStatCards();
            } finally {
                refreshBtn.classList.remove( 'smlc-spinning' );
                refreshBtn.disabled = false;
            }
        } );
    }

    /**
     * Re-fetch stat card HTML from the server and swap the cards container.
     *
     * The AJAX handler (smliser_cache_get_stats) should return:
     *   { success: true, data: { html: '<div class="smlc-cards">…</div>' } }
     *
     * @returns {Promise<void>}
     */
    async function refreshStatCards() {
        const container = document.getElementById( 'smlc-stat-cards' );
        if ( ! container ) return;

        // Show skeleton while loading
        container.style.opacity = '.4';
        container.style.pointerEvents = 'none';

        try {
            const url    = ajaxUrl( 'smliser_cache_get_stats', smliser_var.nonce );
            const result = await smliserFetchJSON( url, { method: 'GET' } );

            if ( result.success && result.data?.html ) {
                container.outerHTML = result.data.html;
            }
        } catch ( err ) {
            // Silently fail — the cards keep showing their last-known values.
            console.warn( '[smlc] Could not refresh stats:', err.message );
        } finally {
            const fresh = document.getElementById( 'smlc-stat-cards' );
            if ( fresh ) {
                fresh.style.opacity       = '';
                fresh.style.pointerEvents = '';
            }
        }
    }

    /* ── 2. Confirm-action buttons ────────────────────────────────────────── */

    dash.addEventListener( 'click', async ( e ) => {
        const btn = e.target.closest( '.smlc-confirm-btn' );
        if ( ! btn ) return;

        // Prevent the global smliserActionBtns from firing immediately —
        // we gate it behind a confirmation modal first.
        e.stopImmediatePropagation();

        const message = btn.dataset.confirm || 'Are you sure you want to proceed?';

        const confirmed = await SmliserModal.confirm( message, 'Confirm Action' );
        if ( ! confirmed ) return;

        // Re-dispatch as a regular .smliser-action-button click so the
        // global handler takes over from here.
        btn.classList.add( 'smliser-action-button' );
        btn.dispatchEvent( new MouseEvent( 'click', { bubbles: true } ) );
        btn.classList.remove( 'smliser-action-button' );
    } );

    /* ── 3. Input-action buttons ──────────────────────────────────────────── */

    dash.addEventListener( 'click', async ( e ) => {
        const btn = e.target.closest( '.smlc-input-action-btn' );
        if ( ! btn ) return;

        const inputSel = btn.dataset.input;
        const action   = btn.dataset.action;
        const nonce    = btn.dataset.nonce;

        if ( ! inputSel || ! action || ! nonce ) {
            console.warn( '[smlc] Input-action button is missing data attributes.', btn );
            return;
        }

        const input = dash.querySelector( inputSel );
        if ( ! input ) return;

        const value = input.value.trim();

        // Validate: must not be empty.
        if ( ! value ) {
            input.classList.add( 'smlc-input--error' );
            input.focus();
            input.addEventListener( 'input', () => input.classList.remove( 'smlc-input--error' ), { once: true } );
            return;
        }

        const restore = setLoading( btn );

        try {
            const url    = ajaxUrl( action, nonce );
            const result = await smliserFetchJSON( url, {
                method: 'POST',
                body:   JSON.stringify( { value } ),
            } );

            if ( result.success ) {
                SmliserModal.success( result.data?.message || 'Done.', 'Success' );
                input.value = '';
                document.dispatchEvent( new CustomEvent( 'smliser:action_success', {
                    detail: { action, value, result },
                } ) );
            } else {
                throw new Error( result.data?.message || 'Operation failed.' );
            }
        } catch ( err ) {
            SmliserModal.error( err.message, 'Error' );
        } finally {
            restore();
        }
    } );

    /* ── 4. Top-keys browser ──────────────────────────────────────────────── */

    const loadKeysBtn  = document.getElementById( 'smlc-load-keys-btn' );
    const topNSelect   = document.getElementById( 'smlc-topn-select' );
    const keysWrap     = document.getElementById( 'smlc-keys-wrap' );

    if ( loadKeysBtn && keysWrap ) {
        loadKeysBtn.addEventListener( 'click', async () => {
            const limit   = parseInt( topNSelect?.value ?? '10', 10 );
            const restore = setLoading( loadKeysBtn );

            // Show skeleton table while loading
            keysWrap.innerHTML = buildSkeletonTable( Math.min( limit, 8 ) );

            try {
                const url = ajaxUrl( 'smliser_cache_get_top_keys', smliser_var.nonce );
                url.searchParams.set( 'limit', limit );

                const result = await smliserFetchJSON( url, { method: 'GET' } );

                if ( result.success && Array.isArray( result.data?.keys ) ) {
                    keysWrap.innerHTML = buildKeysTable( result.data.keys );
                } else {
                    throw new Error( result.data?.message || 'Could not load keys.' );
                }
            } catch ( err ) {
                keysWrap.innerHTML = `
                    <div class="smlc-unavailable">
                        <i class="ti ti-alert-triangle"></i>
                        <div><strong>Could not load keys</strong><p>${ escHtml( err.message ) }</p></div>
                    </div>`;
            } finally {
                restore();
            }
        } );
    }

    /**
     * Build the keys table HTML from the AJAX response.
     *
     * Each key entry shape:
     *   { key: string, hits: number, ttl: number|null, size: number }
     *
     * @param {Array<{key:string, hits:number, ttl:number|null, size:number}>} keys
     * @returns {string}
     */
    function buildKeysTable( keys ) {
        if ( ! keys.length ) {
            return `<div class="smlc-keys-placeholder"><i class="ti ti-inbox"></i><span>No keys found</span></div>`;
        }

        const rows = keys.map( ( entry, i ) => {
            const rank    = i + 1;
            const rankCls = rank <= 3 ? `smlc-key-rank--${ rank }` : '';
            const ttlStr  = entry.ttl === null || entry.ttl < 0
                ? '<span class="smlc-muted">∞</span>'
                : `<span class="smlc-key-ttl">${ fmtSeconds( entry.ttl ) }</span>`;

            return `
            <tr>
                <td><span class="smlc-key-rank ${ rankCls }">${ rank }</span></td>
                <td title="${ escHtml( entry.key ) }">${ escHtml( entry.key ) }</td>
                <td><span class="smlc-key-hits">${ entry.hits.toLocaleString() }</span></td>
                <td>${ ttlStr }</td>
                <td><span class="smlc-key-size">${ fmtBytes( entry.size ) }</span></td>
            </tr>`;
        } ).join( '' );

        return `
        <div class="smlc-keys-table-wrap">
            <table class="smlc-keys-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Key</th>
                        <th>Hits</th>
                        <th>TTL Remaining</th>
                        <th>Size</th>
                    </tr>
                </thead>
                <tbody>${ rows }</tbody>
            </table>
        </div>`;
    }

    /**
     * Build a shimmer skeleton table for the loading state.
     *
     * @param {number} rows
     * @returns {string}
     */
    function buildSkeletonTable( rows ) {
        const skRow = `
        <tr>
            <td><span class="smlc-skeleton" style="width:22px;height:22px;border-radius:50%;display:inline-block"></span></td>
            <td><span class="smlc-skeleton" style="width:60%;height:12px;display:block"></span></td>
            <td><span class="smlc-skeleton" style="width:40px;height:12px;display:block"></span></td>
            <td><span class="smlc-skeleton" style="width:50px;height:12px;display:block"></span></td>
            <td><span class="smlc-skeleton" style="width:40px;height:12px;display:block"></span></td>
        </tr>`;

        return `
        <div class="smlc-keys-table-wrap">
            <table class="smlc-keys-table">
                <thead>
                    <tr>
                        <th>#</th><th>Key</th><th>Hits</th><th>TTL Remaining</th><th>Size</th>
                    </tr>
                </thead>
                <tbody>${ skRow.repeat( rows ) }</tbody>
            </table>
        </div>`;
    }

    /* ── 5. Refresh cards after any successful action ─────────────────────── */

    document.addEventListener( 'smliser:action_success', () => {
        refreshStatCards();
    } );

    /* ── Formatting utilities ─────────────────────────────────────────────── */

    function fmtBytes( bytes ) {
        if ( ! bytes || bytes <= 0 ) return '0 B';
        const units = [ 'B', 'KB', 'MB', 'GB' ];
        const i     = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
        return ( bytes / Math.pow( 1024, i ) ).toFixed( 2 ) + ' ' + units[ i ];
    }

    function fmtSeconds( secs ) {
        if ( secs <= 0 ) return '< 1s';
        const d = Math.floor( secs / 86400 );
        const h = Math.floor( ( secs % 86400 ) / 3600 );
        const m = Math.floor( ( secs % 3600 ) / 60 );
        const s = secs % 60;
        const parts = [];
        if ( d ) parts.push( d + 'd' );
        if ( h ) parts.push( h + 'h' );
        if ( m ) parts.push( m + 'm' );
        if ( s && ! d ) parts.push( s + 's' );
        return parts.join( ' ' ) || '< 1s';
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;'  )
            .replace( />/g, '&gt;'  )
            .replace( /"/g, '&quot;' )
            .replace( /'/g, '&#039;' );
    }

} )();