/**
 * SmartLicenseServer: shared client-side helpers.
 *
 * Classic script. The public helpers are published on `window` so every
 * admin and client script can call them as globals.
 *
 * These helpers expect the following globals to exist when they run:
 * `smliser_var`, `jQuery` (with Select2), `SmliserToast`, `SmliserModal`, `StringUtils`.
 */

( function () {
	'use strict';

	/*
	|-------
	|Spinner
	|-------
	*/

	/**
	 * Add a loading spinner to an element.
	 *
	 * Any spinner already added to the same element is replaced.
	 *
	 * @param {string|HTMLElement} selector - Element or CSS selector.
	 * @param {boolean} [larger=false] - Use the 2x spinner image.
	 * @return {HTMLImageElement|null} The spinner image, or null when the element is not found.
	 */
	function showSpinner( selector, larger = false ) {
		const element = selector instanceof HTMLElement ? selector : document.querySelector( selector );

		if ( ! element ) {
			console.warn( 'Spinner element not found' );
			return null;
		}

		element.querySelector( '#smliser-spinner-image' )?.remove();

		const image = document.createElement( 'img' );

		image.src = larger ? smliser_var.spinner_gif_2x : smliser_var.spinner_gif;
		image.id  = 'smliser-spinner-image';
		image.alt = '';

		element.append( image );
		document.body.style.cursor = 'progress';

		return image;
	}

	/**
	 * Remove a spinner added by showSpinner() and restore the cursor.
	 *
	 * @param {HTMLImageElement|null|undefined} spinner
	 */
	function removeSpinner( spinner ) {
		spinner?.remove();
		document.body.style.removeProperty( 'cursor' );
	}

	/*
	|---------
	|AJAX URLs
	|---------
	*/

	/**
	 * Build a URL for an internal AJAX route.
	 *
	 * The slug is appended to the base path as `/<slug>/`. Pass an empty slug
	 * for legacy `?action=` style endpoints.
	 *
	 * The CSRF token is never put in the URL (URLs end up in logs and browser
	 * history); smliserFetch() sends it in a header instead.
	 *
	 * @param {string} [slug=''] - Route slug. Surrounding slashes are trimmed.
	 * @param {Object<string, string|number|boolean|null|undefined>} [params={}] - Query parameters. Null and undefined values are skipped.
	 * @param {Object} [options={}]
	 * @param {boolean} [options.nonce=false] - Deprecated and ignored: smliserFetch() sends the CSRF token in a header.
	 * @param {string} [options.base] - Base URL. Defaults to `smliser_var.ajaxURL`.
	 * @return {URL}
	 */
	function smliserAjaxUrl( slug = '', params = {}, { base = smliser_var.ajaxURL } = {} ) {
		const url  = new URL( base, window.location.origin );
		const path = String( slug ).replace( /^\/+|\/+$/g, '' );

		if ( path ) {
			url.pathname += `/${ path }/`;
		}

		for ( const [ key, value ] of Object.entries( params ) ) {
			if ( null !== value && undefined !== value ) {
				url.searchParams.set( key, String( value ) );
			}
		}

		return url;
	}

	/*
	|----
	|CSRF
	|----
	*/

	/**
	 * Methods that never change state, so never carry the CSRF token.
	 *
	 * @type {string[]}
	 */
	const CSRF_SAFE_METHODS = [ 'GET', 'HEAD', 'OPTIONS' ];

	/**
	 * The CSRF header as a plain object, for requests made without smliserFetch().
	 *
	 * @return {Object<string, string>} `{ 'X-CSRF-Token': token }`, or `{}` when no token is available.
	 */
	function smliserCsrfHeader() {
		const token = smliser_var?.csrf_token;

		return token ? { [ smliser_var.csrf_header || 'X-CSRF-Token' ]: token } : {};
	}

	/**
	 * Add the CSRF header to a request that changes state on this site.
	 *
	 * Safe methods and requests to other origins are left alone, so the token
	 * is never sent anywhere but this application.
	 *
	 * @param {URL|RequestInfo} url
	 * @param {RequestInit} init
	 * @return {RequestInit}
	 */
	function withCsrfHeader( url, init ) {
		const request = url instanceof Request ? url : null;
		const method  = String( init.method || request?.method || 'GET' ).toUpperCase();

		if ( CSRF_SAFE_METHODS.includes( method ) ) {
			return init;
		}

		let target;

		try {
			target = new URL( request ? request.url : String( url ), window.location.href );
		} catch {
			return init;
		}

		if ( target.origin !== window.location.origin ) {
			return init;
		}

		const headers = new Headers( init.headers ?? request?.headers );

		for ( const [ name, value ] of Object.entries( smliserCsrfHeader() ) ) {
			if ( ! headers.has( name ) ) {
				headers.set( name, value );
			}
		}

		return { ...init, headers };
	}

	/*
	|-----
	|Fetch
	|-----
	*/

	/**
	 * Error thrown by smliserFetch() for HTTP errors, network failures and unreadable bodies.
	 */
	class SmliserFetchError extends Error {
		/**
		 * @param {string} message
		 * @param {Object} [details={}]
		 * @param {string|null} [details.field=null] - Form field the error relates to.
		 * @param {string|null} [details.code=null] - Server error code, or one of `offline`, `network_error`, `timeout`, `aborted`, `request_failed`, `parse_error`.
		 * @param {number} [details.status=0] - HTTP status. 0 when the request never completed.
		 * @param {string} [details.statusText='']
		 * @param {string} [details.contentType='']
		 * @param {*} [details.cause] - Underlying error, if any.
		 */
		constructor( message, { field = null, code = null, status = 0, statusText = '', contentType = '', cause } = {} ) {
			super( message, undefined !== cause ? { cause } : undefined );

			this.name        = 'SmliserFetchError';
			this.field       = field;
			this.code        = code;
			this.status      = status;
			this.statusText  = statusText;
			this.contentType = contentType;
		}
	}

	/**
	 * @typedef {'json'|'text'|'html'|'blob'|'arraybuffer'|'formdata'|'auto'} SmliserResponseType
	 */

	/**
	 * Fetch wrapper with consistent error parsing.
	 *
	 * @param {URL|RequestInfo} url - The URL to fetch.
	 * @param {RequestInit & { responseType?: SmliserResponseType }} [options={}] - Fetch options.
	 *        `responseType` defaults to `json`. `auto` picks a parser from the Content-Type header.
	 * @return {Promise<*>} The parsed response body.
	 * @throws {SmliserFetchError}
	 */
	async function smliserFetch( url, { responseType = 'json', ...init } = {} ) {
		let response;

		try {
			response = await fetch( url, withCsrfHeader( url, init ) );
		} catch ( error ) {
			throw toNetworkError( error );
		}

		const contentType = response.headers.get( 'content-type' ) ?? '';

		if ( ! response.ok ) {
			throw await toHttpError( response, contentType );
		}

		try {
			return await parseBody( response, responseType, contentType );
		} catch ( error ) {
			throw new SmliserFetchError( 'The server response could not be read.', {
				code: 'parse_error',
				status: response.status,
				statusText: response.statusText,
				contentType,
				cause: error,
			} );
		}
	}

	/**
	 * Parse a successful response body.
	 *
	 * @param {Response} response
	 * @param {SmliserResponseType} responseType
	 * @param {string} contentType
	 * @return {Promise<*>}
	 */
	async function parseBody( response, responseType, contentType ) {
		switch ( responseType ) {
			case 'json':
				if ( contentType.includes( 'application/json' ) ) {
					return response.json();
				}

				// Not declared as JSON: try anyway, fall back to a bare success object.
				try {
					return await response.json();
				} catch {
					return { success: true };
				}

			case 'text':
			case 'html':
				return response.text();

			case 'blob':
				return response.blob();

			case 'arraybuffer':
				return response.arrayBuffer();

			case 'formdata':
				return response.formData();

			default:
				if ( contentType.includes( 'application/json' ) ) {
					return response.json();
				}

				if ( contentType.includes( 'text/' ) ) {
					return response.text();
				}

				return response.blob();
		}
	}

	/**
	 * Build a SmliserFetchError from a non-2xx response.
	 *
	 * @param {Response} response
	 * @param {string} contentType
	 * @return {Promise<SmliserFetchError>}
	 */
	async function toHttpError( response, contentType ) {
		let message = `HTTP Error ${ response.status }: ${ response.statusText }`;
		let field   = null;
		let code    = null;

		try {
			if ( contentType.includes( 'application/json' ) ) {
				const body = await response.json();

				message = body.data?.message || body.message || body.error?.message || message;
				field   = body.data?.field_id ?? body.field ?? null;
				code    = body.code ?? body.data?.code ?? null;
			} else if ( contentType.includes( 'text/html' ) ) {
				const doc     = new DOMParser().parseFromString( await response.text(), 'text/html' );
				const element = doc.querySelector( '.error-message, .wp-die-message, h1, title' );

				message = element?.textContent.trim() || message;
			} else {
				message = ( await response.text() ) || message;
			}
		} catch ( error ) {
			console.error( 'Failed to parse error response:', error );
		}

		return new SmliserFetchError( message, {
			field,
			code,
			status: response.status,
			statusText: response.statusText,
			contentType,
		} );
	}

	/**
	 * Build a SmliserFetchError for a request that never got a response.
	 *
	 * @param {*} error - The rejection from fetch().
	 * @return {SmliserFetchError}
	 */
	function toNetworkError( error ) {
		let code    = 'request_failed';
		let message = error?.message || 'An unexpected error occurred';

		if ( 'TimeoutError' === error?.name ) {
			code    = 'timeout';
			message = 'The request timed out.';
		} else if ( 'AbortError' === error?.name ) {
			code    = 'aborted';
			message = 'The request was aborted.';
		} else if ( error instanceof TypeError ) {
			// The request never reached the server.
			if ( ! navigator.onLine ) {
				code    = 'offline';
				message = 'You appear to be offline. Please check your connection.';
			} else {
				// Online but still a TypeError: almost always DNS or CORS.
				code    = 'network_error';
				message = 'Server unreachable (DNS or Connection Refused).';
			}
		}

		return new SmliserFetchError( message, {
			code,
			status: 0,
			statusText: 'Network Error',
			cause: error,
		} );
	}

	/**
	 * Create a smliserFetch() variant with a default Accept header and response type.
	 *
	 * @param {string} accept
	 * @param {SmliserResponseType} responseType
	 * @return {( url: URL|RequestInfo, options?: RequestInit ) => Promise<*>}
	 */
	function fetchAs( accept, responseType ) {
		return ( url, options = {} ) => {
			const headers = new Headers( options.headers );

			if ( ! headers.has( 'Accept' ) ) {
				headers.set( 'Accept', accept );
			}

			return smliserFetch( url, { ...options, headers, responseType } );
		};
	}

	/**
	 * Fetch and expect JSON.
	 *
	 * @type {( url: URL|RequestInfo, options?: RequestInit ) => Promise<Object>}
	 */
	const smliserFetchJSON = fetchAs( 'application/json', 'json' );

	/**
	 * Fetch and expect HTML.
	 *
	 * @type {( url: URL|RequestInfo, options?: RequestInit ) => Promise<string>}
	 */
	const smliserFetchHTML = fetchAs( 'text/html', 'html' );

	/**
	 * Fetch and expect plain text.
	 *
	 * @type {( url: URL|RequestInfo, options?: RequestInit ) => Promise<string>}
	 */
	const smliserFetchText = fetchAs( 'text/plain', 'text' );

	/**
	 * Fetch and expect a blob (files, images).
	 *
	 * @type {( url: URL|RequestInfo, options?: RequestInit ) => Promise<Blob>}
	 */
	const smliserFetchBlob = fetchAs( 'application/octet-stream', 'blob' );

	/*
	|---------
	|Downloads
	|---------
	*/

	/**
	 * Save a blob to the user's device.
	 *
	 * @param {Blob} blob
	 * @param {string} filename
	 */
	function smliserSaveBlob( blob, filename ) {
		const objectUrl = URL.createObjectURL( blob );
		const anchor    = document.createElement( 'a' );

		anchor.href     = objectUrl;
		anchor.download = filename;
		anchor.hidden   = true;

		document.body.append( anchor );
		anchor.click();
		anchor.remove();

		// Delay revocation so the browser can start the download.
		setTimeout( () => URL.revokeObjectURL( objectUrl ), 1000 );
	}

	/**
	 * Resolve a download filename from Content-Disposition, falling back to the URL basename.
	 *
	 * @param {Response} response
	 * @param {string} requestUrl
	 * @return {string}
	 */
	function filenameFromResponse( response, requestUrl ) {
		const disposition = response.headers.get( 'Content-Disposition' );
		const match       = disposition?.match( /filename\*?=(?:UTF-8'')?"?([^";]+)"?/i );

		if ( match ) {
			try {
				return decodeURIComponent( match[ 1 ] );
			} catch {
				return match[ 1 ];
			}
		}

		try {
			const { pathname } = new URL( response.url || requestUrl, window.location.href );
			const basename     = pathname.substring( pathname.lastIndexOf( '/' ) + 1 );

			if ( basename ) {
				return decodeURIComponent( basename );
			}
		} catch {
			// Malformed URL: use the default name.
		}

		return 'download';
	}

	/**
	 * Download a file from a URL.
	 *
	 * @param {string} url - Download URL.
	 * @param {RequestInit} [options={}] - Fetch options.
	 * @return {Promise<{
	 *     filename: string,
	 *     size: number,
	 *     type: string,
	 *     duration: number,
	 *     url: string,
	 *     status: number,
	 *     statusText: string,
	 *     headers: Headers
	 * }>} Information about the downloaded file.
	 * @throws {Error} If the response status is not ok.
	 */
	async function smliserDownloadUrl( url, options = {} ) {
		const started  = performance.now();
		const response = await fetch( url, options );

		if ( ! response.ok ) {
			throw new Error( `Download failed: ${ response.status } ${ response.statusText } (${ response.url || url })` );
		}

		const blob     = await response.blob();
		const filename = filenameFromResponse( response, url );

		smliserSaveBlob( blob, filename );

		return {
			filename,
			size: blob.size,
			type: blob.type,
			duration: performance.now() - started,
			url: response.url,
			status: response.status,
			statusText: response.statusText,
			headers: response.headers,
		};
	}

	/*
	|---------
	|Clipboard
	|---------
	*/

	/**
	 * Copy text to the clipboard and show a toast.
	 *
	 * @param {string} text
	 * @return {Promise<boolean>} Whether the copy succeeded.
	 */
	async function smliserCopyToClipboard( text ) {
		try {
			await navigator.clipboard.writeText( text );

			const suffix = text.length > 0 && text.length < 50 ? `: ${ text }` : '';
			SmliserToast.show( `Copied to clipboard${ suffix }`, 3000 );

			return true;
		} catch ( error ) {
			console.error( 'Could not copy text', error );
			return false;
		}
	}

	/*
	|----------------
	|Select2 Searches
	|----------------
	*/

	/**
	 * Group flat items into Select2 optgroups keyed by `type`.
	 *
	 * @param {Array<Object>} items
	 * @param {( item: Object ) => Object} toOption - Maps an item to a Select2 option.
	 * @return {Array<{ text: string, children: Array<Object> }>}
	 */
	function groupByType( items, toOption ) {
		const groups = new Map();

		for ( const item of items ) {
			if ( ! groups.has( item.type ) ) {
				groups.set( item.type, [] );
			}

			groups.get( item.type ).push( toOption( item ) );
		}

		return Array.from( groups, ( [ type, children ] ) => ( {
			text: String( type ).charAt( 0 ).toUpperCase() + String( type ).slice( 1 ),
			children,
		} ) );
	}

	/**
	 * Build Select2 pagination state from a response.
	 *
	 * @param {Object} data - Response body.
	 * @param {Object} params - Select2 params.
	 * @return {{ more: boolean }}
	 */
	function select2Pagination( data, params ) {
		params.page = params.page || 1;

		return { more: ( data.pagination?.total_pages ?? 0 ) > params.page };
	}

	/**
	 * App search via Select2 with infinite scroll.
	 *
	 * @param {HTMLElement} selectEl
	 */
	function smliserSelect2AppSelect( selectEl ) {
		if ( ! ( selectEl instanceof HTMLElement ) ) {
			console.warn( 'Could not instantiate app selection, invalid html element' );
			return;
		}

		jQuery( selectEl ).select2( {
			placeholder: 'Search apps',
			ajax: {
				url: smliser_var.app_search_api,
				dataType: 'json',
				delay: 100,
				data: ( params ) => ( {
					search: params.term || '',
					page: params.page || 1,
				} ),
				processResults: ( data, params ) => ( {
					results: groupByType( Array.isArray( data.apps ) ? data.apps : [], ( app ) => ( {
						id: `${ app.type }:${ app.slug }`,
						text: app.name,
						type: app.type,
					} ) ),
					pagination: select2Pagination( data, params ),
				} ),
				cache: true,
			},
			allowClear: true,
			minimumInputLength: 1,
			width: '100%',
		} );
	}

	/**
	 * Search security entities via Select2 with pagination.
	 *
	 * @param {HTMLElement} selectEl
	 * @param {Object} [options={}]
	 * @param {string} [options.placeholder='Search users or organizations']
	 * @param {'resource_owners'|'owner_subjects'} [options.entityType='resource_owners']
	 * @param {Array<string>} [options.types=[]] - Entity types to restrict the search to.
	 */
	function smliserSearchSecurityEntities( selectEl, options = {} ) {
		if ( ! ( selectEl instanceof HTMLElement ) ) {
			console.warn( 'Could not instantiate entity selection, invalid html element' );
			return;
		}

		const {
			placeholder = 'Search users or organizations',
			entityType  = 'resource_owners',
			types       = [],
		} = options;

		const $select = jQuery( selectEl );

		$select.select2( {
			placeholder,
			ajax: {
				url: smliserAjaxUrl( `search/${ entityType }`, {}, { nonce: true } ).href,
				dataType: 'json',
				delay: 500,
				data: ( params ) => ( {
					search: params.term || '',
					types,
					page: params.page || 1,
				} ),
				processResults: ( data, params ) => ( {
					results: groupByType( Array.isArray( data.items ) ? data.items : [], ( entity ) => ( {
						id: `${ entity.type }:${ entity.id }`,
						text: entity.name ?? entity.display_name ?? 'No name',
						type: entity.type,
						avatar: entity.avatar,
					} ) ),
					pagination: select2Pagination( data, params ),
				} ),
				cache: true,
			},
			allowClear: true,
			minimumInputLength: 2,
			width: '100%',
		} );

		const $form          = $select.closest( 'form' );
		const $ownerType     = $form.find( '#owner_type' );
		const $name          = $form.find( '#name' );
		const $avatarPreview = $form.find( '.smliser-avatar-upload_image-preview.avatar-only' );
		const defaultType    = $ownerType.val();

		$select.on( 'select2:select select2:unselect', ( e ) => {
			const data = e.params?.data;

			if ( $ownerType.length ) {
				$ownerType.val( data?.selected ? data.type : defaultType );

				if ( data?.selected && $name.length && ! $name.val() ) {
					$name.val( data.text );
				}
			}

			if ( $avatarPreview.length && data?.avatar ) {
				$avatarPreview.attr( { src: data.avatar, title: data.text } );
			}
		} );
	}

	/*
	|-------------
	|Help Tooltips
	|-------------
	*/

	let helpTooltipsBooted = false;

	/**
	 * Wire up `.smliser-help-icon` buttons and their sibling `.smliser-help-tooltip`,
	 * including icons added to the DOM later. Safe to call more than once.
	 */
	function smliserHelpToolTip() {
		if ( helpTooltipsBooted ) {
			return;
		}

		helpTooltipsBooted = true;

		// How close to the viewport edge (px) before the tooltip is flipped.
		const EDGE_THRESHOLD = 16;

		/** @type {WeakSet<HTMLElement>} */
		const wired = new WeakSet();

		/**
		 * @param {HTMLElement} tooltip
		 */
		const reposition = ( tooltip ) => {
			tooltip.classList.remove( 'smliser-tooltip-below', 'smliser-tooltip-left', 'smliser-tooltip-right' );
			tooltip.style.width     = '';
			tooltip.style.left      = '';
			tooltip.style.transform = '';

			void tooltip.offsetWidth;

			const viewportWidth = window.innerWidth;
			const rect          = tooltip.getBoundingClientRect();
			const parentRect    = tooltip.offsetParent?.getBoundingClientRect() ?? { left: 0, right: viewportWidth };

			// Flip below if cropped at the top.
			if ( rect.top < EDGE_THRESHOLD ) {
				tooltip.classList.add( 'smliser-tooltip-below' );
				void tooltip.offsetWidth;
			}

			// Horizontal overflow: right edge first, then left edge. Left always wins.
			if ( rect.right > viewportWidth - EDGE_THRESHOLD ) {
				tooltip.classList.add( 'smliser-tooltip-left' );
			}

			if ( rect.left < EDGE_THRESHOLD ) {
				tooltip.classList.remove( 'smliser-tooltip-left' );
				tooltip.classList.add( 'smliser-tooltip-right' );
			}

			// Last resort: if it still bleeds out after flipping, clamp its width and
			// position it in offset-parent coordinates.
			void tooltip.offsetWidth;
			const finalRect = tooltip.getBoundingClientRect();

			if ( finalRect.left < EDGE_THRESHOLD || finalRect.right > viewportWidth - EDGE_THRESHOLD ) {
				tooltip.style.width     = `${ viewportWidth - ( EDGE_THRESHOLD * 2 ) }px`;
				tooltip.style.left      = `${ EDGE_THRESHOLD - parentRect.left }px`;
				tooltip.style.transform = 'none';
			}
		};

		const show = ( tooltip ) => {
			tooltip.classList.add( 'is-visible' );
			reposition( tooltip );
		};

		const hide = ( tooltip ) => tooltip.classList.remove( 'is-visible' );

		/**
		 * Hide every open tooltip except the one provided.
		 *
		 * @param {HTMLElement|null} [except=null]
		 */
		const hideAll = ( except = null ) => {
			document.querySelectorAll( '.smliser-help-tooltip.is-visible' ).forEach( ( tooltip ) => {
				if ( tooltip !== except ) {
					hide( tooltip );
				}
			} );
		};

		/**
		 * @param {HTMLElement} btn
		 */
		const initIcon = ( btn ) => {
			const tooltip = btn.nextElementSibling;

			if ( wired.has( btn ) || ! tooltip?.classList.contains( 'smliser-help-tooltip' ) ) {
				return;
			}

			wired.add( btn );

			// Toggle on click (mouse and touch).
			btn.addEventListener( 'click', ( e ) => {
				e.stopPropagation();

				const isOpen = tooltip.classList.contains( 'is-visible' );

				hideAll();

				if ( ! isOpen ) {
					show( tooltip );
				}
			} );

			// Show on keyboard focus, hide on blur unless focus moves into the tooltip.
			btn.addEventListener( 'focusin', () => {
				hideAll( tooltip );
				show( tooltip );
			} );

			btn.addEventListener( 'focusout', ( e ) => {
				if ( ! tooltip.contains( e.relatedTarget ) ) {
					hide( tooltip );
				}
			} );
		};

		document.addEventListener( 'keydown', ( e ) => {
			if ( 'Escape' === e.key ) {
				hideAll();
			}
		} );

		document.addEventListener( 'click', () => hideAll() );

		// Pick up icons injected later (e.g. fields rendered via AJAX).
		new MutationObserver( ( mutations ) => {
			for ( const { addedNodes } of mutations ) {
				for ( const node of addedNodes ) {
					if ( ! ( node instanceof HTMLElement ) ) {
						continue;
					}

					if ( node.matches( '.smliser-help-icon' ) ) {
						initIcon( node );
					} else {
						node.querySelectorAll( '.smliser-help-icon' ).forEach( initIcon );
					}
				}
			}
		} ).observe( document.body, { childList: true, subtree: true } );

		document.querySelectorAll( '.smliser-help-icon' ).forEach( initIcon );
	}

	/*
	|--------------
	|Action Buttons
	|--------------
	*/

	/**
	 * @typedef {Object} SmliserButtonArgs
	 * @property {string} slug - The route slug.
	 * @property {string} [method] - Request method. Defaults to POST with a payload, GET without.
	 * @property {string} [url] - Custom base URL. Defaults to `smliser_var.ajaxURL`.
	 * @property {Object<string, *>} [payLoad] - Data sent as a JSON body.
	 */

	/**
	 * Delegated click handler that turns `.smliser-action-button` elements into AJAX actions.
	 *
	 * Dispatches `smliser:action_success` on `document` with `{ button, args, result }` on success.
	 *
	 * @param {MouseEvent} e - Click event.
	 */
	async function smliserActionBtns( e ) {
		/** @type {HTMLButtonElement|null} */
		const button = e.target.closest( '.smliser-action-button' );

		if ( ! button ) {
			return;
		}

		/** @type {SmliserButtonArgs|null} */
		const args = StringUtils.JSONparse( button.dataset.args );

		if ( ! args?.slug ) {
			await SmliserModal.error( 'Action button missing required data-args slug.' );
			return;
		}

		const originalHtml = button.innerHTML;

		button.disabled = true;
		button.insertAdjacentHTML( 'beforeend', '<span class="ti ti-loader rotate"></span>' );

		try {
			const hasPayload = args.payLoad && Object.keys( args.payLoad ).length > 0;
			const url        = smliserAjaxUrl( args.slug, {}, { nonce: true, base: args.url || smliser_var.ajaxURL } );

			const result = await smliserFetchJSON( url, {
				method: args.method || ( hasPayload ? 'POST' : 'GET' ),
				...( hasPayload && { body: JSON.stringify( args.payLoad ) } ),
			} );

			if ( ! result.success ) {
				throw new Error( result.data?.message || 'Operation failed' );
			}

			SmliserModal.success( result.data?.message || 'Action completed!', 'Success' );
			document.dispatchEvent( new CustomEvent( 'smliser:action_success', { detail: { button, args, result } } ) );
		} catch ( error ) {
			SmliserModal.error( error.message, 'Error' );
		} finally {
			button.disabled  = false;
			button.innerHTML = originalHtml;
		}
	}

	/*
	|-------
	|Publish
	|-------
	*/

	Object.assign( window, {
		showSpinner,
		removeSpinner,
		smliserAjaxUrl,
		smliserCsrfHeader,
		SmliserFetchError,
		smliserFetch,
		smliserFetchJSON,
		smliserFetchHTML,
		smliserFetchText,
		smliserFetchBlob,
		smliserSaveBlob,
		smliserDownloadUrl,
		smliserCopyToClipboard,
		smliserSelect2AppSelect,
		smliserSearchSecurityEntities,
		smliserHelpToolTip,
		smliserActionBtns,
	} );
} )();