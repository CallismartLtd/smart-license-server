/**
 * Updates page: actions and progress.
 *
 * Buttons with data-update-action POST to admin/json/update/<action>; the
 * automatic updates form posts to update/auto. Install and rollback run in
 * the request. After an action the page is reloaded, so the
 * server-rendered template stays the single source of what is shown; that
 * reload also finishes an installed update on servers that cannot finish
 * it in a new process.
 *
 * While an install or rollback is queued or running (data-busy="1"), the
 * page polls update/status and reloads when it has ended. During the swap
 * the whole site answers 503 for a few seconds; that is expected and
 * polling just continues.
 */
( function () {
	'use strict';

	const POLL_MS         = 5000;
	const POLL_GIVE_UP_MS = 20 * 60 * 1000;
	const TIMEOUT_MS      = 60000;
	const DRY_RUN_MS      = 5 * 60 * 1000;
	const LONG_ACTIONS    = [ 'dry-run', 'install', 'rollback' ]; // Run in the request; may download.
	const SPINNER         = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-updates' );

		if ( ! root ) {
			return;
		}

		root.querySelectorAll( '[data-update-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => runAction( root, button ) );
		} );

		root.querySelector( '#smliser-update-auto' )?.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			saveAuto( root, event.currentTarget );
		} );

		if ( '1' === root.dataset.busy ) {
			poll( Date.now() );
		}
	} );

	/*
	|--------
	| ACTIONS
	|--------
	*/

	/**
	 * Run a button's action, after confirmation when it asks for one.
	 *
	 * @param {HTMLElement}       root
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function runAction( root, button ) {
		const action = button.dataset.updateAction;

		if ( button.dataset.confirm && ! await SmliserModal.confirm( button.dataset.confirm ) ) {
			return;
		}

		const body = new FormData();

		if ( '1' === button.dataset.reinstall ) {
			body.set( 'reinstall', '1' );
		}

		const restore = setBusy( root, button );

		try {
			const response = await post( action, body, LONG_ACTIONS.includes( action ) ? DRY_RUN_MS : TIMEOUT_MS );

			done( response?.data?.message || 'Done.', 'success' );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The request failed.', { type: 'error' } );
		}
	}

	/**
	 * Save the automatic update mode.
	 *
	 * @param {HTMLElement}     root
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function saveAuto( root, form ) {
		const button  = form.querySelector( '[type="submit"]' );
		const restore = setBusy( root, button );

		try {
			const response = await post( 'auto', new FormData( form ), TIMEOUT_MS );

			restore();
			SmliserToast.show( response?.data?.message || 'Saved.', { type: 'success' } );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The setting could not be saved.', { type: 'error' } );
		}
	}

	/**
	 * Show the outcome, then reload so the page reflects the new state.
	 *
	 * @param {string} message
	 * @param {string} type
	 */
	function done( message, type ) {
		SmliserToast.show( message, { type } );
		setTimeout( () => window.location.reload(), 1500 );
	}

	/*
	|--------
	| POLLING
	|--------
	*/

	/**
	 * Follow a queued or running update until it ends, then reload.
	 *
	 * @param {number} started When polling started.
	 * @return {Promise<void>}
	 */
	async function poll( started ) {
		if ( Date.now() - started > POLL_GIVE_UP_MS ) {
			SmliserToast.show( 'The update has not finished after 20 minutes. Check that the queue worker is running, or run `smliser update status` from the console.', { type: 'warning' } );
			return;
		}

		await wait( POLL_MS );

		try {
			const response = await request( 'status', { method: 'GET' }, TIMEOUT_MS );
			const overview = response?.data?.overview;

			if ( overview && ! overview.in_progress && ! overview.queued ) {
				done( overview.attempt && ! overview.attempt.ok ? 'The update did not complete; see the history below.' : 'The update has ended.', overview.attempt && ! overview.attempt.ok ? 'warning' : 'success' );
				return;
			}
		} catch ( error ) {
			// 503 while files are swapped, or a transient failure: keep polling.
		}

		poll( started );
	}

	/*
	|--------
	| REQUEST
	|--------
	*/

	/**
	 * POST to an update endpoint.
	 *
	 * @param {string}   action
	 * @param {FormData} body
	 * @param {number}   timeout
	 * @return {Promise<Object>}
	 */
	function post( action, body, timeout ) {
		return request( action, { method: 'POST', body }, timeout );
	}

	/**
	 * Call an update endpoint with a timeout. smliserFetchJSON() adds the
	 * CSRF header to POSTs and throws { message, code, status } on failure.
	 *
	 * @param {string}      action
	 * @param {RequestInit} init
	 * @param {number}      timeout
	 * @return {Promise<Object>}
	 */
	async function request( action, init, timeout ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), timeout );

		try {
			return await smliserFetchJSON( buildURL( action ), {
				...init,
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}

	/**
	 * Build an update endpoint URL from the server-set ajax URL.
	 *
	 * @param {string} action
	 * @return {URL}
	 */
	function buildURL( action ) {
		const url = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/update/${ action }`;

		return url;
	}

	/*
	|--------
	| HELPERS
	|--------
	*/

	/**
	 * Disable every action while one runs, and show a spinner on its button.
	 *
	 * @param {HTMLElement}       root
	 * @param {HTMLButtonElement} button
	 * @return {Function} Restores the buttons.
	 */
	function setBusy( root, button ) {
		const buttons = [ ...root.querySelectorAll( 'button' ) ];
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

	function wait( ms ) {
		return new Promise( ( resolve ) => setTimeout( resolve, ms ) );
	}
}() );