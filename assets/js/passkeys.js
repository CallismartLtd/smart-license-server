/**
 * Passkeys.
 *
 * A passkey replaces the password; it is not a second step.
 *
 *   form[data-passkey-register]   Register a passkey (account security page).
 *                                 data-start-url, data-finish-url.
 *   [data-passkey-login]          Sign in with a passkey (sign-in page), hidden
 *                                 until the browser supports passkeys.
 *                                 data-options-url, data-verify-url.
 *   [data-passkey-button]         Inside it: starts the sign-in.
 *   [data-passkey-message]        Inside either: shows the outcome.
 *   [data-passkey-remember]       The sign-in page's "keep me signed in" checkbox.
 *
 * Needs globals.js (smliserFetchJSON, which sends the CSRF header).
 */
( function () {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-passkey-register]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', async ( event ) => {
				event.preventDefault();
				const message = form.querySelector( '[data-passkey-message]' );
				const button = form.querySelector( '[type="submit"]' );
				button.disabled = true;
				try {
					if ( ! window.PublicKeyCredential || ! navigator.credentials ) {
						throw new Error( 'This browser does not support passkeys.' );
					}
					const start = new FormData( form );
					const setup = await smliserFetchJSON( form.dataset.startUrl, { method: 'POST', body: start } );
					const options = setup.options;
					options.challenge = fromBase64Url( options.challenge );
					options.user.id = fromBase64Url( options.user.id );
					(options.excludeCredentials || []).forEach( ( item ) => item.id = fromBase64Url( item.id ) );
					const credential = await navigator.credentials.create( { publicKey: options } );
					if ( ! credential ) throw new Error( 'Passkey setup was cancelled.' );
					const finish = new FormData();
					finish.set( 'challenge', setup.challenge );
					finish.set( 'credential', JSON.stringify( credentialJSON( credential ) ) );
					const result = await smliserFetchJSON( form.dataset.finishUrl, { method: 'POST', body: finish } );
					show( message, result.message || 'Passkey registered.' );
					if ( result.redirect ) window.location.assign( result.redirect );
				} catch ( error ) {
					show( message, error.message || 'Passkey registration failed.', true );
				} finally {
					button.disabled = false;
				}
			} );
		} );
		// Sign in. A passkey replaces the password, so nothing is typed first: the
		// browser offers the passkeys it holds for this site and the server learns
		// who signed in from the one chosen.
		document.querySelectorAll( '[data-passkey-login]' ).forEach( ( container ) => {
			const button = container.querySelector( '[data-passkey-button]' );
			const message = container.querySelector( '[data-passkey-message]' );

			if ( ! button || ! window.PublicKeyCredential || ! navigator.credentials ) {
				return;
			}

			// Shown only where the browser can use a passkey.
			container.hidden = false;

			button.addEventListener( 'click', async () => {
				let redirecting = false;
				button.disabled = true;
				message.hidden = true;
				try {
					const start = new FormData();
					const target = new URL( window.location.href ).searchParams.get( 'redirect_url' );
					if ( target ) start.set( 'redirect_url', target );
					if ( document.querySelector( '[data-passkey-remember]' )?.checked ) start.set( 'remember', '1' );
					const setup = await smliserFetchJSON( container.dataset.optionsUrl, { method: 'POST', body: start } );
					const options = setup.options;
					options.challenge = fromBase64Url( options.challenge );
					( options.allowCredentials || [] ).forEach( ( item ) => item.id = fromBase64Url( item.id ) );
					const credential = await navigator.credentials.get( { publicKey: options } );
					if ( ! credential ) throw new Error( 'Passkey sign-in was cancelled.' );
					const finish = new FormData();
					finish.set( 'challenge', setup.challenge );
					finish.set( 'credential', JSON.stringify( credentialJSON( credential ) ) );
					const result = await smliserFetchJSON( container.dataset.verifyUrl, { method: 'POST', body: finish } );
					show( message, result.message || 'Signed in.' );
					if ( result.redirect ) {
						redirecting = true;
						window.location.assign( result.redirect );
					}
				} catch ( error ) {
					show( message, error.message || 'Passkey sign-in failed.', true );
				} finally {
					button.disabled = redirecting;
				}
			} );
		} );
	} );

	function fromBase64Url( value ) {
		const padded = value.replace( /-/g, '+' ).replace( /_/g, '/' ) + '='.repeat( ( 4 - value.length % 4 ) % 4 );
		const raw = atob( padded );
		return Uint8Array.from( raw, ( character ) => character.charCodeAt( 0 ) ).buffer;
	}

	function credentialJSON( credential ) {
		const response = credential.response;
		const result = {
			clientDataJSON: toBase64Url( response.clientDataJSON ),
		};
		if ( 'attestationObject' in response ) {
			result.attestationObject = toBase64Url( response.attestationObject );
			result.transports = response.getTransports ? response.getTransports() : [];
		} else {
			result.authenticatorData = toBase64Url( response.authenticatorData );
			result.signature = toBase64Url( response.signature );
			result.userHandle = response.userHandle ? toBase64Url( response.userHandle ) : null;
		}
		return {
			id: credential.id,
			rawId: toBase64Url( credential.rawId ),
			type: credential.type,
			response: result,
		};
	}

	function toBase64Url( buffer ) {
		const bytes = new Uint8Array( buffer );
		let binary = '';
		bytes.forEach( ( byte ) => binary += String.fromCharCode( byte ) );
		return btoa( binary ).replace( /\+/g, '-' ).replace( /\//g, '_' ).replace( /=+$/g, '' );
	}

	function show( node, text, error = false ) {
		node.hidden = false;
		node.textContent = text;
		node.dataset.state = error ? 'error' : 'success';
	}
} )();