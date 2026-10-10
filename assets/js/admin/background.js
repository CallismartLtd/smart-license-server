/**
 * Schedules and Queue Monitor pages: run tasks and jobs from the browser.
 *
 * Buttons with data-bg-action POST to admin/json/background/<action>:
 *   schedule/run         data-task-id runs one task; without it, every due task.
 *   queue/process        processes queued jobs for a short time budget.
 *   queue/release-stale  queue/purge  queue/purge-failed (data-days).
 *
 * The outcome is shown as a toast; per-task results or the worker log are
 * shown in a modal when there are any. The page then reloads, so the
 * server-rendered tables stay the single source of what is shown.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 150000; // Above the server's 120-second time limit.
	const SPINNER    = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-bg-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => runAction( button ) );
		} );
	} );

	/*
	|--------
	| ACTIONS
	|--------
	*/

	/**
	 * Run a button's action, after confirmation when it asks for one.
	 *
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function runAction( button ) {
		if ( button.dataset.confirm && ! await SmliserModal.confirm( button.dataset.confirm ) ) {
			return;
		}

		const body = new FormData();

		if ( button.dataset.taskId ) {
			body.set( 'task_id', button.dataset.taskId );
		}

		if ( button.dataset.days ) {
			body.set( 'days', button.dataset.days );
		}

		const restore = setBusy( button );

		try {
			const response = await post( button.dataset.bgAction, body );

			report( response?.data, 'success' );
		} catch ( error ) {
			restore();
			report( { message: error?.message }, 'error' );
		}
	}

	/**
	 * Show an outcome, with its details when there are any, then reload.
	 *
	 * @param {Object|undefined} data  Response data: { message, results? }.
	 * @param {string}           type  Toast type.
	 */
	function report( data, type ) {
		const message = data?.message || ( 'success' === type ? 'Done.' : 'The request failed.' );
		const details = formatResults( data?.results );

		SmliserToast.show( message, { type } );

		if ( '' === details ) {
			if ( 'success' === type ) {
				setTimeout( () => window.location.reload(), 1500 );
			}

			return;
		}

		showDetails( message, details ).then( () => window.location.reload() );
	}

	/**
	 * Turn per-task results or worker log lines into text.
	 *
	 * @param {Array|undefined} results
	 * @return {string}
	 */
	function formatResults( results ) {
		if ( ! Array.isArray( results ) || 0 === results.length ) {
			return '';
		}

		return results
			.map( ( item ) => (
				'string' === typeof item
					? item
					: `${ item.ok ? 'PASSED' : 'FAILED' }  ${ item.label }${ item.error ? ` — ${ item.error }` : '' }`
			) )
			.join( '\n' );
	}

	/**
	 * Show details in the shared modal.
	 *
	 * @param {string} title
	 * @param {string} text
	 * @return {Promise<void>} Resolves when the modal is closed.
	 */
	function showDetails( title, text ) {
		return new Promise( ( resolve ) => {
			const pre = document.createElement( 'pre' );

			pre.textContent   = text;
			pre.style.cssText = 'white-space:pre-wrap;word-break:break-word;font-size:12px;line-height:1.6;margin:0;';

			const modal = new SmliserModal( { title, body: pre, width: '720px' } );

			modal.on( 'afterClose', () => resolve() );
			modal.open();
		} );
	}

	/*
	|--------
	| REQUEST
	|--------
	*/

	/**
	 * POST to a background endpoint with a timeout. smliserFetchJSON() adds
	 * the CSRF header and throws { message, code, status } on failure.
	 *
	 * @param {string}   action
	 * @param {FormData} body
	 * @return {Promise<Object>}
	 */
	async function post( action, body ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );

		try {
			return await smliserFetchJSON( buildURL( action ), {
				method: 'POST',
				body,
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}

	/**
	 * Build a background endpoint URL from the server-set ajax URL.
	 *
	 * @param {string} action E.g. "queue/process".
	 * @return {URL}
	 */
	function buildURL( action ) {
		const url = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/background/${ action }`;

		return url;
	}

	/*
	|--------
	| HELPERS
	|--------
	*/

	/**
	 * Disable every action button while one runs, and show a spinner on it.
	 *
	 * @param {HTMLButtonElement} button
	 * @return {Function} Restores the buttons.
	 */
	function setBusy( button ) {
		const buttons = [ ...document.querySelectorAll( '[data-bg-action]' ) ];
		const states  = buttons.map( ( node ) => node.disabled );
		const icon    = button.querySelector( 'i' );
		const iconWas = icon?.className;

		buttons.forEach( ( node ) => {
			node.disabled = true;
		} );

		if ( icon ) {
			icon.className = SPINNER;
		}

		return () => {
			buttons.forEach( ( node, index ) => {
				node.disabled = states[ index ];
			} );

			if ( icon ) {
				icon.className = iconWas;
			}
		};
	}
}() );