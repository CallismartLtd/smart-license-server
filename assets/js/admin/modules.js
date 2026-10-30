/**
 * Modules pages (Themes, Marketplace; Extensions to come): module card actions.
 *
 * Every module is drawn as a module card ([data-module-card], with
 * data-module-kind and data-slug). Its buttons [data-module-action] post to
 * admin/json/<kind>s/<action> with the card's slug. Page buttons
 * [data-module-page-action] and forms [data-module-form] post to the same
 * endpoints, for the page's kind (#smliser-modules[data-module-kind]).
 *
 * After an action the page reloads, so the server-rendered cards stay the
 * single source of what is shown. "check" is the exception: it writes what
 * the marketplace has into each card's status line.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 180000;
	const SPINNER    = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-modules' );

		if ( ! root ) {
			return;
		}

		root.querySelectorAll( '[data-module-card] [data-module-action]' ).forEach( ( button ) => {
			const card = button.closest( '[data-module-card]' );

			button.addEventListener( 'click', () => run( root, button, card.dataset.moduleKind, button.dataset.moduleAction, { slug: card.dataset.slug } ) );
		} );

		root.querySelectorAll( '[data-module-page-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => run( root, button, root.dataset.moduleKind, button.dataset.modulePageAction, {} ) );
		} );

		root.querySelectorAll( '[data-module-form]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				submitForm( root, form );
			} );
		} );
	} );

	/**
	 * Run an action, after its button's confirmation.
	 *
	 * @param {HTMLElement}           root
	 * @param {HTMLButtonElement}     button
	 * @param {string}                kind   Module kind, e.g. "theme".
	 * @param {string}                action Endpoint action, e.g. "activate".
	 * @param {Object<string,string>} fields Fields to post.
	 * @return {Promise<void>}
	 */
	async function run( root, button, kind, action, fields ) {
		if ( button.dataset.confirm && ! await SmliserModal.confirm( { message: button.dataset.confirm, danger: '1' === button.dataset.danger } ) ) {
			return;
		}

		const body = new FormData();

		Object.entries( fields ).forEach( ( [ key, value ] ) => body.append( key, value ) );

		const restore = setBusy( root, button );

		try {
			const response = await post( kind, action, body );

			if ( 'check' === action ) {
				restore();
				showUpdates( root, response?.data?.updates || {} );
				SmliserToast.show( response?.data?.message || 'Done.', { type: 'success', duration: 10000 } );
				return;
			}

			done( response );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The request failed.', { type: 'error' } );
		}
	}

	/**
	 * Submit a module form (e.g. the theme package upload).
	 *
	 * @param {HTMLElement}     root
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function submitForm( root, form ) {
		const body = new FormData( form );

		if ( body.get( 'trust_unsigned' ) ) {
			const ok = await SmliserModal.confirm( {
				title: 'Trust an unsigned package',
				message: 'The package will be installed even if its signature is missing or invalid. Continue only for a package you built or received directly.',
				confirmText: 'Install anyway',
				danger: true,
			} );

			if ( ! ok ) {
				return;
			}
		}

		const restore = setBusy( root, form.querySelector( '[type="submit"]' ) );

		try {
			done( await post( root.dataset.moduleKind, form.dataset.moduleForm, body ) );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The module could not be installed.', { type: 'error', duration: 15000 } );
		}
	}

	/**
	 * Show the outcome of an action, then reload.
	 *
	 * @param {Object} response
	 * @return {void}
	 */
	function done( response ) {
		( response?.data?.warnings || [] ).forEach( ( warning ) => SmliserToast.show( warning, { type: 'warning', duration: 10000 } ) );
		SmliserToast.show( response?.data?.message || 'Done.', { type: 'success', duration: 10000 } );
		setTimeout( () => window.location.reload(), 1500 );
	}

	/**
	 * Write the marketplace's answer into each card's status line.
	 *
	 * @param {HTMLElement} root
	 * @param {Object<string, {installed: ?string, latest: ?string, update: boolean, error: ?string}>} updates
	 * @return {void}
	 */
	function showUpdates( root, updates ) {
		Object.entries( updates ).forEach( ( [ slug, result ] ) => {
			const status = root.querySelector( `[data-module-card][data-slug="${ CSS.escape( slug ) }"] [data-module-status]` );

			if ( ! status ) {
				return;
			}

			status.hidden       = false;
			status.dataset.tone = result.error ? 'danger' : ( result.update ? 'info' : 'active' );
			status.textContent  = result.error
				? `Could not check: ${ result.error }`
				: ( result.update ? `Version ${ result.latest } is available.` : 'Up to date.' );
		} );
	}

	/*
	|---------
	| REQUEST
	|---------
	*/

	/**
	 * POST to admin/json/<kind>s/<action>. smliserFetchJSON() adds the CSRF
	 * header and throws { message, code, status } on failure.
	 *
	 * @param {string}   kind
	 * @param {string}   action
	 * @param {FormData} body
	 * @return {Promise<Object>}
	 */
	async function post( kind, action, body ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/${ kind }s/${ action }`;

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
		const icon    = button?.querySelector( 'i' );
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