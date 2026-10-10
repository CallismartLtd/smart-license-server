/**
 * Background Processing page.
 *
 *  - The mode radios show the matching configuration panel (and keep the
 *    choice in the URL as ?mode=, so a reload shows the same one).
 *  - Copy buttons copy the snippet next to them.
 *  - Live status refreshes from admin/json/background/status every 30
 *    seconds while the page is visible, and on "Check again": the status
 *    banner, the runners and the "Running now" badge are updated in place,
 *    so the first run of a new setup shows without a reload.
 */
( function () {
	'use strict';

	const POLL_MS    = 30000;
	const TIMEOUT_MS = 20000;
	const TONE       = 'smliser-health-tone--';
	const ICONS      = {
		pass: 'ti ti-circle-check',
		warning: 'ti ti-alert-triangle',
		info: 'ti ti-circle-dashed',
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		const page = document.getElementById( 'smliser-background-setup' );

		if ( ! page ) {
			return;
		}

		initModes( page );
		initCopy( page );
		initStatus( page );
	} );

	/*
	|------
	| MODES
	|------
	*/

	/**
	 * Show the configuration of the checked mode.
	 *
	 * @param {HTMLElement} page
	 */
	function initModes( page ) {
		const radios = page.querySelectorAll( 'input[name="smliser_background_mode"]' );

		radios.forEach( ( radio ) => {
			radio.addEventListener( 'change', () => {
				if ( ! radio.checked ) {
					return;
				}

				page.querySelectorAll( '[data-mode-config]' ).forEach( ( panel ) => {
					panel.hidden = panel.dataset.modeConfig !== radio.value;
				} );

				const url = new URL( window.location.href );

				url.searchParams.set( 'mode', radio.value );
				window.history.replaceState( null, '', url );
			} );
		} );
	}

	/*
	|-----
	| COPY
	|-----
	*/

	/**
	 * Copy buttons next to each snippet.
	 *
	 * @param {HTMLElement} page
	 */
	function initCopy( page ) {
		page.querySelectorAll( '[data-copy-snippet]' ).forEach( ( button ) => {
			button.addEventListener( 'click', async () => {
				const code = button.parentElement.querySelector( 'code' );

				try {
					await writeClipboard( code.textContent );
					SmliserToast.show( 'Copied.', { type: 'success' } );
				} catch ( error ) {
					selectText( code );
					SmliserToast.show( 'Your browser blocked copying. Press Ctrl+C (Cmd+C) to copy the selected text.', { type: 'warning' } );
				}
			} );
		} );
	}

	/**
	 * Write text to the clipboard. navigator.clipboard needs a secure
	 * context (HTTPS or localhost); otherwise fall back to a selection copy.
	 *
	 * @param {string} value
	 * @return {Promise<void>}
	 */
	async function writeClipboard( value ) {
		if ( window.isSecureContext && navigator.clipboard?.writeText ) {
			return navigator.clipboard.writeText( value );
		}

		const area = document.createElement( 'textarea' );

		area.value = value;
		area.setAttribute( 'readonly', '' );
		area.style.cssText = 'position:fixed;top:0;left:0;opacity:0;';
		document.body.append( area );
		area.select();

		const copied = document.execCommand( 'copy' );

		area.remove();

		if ( ! copied ) {
			throw new Error( 'Copy command was refused.' );
		}
	}

	/**
	 * Select an element's text, for a manual copy.
	 *
	 * @param {HTMLElement} node
	 */
	function selectText( node ) {
		const range = document.createRange();

		range.selectNodeContents( node );
		window.getSelection().removeAllRanges();
		window.getSelection().addRange( range );
	}

	/*
	|------------
	| LIVE STATUS
	|------------
	*/

	/**
	 * Refresh the runners on a timer and on "Check again".
	 *
	 * @param {HTMLElement} page
	 */
	function initStatus( page ) {
		const button = document.getElementById( 'smliser-background-refresh' );
		let   busy   = false;

		const refresh = async ( manual ) => {
			if ( busy ) {
				return;
			}

			busy = true;

			const icon    = button?.querySelector( 'i' );
			const iconWas = icon?.className;

			if ( manual && icon ) {
				icon.className = 'ti ti-loader-2 smliser-health-spin';
			}

			try {
				const body = await fetchStatus();

				renderOverview( page, body?.overview );
				renderRunners( page, body?.runners || [] );
				renderBadges( page, body?.mode ?? null );
				setChecked( `Checked at ${ new Date().toLocaleTimeString() }. Refreshes every 30 seconds while the page is open.` );

				if ( manual ) {
					SmliserToast.show( 'Status refreshed.', { type: 'success' } );
				}
			} catch ( error ) {
				if ( manual ) {
					SmliserToast.show( error?.message || 'The status could not be loaded.', { type: 'error' } );
				}
			} finally {
				busy = false;

				if ( icon ) {
					icon.className = iconWas;
				}
			}
		};

		button?.addEventListener( 'click', () => refresh( true ) );

		setInterval( () => {
			if ( 'visible' === document.visibilityState ) {
				refresh( false );
			}
		}, POLL_MS );
	}

	/**
	 * Update the status banner and the Live status panel's tone.
	 *
	 * @param {HTMLElement}  page
	 * @param {Object|null}  overview { tone, icon, title, message }
	 */
	function renderOverview( page, overview ) {
		if ( ! overview?.tone ) {
			return;
		}

		const banner = page.querySelector( '#smliser-background-overview' );
		const live   = page.querySelector( '#smliser-background-live' );

		[ banner, live ].forEach( ( node ) => setTone( node, overview.tone ) );

		if ( ! banner ) {
			return;
		}

		banner.querySelector( '.smliser-health-overview-icon i' ).className    = overview.icon;
		banner.querySelector( '.smliser-health-overview-title' ).textContent   = overview.title;
		banner.querySelector( '.smliser-health-overview-message' ).textContent = overview.message;
	}

	/**
	 * Show "Running now" on the running mode only.
	 *
	 * @param {HTMLElement} page
	 * @param {string|null} mode
	 */
	function renderBadges( page, mode ) {
		page.querySelectorAll( '[data-running-badge]' ).forEach( ( badge ) => {
			badge.hidden = badge.dataset.runningBadge !== mode;
		} );
	}

	/**
	 * Replace an element's smliser-health-tone--* class.
	 *
	 * @param {HTMLElement|null} node
	 * @param {string}           tone
	 */
	function setTone( node, tone ) {
		if ( ! node ) {
			return;
		}

		[ ...node.classList ]
			.filter( ( name ) => name.startsWith( TONE ) )
			.forEach( ( name ) => node.classList.remove( name ) );

		node.classList.add( TONE + tone );
	}

	/**
	 * Update each runner row from the response.
	 *
	 * @param {HTMLElement} page
	 * @param {Array}       runners
	 */
	function renderRunners( page, runners ) {
		runners.forEach( ( runner ) => {
			const row = page.querySelector( `[data-runner="${ CSS.escape( runner.source ) }"]` );

			if ( ! row ) {
				return;
			}

			const tone = runner.alive ? 'pass' : ( null === runner.age ? 'info' : 'warning' );

			setTone( row, tone );
			row.querySelector( '.smliser-health-check-icon i' ).className = ICONS[ tone ];
			row.querySelector( '[data-runner-summary]' ).textContent      = runner.summary;
		} );
	}

	/**
	 * @param {string} text
	 */
	function setChecked( text ) {
		const node = document.getElementById( 'smliser-background-checked' );

		if ( node ) {
			node.textContent = text;
		}
	}

	/*
	|--------
	| REQUEST
	|--------
	*/

	/**
	 * GET the status. smliserFetchJSON() adds the CSRF header, resolves to
	 * the response body ({ mode, overview, runners }) and throws
	 * { message, code, status } on failure.
	 *
	 * @return {Promise<Object>}
	 */
	async function fetchStatus() {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/background/status`;

		try {
			return await smliserFetchJSON( url, {
				method: 'GET',
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}
}() );