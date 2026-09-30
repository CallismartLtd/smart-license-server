/**
 * SmartLicenseServer admin UI.
 *
 * Classic script. Load after globals.js and the libraries it relies on
 * (`smliser_var`, `jQuery`, `SmliserModal`, `SmliserToast`, `StringUtils`,
 * `CallismartDatePicker`, `RoleBuilder`, `tinymce`, `Chart`).
 */

( function () {
	'use strict';

	const queryParam = new URLSearchParams( window.location.search );

	/*
	|----------------
	|Shared Utilities
	|----------------
	*/

	/**
	 * Create an element with properties and children.
	 *
	 * @param {string} tag
	 * @param {Object} [props={}] - Assigned directly to the element (not for `style`).
	 * @param {...(Node|string)} children
	 * @return {HTMLElement}
	 */
	function el( tag, props = {}, ...children ) {
		const node = Object.assign( document.createElement( tag ), props );

		node.append( ...children );

		return node;
	}

	/**
	 * Set a custom validity message that clears on the next edit.
	 *
	 * @param {HTMLInputElement|HTMLSelectElement|HTMLTextAreaElement} field
	 * @param {string} message
	 */
	function flagInvalid( field, message ) {
		field.setCustomValidity( message );
		field.addEventListener( 'input', () => field.setCustomValidity( '' ), { once: true } );
	}

	/**
	 * Fade an element out with jQuery, then remove it.
	 *
	 * @param {Element|null} element
	 * @param {Function} [after] - Runs after removal.
	 */
	function fadeOutAndRemove( element, after ) {
		if ( ! element ) {
			return;
		}

		jQuery( element ).fadeOut( 'slow', () => {
			element.remove();
			after?.();
		} );
	}

	/**
	 * Copy text and flash "Copied!" on the button.
	 *
	 * @param {HTMLButtonElement} button
	 * @param {string} text
	 */
	async function copyWithFeedback( button, text ) {
		try {
			await navigator.clipboard.writeText( text );

			button.dataset.label ??= button.textContent;
			button.textContent     = 'Copied!';

			setTimeout( () => {
				button.textContent = button.dataset.label;
			}, 2000 );
		} catch ( error ) {
			console.error( 'Could not copy text', error );
		}
	}

	/**
	 * Build the body and footer for a one-time secret display (API key, download token).
	 *
	 * @param {Object} args
	 * @param {string} args.warning - Text after the "Important:" prefix.
	 * @param {string} args.label - Label before the identifier.
	 * @param {string|number} args.identifier
	 * @param {string} args.secret - The value shown and copied.
	 * @param {string} args.info - Small text on the left of the footer.
	 * @param {string} args.downloadLabel - Download button text.
	 * @return {{ body: HTMLElement, footer: HTMLElement, downloadBtn: HTMLButtonElement }}
	 */
	function buildSecretDelivery( { warning, label, identifier, secret, info, downloadLabel } ) {
		const body = el( 'div', { className: 'smliser-api-key-delivery' },
			el( 'div', { className: 'smliser-api-key-warning' }, el( 'strong', { textContent: 'Important: ' } ), warning ),
			el( 'span', { className: 'smliser-api-key-label' }, label, el( 'code', { textContent: String( identifier ) } ) ),
			el( 'div', { className: 'smliser-api-key-display', textContent: secret } ),
		);

		const downloadBtn = el( 'button', { type: 'button', className: 'button', textContent: downloadLabel } );
		const copyBtn     = el( 'button', { type: 'button', className: 'smliser-copy-btn', textContent: 'Copy Key' } );
		const infoText    = el( 'span', { textContent: info } );
		const btnGroup    = el( 'div', {}, downloadBtn, copyBtn );
		const footer      = el( 'div', { className: 'smliser-modal-footer-api-actions' }, infoText, btnGroup );

		Object.assign( infoText.style, { fontSize: '12px', color: '#666' } );
		Object.assign( btnGroup.style, { display: 'flex', gap: '10px' } );
		Object.assign( footer.style, { display: 'flex', justifyContent: 'space-between', alignItems: 'center', width: '100%' } );

		copyBtn.addEventListener( 'click', () => copyWithFeedback( copyBtn, secret ) );

		return { body, footer, downloadBtn };
	}

	/**
	 * Safe localStorage access; storage can throw in private modes or when blocked.
	 */
	const storage = {
		get( key ) {
			try {
				return localStorage.getItem( key );
			} catch {
				return null;
			}
		},

		set( key, value ) {
			try {
				localStorage.setItem( key, value );
			} catch ( error ) {
				console.warn( `Could not store "${ key }": ${ error.message }` );
			}
		},
	};

	/*
	|-------------
	|Form Controls
	|-------------
	*/

	function initAutoSelect2() {
		const $adminPage = jQuery( '.smliser-admin-page' ).css( 'position', 'relative' );

		jQuery( '.smliser-auto-select2 select' ).select2( {
			width: '100%',
			dropdownParent: $adminPage.length ? $adminPage : jQuery( document.body ),
		} );
	}

	function initLicenseAppSelect() {
		const select = document.querySelector( '.license-app-select' );

		if ( select ) {
			smliserSelect2AppSelect( select );
		}
	}

	function initDatePickers() {
		CallismartDatePicker.mountAll();
	}

	function initHelpTooltips() {
		smliserHelpToolTip();
	}

	function initEntitySearches() {
		/** @type {HTMLSelectElement|null} */
		const usersSearch        = document.querySelector( '#user_id' );
		const ownerSubjectSearch = document.querySelector( '#subject_id' );
		const ownersSearch       = document.querySelector( '#owner_id, #app_owner_id' );

		if ( usersSearch ) {
			const selectedUser = usersSearch.value.trim();

			// A preselected user is locked: submit it through a hidden input instead.
			if ( selectedUser ) {
				usersSearch.closest( 'form' )?.append(
					el( 'input', { type: 'hidden', name: usersSearch.name, value: selectedUser } )
				);

				usersSearch.removeAttribute( 'name' );
				usersSearch.disabled = true;
			}

			smliserSearchSecurityEntities( usersSearch, {
				entityType: 'owner_subjects',
				placeholder: 'Search users...',
				types: [ 'individual' ],
			} );
		}

		if ( ownerSubjectSearch ) {
			smliserSearchSecurityEntities( ownerSubjectSearch, {
				entityType: 'owner_subjects',
				placeholder: 'Search for users or organizations...',
			} );
		}

		if ( ownersSearch ) {
			smliserSearchSecurityEntities( ownersSearch, {
				entityType: 'resource_owners',
				placeholder: 'Search for resource owners...',
			} );
		}
	}

	function initPasswordGenerator() {
		const btn = document.querySelector( '#smliser-generate-password' );

		if ( ! btn ) {
			return;
		}

		const fieldIds = StringUtils.JSONparse( btn.getAttribute( 'data-fields' ), null );

		if ( ! Array.isArray( fieldIds ) ) {
			return;
		}

		/** @type {HTMLInputElement[]} */
		const fields = fieldIds.map( ( id ) => document.getElementById( id ) ).filter( Boolean );

		btn.addEventListener( 'click', ( e ) => {
			if ( ! e.target.closest( '.button' ) ) {
				return;
			}

			const password = StringUtils.generatePassword();

			fields.forEach( ( field ) => {
				field.value = password;
			} );
		} );
	}

	function initPasswordFields() {
		/** @type {NodeListOf<HTMLInputElement>} */
		const fields = document.querySelectorAll( 'input[type="password"].smliser-password-input' );

		fields.forEach( ( pwdInput ) => {
			pwdInput.parentElement.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '.smliser-password-toggle' );

				if ( ! btn ) {
					return;
				}

				const passwordField = document.getElementById( btn.dataset.target );

				if ( ! passwordField ) {
					return;
				}

				const reveal = 'password' === passwordField.type;

				passwordField.type = reveal ? 'text' : 'password';
				btn.querySelector( '.smliser-eye-show' ).style.display = reveal ? 'none' : 'block';
				btn.querySelector( '.smliser-eye-hide' ).style.display = reveal ? 'block' : 'none';
				btn.setAttribute( 'aria-label', reveal ? 'Hide password' : 'Show password' );
			} );

			// Fields render disabled so browsers don't autofill them; unlock after a beat.
			setTimeout( () => {
				if ( ! pwdInput.disabled ) {
					return;
				}

				pwdInput.disabled = false;
				pwdInput.type     = 'text';

				if ( queryParam.has( 'section', 'edit' ) ) {
					pwdInput.required = false;
				}
			}, 500 );
		} );
	}

	function initOptionForms() {
		/** @type {NodeListOf<HTMLFormElement>} */
		const forms = document.querySelectorAll( 'form.smliser-options-form' );

		forms.forEach( ( form ) => {
			form.addEventListener( 'submit', async ( e ) => {
				e.preventDefault();

				const { slug } = form.dataset;

				if ( ! slug ) {
					await SmliserModal.error( 'This form does not have a slug dataset.', 'Form Error.' );
					return;
				}

				const payLoad   = new FormData( form );
				const submitBtn = form.querySelector( 'button[type="submit"]' );
				const spinner   = showSpinner( '.smliser-spinner', true );

				payLoad.set( 'security', smliser_var.csrf_token );
				submitBtn?.setAttribute( 'disabled', 'disabled' );

				try {
					const response = await smliserFetchJSON( smliserAjaxUrl( `options-form/${ slug }` ), {
						method: 'POST',
						body: payLoad,
						credentials: 'same-origin',
					} );

					await SmliserModal.success( response?.data?.message ?? 'Success', 'Saved' );
				} catch ( error ) {
					await SmliserModal.error( error.message, error.statusText || 'Error' );
				} finally {
					removeSpinner( spinner );
					submitBtn?.removeAttribute( 'disabled' );
				}
			} );
		} );
	}

	function initEmailProviderSelect() {
		const select = document.querySelector( '#email_default_provider' );

		if ( ! select ) {
			return;
		}

		jQuery( select ).on( 'select2:select', ( e ) => {
			const value = e.params.data.id;

			jQuery( '.smliser-provider-card' ).removeClass( 'smliser-provider-card--active' );
			jQuery( `.smliser-provider-card.${ CSS.escape( value ) }` ).addClass( 'smliser-provider-card--active' );
		} );
	}

	function initAvatarUploads() {
		document.querySelectorAll( '.smliser-avatar-upload' ).forEach( ( avatarUpload ) => {
			/** @type {HTMLInputElement} */
			const fileInput = avatarUpload.querySelector( 'input[type="file"]' );

			/** @type {HTMLImageElement} */
			const imagePreview     = avatarUpload.querySelector( '.smliser-avatar-upload_image-preview' );
			const imageHolder      = avatarUpload.querySelector( '.smliser-avatar-upload_image-holder' );
			const imageNamePreview = avatarUpload.querySelector( '.smliser-avatar-upload_data-filename' );
			const buttonsRow       = avatarUpload.querySelector( '.smliser-avatar-upload_buttons-row' );

			const original = {
				src: imagePreview.src,
				title: imagePreview.title,
				filename: imageNamePreview?.textContent,
			};

			const MAX_SIZE = 2 * 1024 * 1024;

			imagePreview.draggable = false;

			const revokePreview = () => {
				if ( imagePreview.src.startsWith( 'blob:' ) ) {
					URL.revokeObjectURL( imagePreview.src );
				}
			};

			const clearImagePreview = () => {
				revokePreview();

				fileInput.value    = '';
				imagePreview.src   = original.src;
				imagePreview.title = original.title;

				if ( imageNamePreview ) {
					imageNamePreview.textContent = original.filename;
				}

				buttonsRow?.querySelector( '.clear' )?.classList.add( 'smliser-hide' );
				buttonsRow?.querySelector( '.add-file' )?.classList.remove( 'smliser-hide' );
			};

			const openFullscreen = async () => {
				if ( ! imageHolder?.requestFullscreen ) {
					SmliserToast.show( 'Fullscreen not supported by your browser.', 3000 );
					return;
				}

				try {
					await imageHolder.requestFullscreen();
				} catch ( error ) {
					SmliserToast.show( `Error attempting to enable fullscreen: ${ error.message }`, 3000 );
				}
			};

			avatarUpload.addEventListener( 'dragover', ( e ) => {
				e.preventDefault();
				e.dataTransfer.dropEffect = e.dataTransfer.types.includes( 'Files' ) ? 'copy' : 'none';
				avatarUpload.style.border = 'dashed 3px #dcdcde';
			} );

			avatarUpload.addEventListener( 'dragleave', ( e ) => {
				e.preventDefault();
				avatarUpload.style.removeProperty( 'border' );
			} );

			avatarUpload.addEventListener( 'drop', ( e ) => {
				e.preventDefault();
				avatarUpload.style.removeProperty( 'border' );

				const [ file ] = e.dataTransfer.files;

				if ( ! file ) {
					return;
				}

				const transfer = new DataTransfer();

				transfer.items.add( file );
				fileInput.files = transfer.files;
				fileInput.dispatchEvent( new Event( 'change' ) );
			} );

			imageNamePreview?.addEventListener( 'click', openFullscreen );
			imagePreview.addEventListener( 'dblclick', openFullscreen );

			buttonsRow?.addEventListener( 'click', ( e ) => {
				const btn = e.target.closest( '.button' );

				if ( btn?.classList.contains( 'clear' ) ) {
					clearImagePreview();
				} else if ( btn?.classList.contains( 'add-file' ) ) {
					fileInput.click();
				}
			} );

			fileInput?.addEventListener( 'change', () => {
				const [ image ] = fileInput.files;

				if ( ! image?.type.startsWith( 'image/' ) ) {
					clearImagePreview();
					SmliserToast.show( 'Please upload an image.', 3000 );
					return;
				}

				if ( image.size > MAX_SIZE ) {
					SmliserToast.show( 'File is too large. Maximum size is 2MB.', 3000 );
					fileInput.value = '';
					return;
				}

				revokePreview();

				imagePreview.src   = URL.createObjectURL( image );
				imagePreview.title = image.name;

				if ( imageNamePreview ) {
					imageNamePreview.textContent = image.name;
				}

				buttonsRow?.querySelector( '.clear' )?.classList.remove( 'smliser-hide' );
			} );
		} );
	}

	function initCopyElements() {
		document.querySelectorAll( '.smliser-click-to-copy' ).forEach( ( element ) => {
			element.addEventListener( 'click', () => smliserCopyToClipboard( element.dataset.copyValue ?? '' ) );
		} );
	}

	function initLegacyTooltips() {
		document.querySelectorAll( '.smliser-form-description, .smliser-tooltip' ).forEach( ( target ) => {
			/** @type {HTMLElement|null} */
			let bubble = null;

			target.addEventListener( 'mouseenter', () => {
				const title = target.getAttribute( 'title' );

				if ( ! title ) {
					return;
				}

				target.dataset.title = title;
				target.removeAttribute( 'title' );

				bubble = el( 'div', { className: 'custom-tooltip', innerText: title } );
				document.body.append( bubble );

				const rect = target.getBoundingClientRect();

				bubble.style.top  = `${ rect.top + window.scrollY - bubble.offsetHeight - 5 }px`;
				bubble.style.left = `${ rect.left + window.scrollX + ( rect.width / 2 ) - ( bubble.offsetWidth / 2 ) }px`;
				bubble.classList.add( 'show' );
			} );

			target.addEventListener( 'mouseleave', () => {
				if ( ! bubble ) {
					return;
				}

				const leaving = bubble;

				bubble = null;
				leaving.classList.remove( 'show' );
				setTimeout( () => leaving.remove(), 300 );

				target.setAttribute( 'title', target.dataset.title );
				delete target.dataset.title;
			} );
		} );
	}

	/*
	|----------
	|Navigation
	|----------
	*/

	function initStickyNav() {
		const adminNav = document.querySelector( '.smliser-top-nav' );

		if ( ! adminNav ) {
			return;
		}

		const mobile = window.matchMedia( '(min-width: 19px) and (max-width: 600px)' );

		document.addEventListener( 'scroll', () => {
			adminNav.classList.toggle( 'is-scrolled', window.scrollY > 0 );

			if ( mobile.matches ) {
				adminNav.style.top = window.scrollY > 20 ? '0' : '35px';
			}
		}, { passive: true } );
	}

	/*
	|--------
	|Licenses
	|--------
	*/

	function initLicenseDelete() {
		const deleteBtn = document.getElementById( 'smliser-license-delete-button' );

		if ( ! deleteBtn ) {
			return;
		}

		deleteBtn.addEventListener( 'click', async ( e ) => {
			e.preventDefault();

			const confirmed = await SmliserModal.confirm( 'You are about to delete this license, be careful action cannot be reversed' );

			if ( ! confirmed ) {
				return;
			}

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( 'license-delete', { license_id: queryParam.get( 'id' ) } ), {
					method: 'DELETE',
					credentials: 'same-origin',
				} );

				if ( ! response.success ) {
					throw new Error( response.data?.message ?? 'Unable to delete license' );
				}

				await SmliserModal.success( response.data?.message ?? 'Deleted successfully' );

				if ( response.data?.redirect ) {
					window.location.href = new URL( response.data.location ).href;
				}
			} catch ( error ) {
				await SmliserModal.error( error.message );
			}
		} );
	}

	function initDownloadTokenModal() {
		const trigger = document.querySelector( '.smliser-generate-download-token-btn' );

		if ( ! trigger ) {
			return;
		}

		const config = StringUtils.JSONparse( trigger.getAttribute( 'data-args' ) );

		if ( ! config ) {
			return;
		}

		const { license_id: licenseId, app_name: appName } = config;

		const form = el( 'form', {
			className: 'smliser-license-download-token-form',
			id: 'licenseDownloadTokenForm',
		} );

		form.innerHTML = `
			<input type="hidden" name="license_id">
			<em>Download tokens allow clients to download the application monetized under this license without exposing the primary license key. If expiry is not set, the token will be valid for 24 hours by default.</em>
			<label for="expiryDate" class="smliser-form-label-row">Token Expiry (optional)
				<input type="datetime-local" name="expiry" id="expiryDate" class="smliser-input" smliser-date-picker="date">
			</label>
		`;

		form.elements.license_id.value = licenseId;

		const footer = el( 'div', { className: 'smliser-dialog-buttons' } );

		footer.innerHTML = `<button type="submit" class="smliser-btn" form="${ form.id }">Generate Token</button>`;

		const modal = new SmliserModal( {
			title: appName ? `Generate Download Token for ${ appName }` : 'Generate Download Token',
			body: form,
			showCloseButton: true,
			closeOnBackdropClick: false,
			animation: true,
			closeOnEscape: true,
			footer,
			maxWidth: '600px',
		} );

		let pickerMounted = false;

		modal.on( 'afterOpen', () => {
			if ( ! pickerMounted ) {
				CallismartDatePicker.mountAll();
				pickerMounted = true;
			}
		} );

		modal.on( 'onSubmit', async ( e ) => {
			if ( ! config.is_issued ) {
				await SmliserModal.error( 'Download token can only be generated for issued licenses.' );
				// return;
			}

			try {
				const payLoad = new FormData( e.getBody( 'form' ) );

				payLoad.set( 'security', smliser_var.csrf_token );

				const response = await smliserFetchJSON( smliserAjaxUrl( 'generate-app-download-token' ), {
					method: 'POST',
					body: payLoad,
				} );

				if ( ! response.success ) {
					throw new Error( response.data?.message ?? 'Unable to generate download token' );
				}

				const {
					token,
					expiry,
					licensee_fullname: licensee,
					document_download_url: documentUrl,
				} = response.data ?? {};

				if ( ! token ) {
					await SmliserModal.error( 'Unable to get download token data' );
					return;
				}

				const delivery = buildSecretDelivery( {
					warning: 'Copy this token now. For security, it will not be shown to you again.',
					label: 'License ID: ',
					identifier: licenseId,
					secret: token,
					info: `License issued to: ${ licensee ?? 'N/A' }`,
					downloadLabel: 'Download License File',
				} );

				delivery.downloadBtn.addEventListener( 'click', async () => {
					try {
						const licenseFile = await smliserFetchBlob( documentUrl );
						const tokenLines  = [
							'\r\n',
							`Download Token: ${ token }`,
							`Token Expiry: ${ expiry ?? '24 hours from now' }`,
						].join( '\r\n' );

						const filename = ( licensee ?? 'licensee' ).replace( /\s+/g, '-' ).toLowerCase();

						smliserSaveBlob(
							new Blob( [ licenseFile, tokenLines ], { type: 'text/plain' } ),
							`${ filename }-license-${ Date.now() }.txt`
						);
					} catch ( error ) {
						await SmliserModal.error( error.message, 'Download Failed' );
					}
				} );

				modal.setBody( delivery.body )
					.setFooter( delivery.footer )
					.setTitle( 'Download Token Generated' );

				modal.open().then( () => delivery.downloadBtn.focus() );
				modal.on( 'afterClose', () => window.location.reload() );
			} catch ( error ) {
				SmliserModal.error( error.message, 'Request Error' );
			}
		} );

		trigger.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			modal.open();
		} );
	}

	function initLicenseKeyContainers() {
		document.querySelectorAll( '.smliser-license-obfuscation' ).forEach( ( container ) => {
			const inputField = container.querySelector( '.smliser-license-input' );

			container.addEventListener( 'click', async ( e ) => {
				if ( e.target.closest( '.smliser-licence-key-visibility-toggle' ) ) {
					inputField.classList.toggle( 'active' );
					return;
				}

				if ( ! e.target.closest( '.copy-key' ) ) {
					return;
				}

				try {
					await navigator.clipboard.writeText( container.querySelector( '.smliser-license-text' ).value );
					SmliserToast.show( 'copied', 2000 );
				} catch ( error ) {
					SmliserToast.show( error.message, 2000 );
				}
			} );
		} );
	}

	function initLicenseDomains() {
		const container = document.querySelector( '.smliser-all-license-domains' );

		if ( ! container ) {
			return;
		}

		container.addEventListener( 'click', async ( e ) => {
			if ( e.target.closest( 'a' ) || ! e.target.closest( '.remove' ) ) {
				return;
			}

			const confirmed = await SmliserModal.confirm( 'Are you sure you want to remove this domain?' );

			if ( ! confirmed ) {
				return;
			}

			const row    = e.target.closest( '[data-domain-value]' );
			const domain = row?.dataset.domainValue;

			if ( ! domain ) {
				SmliserToast.show( 'Domain value was not found', 5000 );
				return;
			}

			const url = smliserAjaxUrl( '', {
				action: 'smliser_remove_licensed_domain',
				license_id: queryParam.get( 'license_id' ),
				domain,
			}, { nonce: true } );

			try {
				const response = await smliserFetchJSON( url, { credentials: 'same-origin' } );

				if ( ! response.success ) {
					throw new Error( response.data?.message ?? 'An error occurred' );
				}

				SmliserToast.show( response.data?.message ?? 'Success', 5000 );
				fadeOutAndRemove( row );
			} catch ( error ) {
				SmliserToast.show( error.message, 5000 );
			}
		} );
	}

	function initLicenseForm() {
		/** @type {HTMLFormElement|null} */
		const form = document.querySelector( '.smliser-license-form' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();

			const spinner = showSpinner( '.smliser-spinner', true );

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( form.dataset.slug, {}, { nonce: true } ), {
					credentials: 'same-origin',
					method: 'POST',
					body: new FormData( form ),
				} );

				if ( ! response.success ) {
					throw new Error( response.data?.message ?? response.message ?? 'Unable to save license' );
				}

				await SmliserModal.success( response.data?.message ?? response.message );

				if ( response.redirect_url ) {
					window.location.href = response.redirect_url;
				}
			} catch ( error ) {
				SmliserModal.error( error.message );
			} finally {
				removeSpinner( spinner );
			}
		} );
	}

	/*
	|----
	|Apps
	|----
	*/

	function initUpgradeButton() {
		const updateBtn = document.querySelector( '#smliser-update-btn' );

		if ( ! updateBtn ) {
			return;
		}

		const notice = document.querySelector( '#smliser-click-notice' );

		updateBtn.addEventListener( 'click', async ( e ) => {
			e.preventDefault();

			updateBtn.parentElement.style.display = 'none';

			if ( notice ) {
				const dismissBtn = el( 'span', { className: 'ti ti-x' } );

				Object.assign( dismissBtn.style, { color: 'red', float: 'right' } );
				dismissBtn.addEventListener( 'click', () => dismissBtn.parentElement.parentElement.remove() );

				notice.style.display = 'block';
				notice.append( dismissBtn );
			}

			try {
				const data = await smliserFetchJSON( smliserAjaxUrl( '', { action: 'smliser_upgrade' }, { nonce: true } ) );

				SmliserToast.show(
					data.data?.message || ( data.success ? 'Upgrade successful!' : 'An error occurred' ),
					5000
				);
			} catch ( error ) {
				SmliserToast.show( error.message || 'An unexpected error occurred', 5000 );
			}
		} );
	}

	function initAppStatusActions() {
		document.querySelectorAll( '.smliser-app-delete-button, .smliser-app-restore-button' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async ( e ) => {
				e.preventDefault();

				const args = StringUtils.JSONparse( btn.getAttribute( 'data-action-args' ) );

				if ( ! args ) {
					SmliserToast.show( 'App data not found', 5000 );
					return;
				}

				if ( 'trash' === args.status ) {
					const confirmed = await SmliserModal.confirm(
						`You are about to trash this ${ args.type }, it will be automatically deleted after 60 days. Are you sure you want to proceed?`
					);

					if ( ! confirmed ) {
						return;
					}
				}

				const url = smliserAjaxUrl( '', {
					action: 'smliser_app_status_action',
					app_slug: args.slug,
					app_type: args.type,
					app_status: args.status,
				}, { nonce: true } );

				try {
					const response = await smliserFetchJSON( url );

					if ( ! response.success ) {
						throw new Error( response.data?.message ?? 'Request failed' );
					}

					SmliserToast.show( `Success: ${ response.data.message }`, 3000 );
					setTimeout( () => {
						window.location.href = response.data.redirect_url;
					}, 3000 );
				} catch ( error ) {
					SmliserToast.show( `Error: ${ error.message }`, 6000 );
				}
			} );
		} );
	}

	/*
	|------
	|Tables
	|------
	*/

	function initTableSearch() {
		const searchInput = document.getElementById( 'smliser-search' );
		const tableBody   = document.querySelector( '.smliser-table tbody' );

		if ( ! searchInput || ! tableBody ) {
			return;
		}

		// Columns 2-7: license ID, client, key, service ID, item ID, status.
		const SEARCH_COLUMNS = [ 2, 3, 4, 5, 6, 7 ];

		const rows = Array.from( tableBody.querySelectorAll( 'tr' ), ( row ) => ( {
			row,
			cells: SEARCH_COLUMNS.map( ( n ) => row.querySelector( `td:nth-child(${ n })` )?.textContent.toLowerCase() ?? '' ),
		} ) );

		searchInput.addEventListener( 'input', () => {
			const term    = searchInput.value.toLowerCase();
			let anyMatch  = false;

			for ( const { row, cells } of rows ) {
				const match = cells.some( ( text ) => text.includes( term ) );

				row.style.display = match ? '' : 'none';
				anyMatch ||= match;
			}

			const notFound = tableBody.querySelector( '.smliser-not-found' );

			if ( anyMatch ) {
				notFound?.remove();
			} else if ( ! notFound ) {
				tableBody.insertAdjacentHTML( 'beforeend', '<tr class="smliser-not-found"><td colspan="7">No results found</td></tr>' );
			}
		} );
	}

	function initBulkSelect() {
		/** @type {HTMLInputElement|null} */
		const selectAll = document.querySelector( '#smliser-select-all' );

		if ( ! selectAll ) {
			return;
		}

		/** @type {HTMLInputElement[]} */
		const checkboxes = Array.from( document.querySelectorAll( '.smliser-license-checkbox, .smliser-checkbox' ) );
		let lastChecked  = null;

		selectAll.addEventListener( 'change', () => {
			checkboxes.forEach( ( checkbox ) => {
				checkbox.checked = selectAll.checked;
			} );
		} );

		checkboxes.forEach( ( checkbox, index ) => {
			checkbox.addEventListener( 'click', ( e ) => {
				// Shift-click applies the clicked state to the whole range.
				if ( e.shiftKey && null !== lastChecked ) {
					const start = Math.min( index, lastChecked );
					const end   = Math.max( index, lastChecked );

					for ( let i = start; i <= end; i++ ) {
						checkboxes[ i ].checked = checkbox.checked;
					}
				}

				lastChecked       = index;
				selectAll.checked = checkboxes.every( ( cb ) => cb.checked );
			} );
		} );
	}

	function initEntityDelete() {
		const deleteButtons = document.querySelectorAll( '.smliser-delete-entity' );

		if ( ! deleteButtons.length ) {
			return;
		}

		/**
		 * @param {HTMLTableElement|null} table
		 */
		const renderEmptyTableState = ( table ) => {
			if ( ! table ) {
				return;
			}

			const colCount = table.querySelector( 'thead tr' )?.cells.length || 1;
			const label    = ( queryParam.get( 'tab' ) ?? 'items' ).replace( '-', ' ' );

			table.querySelector( 'thead' )?.classList.add( 'smliser-hide' );
			table.tBodies[ 0 ].innerHTML = `
				<tr class="no-results">
					<td colspan="${ colCount }" style="text-align: center; padding: 20px; background-color: #ffffff">
						No ${ label } found
					</td>
				</tr>`;
		};

		deleteButtons.forEach( ( deleteBtn ) => {
			deleteBtn.addEventListener( 'click', async ( e ) => {
				e.preventDefault();

				const args = StringUtils.JSONparse( deleteBtn.dataset.args, null );

				if ( ! args ) {
					return;
				}

				deleteBtn.blur();
				deleteBtn.style.pointerEvents = 'none';

				try {
					const confirmed = await SmliserModal.confirm( 'Are you sure to delete' );

					if ( ! confirmed ) {
						deleteBtn.focus();
						return;
					}

					const result = await smliserFetchJSON( smliserAjaxUrl( 'delete-account', args, { nonce: true } ), {
						method: 'DELETE',
						credentials: 'same-origin',
						headers: { 'X-HTTP-Method-Override': 'DELETE' },
					} );

					if ( ! result.success ) {
						throw new Error( result.data?.message ?? 'Unable to delete' );
					}

					await SmliserModal.success( result.data?.message || 'Deleted' );

					const tableRow = deleteBtn.closest( 'tr' );
					const table    = tableRow?.closest( 'table' );

					fadeOutAndRemove( tableRow, () => {
						if ( ! table?.tBodies[ 0 ]?.rows.length ) {
							renderEmptyTableState( table );
						}
					} );
				} catch ( error ) {
					await SmliserModal.error( error.message, error.statusText );
				} finally {
					deleteBtn.style.removeProperty( 'pointer-events' );
				}
			} );
		} );
	}

	/*
	|---------
	|Dashboard
	|---------
	*/

	function initDashboardCharts() {
		if ( ! document.querySelector( '.smliser-admin-dashboard-template.overview' ) ) {
			return;
		}

		Object.assign( Chart.defaults.font, {
			family: "'Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', Roboto, sans-serif",
			size: 12,
		} );

		Chart.defaults.color                        = '#64748b'; // Slate 500.
		Chart.defaults.plugins.tooltip.padding      = 12;
		Chart.defaults.plugins.tooltip.borderRadius = 8;
		Chart.defaults.elements.bar.borderRadius    = 4;
		Chart.defaults.elements.line.borderWidth    = 3;
		Chart.defaults.elements.point.radius        = 0; // Hidden until hover.
		Chart.defaults.elements.point.hoverRadius   = 5;

		document.querySelectorAll( 'canvas[data-chart-json]' ).forEach( ( canvas ) => {
			const config = StringUtils.JSONparse( canvas.dataset.chartJson );

			if ( ! config ) {
				return;
			}

			// Smooth, filled area look for line charts.
			if ( 'line' === config.type ) {
				config.data.datasets.forEach( ( dataset ) => {
					dataset.tension = 0.4;
					dataset.fill    = true;
				} );
			}

			new Chart( canvas.getContext( '2d' ), config );
		} );
	}

	/*
	|------------
	|Monetization
	|------------
	*/

	function initMonetization() {
		const ui = document.querySelector( '.smliser-monetization-ui' );

		if ( ! ui ) {
			return;
		}

		const editor = document.querySelector( '#smliser-app-monetization-editor' );

		/** @type {HTMLFormElement} */
		const tierForm = document.querySelector( '#tier-form' );

		/** @type {SmliserModal|null} */
		let editorModal = null;

		const requireField = ( field ) => {
			if ( ! field.value.trim() ) {
				flagInvalid( field, `${ field.getAttribute( 'field-name' ) || 'This field' } is required.` );
			}
		};

		const highlightErrorField = ( fieldId, message ) => {
			const field = tierForm.querySelector( `#${ CSS.escape( fieldId ) }` );

			if ( field ) {
				flagInvalid( field, message );
				field.reportValidity();
			}
		};

		const openEditor = ( isEdit = false ) => {
			if ( ! editorModal ) {
				const submitBtn = el( 'button', {
					type: 'submit',
					className: 'button smliser-nav-btn',
					innerHTML: '<span class="ti ti-cloud"></span> Save',
				} );

				submitBtn.setAttribute( 'form', tierForm.id );
				editor.classList.remove( 'smliser-hide' );

				editorModal = new SmliserModal( {
					title: 'Add Pricing Tier',
					body: editor,
					footer: submitBtn,
				} );
			}

			editorModal.setTitle( isEdit ? 'Edit Pricing Tier' : 'Add Pricing Tier' );
			editorModal.open();
		};

		const actions = {
			addNewTier() {
				tierForm.querySelectorAll( 'input, select, textarea' ).forEach( ( input ) => {
					if ( 'action' === input.name ) {
						input.value = 'smliser_save_monetization_tier';
					} else if ( 'tier_id' === input.name || 'hidden' !== input.type ) {
						input.value = '';
					}
				} );

				openEditor();
			},

			editTier( json ) {
				const tier = StringUtils.JSONparse( json );

				if ( ! tier ) {
					return;
				}

				tierForm.querySelector( 'input[name="action"]' ).value  = 'smliser_save_monetization_tier';
				tierForm.querySelector( 'input[name="tier_id"]' ).value = tier.id ?? '';

				const values = {
					tier_name: tier.name,
					product_id: tier.product_id,
					billing_cycle: tier.billing_cycle,
					provider_id: tier.provider_id,
					max_sites: tier.max_sites,
					features: Array.isArray( tier.features ) ? tier.features.join( ', ' ) : tier.features,
				};

				for ( const [ id, value ] of Object.entries( values ) ) {
					tierForm.querySelector( `#${ id }` ).value = value ?? '';
				}

				openEditor( true );
			},

			async deleteTier( json ) {
				const tier = StringUtils.JSONparse( json );

				if ( ! tier ) {
					return;
				}

				const confirmed = await SmliserModal.confirm( `Are you sure you want to delete tier "${ tier.name }"?` );

				if ( ! confirmed ) {
					return;
				}

				const payLoad = new FormData();

				payLoad.set( 'security', smliser_var.csrf_token );
				payLoad.set( 'monetization_id', tier.monetization_id );
				payLoad.set( 'tier_id', tier.id );

				try {
					const response = await smliserFetchJSON( smliserAjaxUrl( 'pricing-tier' ), { method: 'DELETE', body: payLoad } );

					if ( ! response.success ) {
						SmliserToast.show( response.data?.message || 'Delete failed', 6000 );
						return;
					}

					SmliserToast.show( response.data?.message || 'Tier deleted', 3000 );

					const table = document.querySelector( 'table.tier-list' );

					fadeOutAndRemove( table?.querySelector( `tr.tier-row-${ CSS.escape( String( tier.id ) ) }` ), () => {
						if ( ! table.querySelectorAll( 'tr' ).length ) {
							table.innerHTML = '<tr><td>No pricing tiers has been set</td></tr>';
						}
					} );
				} catch ( error ) {
					SmliserToast.show( error.message || 'Delete failed', 6000 );
				}
			},

			closeModal() {
				editorModal?.close();
			},

			async viewProductData( json ) {
				const tier = StringUtils.JSONparse( json );

				if ( ! tier ) {
					return;
				}

				const imageSlot = el( 'span', { className: 'product-image-slot' } );
				const priceCell = el( 'span', { className: 'price-field', textContent: 'Loading...' } );
				const descCell  = el( 'span', { className: 'desc-field', textContent: 'Loading...' } );
				const overlay   = el( 'div', { className: 'spinner-overlay show' },
					el( 'img', { src: smliser_var.spinner_gif_2x, alt: 'Loading...', className: 'spinner-img' } ),
				);

				const row = ( label, value ) => el( 'tr', {},
					el( 'th', { scope: 'row', textContent: label } ),
					el( 'td', {}, value ?? '' ),
				);

				const body = el( 'div', {},
					el( 'h2', { className: 'product-data-header' },
						imageSlot,
						el( 'span', { className: 'product-title', textContent: tier.name ?? '' } ),
					),
					el( 'table', { className: 'striped' },
						el( 'tbody', {},
							row( 'Product ID', String( tier.product_id ?? '' ) ),
							row( 'Provider', String( tier.provider_id ?? '' ) ),
							row( 'Billing Cycle', String( tier.billing_cycle ?? '' ) ),
							row( 'Price', priceCell ),
							row( 'Description', descCell ),
						),
					),
					overlay,
				);

				const modal = new SmliserModal( {
					body,
					title: 'Product Details',
					closeOnEscape: true,
				} );

				modal.on( 'afterClose', () => modal.destroy() );
				modal.open();

				const url = smliserAjaxUrl( 'tier-product', {
					action: 'smliser_get_product_data',
					provider_id: tier.provider_id,
					product_id: tier.product_id,
				}, { nonce: true } );

				try {
					const response = await smliserFetchJSON( url, { method: 'GET' } );

					if ( ! response.success ) {
						SmliserToast.show( response.data?.message || 'Could not fetch product data', 6000 );
						return;
					}

					const product = response.data?.product ?? {};
					const image   = product.images?.[ 0 ];

					if ( image ) {
						imageSlot.append( el( 'img', { src: image.src, alt: image.alt || 'Product Image', className: 'product-thumb' } ) );
					}

					priceCell.textContent = product.pricing?.price
						? StringUtils.formatCurrency( product.pricing.price, product.currency )
						: 'N/A';

					// Provider-supplied HTML description.
					descCell.innerHTML = product.description || '';
				} catch ( error ) {
					SmliserToast.show( error.message || 'An unexpected error occurred', 6000 );
				} finally {
					overlay.remove();
				}
			},

			/**
			 * @param {HTMLInputElement} input - The toggle switch.
			 */
			async toggleMonetization( input ) {
				const payLoad = new FormData();

				payLoad.set( 'action', 'smliser_toggle_monetization' );
				payLoad.set( 'security', smliser_var.csrf_token );
				payLoad.set( 'monetization_id', input.dataset.monetizationId );
				payLoad.set( 'enabled', input.checked ? '1' : '0' );

				try {
					const response = await smliserFetchJSON( smliserAjaxUrl( 'toggle-monetization-status' ), {
						method: 'POST',
						body: payLoad,
					} );

					if ( ! response.success ) {
						throw new Error( response.data?.message || 'Update failed' );
					}

					SmliserToast.show( response.data?.message || 'Monetization updated', 3000 );
				} catch ( error ) {
					SmliserToast.show( error.message || 'An unexpected error occurred', 6000 );
					input.checked = ! input.checked;
				}
			},
		};

		const run = ( name, ...args ) => {
			if ( name && Object.hasOwn( actions, name ) ) {
				actions[ name ]( ...args );
			}
		};

		ui.addEventListener( 'click', ( e ) => {
			const command = e.target.closest( '#add-pricing-tier, .remove-modal' );

			if ( command ) {
				e.preventDefault();
				run( command.dataset.command );
				return;
			}

			const tierBtn = e.target.closest( '.smliser-tier-edit, .smliser-tier-delete, .smliser-tier-view' );

			if ( tierBtn ) {
				e.preventDefault();
				run( tierBtn.dataset.action, tierBtn.closest( '.smliser-pricing-tier-info' )?.dataset.json );
			}
		} );

		ui.addEventListener( 'change', ( e ) => {
			const input = e.target.closest( '.smliser_toggle-switch-input' );

			if ( 'toggleMonetization' === input?.dataset.action ) {
				actions.toggleMonetization( input );
			}
		} );

		tierForm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();

			tierForm.querySelectorAll( '#tier_name, #product_id, #billing_cycle, #provider_id, #features' ).forEach( requireField );

			if ( ! tierForm.reportValidity() ) {
				return;
			}

			const payLoad = new FormData( tierForm );
			const spinner = showSpinner( '.smliser-spinner', true );

			payLoad.set( 'security', smliser_var.csrf_token );

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( 'save-monetization' ), { method: 'POST', body: payLoad } );

				if ( response.success ) {
					SmliserToast.show( response.data?.message || 'Operation successful', 3000 );
					setTimeout( () => window.location.reload(), 3000 );
					return;
				}

				const message = response.data?.message || 'An unknown error occurred.';

				if ( response.data?.field_id ) {
					highlightErrorField( response.data.field_id, message );
				}

				SmliserToast.show( message, 6000 );
			} catch ( error ) {
				if ( error.field ) {
					highlightErrorField( error.field, error.message );
				}

				SmliserToast.show( error.message || 'An unexpected error occurred', 6000 );
			} finally {
				removeSpinner( spinner );
			}
		} );
	}

	/*
	|------------------
	|Broadcast Messages
	|------------------
	*/

	/**
	 * Initialize the broadcast message editor with no theme flash.
	 */
	function initBroadcastEditor() {
		const selector = '#message-body';
		const targetEl = document.querySelector( selector );

		if ( ! targetEl ) {
			return;
		}

		const isDark = 'dark' === document.documentElement.getAttribute( 'data-theme' );

		let container = targetEl.closest( '.tox-tinymce-wrapper' );

		if ( ! container ) {
			container = el( 'div', { className: 'tox-tinymce-wrapper' } );
			targetEl.before( container );
			container.append( targetEl );
		}

		container.style.opacity       = '0';
		container.style.pointerEvents = 'none';

		if ( tinymce.get( 'message-body' ) ) {
			tinymce.remove( selector );
		}

		tinymce.init( {
			selector,
			skin: isDark ? 'oxide-dark' : 'oxide',
			content_css: isDark ? 'dark' : 'default',
			branding: false,
			license_key: 'gpl',
			menubar: 'file insert table',
			plugins: 'lists link image media table code preview fullscreen autosave searchreplace visualblocks insertdatetime emoticons',
			toolbar: 'add_media_button | styles | alignleft aligncenter alignjustify alignright bullist numlist outdent indent | forecolor backcolor | code fullscreen preview | undo redo',
			height: 600,
			relative_urls: false,
			remove_script_host: false,
			promotion: false,
			valid_children: '+div[div|span],+span[span|div]',
			font_formats: 'Inter=Inter, sans-serif; Arial=Arial, Helvetica, sans-serif; Verdana=Verdana, Geneva, sans-serif; Tahoma=Tahoma, Geneva, sans-serif; Trebuchet MS=Trebuchet MS, Helvetica, sans-serif; Times New Roman=Times New Roman, Times, serif; Georgia=Georgia, serif; Palatino Linotype=Palatino Linotype, Palatino, serif; Courier New=Courier New, Courier, monospace',
			toolbar_mode: 'sliding',
			content_style: `
				body {
					font-family: "Inter", sans-serif;
					font-size: 16px;
					background-color: ${ isDark ? '#1b1e27' : '#ffffff' };
					color: ${ isDark ? '#e6e8ee' : '#1f2430' };
				}
			`,
			setup( editor ) {
				editor.on( 'init', () => {
					requestAnimationFrame( () => {
						container.style.opacity       = '1';
						container.style.pointerEvents = 'all';
					} );
				} );
			},
		} );
	}

	function initBulkMessageForm() {
		/** @type {HTMLFormElement|null} */
		const form = document.querySelector( 'form.smliser-compose-message-container' );

		if ( ! form ) {
			return;
		}

		const appSelect = form.querySelector( '#smliser-app-select' );

		if ( appSelect ) {
			smliserSelect2AppSelect( appSelect );
		}

		initBroadcastEditor();

		// Re-init the editor when the theme toggles.
		new MutationObserver( initBroadcastEditor ).observe( document.documentElement, {
			attributes: true,
			attributeFilter: [ 'data-theme' ],
		} );

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();

			const editor = tinymce.get( 'message-body' );

			editor?.save();

			const subject     = form.querySelector( '#subject' );
			const messageBody = form.querySelector( '#message-body' );

			if ( ! subject.value.trim() ) {
				flagInvalid( subject, 'Message subject is required.' );
			}

			if ( ! messageBody.value.trim() ) {
				editor?.notificationManager.open( {
					text: 'Message body cannot be empty.',
					type: 'error',
					timeout: 5000,
				} );

				return;
			}

			if ( ! form.reportValidity() ) {
				return;
			}

			const payLoad   = new FormData( form );
			const submitBtn = form.querySelector( 'button[type="submit"]' );
			const spinner   = showSpinner( submitBtn );

			payLoad.set( 'security', smliser_var.csrf_token );

			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( form.dataset.slug ), {
					method: 'POST',
					body: payLoad,
					credentials: 'same-origin',
				} );

				if ( ! response.success ) {
					throw new Error( response.data?.message || 'An unknown error occurred.' );
				}

				await SmliserModal.success( response.data?.message || 'Message saved successfully' );

				if ( response.data?.redirect_url ) {
					window.location.href = new URL( response.data.redirect_url ).href;
				}
			} catch ( error ) {
				await SmliserModal.error( error.message );
			} finally {
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}

				removeSpinner( spinner );
			}
		} );
	}

	/*
	|--------------
	|Access Control
	|--------------
	*/

	function initRoleBuilder() {
		const roleBuilderEl = document.querySelector( '#smliser-role-builder' );

		if ( ! roleBuilderEl ) {
			return;
		}

		const existingRoles = StringUtils.JSONparse( roleBuilderEl.getAttribute( 'data-roles' ), null );

		window.SmliserRoleBuilder = new RoleBuilder( roleBuilderEl, smliser_var.default_roles, existingRoles );
	}

	/**
	 * Show a newly generated API key, then send the user to the edit screen on close.
	 *
	 * @param {Object} apiKey - `api_keys` from the save response.
	 * @param {URL} redirectUrl
	 */
	function showApiKeyModal( apiKey, redirectUrl ) {
		const delivery = buildSecretDelivery( {
			warning: 'Save this key now. For security, it will not be shown to you again.',
			label: 'Identifier: ',
			identifier: apiKey.identifier,
			secret: apiKey.api_key,
			info: `For: ${ apiKey.display_name }`,
			downloadLabel: 'Download Key',
		} );

		delivery.downloadBtn.addEventListener( 'click', () => {
			const content = [
				`${ smliser_var.app_name } - API Key Export`,
				'------------------------------------',
				`Display Name : ${ apiKey.display_name }`,
				`Identifier   : ${ apiKey.identifier }`,
				`Description  : ${ apiKey.description || 'N/A' }`,
				`Created At   : ${ new Date().toLocaleString() }`,
				'------------------------------------',
				'SECRET API KEY (Keep this safe):',
				apiKey.api_key,
				'------------------------------------',
				'Note: This key provides access to your service account. Do not share it.',
			].join( '\r\n' );

			smliserSaveBlob( new Blob( [ content ], { type: 'text/plain' } ), `${ apiKey.identifier }_key.txt` );
		} );

		const modal = new SmliserModal( {
			title: 'API Key Generated',
			body: delivery.body,
			footer: delivery.footer,
		} );

		modal.open().then( () => delivery.downloadBtn.focus() );
		modal.on( 'afterClose', () => {
			window.location.href = redirectUrl.href;
		} );
	}

	/**
	 * Route the user after a successful access control form save.
	 *
	 * @param {Object} responseBody - The HTTP response body.
	 */
	function processAfterEntitySave( responseBody ) {
		const data        = responseBody.data ?? {};
		const section     = queryParam.get( 'section' );
		const redirectUrl = new URL( window.location.href );

		redirectUrl.searchParams.set( 'section', 'edit' );
		redirectUrl.searchParams.set( 'id', data.entity_id ?? 0 );

		if ( 'add-new' === section ) {
			if ( [ 'user', 'organization', 'owner' ].includes( data.entity ) ) {
				window.location.href = redirectUrl.href;
				return;
			}

			if ( 'service_account' === data.entity ) {
				if ( data.api_keys?.api_key ) {
					showApiKeyModal( data.api_keys, redirectUrl );
				} else {
					console.warn( 'Unable to get API key Data' );
				}

				return;
			}
		}

		if ( 'organization' === data.entity && 'add-new-member' === section ) {
			redirectUrl.searchParams.set( 'id', queryParam.get( 'org_id' ) );
			redirectUrl.searchParams.delete( 'org_id' );
			window.location.href = redirectUrl.href;
			return;
		}

		if ( 'edit' === section ) {
			window.location.reload();
		}
	}

	function initAccessControlForm() {
		/** @type {HTMLFormElement|null} */
		const form = document.querySelector( '.smliser-access-control-form' );

		if ( ! form ) {
			return;
		}

		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();

			const payLoad = new FormData( form );

			if ( window.SmliserRoleBuilder ) {
				const { roleSlug, roleLabel, capabilities } = window.SmliserRoleBuilder.getValue();

				payLoad.set( 'role_slug', roleSlug ?? '' );
				payLoad.set( 'role_label', roleLabel );
				capabilities.forEach( ( cap ) => payLoad.append( 'capabilities[]', cap ) );
			}

			payLoad.set( 'security', smliser_var.csrf_token );

			const spinner = showSpinner( '.smliser-spinner', true );

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( form.dataset.slug ).href, {
					method: 'POST',
					body: payLoad,
					credentials: 'same-origin',
				} );

				const message = response?.data?.message;

				if ( ! response.success ) {
					throw new Error( message ?? 'Request failed.' );
				}

				await SmliserModal.success( message ?? 'Request was successful, but no response message.' );
				processAfterEntitySave( response );
			} catch ( error ) {
				await SmliserModal.error( error.message );
			} finally {
				removeSpinner( spinner );
			}
		} );

		initOrganizationMembers();
	}

	function initOrganizationMembers() {
		const container = document.querySelector( '.smliser-organization-members-list' );

		if ( ! container ) {
			return;
		}

		container.addEventListener( 'click', async ( e ) => {
			const addBtn    = e.target.closest( '.smliser-add-member-to-org-btn' );
			const editBtn   = e.target.closest( '.button.edit-member' );
			const deleteBtn = e.target.closest( '.button.delete-member' );
			const clicked   = addBtn ?? editBtn ?? deleteBtn;

			if ( ! clicked ) {
				return;
			}

			e.preventDefault();
			clicked.disabled = true;

			const orgId = queryParam.get( 'id' );

			if ( deleteBtn ) {
				const confirmed = await SmliserModal.confirm( {
					confirmText: 'Yes',
					cancelText: 'No',
					message: 'Are you sure you want to remove the selected member from this organization?',
					title: 'Confirm Member Removal',
				} );

				if ( ! confirmed ) {
					clicked.disabled = false;
					return;
				}

				const url = smliserAjaxUrl( 'delete-organization-member', {
					organization_id: orgId,
					member_id: deleteBtn.dataset.memberId,
				}, { nonce: true } );

				const spinner = showSpinner( '.smliser-spinner', true );

				try {
					const response = await smliserFetchJSON( url.href, { method: 'DELETE' } );
					const message  = response?.data?.message;

					if ( ! response?.success ) {
						throw new Error( message ?? 'Unable to remove member' );
					}

					fadeOutAndRemove( clicked.closest( 'li.smliser-org-member' ) );
					SmliserToast.show( message, 5000 );
				} catch ( error ) {
					clicked.disabled = false;
					SmliserToast.show( error.message, 10000 );
				} finally {
					removeSpinner( spinner );
				}

				return;
			}

			const params = new URLSearchParams( queryParam );

			params.set( 'org_id', orgId );

			if ( editBtn ) {
				params.set( 'section', 'edit-member' );
				params.set( 'id', editBtn.dataset.memberId );
			} else {
				params.set( 'section', 'add-new-member' );
			}

			showSpinner( '.smliser-spinner', true );

			const url = new URL( window.location.href );

			url.search           = params.toString();
			window.location.href = url.href;
		} );
	}

	/*
	|---------------
	|Email Templates
	|---------------
	*/

	function initEmailTemplateFilters() {
		if ( ! document.querySelector( '#smliser-email-templates-table' ) ) {
			return;
		}

		const buttons = document.querySelectorAll( '.smliser-filter-btn' );
		const rows    = document.querySelectorAll( '#smliser-email-templates-table tbody tr' );

		buttons.forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const { group } = btn.dataset;

				buttons.forEach( ( b ) => b.classList.toggle( 'smliser-filter-btn--active', b === btn ) );
				rows.forEach( ( row ) => {
					row.style.display = 'all' === group || row.dataset.group === group ? '' : 'none';
				} );
			} );
		} );
	}

	function initEmailTemplateToggles() {
		const STATES = {
			// Template is now enabled: offer to disable.
			enabled: { border: '1px solid #fecaca', background: '#fef2f2', color: '#991b1b', icon: [ 'ti-eye', 'ti-eye-off' ], label: 'Disable', title: 'Disable this template' },
			// Template is now disabled: offer to enable.
			disabled: { border: '1px solid #bbf7d0', background: '#f0fdf4', color: '#166534', icon: [ 'ti-eye-off', 'ti-eye' ], label: 'Enable', title: 'Enable this template' },
		};

		document.querySelectorAll( '.smliser-template-toggle' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				const enabling = '0' === btn.dataset.enabled;
				const payLoad  = new FormData();

				payLoad.set( 'security', smliser_var.csrf_token );
				payLoad.set( 'template_key', btn.dataset.key );

				btn.disabled = true;

				try {
					const response = await smliserFetchJSON( smliserAjaxUrl( 'options-form/email-template-status-toggle' ), {
						method: 'POST',
						credentials: 'same-origin',
						body: payLoad,
					} );

					if ( ! response.success ) {
						return;
					}

					await SmliserModal.success( response.data?.message || 'Success' );

					const { border, background, color, icon, label, title } = STATES[ enabling ? 'enabled' : 'disabled' ];

					btn.dataset.enabled = response.data.is_enable ? '1' : '0';
					Object.assign( btn.style, { border, background, color } );
					btn.querySelector( 'i.ti' )?.classList.replace( ...icon );
					btn.lastChild.textContent = label;
					btn.title                 = title;
				} catch ( error ) {
					SmliserModal.error( error.message, 'Error Occurred' );
				} finally {
					btn.disabled = false;
				}
			} );
		} );
	}

	/*
	|--------------
	|Cache Adapters
	|--------------
	*/

	/**
	 * Run a cache adapter request with a loading state on the button.
	 *
	 * @param {HTMLButtonElement} btn
	 * @param {string} route
	 * @param {FormData} payLoad
	 * @param {string} fallbackMessage
	 * @return {Promise<Object>} The response body.
	 */
	async function runCacheAdapterRequest( btn, route, payLoad, fallbackMessage ) {
		const originalHtml = btn.innerHTML;

		btn.innerHTML = '<i class="ti ti-loader rotate"></i>';
		btn.disabled  = true;
		payLoad.set( 'security', smliser_var.csrf_token );

		try {
			const response = await smliserFetchJSON( smliserAjaxUrl( route ), {
				method: 'POST',
				credentials: 'same-origin',
				body: payLoad,
			} );

			if ( ! response.success ) {
				throw new Error( response.data?.message || fallbackMessage );
			}

			return response;
		} finally {
			btn.innerHTML = originalHtml;
			btn.disabled  = false;
		}
	}

	function initCacheAdapterButtons() {
		const testBtn  = document.querySelector( '.test-cache-btn' );
		const resetBtn = document.querySelector( '.reset-cache-btn' );

		testBtn?.addEventListener( 'click', async ( e ) => {
			e.preventDefault();

			const form = testBtn.closest( 'form' );

			if ( ! form ) {
				return;
			}

			/** @type {NodeListOf<HTMLInputElement>} */
			const requiredFields = form.querySelectorAll( 'input[required]' );
			let hasError         = false;

			requiredFields.forEach( ( input ) => {
				if ( ! input.value.trim() ) {
					flagInvalid( input, `${ input.getAttribute( 'field_name' ) } is required.` );
					hasError = true;
				}
			} );

			if ( hasError ) {
				form.reportValidity();
				return;
			}

			try {
				const result = await runCacheAdapterRequest( testBtn, 'options-form/cache-test-adapter', new FormData( form ), 'Something went wrong!' );

				await SmliserModal.success( result.data?.message || 'Test passed' );
			} catch ( error ) {
				await SmliserModal.error( error.message, 'Test Failed' );
			}
		} );

		resetBtn?.addEventListener( 'click', async ( e ) => {
			e.preventDefault();

			const confirmed = await SmliserModal.confirm( 'Are you sure you want to reset the cache adapter to default settings?' );

			if ( ! confirmed ) {
				return;
			}

			const payLoad = new FormData();

			payLoad.set( 'adapter_id', queryParam.get( 'adapter' ) ?? '' );

			try {
				const result = await runCacheAdapterRequest( resetBtn, 'options-form/cache-reset-adapter', payLoad, 'Something went wrong!' );

				await SmliserModal.success( result.data?.message || 'Reset successful' );
				window.location.reload();
			} catch ( error ) {
				await SmliserModal.error( error.message, 'Reset Failed' );
			}
		} );
	}

	/*
	|------
	|Queues
	|------
	*/

	/**
	 * Open a detail modal for a queues table action.
	 *
	 * @param {HTMLElement} trigger - Element carrying `data-title` and `data-content`.
	 */
	function openQueueDetails( trigger ) {
		const modal = new SmliserModal( {
			title: trigger.dataset.title || 'Detail',
			// Content is raw text, not HTML.
			body: el( 'pre', { className: 'smliser-detail-modal-content', textContent: trigger.dataset.content || '' } ),
			width: '640px',
			customClass: 'smliser-detail-modal',
		} );

		modal.on( 'afterClose', () => modal.destroy() );
		modal.open();
	}

	function initQueueDetails() {
		document.querySelectorAll( '.smliser-view-detail' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => openQueueDetails( btn ) );
		} );
	}

	/*
	|-----------
	|Diagnostics
	|-----------
	*/

	function initDiagnostics() {
		const grid = document.getElementById( 'smliser-diagnostics-grid' );

		if ( ! grid ) {
			return;
		}

		const BREAKPOINTS = [
			{ maxWidth: 640, columns: 1 },
			{ maxWidth: 1100, columns: 2 },
			{ maxWidth: Infinity, columns: 3 },
		];

		const columnCountForViewport = () => BREAKPOINTS.find( ( bp ) => window.innerWidth <= bp.maxWidth ).columns;

		/**
		 * Greedy shortest-column-first placement: measure each panel, then place it
		 * into whichever column is currently shortest. Runs only on load and on
		 * breakpoint changes, never on toggle.
		 *
		 * @param {HTMLDetailsElement[]} panels
		 * @param {number} columnCount
		 */
		const distribute = ( panels, columnCount ) => {
			// Measure before moving anything; moving nodes changes layout mid-measurement.
			const heights = panels.map( ( panel ) => panel.getBoundingClientRect().height );
			const columns = Array.from( { length: columnCount }, () => el( 'div', { className: 'smliser-diagnostics-column' } ) );
			const totals  = new Array( columnCount ).fill( 0 );

			panels.forEach( ( panel, index ) => {
				const shortest = totals.indexOf( Math.min( ...totals ) );

				columns[ shortest ].append( panel ); // Moves the node; state and listeners stay intact.
				totals[ shortest ] += heights[ index ];
			} );

			grid.replaceChildren( el( 'div', { className: 'smliser-diagnostics-columns' }, ...columns ) );
			grid.classList.add( 'smliser-diagnostics-grid--columns' );
		};

		let currentColumnCount = null;

		const apply = () => {
			const count = columnCountForViewport();

			// Never reshuffle within the same breakpoint.
			if ( count === currentColumnCount ) {
				return;
			}

			currentColumnCount = count;

			/** @type {HTMLDetailsElement[]} */
			const panels = Array.from( grid.querySelectorAll( '.smliser-diagnostics-panel' ) );

			if ( ! panels.length ) {
				return;
			}

			// Flatten back into the grid so heights are measured in single-column flow.
			grid.replaceChildren( ...panels );
			distribute( panels, count );
		};

		const restoreState = () => {
			grid.querySelectorAll( '.smliser-diagnostics-panel[id]' ).forEach( ( panel ) => {
				const stored = storage.get( panel.id );

				if ( null !== stored ) {
					panel.open = '1' === stored;
				}
			} );
		};

		apply();
		restoreState();

		let resizeTimer = null;

		window.addEventListener( 'resize', () => {
			clearTimeout( resizeTimer );
			resizeTimer = setTimeout( apply, 200 );
		} );

		// `toggle` doesn't bubble, so listen in the capture phase.
		grid.addEventListener( 'toggle', ( e ) => {
			const panel = e.target;

			if ( panel.matches?.( '.smliser-diagnostics-panel' ) && panel.id ) {
				storage.set( panel.id, panel.open ? '1' : '0' );
			}
		}, true );
	}

	/*
	|----
	|Boot
	|----
	*/

	const initializers = [
		initAutoSelect2,
		initLicenseAppSelect,
		initDatePickers,
		initHelpTooltips,
		initEntitySearches,
		initPasswordGenerator,
		initPasswordFields,
		initStickyNav,
		initOptionForms,
		initEmailProviderSelect,
		initLicenseDelete,
		initDownloadTokenModal,
		initLicenseKeyContainers,
		initTableSearch,
		initLegacyTooltips,
		initUpgradeButton,
		initAppStatusActions,
		initBulkSelect,
		initDashboardCharts,
		initMonetization,
		initBulkMessageForm,
		initCopyElements,
		initLicenseDomains,
		initRoleBuilder,
		initAccessControlForm,
		initAvatarUploads,
		initEntityDelete,
		initLicenseForm,
		initEmailTemplateFilters,
		initEmailTemplateToggles,
		initCacheAdapterButtons,
		initQueueDetails,
		initDiagnostics,
	];

	/**
	 * Run every initializer. One failing feature doesn't stop the rest.
	 */
	function boot() {
		for ( const init of initializers ) {
			try {
				init();
			} catch ( error ) {
				console.error( `[smliser] ${ init.name } failed:`, error );
			}
		}
	}

	document.addEventListener( 'click', smliserActionBtns );

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot, { once: true } );
	} else {
		boot();
	}
} )();