/**
 * Site health page: non-blocking checks.
 *
 * Each task below makes its own request(s) to the site-health-check
 * endpoints and resolves to an array of checks, so a slow or failing
 * task doesn't hold up or break the others. Results are kept in
 * localStorage for CACHE_TTL and re-rendered on reload; "Run again"
 * bypasses them.
 *
 * Display strings (badge labels, icons, overall states) come from the
 * panel's data-config attribute, so the template stays their single
 * source.
 */
( function () {
	'use strict';

	const TIMEOUT_MS  = 30000;
	const CACHE_TTL   = 5 * 60 * 1000;
	const STORAGE_KEY = 'smliser_site_health_async';
	const TONE_PREFIX = 'smliser-health-tone--';
	const SPINNER     = 'ti ti-loader-2 smliser-health-spin';
	const SEVERITY    = { pass: 0, info: 1, warning: 2, critical: 3 };

	/**
	 * IDs and labels must match the server's checks (SystemManagement's
	 * constants, ToolsPage::database_check_data()) — they are used for the
	 * row shown when a task's request fails outright.
	 */
	const TASKS = [
		{
			id: 'remote_service_connection',
			label: 'Update Server Connection',
			run: () => requestChecks( 'remote-service' ),
		},
		{
			id: 'cache_persistence',
			label: 'Cache Persistence',
			run: runCachePersistence,
		},
		{
			id: 'database_connection',
			label: 'Database Connection',
			run: () => requestChecks( 'database' ),
		},
	];

	const state = {
		config: {},
		baseline: 'pass',  // Overall status from server-side checks alone.
		displayed: 'pass', // Overall status currently shown.
		applied: [],       // Async statuses currently added to the overview counts.
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		const panel = document.getElementById( 'smliser-health-async' );

		if ( ! panel ) {
			return;
		}

		state.config    = readConfig( panel );
		state.baseline  = state.config.overall_status || 'pass';
		state.displayed = state.baseline;

		const cached = readCache();

		if ( cached ) {
			render( panel, cached.checks, cached.time );
			return;
		}

		run( panel );
	} );

	/*
	|----------
	| LIFECYCLE
	|----------
	*/

	/**
	 * Run every task in parallel, render the results, and cache them
	 * unless a request failed outright.
	 *
	 * @param {HTMLElement} panel
	 * @return {Promise<void>}
	 */
	async function run( panel ) {
		const list = panel.querySelector( '.smliser-health-checks' );

		removeFooter( panel );
		setPanelState( panel, 'pending', SPINNER, '…' );
		list.replaceChildren( ...TASKS.map( ( task ) => buildMessageItem( 'pending', SPINNER, 'Running…', task.label ) ) );

		const results = await Promise.all( TASKS.map( runTask ) );
		const checks  = results.flatMap( ( result ) => result.checks );
		const time    = Date.now();

		// Don't pin a transient request failure for five minutes.
		if ( results.every( ( result ) => ! result.failed ) ) {
			writeCache( checks, time );
		}

		render( panel, checks, time );
	}

	/**
	 * Run one task, turning a request-level failure into a warning row
	 * for that task alone.
	 *
	 * @param {Object} task
	 * @return {Promise<{checks: Array<Object>, failed: boolean}>}
	 */
	async function runTask( task ) {
		try {
			return { checks: await task.run(), failed: false };
		} catch ( error ) {
			return {
				failed: true,
				checks: [ {
					id: task.id,
					label: task.label,
					status: 'warning',
					message: error?.message || 'This check could not run.',
					recommendation: 'Run the checks again. If this keeps failing, check the server error log.',
				} ],
			};
		}
	}

	/**
	 * Cache persistence takes two requests: cache-seed writes a probe,
	 * cache-verify reads it back in a separate request.
	 *
	 * @return {Promise<Array<Object>>}
	 */
	async function runCachePersistence() {
		const seed = await getJSON( 'cache-seed' );

		// The adapter was unusable; the seed response carries the failed check.
		if ( Array.isArray( seed?.checks ) && seed.checks.length > 0 ) {
			return seed.checks.map( normalizeCheck );
		}

		if ( 'string' !== typeof seed?.token || '' === seed.token ) {
			throw { message: 'The server returned an unexpected response.' };
		}

		return requestChecks( 'cache-verify', { token: seed.token } );
	}

	/*
	|--------
	| REQUEST
	|--------
	*/

	/**
	 * Fetch an endpoint that responds with { checks: [...] }.
	 *
	 * @param {string}                 path
	 * @param {Object<string, string>} query
	 * @return {Promise<Array<Object>>}
	 */
	async function requestChecks( path, query = {} ) {
		const body = await getJSON( path, query );

		if ( ! Array.isArray( body?.checks ) ) {
			throw { message: 'The server returned an unexpected response.' };
		}

		return body.checks.map( normalizeCheck );
	}

	/**
	 * GET a site-health-check endpoint with a timeout.
	 *
	 * smliserFetchJSON() throws a structured { message, code, status }
	 * object on HTTP, network and timeout failures.
	 *
	 * @param {string}                 path
	 * @param {Object<string, string>} query
	 * @return {Promise<Object>}
	 */
	async function getJSON( path, query = {} ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );

		try {
			return await smliserFetchJSON( buildURL( path, query ), {
				method: 'GET',
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}

	/**
	 * Build a site-health-check endpoint URL from the server-set ajax URL,
	 * tolerating a trailing slash on it.
	 *
	 * @param {string}                 path
	 * @param {Object<string, string>} query
	 * @return {URL}
	 */
	function buildURL( path, query ) {
		const url = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/site-health-check/${ path }`;

		Object.entries( query ).forEach( ( [ key, value ] ) => url.searchParams.set( key, value ) );

		return url;
	}

	/**
	 * Coerce one check to the expected shape. Unknown statuses become
	 * "info", matching how the template treats server-side checks.
	 *
	 * @param {*}      check
	 * @param {number} index
	 * @return {Object}
	 */
	function normalizeCheck( check, index ) {
		const raw    = check && 'object' === typeof check ? check : {};
		const status = Object.hasOwn( SEVERITY, raw.status ) ? raw.status : 'info';

		return {
			id: String( raw.id ?? `async-${ index }` ),
			label: String( raw.label ?? 'Unnamed check' ),
			status,
			message: String( raw.message ?? '' ),
			recommendation: 'string' === typeof raw.recommendation && '' !== raw.recommendation
				? raw.recommendation
				: null,
		};
	}

	/*
	|--------
	| STORAGE
	|--------
	*/

	/**
	 * Return cached results if still fresh. Expiry is checked on read, so
	 * a stale entry is ignored even if the tab that wrote it was closed.
	 *
	 * @return {{time: number, checks: Array<Object>}|null}
	 */
	function readCache() {
		try {
			const raw = JSON.parse( window.localStorage.getItem( STORAGE_KEY ) );

			if ( raw && Array.isArray( raw.checks ) && Date.now() - raw.time < CACHE_TTL ) {
				return { time: raw.time, checks: raw.checks.map( normalizeCheck ) };
			}

			window.localStorage.removeItem( STORAGE_KEY );
		} catch ( error ) {
			// Storage unavailable or entry malformed; run the checks instead.
		}

		return null;
	}

	function writeCache( checks, time ) {
		try {
			window.localStorage.setItem( STORAGE_KEY, JSON.stringify( { time, checks } ) );
		} catch ( error ) {
			// Storage unavailable or full; results just won't be reused.
		}
	}

	/*
	|----------
	| RENDERING
	|----------
	*/

	/**
	 * @param {HTMLElement}   panel
	 * @param {Array<Object>} checks
	 * @param {number}        time When the checks ran.
	 */
	function render( panel, checks, time ) {
		const list  = panel.querySelector( '.smliser-health-checks' );
		const worst = worstStatus( checks.map( ( check ) => check.status ) );

		setPanelState( panel, worst, iconFor( worst ), String( checks.length ) );

		if ( 0 === checks.length ) {
			list.replaceChildren( buildMessageItem( 'pass', iconFor( 'pass' ), 'No additional checks were returned.' ) );
		} else {
			list.replaceChildren( ...checks.map( ( check ) => buildCheckItem( check ) ) );
		}

		renderFooter( panel, time );
		applyOverview( checks );
	}

	/**
	 * @param {Object} check
	 * @return {HTMLLIElement}
	 */
	function buildCheckItem( check ) {
		const item   = el( 'li', `smliser-health-check ${ TONE_PREFIX }${ check.status }` );
		const icon   = el( 'span', 'smliser-health-check-icon' );
		const body   = el( 'div', 'smliser-health-check-body' );
		const header = el( 'div', 'smliser-health-check-header' );

		item.id = `smliser-health-check-${ check.id }`;
		icon.append( el( 'i', iconFor( check.status ) ) );
		header.append(
			el( 'span', 'smliser-health-check-label', check.label ),
			el( 'span', 'smliser-health-badge', labelFor( check.status ) )
		);
		body.append( header, el( 'p', 'smliser-health-check-message', check.message ) );

		if ( null !== check.recommendation && check.recommendation !== check.message ) {
			const recommendation = el( 'p', 'smliser-health-check-recommendation' );

			recommendation.append( el( 'i', 'ti ti-bulb' ), el( 'span', '', check.recommendation ) );
			body.append( recommendation );
		}

		item.append( icon, body );

		return item;
	}

	/**
	 * A single status row that isn't a check result (loading, empty).
	 *
	 * @param {string}      tone
	 * @param {string}      iconClass
	 * @param {string}      message
	 * @param {string|null} label
	 * @return {HTMLLIElement}
	 */
	function buildMessageItem( tone, iconClass, message, label = null ) {
		const item = el( 'li', `smliser-health-check ${ TONE_PREFIX }${ tone }` );
		const icon = el( 'span', 'smliser-health-check-icon' );
		const body = el( 'div', 'smliser-health-check-body' );

		icon.append( el( 'i', iconClass ) );

		if ( label ) {
			const header = el( 'div', 'smliser-health-check-header' );

			header.append( el( 'span', 'smliser-health-check-label', label ) );
			body.append( header );
		}

		body.append( el( 'p', 'smliser-health-check-message', message ) );
		item.append( icon, body );

		return item;
	}

	/**
	 * "Last checked" line and the "Run again" button.
	 *
	 * @param {HTMLElement} panel
	 * @param {number}      time
	 */
	function renderFooter( panel, time ) {
		const footer = el( 'div', 'smliser-health-async-footer' );
		const button = el( 'button', 'smliser-btn smliser-btn-glass smliser-health-retry' );

		button.type = 'button';
		button.append( el( 'i', 'ti ti-refresh' ), document.createTextNode( 'Run again' ) );
		button.addEventListener( 'click', () => run( panel ), { once: true } );

		footer.append( el( 'span', 'smliser-health-async-time', `Last checked ${ formatAge( time ) }` ), button );

		removeFooter( panel );
		panel.querySelector( '.smliser-diagnostics-panel-body' ).append( footer );
	}

	function removeFooter( panel ) {
		panel.querySelector( '.smliser-health-async-footer' )?.remove();
	}

	/**
	 * Fold async results into the overview counts and overall status.
	 *
	 * Replaces the previous run's contribution rather than adding to it,
	 * so "Run again" doesn't double-count, and the overall status can
	 * return to the server-side baseline once an async issue is fixed.
	 *
	 * @param {Array<Object>} checks
	 */
	function applyOverview( checks ) {
		const overview = document.getElementById( 'smliser-health-overview' );

		if ( ! overview ) {
			return;
		}

		const statuses = checks.map( ( check ) => check.status );

		adjustCounts( overview, state.applied, -1 );
		adjustCounts( overview, statuses, 1 );
		state.applied = statuses;

		const next = worstStatus( [ state.baseline, ...statuses.filter( isProblem ) ] );
		const def  = state.config.overall?.[ next ];

		if ( next === state.displayed || ! def ) {
			return;
		}

		const escalated = SEVERITY[ next ] > SEVERITY[ state.displayed ];

		setTone( overview, next );
		overview.querySelector( '.smliser-health-overview-icon i' ).className = def.icon;
		overview.querySelector( '.smliser-health-overview-title' ).textContent   = def.title;
		overview.querySelector( '.smliser-health-overview-message' ).textContent = def.message;
		state.displayed = next;

		if ( escalated ) {
			SmliserToast.show( `Additional checks found ${ statuses.filter( isProblem ).length } issue(s).`, {
				type: 'critical' === next ? 'error' : 'warning',
			} );
		}
	}

	function adjustCounts( overview, statuses, delta ) {
		statuses.forEach( ( status ) => {
			const value = overview.querySelector( `[data-health-count="${ status }"] .smliser-health-count-value` );

			if ( value ) {
				value.textContent = String( Math.max( 0, ( parseInt( value.textContent, 10 ) || 0 ) + delta ) );
			}
		} );
	}

	/*
	|--------
	| HELPERS
	|--------
	*/

	function readConfig( panel ) {
		try {
			return JSON.parse( panel.dataset.config || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function setPanelState( panel, tone, iconClass, countText ) {
		const icon  = panel.querySelector( '.smliser-diagnostics-panel-icon i' );
		const count = panel.querySelector( '.smliser-diagnostics-panel-count' );

		setTone( panel, tone );

		if ( icon ) {
			icon.className = iconClass;
		}

		if ( count ) {
			count.textContent = countText;
		}
	}

	function setTone( node, tone ) {
		[ ...node.classList ]
			.filter( ( name ) => name.startsWith( TONE_PREFIX ) )
			.forEach( ( name ) => node.classList.remove( name ) );

		node.classList.add( `${ TONE_PREFIX }${ tone }` );
	}

	function worstStatus( statuses ) {
		return statuses.reduce(
			( worst, status ) => ( SEVERITY[ status ] > SEVERITY[ worst ] ? status : worst ),
			'pass'
		);
	}

	function isProblem( status ) {
		return 'critical' === status || 'warning' === status;
	}

	function formatAge( time ) {
		const minutes = Math.floor( ( Date.now() - time ) / 60000 );

		return minutes < 1 ? 'just now' : `${ minutes } min ago`;
	}

	function iconFor( status ) {
		return state.config.statuses?.[ status ]?.icon ?? 'ti ti-info-circle';
	}

	function labelFor( status ) {
		return state.config.statuses?.[ status ]?.label ?? status;
	}

	function el( tag, className = '', text = null ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( null !== text ) {
			node.textContent = text;
		}

		return node;
	}
}() );