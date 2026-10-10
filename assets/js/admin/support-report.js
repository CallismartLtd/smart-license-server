/**
 * "Copy report" buttons on the Site Health and System Diagnostics pages.
 *
 * Builds a plain-text report for support requests from what the page
 * already shows, so nothing is computed twice:
 *
 *  - Site Health: every check on the page (the additional checks too,
 *    once they have finished), then the System Diagnostics, fetched from
 *    site-health-check/diagnostics only when the button is clicked —
 *    collecting them is too slow for every page load.
 *  - System Diagnostics: the panels on the page.
 *
 * A button carries data-support-report="health" or "diagnostics" and
 * data-report-meta, the header from ToolsPage::report_meta().
 * Diagnostics never include passwords, keys or .env contents.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 60000;
	const STATUSES   = [ 'critical', 'warning', 'info', 'pass' ];
	const TONE       = 'smliser-health-tone--';

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-support-report]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => copyReport( button ) );
		} );
	} );

	/**
	 * Build the report for the button's page and copy it.
	 *
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function copyReport( button ) {
		const kind    = button.dataset.supportReport;
		const meta    = readJSON( button.dataset.reportMeta );
		const restore = setBusy( button );

		let text;

		try {
			text = 'health' === kind
				? await healthReport( meta )
				: header( 'System Diagnostics', meta ) + formatDiagnostics( diagnosticsFromPage() ).replace( /^\n/, '' );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The report could not be built.', { type: 'error' } );
			return;
		}

		restore();

		try {
			await writeClipboard( text );
			SmliserToast.show( 'Report copied. Paste it into your support request.', { type: 'success' } );
		} catch ( error ) {
			showForManualCopy( text );
		}
	}

	/*
	|--------
	| REPORTS
	|--------
	*/

	/**
	 * Site Health: the checks on the page, then the diagnostics from the server.
	 *
	 * @param {Object} meta
	 * @return {Promise<string>}
	 */
	async function healthReport( meta ) {
		const { checks, pending } = checksFromPage();
		const lines               = [ '== Health Checks ==' ];

		checks
			.sort( ( a, b ) => STATUSES.indexOf( a.status ) - STATUSES.indexOf( b.status ) )
			.forEach( ( check ) => {
				lines.push( `[${ check.status.toUpperCase() }] ${ check.label }: ${ check.message }` );

				if ( check.recommendation ) {
					lines.push( `    Recommendation: ${ check.recommendation }` );
				}
			} );

		if ( pending > 0 ) {
			lines.push( `(${ pending } additional check(s) were still running when this report was copied.)` );
		}

		let diagnostics;

		try {
			diagnostics = formatDiagnostics( ( await fetchDiagnostics() ).diagnostics ?? {} );
		} catch ( error ) {
			diagnostics = `\n== System Diagnostics ==\nCould not be collected: ${ error?.message || 'request failed' }\n`;
		}

		return header( 'Site Health', meta ) + lines.join( '\n' ) + '\n' + diagnostics;
	}

	/**
	 * Report header.
	 *
	 * @param {string} title
	 * @param {Object} meta {product, version, schema, site}
	 * @return {string}
	 */
	function header( title, meta ) {
		return [
			`${ meta.product || 'Smart License Server' } ${ title } Report`,
			`Generated: ${ new Date().toISOString().replace( 'T', ' ' ).replace( /\.\d+Z$/, ' UTC' ) }`,
			`Site: ${ meta.site || window.location.origin }`,
			`Version: ${ meta.version || 'unknown' } (schema ${ meta.schema || 'unknown' })`,
			'',
			'',
		].join( '\n' );
	}

	/**
	 * Format diagnostics sections as text.
	 *
	 * @param {Object<string, Object<string, string>>} sections
	 * @return {string}
	 */
	function formatDiagnostics( sections ) {
		return Object.entries( sections ).map( ( [ section, rows ] ) => {
			const entries = Object.entries( rows ?? {} );

			// The full extension list is long; one line is enough for support.
			const body = 'Loaded Extensions' === section
				? [ entries.map( ( [ name, version ] ) => ( 'Loaded' === version ? name : `${ name } ${ version }` ) ).join( ', ' ) ]
				: entries.map( ( [ label, value ] ) => `${ label }: ${ value }` );

			return `\n== ${ section } ==\n${ body.join( '\n' ) }\n`;
		} ).join( '' );
	}

	/*
	|-------------------
	| READING THE PAGE
	|-------------------
	*/

	/**
	 * Every check shown on the Site Health page, server-side and additional.
	 *
	 * @return {{checks: Array<Object>, pending: number}}
	 */
	function checksFromPage() {
		const checks = [];
		let pending  = 0;

		document.querySelectorAll( '#smliser-health-grid .smliser-health-check' ).forEach( ( item ) => {
			const status = [ ...item.classList ]
				.find( ( name ) => name.startsWith( TONE ) )
				?.slice( TONE.length );

			if ( 'pending' === status ) {
				pending++;
				return;
			}

			const label = text( item, '.smliser-health-check-label' );

			// Status rows without a label ("No additional checks were returned").
			if ( '' === label ) {
				return;
			}

			checks.push( {
				status: STATUSES.includes( status ) ? status : 'info',
				label,
				message: text( item, '.smliser-health-check-message' ),
				recommendation: text( item, '.smliser-health-check-recommendation span' ),
			} );
		} );

		return { checks, pending };
	}

	/**
	 * The diagnostics panels shown on the System Diagnostics page.
	 *
	 * @return {Object<string, Object<string, string>>}
	 */
	function diagnosticsFromPage() {
		const sections = {};

		document.querySelectorAll( '#smliser-diagnostics-grid .smliser-diagnostics-panel' ).forEach( ( panel ) => {
			const rows = {};

			panel.querySelectorAll( '.smliser-app-meta > li' ).forEach( ( row ) => {
				const [ label, value ] = row.querySelectorAll( ':scope > span' );

				if ( label ) {
					rows[ label.textContent.trim() ] = value ? value.textContent.trim() : '';
				}
			} );

			sections[ text( panel, '.smliser-diagnostics-panel-title' ) ] = rows;
		} );

		return sections;
	}

	function text( root, selector ) {
		return root.querySelector( selector )?.textContent.trim() ?? '';
	}

	/*
	|--------
	| REQUEST
	|--------
	*/

	/**
	 * GET site-health-check/diagnostics.
	 *
	 * @return {Promise<Object>}
	 */
	async function fetchDiagnostics() {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/site-health-check/diagnostics`;

		try {
			return await smliserFetchJSON( url, { method: 'GET', credentials: 'same-origin', signal: controller.signal } );
		} finally {
			clearTimeout( timer );
		}
	}

	/*
	|----------
	| CLIPBOARD
	|----------
	*/

	/**
	 * Write text to the clipboard. navigator.clipboard needs a secure
	 * context (HTTPS or localhost); otherwise fall back to a selection copy.
	 *
	 * @param {string} value
	 * @return {Promise<void>}
	 */
	async function writeClipboard( value ) {
		if ( window.isSecureContext && navigator.clipboard?.writeText ) {
			return navigator.clipboard.writeText( value );
		}

		const area = document.createElement( 'textarea' );

		area.value = value;
		area.setAttribute( 'readonly', '' );
		area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
		document.body.append( area );
		area.select();

		const copied = document.execCommand( 'copy' );

		area.remove();

		if ( ! copied ) {
			throw new Error( 'Copy command was refused.' );
		}
	}

	/**
	 * Show the report selected in a modal when the browser refuses to copy.
	 *
	 * @param {string} value
	 */
	function showForManualCopy( value ) {
		const area = document.createElement( 'textarea' );

		area.value         = value;
		area.readOnly      = true;
		area.style.cssText = 'width:100%;min-height:360px;font-family:monospace;font-size:12px;';

		const modal = new SmliserModal( { title: 'Copy this report', body: area, width: '760px' } );

		modal.open();
		area.focus();
		area.select();

		SmliserToast.show( 'Your browser blocked copying. Press Ctrl+C (Cmd+C) to copy the selected report.', { type: 'warning' } );
	}

	/*
	|--------
	| HELPERS
	|--------
	*/

	function readJSON( value ) {
		try {
			return JSON.parse( value || '{}' );
		} catch ( error ) {
			return {};
		}
	}

	function setBusy( button ) {
		const icon    = button.querySelector( 'i' );
		const iconWas = icon?.className;

		button.disabled = true;

		if ( icon ) {
			icon.className = 'ti ti-loader-2 smliser-health-spin';
		}

		return () => {
			button.disabled = false;

			if ( icon ) {
				icon.className = iconWas;
			}
		};
	}
}() );