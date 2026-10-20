/**
 * Appearance > Themes page: install, activate, update and remove site themes.
 *
 * Buttons with data-theme-action post to admin/json/themes/<action> with
 * their data-slug; the forms data-theme-form="install|upload" post their
 * fields. After an action the page reloads, so the server-rendered template
 * stays the single source of what is shown. "check" is the exception: it
 * only marks the rows that have a newer marketplace version.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 180000;
	const SPINNER    = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-themes' );

		if ( ! root ) {
			return;
		}

		root.querySelectorAll( '[data-theme-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => runAction( root, button ) );
		} );

		root.querySelectorAll( '[data-theme-form]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				submitForm( root, form );
			} );
		} );
	} );

	/**
	 * Run a button's action, after its confirmation.
	 *
	 * @param {HTMLElement}       root
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function runAction( root, button ) {
		const action = button.dataset.themeAction;

		if ( button.dataset.confirm && ! await SmliserModal.confirm( { message: button.dataset.confirm, danger: '1' === button.dataset.danger } ) ) {
			return;
		}

		const body = new FormData();

		if ( button.dataset.slug ) {
			body.append( 'slug', button.dataset.slug );
		}

		const restore = setBusy( root, button );

		try {
			const response = await post( action, body );

			if ( 'check' === action ) {
				restore();
				markUpdates( root, response?.data?.updates || {} );
				SmliserToast.show( response?.data?.message || 'Done.', { type: 'success' } );
				return;
			}

			done( response );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The request failed.', { type: 'error' } );
		}
	}

	/**
	 * Submit the install or upload form.
	 *
	 * @param {HTMLElement}     root
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function submitForm( root, form ) {
		const action = form.dataset.themeForm;
		const body   = new FormData( form );

		if ( 'upload' === action && body.get( 'trust_unsigned' ) ) {
			const ok = await SmliserModal.confirm( {
				title: 'Trust an unsigned package',
				message: 'The package will be installed even if its signature is missing or invalid. Its templates run on every public page. Continue only for a package you built or received directly.',
				confirmText: 'Install anyway',
				danger: true,
			} );

			if ( ! ok ) {
				return;
			}
		}

		const restore = setBusy( root, form.querySelector( '[type="submit"]' ) );

		try {
			done( await post( action, body ) );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The theme could not be installed.', { type: 'error' } );
		}
	}

	/**
	 * Show the outcome of an action, then reload.
	 *
	 * @param {Object} response
	 * @return {void}
	 */
	function done( response ) {
		( response?.data?.warnings || [] ).forEach( ( warning ) => SmliserToast.show( warning, { type: 'warning' } ) );
		SmliserToast.show( response?.data?.message || 'Done.', { type: 'success' } );
		setTimeout( () => window.location.reload(), 1500 );
	}

	/**
	 * Mark the rows of themes that have a newer marketplace version.
	 *
	 * @param {HTMLElement} root
	 * @param {Object<string, {installed: ?string, latest: ?string, update: boolean, error: ?string}>} updates
	 * @return {void}
	 */
	function markUpdates( root, updates ) {
		Object.entries( updates ).forEach( ( [ slug, result ] ) => {
			const row    = root.querySelector( `[data-theme-row="${ CSS.escape( slug ) }"]` );
			const latest = row?.querySelector( '[data-theme-latest]' );

			if ( ! latest ) {
				return;
			}

			latest.hidden      = false;
			latest.textContent = result.error
				? ` (could not check: ${ result.error })`
				: ( result.update ? ` → ${ result.latest } available` : ' (latest)' );
		} );
	}

	/*
	|---------
	| REQUEST
	|---------
	*/

	/**
	 * POST to a themes endpoint. smliserFetchJSON() adds the CSRF header and
	 * throws { message, code, status } on failure.
	 *
	 * @param {string}   action
	 * @param {FormData} body
	 * @return {Promise<Object>}
	 */
	async function post( action, body ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/themes/${ action }`;

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