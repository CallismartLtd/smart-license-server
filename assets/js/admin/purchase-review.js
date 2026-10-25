/**
 * Purchase request review: the receipt viewer.
 *
 * The receipt is shown only when the reviewer asks, in a SmliserModal, never
 * in a new tab. Markup contract:
 *
 *   button[data-smliser-receipt]  Opens the viewer.
 *     data-url="…"                The receipt's admin download URL.
 *     data-mime="…"               Its MIME type as stored by the server.
 *     data-pages="[…]"            For a PDF: JSON list of its page image URLs, rendered
 *                                 by the server (PdfRasterizer); empty when none.
 *     data-name="…"               File name for "Download".
 *
 * The file is fetched with the reviewer's session and shown from a blob
 * URL typed with the stored MIME type, and only for the types receipts
 * may have: an image as an image; a PDF as a document of the pages the
 * server rendered (documentViewer()), the same in every browser, or,
 * when there are none, in the browser's PDF viewer if it has one
 * (otherwise the reviewer is told to download it).
 * Anything else is offered as a download, never rendered. The blob URL
 * is released when the modal closes.
 *
 * Needs globals.js (smliserFetchBlob) and modal.js (SmliserModal).
 */
( function () {
	'use strict';

	/**
	 * Receipt types shown in the viewer, as stored by PurchaseRequestService.
	 *
	 * @type {string[]}
	 */
	const VIEWABLE = [ 'image/jpeg', 'image/png', 'image/webp', 'application/pdf' ];

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( 'button[data-smliser-receipt]' );

		if ( button ) {
			event.preventDefault();
			view( button );
		}
	} );

	/**
	 * Open the viewer for a receipt button.
	 *
	 * @param {HTMLButtonElement} button
	 * @return {Promise<void>}
	 */
	async function view( button ) {
		const { url, mime = '', name = 'receipt' } = button.dataset;
		const pages = parsePages( button.dataset.pages );
		const status = element( 'p', 'smliser-receipt-viewer__status', 'Loading the receipt…' );
		const body   = element( 'div', 'smliser-receipt-viewer' );
		const footer = element( 'div', 'smliser-receipt-viewer__footer' );
		const close  = element( 'button', 'smliser-btn smliser-surface-btn', 'Close' );

		close.type = 'button';
		body.appendChild( status );
		footer.appendChild( close );

		const modal = new SmliserModal( {
			title: `Receipt: ${ name }`,
			body,
			footer,
			width: pages.length ? '960px' : '900px',
			// A document fills the modal's height and scrolls inside; see core.css.
			customClass: pages.length ? 'smliser-receipt-modal is-document' : 'smliser-receipt-modal',
		} );

		let objectUrl = '';

		close.addEventListener( 'click', () => modal.close() );
		modal.on( 'afterClose', async () => {
			if ( objectUrl ) {
				URL.revokeObjectURL( objectUrl );
			}

			await modal.destroy();
			button.focus();
		} );

		await modal.open();

		let blob;

		try {
			blob = await smliserFetchBlob( url, { credentials: 'same-origin' } );
		} catch ( error ) {
			status.textContent = error?.message || 'The receipt could not be loaded.';
			status.dataset.state = 'error';
			return;
		}

		// Typed by what the server stored, not by what the response claims.
		const viewable = VIEWABLE.includes( mime );

		objectUrl = URL.createObjectURL( new Blob( [ blob ], { type: viewable ? mime : 'application/octet-stream' } ) );

		const download = element( 'a', 'smliser-btn smliser-btn-glass', 'Download' );
		download.href     = objectUrl;
		download.download = name;
		footer.prepend( download );

		status.remove();

		if ( ! viewable ) {
			body.appendChild( element( 'p', 'smliser-receipt-viewer__status', 'This file type is not shown here. Download it to open it.' ) );
			return;
		}

		if ( 'application/pdf' === mime && pages.length ) {
			// The pages as the server rendered them: one look in every browser, nothing in the PDF runs.
			body.classList.add( 'is-document' );
			body.appendChild( documentViewer( pages, name ) );
			return;
		}

		if ( 'application/pdf' === mime ) {
			// The browser says whether it shows PDFs inside a page; most phones do not.
			if ( ! canShowPdf() ) {
				body.appendChild( element( 'p', 'smliser-receipt-viewer__status', 'This browser cannot show the PDF here. Use Download to open it.' ) );
				return;
			}

			const frame = element( 'iframe', 'smliser-receipt-viewer__pdf' );
			frame.title = `Receipt ${ name }`;
			// Hide the viewer's toolbar and side panes where the viewer honours it (Chromium does; Firefox and Safari ignore it).
			frame.src   = objectUrl + '#toolbar=0&navpanes=0&scrollbar=0&view=FitH';
			body.appendChild( frame );
			return;
		}

		// Images fit the modal; a click shows them at full size, and back.
		const image = element( 'img', 'smliser-receipt-viewer__image' );
		image.alt   = `Receipt ${ name }`;
		image.src   = objectUrl;
		image.title = 'Click to zoom';
		image.addEventListener( 'click', () => body.classList.toggle( 'is-zoomed' ) );
		body.appendChild( image );
	}

	/*
	|----------------
	|Document viewer
	|----------------
	*/

	/**
	 * Zoom levels, as a share of the fitted page width.
	 *
	 * @type {number[]}
	 */
	const ZOOMS = [ 0.5, 0.75, 1, 1.25, 1.5, 2, 3 ];

	/**
	 * Widest a page is drawn at 100%, in CSS pixels.
	 *
	 * @type {number}
	 */
	const PAGE_MAX_WIDTH = 820;

	/**
	 * A document viewer for the rendered pages of a PDF.
	 *
	 * Pages are sheets of paper on a canvas, each drawn as the background of
	 * an element sized to the page's own proportions, so it reads as a
	 * document: no image cursor, dragging or "save image". The toolbar pages
	 * through and zooms; 100% fits the page to the canvas width.
	 *
	 * Keyboard, on the canvas: PageDown/PageUp change page, + and - zoom,
	 * 0 fits the width.
	 *
	 * @param {string[]} pages Page image URLs, in order.
	 * @param {string}   name  The receipt's file name, for labels.
	 * @return {HTMLElement}
	 */
	function documentViewer( pages, name ) {
		const root    = element( 'div', 'smliser-doc' );
		const toolbar = element( 'div', 'smliser-doc__toolbar' );
		const canvas  = element( 'div', 'smliser-doc__canvas' );
		const counter = element( 'span', 'smliser-doc__counter' );
		const level   = element( 'span', 'smliser-doc__zoom-level' );
		const prev    = toolButton( 'ti ti-chevron-up', 'Previous page' );
		const next    = toolButton( 'ti ti-chevron-down', 'Next page' );
		const out     = toolButton( 'ti ti-minus', 'Zoom out' );
		const into    = toolButton( 'ti ti-plus', 'Zoom in' );
		const fit     = toolButton( 'ti ti-arrows-horizontal', 'Fit width' );
		const sheets  = [];

		let zoom    = ZOOMS.indexOf( 1 );
		let current = 0;

		root.setAttribute( 'role', 'region' );
		root.setAttribute( 'aria-label', `Receipt ${ name }` );
		toolbar.setAttribute( 'role', 'toolbar' );
		canvas.tabIndex = 0;
		canvas.setAttribute( 'aria-label', 'Receipt pages' );
		counter.setAttribute( 'aria-live', 'polite' );

		toolbar.append(
			group( prev, counter, next ),
			group( out, level, into, fit )
		);

		pages.forEach( ( src, index ) => {
			const wrap  = element( 'figure', 'smliser-doc__page-wrap' );
			const sheet = element( 'div', 'smliser-doc__page is-loading' );
			const label = element( 'figcaption', 'smliser-doc__page-number', `${ index + 1 }` );

			sheet.setAttribute( 'role', 'img' );
			sheet.setAttribute( 'aria-label', `Page ${ index + 1 } of ${ pages.length }` );
			sheet.dataset.index = String( index );

			// Size the sheet to the page before showing it, so nothing jumps.
			const probe = new Image();
			probe.onload = () => {
				sheet.style.aspectRatio     = `${ probe.naturalWidth } / ${ probe.naturalHeight }`;
				sheet.style.backgroundImage = `url("${ src.replace( /"/g, '%22' ) }")`;
				sheet.classList.remove( 'is-loading' );
			};
			probe.onerror = () => {
				sheet.classList.remove( 'is-loading' );
				sheet.classList.add( 'is-failed' );
				sheet.textContent = 'This page could not be loaded.';
			};
			probe.src = src;

			wrap.append( sheet, label );
			canvas.appendChild( wrap );
			sheets.push( sheet );
		} );

		root.append( toolbar, canvas );

		/**
		 * Draw the counter, the zoom level and the buttons' states.
		 */
		const update = () => {
			counter.textContent = `Page ${ current + 1 } of ${ pages.length }`;
			level.textContent   = `${ Math.round( ZOOMS[ zoom ] * 100 ) }%`;
			prev.disabled       = 0 === current;
			next.disabled       = pages.length - 1 === current;
			out.disabled        = 0 === zoom;
			into.disabled       = ZOOMS.length - 1 === zoom;
			fit.disabled        = 1 === ZOOMS[ zoom ];
		};

		/**
		 * Scroll a page to the top of the canvas.
		 *
		 * @param {number} index
		 * @param {ScrollBehavior} [behavior='smooth']
		 */
		const goTo = ( index, behavior = 'smooth' ) => {
			const target = sheets[ Math.max( 0, Math.min( pages.length - 1, index ) ) ].parentElement;

			canvas.scrollTo( { top: target.offsetTop - canvas.offsetTop - 16, behavior } );
		};

		/**
		 * The page width at 100%: the canvas width less its padding, at most PAGE_MAX_WIDTH.
		 *
		 * @return {number}
		 */
		const fitted = () => Math.max( 200, Math.min( canvas.clientWidth - 48, PAGE_MAX_WIDTH ) );

		/**
		 * Size the pages for a zoom level, keeping the current page in view.
		 *
		 * @param {number} index Index in ZOOMS.
		 */
		const setZoom = ( index ) => {
			zoom = Math.max( 0, Math.min( ZOOMS.length - 1, index ) );

			root.style.setProperty( '--smliser-doc-page-width', `${ Math.round( fitted() * ZOOMS[ zoom ] ) }px` );
			update();
			goTo( current, 'auto' );
		};

		// The current page is the one showing most.
		const visible = new Map();
		const observer = new IntersectionObserver( ( entries ) => {
			entries.forEach( ( entry ) => visible.set( Number( entry.target.dataset.index ), entry.intersectionRatio ) );

			let best = current;

			visible.forEach( ( ratio, index ) => {
				if ( ratio > ( visible.get( best ) ?? 0 ) ) {
					best = index;
				}
			} );

			if ( best !== current ) {
				current = best;
				update();
			}
		}, { root: canvas, threshold: [ 0, 0.25, 0.5, 0.75, 1 ] } );

		sheets.forEach( ( sheet ) => observer.observe( sheet ) );

		prev.addEventListener( 'click', () => goTo( current - 1 ) );
		next.addEventListener( 'click', () => goTo( current + 1 ) );
		out.addEventListener( 'click', () => setZoom( zoom - 1 ) );
		into.addEventListener( 'click', () => setZoom( zoom + 1 ) );
		fit.addEventListener( 'click', () => setZoom( ZOOMS.indexOf( 1 ) ) );

		canvas.addEventListener( 'keydown', ( event ) => {
			const keys = {
				PageDown: () => goTo( current + 1 ),
				PageUp: () => goTo( current - 1 ),
				'+': () => setZoom( zoom + 1 ),
				'=': () => setZoom( zoom + 1 ),
				'-': () => setZoom( zoom - 1 ),
				0: () => setZoom( ZOOMS.indexOf( 1 ) ),
			};

			if ( keys[ event.key ] ) {
				event.preventDefault();
				keys[ event.key ]();
			}
		} );

		// The canvas has its width once the modal shows it; refit when the window changes.
		requestAnimationFrame( () => setZoom( zoom ) );
		window.addEventListener( 'resize', () => root.isConnected && setZoom( zoom ) );

		return root;
	}

	/**
	 * A toolbar button showing an icon.
	 *
	 * @param {string} icon  Icon classes.
	 * @param {string} label Accessible name and tooltip.
	 * @return {HTMLButtonElement}
	 */
	function toolButton( icon, label ) {
		const button = element( 'button', 'smliser-doc__tool' );
		const glyph  = element( 'i', icon );

		button.type  = 'button';
		button.title = label;
		button.setAttribute( 'aria-label', label );
		glyph.setAttribute( 'aria-hidden', 'true' );
		button.appendChild( glyph );

		return button;
	}

	/**
	 * Group toolbar controls.
	 *
	 * @param {...HTMLElement} controls
	 * @return {HTMLElement}
	 */
	function group( ...controls ) {
		const wrap = element( 'div', 'smliser-doc__group' );

		wrap.append( ...controls );

		return wrap;
	}

	/**
	 * The page image URLs of a button's data-pages.
	 *
	 * @param {string|undefined} json
	 * @return {string[]}
	 */
	function parsePages( json ) {
		try {
			const pages = JSON.parse( json || '[]' );

			return Array.isArray( pages ) ? pages.filter( ( page ) => 'string' === typeof page ) : [];
		} catch {
			return [];
		}
	}

	/**
	 * Whether this browser shows PDFs inside a page.
	 *
	 * navigator.pdfViewerEnabled answers it (Chromium 94+, Firefox 99+,
	 * Safari 16.4+). Older browsers do not say; they are assumed to, as
	 * the desktop browsers of that age did.
	 *
	 * @return {boolean}
	 */
	function canShowPdf() {
		return 'boolean' === typeof navigator.pdfViewerEnabled ? navigator.pdfViewerEnabled : true;
	}

	/**
	 * Create an element with a class and text.
	 *
	 * @param {string} tag
	 * @param {string} className
	 * @param {string} [text]
	 * @return {HTMLElement}
	 */
	function element( tag, className, text = '' ) {
		const node = document.createElement( tag );

		node.className = className;

		if ( text ) {
			node.textContent = text;
		}

		return node;
	}
}() );