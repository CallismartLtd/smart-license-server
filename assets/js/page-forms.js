/**
 * Forms of themed pages.
 *
 * Pages return HTML and actions return JSON. The theme owns the markup; this
 * script posts a page's forms to their actions and shows the answer, through
 * a markup contract any theme can follow:
 *
 *   form[data-smliser-form]           Posted with fetch() to its action URL, with
 *                                     its method (POST by default; PUT or DELETE
 *                                     through data-method). The answer is JSON:
 *                                     { success, message, redirect? }.
 *   [data-smliser-form-message]       Inside the form: the message is written here,
 *                                     with data-state="error|success".
 *   form[data-pass-redirect]          Also posts the page's redirect_url query
 *                                     parameter, so sign-in returns the visitor
 *                                     where they were sent from.
 *   input[data-password-meter]        A password whose strength is shown on the
 *   [data-password-strength]          element in the same form, as
 *                                     data-strength="weak|medium|strong".
 *
 * After success the message is shown; with a redirect URL the browser
 * follows it shortly after, otherwise the form is cleared. What happens
 * next is decided by the action, not here.
 *
 * Needs globals.js (smliserFetchJSON, which sends the CSRF header).
 */
( function () {
	'use strict';

	const REDIRECT_DELAY_MS = 900;

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( 'form[data-smliser-form]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				submit( form );
			} );
		} );

		document.querySelectorAll( 'input[data-password-meter]' ).forEach( ( input ) => {
			input.addEventListener( 'input', () => meter( input ) );
		} );
	} );

	/**
	 * Post a form and show the answer.
	 *
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function submit( form ) {
		const button = form.querySelector( '[type="submit"]' );
		const body   = new FormData( form );
		const target = new URL( window.location.href ).searchParams.get( 'redirect_url' );

		if ( target && form.hasAttribute( 'data-pass-redirect' ) ) {
			body.set( 'redirect_url', target );
		}

		say( form, '', null );
		busy( button, true );

		let response;

		try {
			response = await smliserFetchJSON( form.action, {
				method: ( form.dataset.method || 'POST' ).toUpperCase(),
				body,
				credentials: 'same-origin',
			} );
		} catch ( error ) {
			busy( button, false );
			say( form, error?.message || 'The request failed. Please try again.', 'error' );
			return;
		}

		if ( ! response?.success ) {
			busy( button, false );
			say( form, response?.message || response?.data?.message || 'The request failed. Please try again.', 'error' );
			return;
		}

		say( form, response.message || response.data?.message || 'Done.', 'success' );

		const redirect = response.redirect || response.data?.redirect;

		if ( redirect ) {
			setTimeout( () => {
				window.location.href = redirect;
			}, REDIRECT_DELAY_MS );
			return;
		}

		busy( button, false );
		form.reset();
		form.querySelectorAll( '[data-password-strength]' ).forEach( ( output ) => {
			output.dataset.strength = '';
		} );
	}

	/**
	 * Write the outcome in the form; an empty message hides it.
	 *
	 * @param {HTMLFormElement} form
	 * @param {string}          message
	 * @param {string|null}     state "error" or "success".
	 */
	function say( form, message, state ) {
		const box = form.querySelector( '[data-smliser-form-message]' );

		if ( ! box ) {
			return;
		}

		box.textContent = message;
		box.hidden      = '' === message;

		if ( state ) {
			box.dataset.state = state;
			box.setAttribute( 'role', 'error' === state ? 'alert' : 'status' );
		}
	}

	/**
	 * @param {HTMLButtonElement|null} button
	 * @param {boolean}                on
	 */
	function busy( button, on ) {
		if ( button ) {
			button.disabled = on;
			button.setAttribute( 'aria-busy', String( on ) );
		}
	}

	/**
	 * Rate a password: length, and the kinds of characters it uses.
	 *
	 * @param {HTMLInputElement} input
	 */
	function meter( input ) {
		const output = input.form?.querySelector( '[data-password-strength]' );

		if ( ! output ) {
			return;
		}

		const value = input.value;
		const score = [ value.length >= 8, value.length >= 12, /[a-z]/.test( value ), /[A-Z]/.test( value ), /[0-9]/.test( value ), /[^a-zA-Z0-9]/.test( value ) ]
			.filter( Boolean ).length;

		output.dataset.strength = '' === value ? '' : ( score <= 2 ? 'weak' : ( score <= 4 ? 'medium' : 'strong' ) );
	}
} )();