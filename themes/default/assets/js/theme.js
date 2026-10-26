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

	document.addEventListener( 'click', ( event ) => {
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
} )();