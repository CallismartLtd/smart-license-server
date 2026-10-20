/**
 * Sign-in page behaviour.
 *
 * The theme owns the markup and the look; this script owns the behaviour,
 * through a markup contract any theme can follow:
 *
 *   [data-smliser-auth]                        The root. data-default-panel names
 *                                              the panel shown without a hash.
 *   [data-smliser-auth-panel="<name>"]         One per form: login, signup,
 *                                              forgot-password, reset-password.
 *                                              The URL hash (#signup) picks the
 *                                              visible panel; the others get [hidden].
 *   form[data-smliser-auth-form="<name>"]      Posted with fetch() to its action URL;
 *                                              the answer is JSON.
 *   [data-smliser-auth-message]                In each panel: the outcome is written
 *                                              here, with data-state="error|success".
 *   [data-smliser-auth-continue]               Optional link in the signup panel,
 *                                              shown with the server's redirect URL.
 *   input[data-password-meter]                 A password whose strength is shown on
 *   [data-password-strength]                   the element in the same form, as
 *                                              data-strength="weak|medium|strong".
 *
 * After success:
 *   login           → message, then the server's redirect URL.
 *   signup          → message, the form is hidden, the continue link is shown.
 *   forgot-password → message, the form is cleared.
 *   reset-password  → the login panel, with the message; the key leaves the URL.
 *
 * A redirect_url query parameter is passed on to login and signup.
 * Needs globals.js (smliserFetchJSON, which sends the CSRF header).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.querySelector( '[data-smliser-auth]' );

		if ( root ) {
			init( root );
		}
	} );

	/**
	 * @param {HTMLElement} root
	 */
	function init( root ) {
		const panels = new Map(
			[ ...root.querySelectorAll( '[data-smliser-auth-panel]' ) ].map( ( panel ) => [ panel.dataset.smliserAuthPanel, panel ] )
		);
		const fallback = panels.has( root.dataset.defaultPanel ) ? root.dataset.defaultPanel : panels.keys().next().value;

		const show = ( focus ) => {
			const wanted = window.location.hash.replace( /^#/, '' );
			const name   = panels.has( wanted ) ? wanted : fallback;

			panels.forEach( ( panel, key ) => {
				panel.hidden = key !== name;
			} );

			if ( focus ) {
				panels.get( name )?.querySelector( 'input:not([type="hidden"])' )?.focus();
			}

			return panels.get( name );
		};

		window.addEventListener( 'hashchange', () => show( true ) );
		show( false );

		root.querySelectorAll( 'form[data-smliser-auth-form]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				submit( form, panels );
			} );
		} );

		root.querySelectorAll( 'input[data-password-meter]' ).forEach( ( input ) => {
			input.addEventListener( 'input', () => meter( input ) );
		} );
	}

	/**
	 * Post a form and act on the answer.
	 *
	 * @param {HTMLFormElement}          form
	 * @param {Map<string, HTMLElement>} panels
	 * @return {Promise<void>}
	 */
	async function submit( form, panels ) {
		const name   = form.dataset.smliserAuthForm;
		const panel  = form.closest( '[data-smliser-auth-panel]' );
		const button = form.querySelector( '[type="submit"]' );
		const body   = new FormData( form );
		const target = new URL( window.location.href ).searchParams.get( 'redirect_url' );

		if ( target && [ 'login', 'signup' ].includes( name ) ) {
			body.set( 'redirect_url', target );
		}

		say( panel, '', null );
		busy( button, true );

		let response;

		try {
			response = await smliserFetchJSON( form.action, {
				method: 'POST',
				body,
				credentials: 'same-origin',
			} );
		} catch ( error ) {
			busy( button, false );
			say( panel, error?.message || 'The request failed. Please try again.', 'error' );
			return;
		}

		busy( button, false );

		if ( ! response?.success ) {
			say( panel, response?.message || 'The request failed. Please try again.', 'error' );
			return;
		}

		const message = response.message || 'Done.';

		switch ( name ) {
			case 'login':
				say( panel, message, 'success' );
				busy( button, true );
				setTimeout( () => {
					window.location.href = response.redirect || window.location.origin;
				}, 600 );
				break;

			case 'signup': {
				const next = panel.querySelector( '[data-smliser-auth-continue]' );

				say( panel, message, 'success' );
				form.hidden = true;

				if ( next && response.redirect ) {
					next.href   = response.redirect;
					next.hidden = false;
				}
				break;
			}

			case 'reset-password': {
				const url = new URL( window.location.href );

				url.searchParams.delete( 'key' );
				url.hash = 'login';
				history.replaceState( null, '', url );
				window.dispatchEvent( new HashChangeEvent( 'hashchange' ) );
				say( panels.get( 'login' ), message, 'success' );
				break;
			}

			default:
				form.reset();
				say( panel, message, 'success' );
		}
	}

	/**
	 * Write the outcome in a panel; an empty message hides it.
	 *
	 * @param {HTMLElement|undefined} panel
	 * @param {string}                message
	 * @param {string|null}           state "error" or "success".
	 */
	function say( panel, message, state ) {
		const box = panel?.querySelector( '[data-smliser-auth-message]' );

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
		output.setAttribute( 'aria-label', output.dataset.strength ? `${ output.dataset.strength } password` : '' );
	}
} )();