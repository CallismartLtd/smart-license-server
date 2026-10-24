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
 *                                 by the server (ReceiptRasterizer); empty when none.
 *     data-name="…"               File name for "Download".
 *
 * The file is fetched with the reviewer's session and shown from a blob
 * URL typed with the stored MIME type, and only for the types receipts
 * may have: an image as an image; a PDF as the images of its pages the
 * server rendered, the same in every browser, or, when there are none, in
 * the browser's PDF viewer if it has one (otherwise the reviewer is told
 * to download it).
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
			width: '900px',
			customClass: 'smliser-receipt-modal',
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
			pages.forEach( ( src, index ) => {
				const page = element( 'img', 'smliser-receipt-viewer__image smliser-receipt-viewer__page' );
				page.alt     = `Receipt ${ name }, page ${ index + 1 } of ${ pages.length }`;
				page.src     = src;
				page.loading = 'lazy';
				page.title   = 'Click to zoom';
				page.addEventListener( 'click', () => body.classList.toggle( 'is-zoomed' ) );
				body.appendChild( page );
			} );
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