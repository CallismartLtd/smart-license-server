/**
 * "Generate report" buttons on the Site Health and System Diagnostics pages.
 *
 * Builds a support report from what the page already shows, so nothing
 * is computed twice:
 *
 *  - Site Health: every check on the page (the additional checks too,
 *    once they have finished), then the System Diagnostics, fetched from
 *    site-health-check/diagnostics only when the button is clicked —
 *    collecting them is too slow for every page load.
 *  - System Diagnostics: the panels on the page.
 *
 * The report always ends up in a selected, readonly textarea: the page's
 * own [data-support-report-text] field when it has one, otherwise a
 * SmliserModal with download buttons (.txt and .json) in its footer.
 * Copying to the clipboard is attempted, but never relied on.
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
	const TITLES     = {
		health: 'Site Health',
		diagnostics: 'System Diagnostics',
	};

	const generated  = {};

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-support-report]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => generateReport( button ) );
		} );

		document.querySelectorAll( '[data-support-report-download]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => downloadGenerated( button ) );
		} );
	} );

	/**
	 * Build the report for the button's page and show it.
	 *
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function generateReport( button ) {
		const kind    = button.dataset.supportReport;
		const meta    = readJSON( button.dataset.reportMeta );
		const restore = setBusy( button );

		let report;

		try {
			report = 'health' === kind
				? await healthReport( meta )
				: diagnosticsReport( meta );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The report could not be built.', { type: 'error' } );
			return;
		}

		restore();

		const text = formatReport( report );

		// Copy before displaying: the fallback copy selects its own temporary
		// textarea, which would otherwise steal the selection from ours.
		let copied = true;

		try {
			await writeClipboard( text );
		} catch ( error ) {
			copied = false;
		}

		const field = reportField( kind );

		if ( field ) {
			field.value     = text;
			generated[ kind ] = { report, text };

			setDownloads( kind, true );
			selectField( field );
		} else {
			showInModal( report, text );
		}

		SmliserToast.show(
			copied
				? 'Report generated and copied. Paste it into your support request.'
				: 'Report generated. Press Ctrl+C (Cmd+C) to copy the selected text.',
			{ type: copied ? 'success' : 'warning' }
		);
	}

	/*
	|--------
	| REPORTS
	|--------
	*/

	/**
	 * Fields every report shares.
	 *
	 * @param {string} kind "health" or "diagnostics".
	 * @param {Object} meta {product, version, schema, site}
	 * @return {Object}
	 */
	function baseReport( kind, meta ) {
		return {
			kind,
			product: meta.product || 'Smart License Server',
			generated: new Date().toISOString(),
			site: meta.site || window.location.origin,
			version: meta.version || 'unknown',
			schema: meta.schema || 'unknown',
		};
	}

	/**
	 * Site Health: the checks on the page, then the diagnostics from the server.
	 *
	 * @param {Object} meta
	 * @return {Promise<Object>}
	 */
	async function healthReport( meta ) {
		const { checks, pending } = checksFromPage();
		const report              = baseReport( 'health', meta );

		report.checks  = checks.sort( ( a, b ) => STATUSES.indexOf( a.status ) - STATUSES.indexOf( b.status ) );
		report.pending = pending;

		try {
			report.diagnostics = ( await fetchDiagnostics() ).diagnostics ?? {};
		} catch ( error ) {
			report.diagnostics       = {};
			report.diagnostics_error = error?.message || 'request failed';
		}

		return report;
	}

	/**
	 * System Diagnostics: the panels on the page.
	 *
	 * @param {Object} meta
	 * @return {Object}
	 */
	function diagnosticsReport( meta ) {
		return { ...baseReport( 'diagnostics', meta ), diagnostics: diagnosticsFromPage() };
	}

	/*
	|-----------
	| FORMATTING
	|-----------
	*/

	/**
	 * Render a report as plain text.
	 *
	 * @param {Object} report
	 * @return {string}
	 */
	function formatReport( report ) {
		const blocks = [];

		if ( 'health' === report.kind ) {
			blocks.push( formatChecks( report ) );
		}

		blocks.push( formatDiagnostics( report ) );

		return header( report ) + blocks.join( '\n' );
	}

	/**
	 * Report header.
	 *
	 * @param {Object} report
	 * @return {string}
	 */
	function header( report ) {
		return [
			`${ report.product } ${ TITLES[ report.kind ] } Report`,
			`Generated: ${ report.generated.replace( 'T', ' ' ).replace( /\.\d+Z$/, ' UTC' ) }`,
			`Site: ${ report.site }`,
			`Version: ${ report.version } (schema ${ report.schema })`,
		].join( '\n' ) + '\n\n';
	}

	/**
	 * Health checks as text.
	 *
	 * @param {Object} report
	 * @return {string}
	 */
	function formatChecks( report ) {
		const lines = [ '== Health Checks ==' ];

		report.checks.forEach( ( check ) => {
			lines.push( `[${ check.status.toUpperCase() }] ${ check.label }: ${ check.message }` );

			if ( check.recommendation ) {
				lines.push( `    Recommendation: ${ check.recommendation }` );
			}
		} );

		if ( report.pending > 0 ) {
			lines.push( `(${ report.pending } additional check(s) were still running when this report was generated.)` );
		}

		return lines.join( '\n' ) + '\n';
	}

	/**
	 * Diagnostics sections as text.
	 *
	 * @param {Object} report
	 * @return {string}
	 */
	function formatDiagnostics( report ) {
		if ( report.diagnostics_error ) {
			return `== System Diagnostics ==\nCould not be collected: ${ report.diagnostics_error }\n`;
		}

		return Object.entries( report.diagnostics ).map( ( [ section, rows ] ) => {
			const entries = Object.entries( rows ?? {} );

			// The full extension list is long; one line is enough for support.
			const body = 'Loaded Extensions' === section
				? [ entries.map( ( [ name, version ] ) => ( 'Loaded' === version ? name : `${ name } ${ version }` ) ).join( ', ' ) ]
				: entries.map( ( [ label, value ] ) => `${ label }: ${ value }` );

			return `== ${ section } ==\n${ body.join( '\n' ) }\n`;
		} ).join( '\n' );
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

	/*
	|--------------
	| DISPLAYING IT
	|--------------
	*/

	/**
	 * The page's report textarea, if it has one.
	 *
	 * @param {string} kind "health" or "diagnostics".
	 * @return {HTMLTextAreaElement|null}
	 */
	function reportField( kind ) {
		return document.querySelector( `[data-support-report-text="${ kind }"]` );
	}

	/**
	 * Select all the text in a field, opening its panel first: a selection
	 * inside a closed <details> is invisible. Focus keeps the highlight in
	 * its active colour.
	 *
	 * @param {HTMLTextAreaElement} field
	 */
	function selectField( field ) {
		const panel = field.closest( 'details' );

		if ( panel ) {
			panel.open = true;
		}

		field.focus();
		field.select();
		field.setSelectionRange( 0, field.value.length ); // iOS Safari ignores select() alone.
	}

	/**
	 * Show the report in a modal, for pages without a report textarea.
	 *
	 * @param {Object} report
	 * @param {string} value  The report as text.
	 */
	function showInModal( report, value ) {
		const area = document.createElement( 'textarea' );

		area.value         = value;
		area.readOnly      = true;
		area.spellcheck    = false;
		area.style.cssText = 'width:100%;min-height:360px;box-sizing:border-box;font-family:monospace;font-size:12px;';
		area.setAttribute( 'aria-label', 'Support report' );

		const stamp  = report.generated.replace( /[-:]/g, '' ).replace( /\.\d+Z$/, '' ).replace( 'T', '-' );
		const name   = fileName( report );
		const footer = document.createElement( 'div' );

		footer.style.cssText = 'display:flex;gap:8px;justify-content:flex-end;';
		footer.append(
			downloadButton( 'Download .txt', () => download( `${ name }.txt`, value, 'text/plain' ) ),
			downloadButton( 'Download .json', () => download( `${ name }.json`, JSON.stringify( report, null, 2 ), 'application/json' ) )
		);

		const modal = new SmliserModal( {
			title: `${ TITLES[ report.kind ] } Report`,
			body: area,
			footer,
			width: '760px',
			closeOnBackdropClick: false, // A drag-selection ending on the backdrop would close it.
			autoFocus: false,            // Otherwise the close button takes focus after the selection.
		} );

		// afterOpen fires once the modal is in the DOM and its animation is done.
		modal.on( 'afterOpen', () => selectField( area ) );
		modal.on( 'afterClose', () => modal.destroy() );

		modal.open();
	}
	/**
	 * File name shared by every download of a report.
	 *
	 * @param {Object} report
	 * @return {string} Without extension.
	 */
	function fileName( report ) {
		const stamp = report.generated.replace( /[-:]/g, '' ).replace( /\.\d+Z$/, '' ).replace( 'T', '-' );

		return `smliser-${ report.kind }-report-${ stamp }`;
	}

	/**
	 * Enable or disable the page's download buttons for a report kind.
	 *
	 * @param {string}  kind
	 * @param {boolean} enabled
	 */
	function setDownloads( kind, enabled ) {
		document.querySelectorAll( `[data-support-report-download][data-support-report-kind="${ kind }"]` ).forEach( ( button ) => {
			button.disabled = ! enabled;
		} );
	}

	/**
	 * Download the report last generated on this page.
	 *
	 * @param {HTMLButtonElement} button
	 */
	function downloadGenerated( button ) {
		const entry = generated[ button.dataset.supportReportKind ];

		if ( ! entry ) {
			return;
		}

		const name = fileName( entry.report );

		if ( 'json' === button.dataset.supportReportDownload ) {
			download( `${ name }.json`, JSON.stringify( entry.report, null, 2 ), 'application/json' );
		} else {
			download( `${ name }.txt`, entry.text, 'text/plain' );
		}
	}
	
	/**
	 * @param {string}   label
	 * @param {Function} onClick
	 * @return {HTMLButtonElement}
	 */
	function downloadButton( label, onClick ) {
		const button = document.createElement( 'button' );
		const icon   = document.createElement( 'i' );

		button.type      = 'button';
		button.className = 'smliser-btn smliser-btn-glass';
		icon.className   = 'ti ti-download';

		button.append( icon, ` ${ label }` );
		button.addEventListener( 'click', onClick );

		return button;
	}

	/**
	 * Save text as a file.
	 *
	 * @param {string} filename
	 * @param {string} content
	 * @param {string} mime
	 */
	function download( filename, content, mime ) {
		const url  = URL.createObjectURL( new Blob( [ content ], { type: `${ mime };charset=utf-8` } ) );
		const link = document.createElement( 'a' );

		link.href     = url;
		link.download = filename;
		document.body.append( link );
		link.click();
		link.remove();

		setTimeout( () => URL.revokeObjectURL( url ), 1000 );
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