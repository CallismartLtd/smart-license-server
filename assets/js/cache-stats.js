/**
 * Cache statistics dashboard — client-side behaviour.
 *
 * Responsibilities:
 *  1. Refresh button  — re-fetch stats via AJAX and re-render cards + perf panel.
 *  2. Confirm buttons — gate destructive actions behind a modal before forwarding
 *                       to the global smliserActionBtns handler.
 *  3. Auto-refresh    — re-render after any smliser:action_success event.
 *
 * Depends on: smliser_var.ajaxURL, smliser_var.csrf_token,
 *             SmliserModal.confirm, smliserActionBtns, smliserFetchJSON.
 *
 * @package SmartLicenseServer
 * @since   0.2.0
 */

( function () {
    'use strict';

    const wrap = document.querySelector( '.smlcd-wrap' );
    if ( ! wrap ) return;

    /* ── Helpers ──────────────────────────────────────────────────────────── */

    function ajaxUrl( action, nonce ) {
        const url = new URL( smliser_var.ajaxURL, window.location.origin );
        url.searchParams.set( 'action',   action );
        url.searchParams.set( 'security', nonce  );
        return url;
    }

    function fmtBytes( bytes ) {
        if ( ! bytes || bytes <= 0 ) return '0 B';
        const u = [ 'B', 'KB', 'MB', 'GB' ];
        const i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
        return ( bytes / Math.pow( 1024, i ) ).toFixed( 2 ) + '\u00a0' + u[ i ];
    }

    function fmtDuration( secs ) {
        if ( secs <= 0 ) return '—';
        const d = Math.floor( secs / 86400 );
        const h = Math.floor( ( secs % 86400 ) / 3600 );
        const m = Math.floor( ( secs % 3600  ) / 60   );
        const s = secs % 60;
        const p = [];
        if ( d )        p.push( d + 'd' );
        if ( h )        p.push( h + 'h' );
        if ( m )        p.push( m + 'm' );
        if ( s && ! d ) p.push( s + 's' );
        return p.join( '\u00a0' ) || '< 1s';
    }

    function escHtml( str ) {
        return String( str )
            .replace( /&/g, '&amp;' ).replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
    }

    /* ── 1. Refresh ───────────────────────────────────────────────────────── */

    const refreshBtn = wrap.querySelector( '.smlcd-refresh-btn' );

    if ( refreshBtn ) {
        refreshBtn.addEventListener( 'click', async () => {
            refreshBtn.classList.add( 'smlcd-spinning' );
            refreshBtn.disabled = true;
            try {
                await refreshStats();
            } finally {
                refreshBtn.classList.remove( 'smlcd-spinning' );
                refreshBtn.disabled = false;
            }
        } );
    }

    /**
     * Fetch fresh stats from the server and re-render cards + perf panel in-place.
     *
     * Controller returns:
     *   { success: true, data: { adapter_id, adapter_name, stats: CacheStats::to_array() } }
     *
     * @returns {Promise<void>}
     */
    async function refreshStats() {
        const cards = document.getElementById( 'smlcd-stat-cards' );
        if ( ! cards ) return;

        cards.classList.add( 'smlcd-cards--loading' );

        try {
            const url    = ajaxUrl( 'smliser_cache_get_stats', smliser_var.csrf_token );
            const result = await smliserFetchJSON( url, { method: 'GET' } );

            if ( result.success && result.data?.stats ) {
                const s          = result.data.stats;
                const adapterId  = result.data.adapter_id ?? wrap.dataset.adapter ?? '';
                renderCards( cards, s, adapterId );
                renderPerf( s );
            }
        } catch ( err ) {
            // Silent fail — cards keep their last-known values.
            console.warn( '[smlcd] Could not refresh stats:', err.message );
        } finally {
            cards.classList.remove( 'smlcd-cards--loading' );
        }
    }

    /**
     * Re-render stat cards from a CacheStats payload.
     *
     * @param {HTMLElement} container
     * @param {Object}      s           CacheStats::to_array()
     * @param {string}      adapterId
     */
    function renderCards( container, s, adapterId ) {
        const hits       = s.hits         ?? 0;
        const misses     = s.misses       ?? 0;
        const entries    = s.entries      ?? 0;
        const memUsed    = s.memory_used  ?? 0;
        const memTotal   = s.memory_total ?? 0;
        const uptime     = s.uptime       ?? 0;
        const extra      = s.extra        ?? {};

        const totalReq    = hits + misses;
        const tracksHits  = totalReq > 0;
        const hitPct      = tracksHits ? +( ( hits   / totalReq ) * 100 ).toFixed( 1 ) : null;
        const missPct     = tracksHits ? +( ( misses / totalReq ) * 100 ).toFixed( 1 ) : null;
        const hasMemCeil  = memTotal > 0;
        const memPct      = hasMemCeil ? +( ( memUsed / memTotal ) * 100 ).toFixed( 1 ) : null;

        const hitGrade    = hitPct === null ? 'neutral'
            : hitPct >= 70 ? 'good' : hitPct >= 50 ? 'warn' : 'bad';
        const memGrade    = memPct === null ? 'neutral'
            : memPct >= 90 ? 'bad' : memPct >= 70 ? 'warn' : 'good';

        const expired     = extra.expired_entries ?? extra.expired_slots ?? 0;

        const uptimeStr   = uptime > 0 ? fmtDuration( uptime ) : null;
        const uptimeSub   = uptime > 0 ? 'Since last restart'
            : ( [ 'runtime', 'arraycache' ].includes( adapterId ) ? 'Request-scoped' : 'Not reported' );

        container.innerHTML = `
            <div class="smlcd-card smlcd-card--${ hitGrade }">
                <div class="smlcd-card__label">Hit Rate</div>
                <div class="smlcd-card__value">
                    ${ hitPct !== null
                        ? `${ hitPct }<span class="smlcd-card__unit">%</span>`
                        : `<span class="smlcd-card__na">—</span>` }
                </div>
                <div class="smlcd-card__detail">
                    ${ tracksHits
                        ? `${ hits.toLocaleString() } hits · ${ misses.toLocaleString() } misses`
                        : 'Not tracked by this adapter' }
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Total Requests</div>
                <div class="smlcd-card__value">
                    ${ tracksHits
                        ? `${ totalReq.toLocaleString() }`
                        : `<span class="smlcd-card__na">—</span>` }
                </div>
                <div class="smlcd-card__detail">
                    ${ tracksHits ? `Miss rate: ${ missPct }%` : 'Not tracked by this adapter' }
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Cached Entries</div>
                <div class="smlcd-card__value">${ entries.toLocaleString() }</div>
                <div class="smlcd-card__detail">
                    ${ expired > 0
                        ? `<span class="smlcd-warn">${ expired.toLocaleString() } expired</span>`
                        : 'All entries live' }
                </div>
            </div>

            <div class="smlcd-card smlcd-card--${ memGrade }">
                <div class="smlcd-card__label">Memory</div>
                <div class="smlcd-card__value">
                    ${ memPct !== null
                        ? `${ memPct }<span class="smlcd-card__unit">%</span>`
                        : memUsed > 0
                            ? escHtml( fmtBytes( memUsed ) )
                            : `<span class="smlcd-card__na">—</span>` }
                </div>
                <div class="smlcd-card__detail">
                    ${ hasMemCeil
                        ? `${ fmtBytes( memUsed ) } of ${ fmtBytes( memTotal ) }`
                        : memUsed > 0
                            ? `${ fmtBytes( memUsed ) } · No fixed ceiling`
                            : 'Not reported' }
                </div>
            </div>

            <div class="smlcd-card smlcd-card--neutral">
                <div class="smlcd-card__label">Uptime</div>
                <div class="smlcd-card__value">
                    ${ uptimeStr
                        ? escHtml( uptimeStr )
                        : `<span class="smlcd-card__na">—</span>` }
                </div>
                <div class="smlcd-card__detail">${ escHtml( uptimeSub ) }</div>
            </div>`;
    }

    /**
     * Update the performance breakdown panel from fresh stats.
     * Removes the panel entirely if the adapter doesn't track hits.
     *
     * @param {Object} s  CacheStats::to_array()
     */
    function renderPerf( s ) {
        const perf = document.getElementById( 'smlcd-perf' );

        const hits    = s.hits   ?? 0;
        const misses  = s.misses ?? 0;
        const total   = hits + misses;

        if ( ! total ) {
            // Adapter doesn't track hits — remove the panel if it exists.
            perf?.remove();
            return;
        }

        const memUsed  = s.memory_used  ?? 0;
        const memTotal = s.memory_total ?? 0;

        const hitPct  = +( ( hits   / total ) * 100 ).toFixed( 1 );
        const missPct = +( ( misses / total ) * 100 ).toFixed( 1 );
        const memPct  = memTotal > 0 ? +( ( memUsed / memTotal ) * 100 ).toFixed( 1 ) : null;
        const memGrade = memPct === null ? 'neutral'
            : memPct >= 90 ? 'bad' : memPct >= 70 ? 'warn' : 'good';

        const rating = hitPct >= 80 ? 'excellent'
            : hitPct >= 60 ? 'good' : hitPct >= 40 ? 'fair' : 'poor';
        const ratingLabel = rating.charAt( 0 ).toUpperCase() + rating.slice( 1 );

        const memRow = memPct !== null ? `
            <div class="smlcd-perf__mem">
                <span class="smlcd-perf__mem-label">Memory pressure</span>
                <div class="smlcd-perf__mem-track">
                    <div class="smlcd-perf__mem-fill smlcd-perf__mem-fill--${ memGrade }"
                         style="width:${ Math.min( memPct, 100 ) }%"></div>
                </div>
                <span class="smlcd-perf__mem-pct smlcd-perf__mem-pct--${ memGrade }">${ memPct }%</span>
            </div>` : '';

        const html = `
            <div class="smlcd-perf__header">
                <span class="smlcd-perf__title">Request Breakdown</span>
                <span class="smlcd-perf__total">${ total.toLocaleString() } total</span>
            </div>
            <div class="smlcd-perf__bar-wrap">
                <div class="smlcd-perf__bar">
                    <div class="smlcd-perf__bar-hit"  style="width:${ hitPct }%"  title="${ hitPct }% hits"></div>
                    <div class="smlcd-perf__bar-miss" style="width:${ missPct }%" title="${ missPct }% misses"></div>
                </div>
            </div>
            <div class="smlcd-perf__legend">
                <span class="smlcd-perf__legend-hit">
                    <span class="smlcd-perf__dot smlcd-perf__dot--hit"></span>
                    Hits <strong>${ hits.toLocaleString() }</strong> <em>${ hitPct }%</em>
                </span>
                <span class="smlcd-perf__legend-miss">
                    <span class="smlcd-perf__dot smlcd-perf__dot--miss"></span>
                    Misses <strong>${ misses.toLocaleString() }</strong> <em>${ missPct }%</em>
                </span>
                <span class="smlcd-perf__rating smlcd-perf__rating--${ rating }">${ ratingLabel }</span>
            </div>
            ${ memRow }`;

        if ( perf ) {
            perf.innerHTML = html;
        } else {
            // Panel didn't exist on first load (adapter had no data then) — create it.
            const newPanel = document.createElement( 'div' );
            newPanel.className = 'smlcd-perf';
            newPanel.id        = 'smlcd-perf';
            newPanel.innerHTML = html;
            document.getElementById( 'smlcd-stat-cards' )?.after( newPanel );
        }
    }

    /* ── 2. Confirm-action buttons ────────────────────────────────────────── */

    // Capture phase — fires before the bubble-phase global smliserActionBtns handler.
    wrap.addEventListener( 'click', async ( e ) => {
        const btn = e.target.closest( '.smlcd-confirm-btn' );
        if ( ! btn ) return;

        e.stopImmediatePropagation();

        const confirmed = await SmliserModal.confirm(
            btn.dataset.confirm || 'Are you sure?',
            'Confirm Action'
        );
        if ( ! confirmed ) return;

        smliserActionBtns( { target: btn } );

    }, true /* capture */ );

    /* ── 3. Auto-refresh after any successful action ──────────────────────── */

    document.addEventListener( 'smliser:action_success', () => {
        refreshStats();
    } );

} )();