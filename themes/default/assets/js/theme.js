/**
 * Default theme behaviour.
 *
 *  - Buttons with data-copy copy their value to the clipboard.
 *  - Buttons with data-action="back" go back in history.
 */
( function () {
	'use strict';

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