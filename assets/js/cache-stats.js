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

    /**
     * Safely escape a string for HTML insertion.
     *
     * @param {string} str
     * @returns {string}
     */
    function escHtml( str ) {
        return String( str )
            .replace( /&/g,  '&amp;'  )
            .replace( /</g,  '&lt;'   )
            .replace( />/g,  '&gt;'   )
            .replace( /"/g,  '&quot;' )
            .replace( /'/g,  '&#039;' );
    }

    /**
     * Format a byte count into a human-readable string.
     *
     * @param {number} bytes
     * @returns {string}
     */
    function fmtBytes( bytes ) {
        if ( ! bytes || bytes <= 0 ) return '0 B';
        const units = [ 'B', 'KB', 'MB', 'GB' ];
        const i     = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
        return ( bytes / Math.pow( 1024, i ) ).toFixed( 2 ) + ' ' + units[ i ];
    }

    /**
     * Format a duration in seconds into a compact human-readable string.
     *
     * @param {number} secs
     * @returns {string}
     */
    function fmtSeconds( secs ) {
        if ( secs <= 0 ) return '< 1s';
        const d = Math.floor( secs / 86400 );
        const h = Math.floor( ( secs % 86400 ) / 3600 );
        const m = Math.floor( ( secs % 3600  ) / 60   );
        const s = secs % 60;
        const parts = [];
        if ( d )        parts.push( d + 'd' );
        if ( h )        parts.push( h + 'h' );
        if ( m )        parts.push( m + 'm' );
        if ( s && ! d ) parts.push( s + 's' );
        return parts.join( ' ' ) || '< 1s';
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
     * Re-fetch stats from the server and re-render the cards in-place.
     *
     * The controller (smliser_cache_get_stats) returns:
     *   { success: true, data: { adapter_id, adapter_name, stats: { hits, misses, … } } }
     *
     * We update innerHTML on the stable #smlc-stat-cards wrapper — never outerHTML —
     * so the container reference stays valid across the async gap and the finally
     * block can always clear the loading state on the same node.
     *
     * @returns {Promise<void>}
     */
    async function refreshStatCards() {
        const container = document.getElementById( 'smlc-stat-cards' );
        if ( ! container ) return;

        container.style.opacity       = '0.4';
        container.style.pointerEvents = 'none';

        try {
            const url    = ajaxUrl( 'smliser_cache_get_stats', smliser_var.nonce );
            const result = await smliserFetchJSON( url, { method: 'GET' } );

            if ( result.success && result.data?.stats ) {
                container.innerHTML = buildStatCards( result.data.stats );
            }
        } catch ( err ) {
            // Silent fail — cards keep showing their last-known values.
            console.warn( '[smlc] Could not refresh stats:', err.message );
        } finally {
            // container was never replaced so this always references the live node.
            container.style.opacity       = '';
            container.style.pointerEvents = '';
        }
    }

    /**
     * Build stat card inner HTML from a raw CacheStats payload.
     *
     * Mirrors the rendering logic in cache-stats.php so cards look identical
     * after a JS refresh as they do on first page load.
     *
     * @param {Object} s  CacheStats::to_array() payload
     * @returns {string}
     */
    function buildStatCards( s ) {
        const hits      = s.hits         ?? 0;
        const misses    = s.misses       ?? 0;
        const entries   = s.entries      ?? 0;
        const memUsed   = s.memory_used  ?? 0;
        const memTotal  = s.memory_total ?? 0;
        const uptime    = s.uptime       ?? 0;
        const extra     = s.extra        ?? {};

        const total      = hits + misses;
        const tracksHits = total > 0;
        const hitPct     = tracksHits ? +( ( hits / total ) * 100 ).toFixed( 1 ) : null;
        const hasMemCeil = memTotal > 0;
        const memPct     = hasMemCeil ? +( ( memUsed / memTotal ) * 100 ).toFixed( 1 ) : null;

        const hitColour = hitPct === null  ? 'neutral'
            : hitPct >= 70 ? 'good' : hitPct >= 50 ? 'warn' : 'bad';
        const memColour = memPct === null  ? 'neutral'
            : memPct >= 90 ? 'bad'  : memPct >= 70 ? 'warn' : 'good';

        const expired = extra.expired_entries ?? extra.expired_slots ?? 0;

        // Hit rate card
        const hitValue = hitPct !== null
            ? `${ hitPct }<span class="smlc-card__unit">%</span>`
            : `<span class="smlc-card__na">—</span>`;

        const hitSub = tracksHits
            ? `<span class="smlc-hit">${ hits.toLocaleString() } hits</span>
               <span class="smlc-dot">·</span>
               <span class="smlc-miss">${ misses.toLocaleString() } misses</span>`
            : `<span class="smlc-muted">Not tracked by this adapter</span>`;

        // Entries card
        const entriesSub = expired > 0
            ? `<span class="smlc-warn-txt">+${ expired.toLocaleString() } expired</span>`
            : `<span class="smlc-muted">All entries live</span>`;

        // Memory card
        const memValue = memPct !== null
            ? `${ memPct }<span class="smlc-card__unit">%</span>`
            : fmtBytes( memUsed );

        const memBar = hasMemCeil
            ? `<div class="smlc-mem-bar">
                   <div class="smlc-mem-bar__fill smlc-mem-bar__fill--${ memColour }"
                        style="width:${ Math.min( memPct, 100 ) }%"></div>
               </div>
               <div class="smlc-card__sub smlc-mem-detail">
                   <span>Used ${ fmtBytes( memUsed ) }</span>
                   <span>·</span>
                   <span>Free ${ fmtBytes( memTotal - memUsed ) }</span>
                   <span>·</span>
                   <span>Total ${ fmtBytes( memTotal ) }</span>
               </div>`
            : `<div class="smlc-card__sub smlc-muted">${
                  memUsed > 0 ? fmtBytes( memUsed ) + ' used · No fixed ceiling' : 'Not reported'
              }</div>`;

        // Uptime card
        const uptimeValue = uptime > 0
            ? fmtSeconds( uptime )
            : `<span class="smlc-card__na">—</span>`;

        const adapterHint = dash.dataset.adapter ?? '';
        const uptimeSub   = uptime > 0
            ? 'Since last restart'
            : ( adapterHint === 'runtime' || adapterHint === 'arraycache'
                ? 'Request-scoped'
                : 'Not reported' );

        return `
            <div class="smlc-card smlc-card--${ hitColour }">
                <div class="smlc-card__eyebrow">Hit Rate</div>
                <div class="smlc-card__value">${ hitValue }</div>
                <div class="smlc-card__sub">${ hitSub }</div>
            </div>

            <div class="smlc-card smlc-card--neutral">
                <div class="smlc-card__eyebrow">Cached Entries</div>
                <div class="smlc-card__value">${ entries.toLocaleString() }</div>
                <div class="smlc-card__sub">${ entriesSub }</div>
            </div>

            <div class="smlc-card smlc-card--${ memColour } smlc-card--wide">
                <div class="smlc-card__eyebrow">Memory</div>
                <div class="smlc-card__value">${ memValue }</div>
                ${ memBar }
            </div>

            <div class="smlc-card smlc-card--neutral">
                <div class="smlc-card__eyebrow">Uptime</div>
                <div class="smlc-card__value">${ uptimeValue }</div>
                <div class="smlc-card__sub smlc-muted">${ uptimeSub }</div>
            </div>`;
    }

    /* ── 2. Confirm-action buttons ────────────────────────────────────────── */

    // Use capture phase so this fires before the bubble-phase global
    // smliserActionBtns handler. No need for stopImmediatePropagation tricks
    // or class-add/remove races — we simply call smliserActionBtns directly
    // after the user confirms.
    dash.addEventListener( 'click', async ( e ) => {
        const btn = e.target.closest( '.smlc-confirm-btn' );
        if ( ! btn ) return;

        e.stopImmediatePropagation();

        const message   = btn.dataset.confirm || 'Are you sure you want to proceed?';
        const confirmed = await SmliserModal.confirm( message, 'Confirm Action' );
        if ( ! confirmed ) return;

        smliserActionBtns( { target: btn } );

    }, true /* capture phase */ );

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

        if ( ! value ) {
            input.classList.add( 'smlc-input--error' );
            input.focus();
            input.addEventListener( 'input', () => input.classList.remove( 'smlc-input--error' ), { once: true } );
            return;
        }

        const restore = setLoading( btn );

        try {
            const url    = ajaxUrl( action, nonce );
            // security must be in the POST body — the controller reads it via
            // $request->get('security'), not from the query string.
            const result = await smliserFetchJSON( url, {
                method : 'POST',
                body   : JSON.stringify( { value, security: nonce } ),
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

    const loadKeysBtn = document.getElementById( 'smlc-load-keys-btn' );
    const topNSelect  = document.getElementById( 'smlc-topn-select'   );
    const keysWrap    = document.getElementById( 'smlc-keys-wrap'     );

    if ( loadKeysBtn && keysWrap ) {
        loadKeysBtn.addEventListener( 'click', async () => {
            const limit   = parseInt( topNSelect?.value ?? '10', 10 );
            const restore = setLoading( loadKeysBtn );

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
     * @param {number} rowCount
     * @returns {string}
     */
    function buildSkeletonTable( rowCount ) {
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
                    <tr><th>#</th><th>Key</th><th>Hits</th><th>TTL Remaining</th><th>Size</th></tr>
                </thead>
                <tbody>${ skRow.repeat( rowCount ) }</tbody>
            </table>
        </div>`;
    }

    /* ── 5. Re-fetch cards after any successful cache action ──────────────── */

    document.addEventListener( 'smliser:action_success', () => {
        refreshStatCards();
    } );

} )();