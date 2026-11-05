/**
 * Default theme behaviour.
 *
 *  - Buttons with data-copy copy their value to the clipboard.
 *  - Buttons with data-action="back" go back in history.
 *  - A form with data-auto-submit is submitted when one of its fields
 *    changes (page-forms.js posts it), e.g. the dashboard context switcher.
 */
( function () {
	'use strict';

	document.addEventListener( 'change', ( event ) => {
		const form = event.target.closest( 'form[data-auto-submit]' );

		if ( form ) {
			form.requestSubmit();
		}
	} );

	document.addEventListener( 'toggle', ( event ) => {
		const dropdown = event.target.closest( '.dash-context-dropdown' );

		if ( ! dropdown ) {
			return;
		}

		if ( dropdown.open ) {
			positionContextPanel( dropdown );
			return;
		}

		const panel = dropdown.querySelector( '.dash-context-panel' );

		if ( panel ) {
			panel.removeAttribute( 'data-positioned' );
			panel.removeAttribute( 'style' );
		}
	}, true );

	window.addEventListener( 'resize', scheduleOpenContextPanelPosition );
	window.addEventListener( 'scroll', scheduleOpenContextPanelPosition, true );

	document.addEventListener( 'click', ( event ) => {
		document.querySelectorAll( '.dash-context-dropdown[open]' ).forEach( ( dropdown ) => {
			if ( ! dropdown.contains( event.target ) ) {
				dropdown.open = false;
			}
		} );

		const back = event.target.closest( '[data-action="back"]' );

		if ( back ) {
			history.back();
			return;
		}

		const button = event.target.closest( '[data-copy]' );

		if ( ! button || ! navigator.clipboard ) {
			return;
		}

		navigator.clipboard.writeText( button.dataset.copy ).then( () => {
			const label = button.textContent;

			button.textContent = 'Copied';
			setTimeout( () => {
				button.textContent = label;
			}, 2000 );
		} );
	} );

	/**
	 * Keep an open context menu inside the viewport and choose the roomier side.
	 *
	 * @param {HTMLDetailsElement} dropdown
	 */
	function positionContextPanel( dropdown ) {
		const panel = dropdown.querySelector( '.dash-context-panel' );
		const trigger = dropdown.querySelector( 'summary' );

		if ( ! panel || ! trigger ) {
			return;
		}

		const margin = 12;
		const gap = 8;
		const viewportWidth = document.documentElement.clientWidth;
		const viewportHeight = window.innerHeight;
		const anchor = trigger.getBoundingClientRect();

		panel.style.position = 'fixed';
		panel.style.top = `${margin}px`;
		panel.style.left = `${margin}px`;
		panel.style.right = 'auto';
		panel.style.maxHeight = 'none';

		const width = panel.getBoundingClientRect().width;
		const left = Math.max( margin, Math.min( anchor.right - width, viewportWidth - width - margin ) );
		const below = Math.max( 0, viewportHeight - anchor.bottom - gap - margin );
		const above = Math.max( 0, anchor.top - gap - margin );
		const desiredHeight = panel.scrollHeight;
		const placeBelow = desiredHeight <= below || below >= above;
		const available = Math.max( 0, placeBelow ? below : above );

		panel.style.left = `${left}px`;
		panel.style.maxHeight = `${available}px`;

		const height = panel.getBoundingClientRect().height;
		const top = placeBelow ? anchor.bottom + gap : anchor.top - gap - height;

		panel.style.top = `${Math.max( margin, Math.min( top, viewportHeight - height - margin ) )}px`;
		panel.setAttribute( 'data-positioned', '' );
	}

	let contextPanelPositionFrame = null;

	function scheduleOpenContextPanelPosition() {
		if ( null !== contextPanelPositionFrame ) {
			return;
		}

		contextPanelPositionFrame = window.requestAnimationFrame( () => {
			contextPanelPositionFrame = null;
			positionOpenContextPanels();
		} );
	}

	function positionOpenContextPanels() {
		document.querySelectorAll( '.dash-context-dropdown' ).forEach( ( dropdown ) => {
			if ( dropdown.open ) {
				positionContextPanel( dropdown );
			}
		} );
	}
} )();