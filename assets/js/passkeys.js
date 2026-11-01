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
		document.querySelectorAll( '[data-passkey-authenticate]' ).forEach( ( container ) => {
			container.querySelector( '[data-passkey-button]' )?.addEventListener( 'click', async () => {
				const message = container.querySelector( '[data-passkey-message]' );
				const button = container.querySelector( '[data-passkey-button]' );
				button.disabled = true;
				try {
					const start = new FormData();
					start.set( 'challenge', container.dataset.loginChallenge );
					const setup = await smliserFetchJSON( container.dataset.optionsUrl, { method: 'POST', body: start } );
					const options = setup.options;
					options.challenge = fromBase64Url( options.challenge );
					(options.allowCredentials || []).forEach( ( item ) => item.id = fromBase64Url( item.id ) );
					const credential = await navigator.credentials.get( { publicKey: options } );
					if ( ! credential ) throw new Error( 'Passkey verification was cancelled.' );
					const finish = new FormData();
					finish.set( 'challenge', setup.challenge );
					finish.set( 'credential', JSON.stringify( credentialJSON( credential ) ) );
					const result = await smliserFetchJSON( container.dataset.verifyUrl, { method: 'POST', body: finish } );
					show( message, result.message || 'Signed in.' );
					if ( result.redirect ) window.location.assign( result.redirect );
				} catch ( error ) {
					show( message, error.message || 'Passkey verification failed.', true );
				} finally {
					button.disabled = false;
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
