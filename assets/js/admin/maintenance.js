/**
 * Maintenance page: switch maintenance mode on and off.
 *
 * The form posts to admin/json/maintenance/enable; buttons with
 * data-maintenance-action post to maintenance/<action>. Switching on (or
 * making a new link) returns the bypass link, which is shown once: only
 * its hash is stored. The response also gives this browser the bypass
 * cookie, so it keeps using the site. After an action the page reloads,
 * so the server-rendered template stays the single source of what is shown.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 30000;
	const SPINNER    = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-maintenance' );

		if ( ! root ) {
			return;
		}

		root.querySelector( '#smliser-maintenance-enable' )?.addEventListener( 'submit', ( event ) => {
			event.preventDefault();
			enable( root, event.currentTarget );
		} );

		root.querySelectorAll( '[data-maintenance-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => runAction( root, button ) );
		} );
	} );

	/**
	 * Switch maintenance on (again) with the form's settings.
	 *
	 * @param {HTMLElement}     root
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function enable( root, form ) {
		const again  = '1' === form.dataset.active;
		const paused = ! form.querySelector( '[name="keep_background"]' )?.checked;
		const ok     = await SmliserModal.confirm( {
			title: again ? 'Change maintenance' : 'Turn maintenance on',
			message: again
				? 'Apply these settings? Earlier bypass links stop working; this browser keeps access.'
				: `Visitors will get the maintenance page${ paused ? ', and scheduled tasks and queued jobs will wait' : '' }. This browser keeps access. Continue?`,
			confirmText: again ? 'Apply' : 'Turn on',
			danger: true,
		} );

		if ( ! ok ) {
			return;
		}

		const button  = form.querySelector( '[type="submit"]' );
		const restore = setBusy( root, button );

		try {
			const response = await post( 'enable', new FormData( form ) );

			await showBypass( response?.data?.message, response?.data?.bypass_url );
			window.location.reload();
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'Maintenance mode could not be switched on.', { type: 'error' } );
		}
	}

	/**
	 * Run a button's action, after its confirmation.
	 *
	 * @param {HTMLElement}       root
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function runAction( root, button ) {
		const action = button.dataset.maintenanceAction;

		if ( button.dataset.confirm && ! await SmliserModal.confirm( button.dataset.confirm ) ) {
			return;
		}

		const restore = setBusy( root, button );

		try {
			const response = await post( action, new FormData() );

			if ( response?.data?.bypass_url ) {
				await showBypass( response.data.message, response.data.bypass_url );
				window.location.reload();
				return;
			}

			SmliserToast.show( response?.data?.message || 'Done.', { type: 'success' } );
			setTimeout( () => window.location.reload(), 1200 );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The request failed.', { type: 'error' } );
		}
	}

	/**
	 * Show the bypass link once, with a copy button.
	 *
	 * @param {string} message
	 * @param {string} url
	 * @return {Promise<void>}
	 */
	async function showBypass( message, url ) {
		if ( ! url ) {
			SmliserToast.show( message || 'Done.', { type: 'success' } );
			return;
		}

		const escape = ( text ) => String( text ).replace( /[&<>"']/g, ( c ) => `&#${ c.charCodeAt( 0 ) };` );
		const copied = navigator.clipboard?.writeText( url ).then( () => true, () => false ) ?? Promise.resolve( false );

		await SmliserModal.alert( {
			title: 'Bypass link',
			type: 'success',
			message: `<p>${ escape( message || '' ) }</p>`
				+ '<p>Open this link in another browser to use the site while visitors wait. Keep it private; it is shown only now.</p>'
				+ `<p><code style="word-break: break-all;">${ escape( url ) }</code></p>`
				+ `<p>${ await copied ? 'Copied to the clipboard.' : 'Copy it before closing this message.' }</p>`,
			buttonText: 'Done',
		} );
	}

	/*
	|---------
	| REQUEST
	|---------
	*/

	/**
	 * POST to a maintenance endpoint. smliserFetchJSON() adds the CSRF
	 * header and throws { message, code, status } on failure.
	 *
	 * @param {string}   action
	 * @param {FormData} body
	 * @return {Promise<Object>}
	 */
	async function post( action, body ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/maintenance/${ action }`;

		try {
			return await smliserFetchJSON( url, {
				method: 'POST',
				body,
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}

	/*
	|---------
	| HELPERS
	|---------
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
} )();