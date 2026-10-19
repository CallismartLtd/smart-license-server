/**
 * Default theme behaviour.
 *
 * Buttons with data-copy copy their value to the clipboard.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-copy]' );

		if ( ! button || ! navigator.clipboard ) {
			return;
		}

		navigator.clipboard.writeText( button.dataset.copy ).then( () => {
			const label = button.textContent;

			button.textContent = 'Copied!';
			setTimeout( () => {
				button.textContent = label;
			}, 2000 );
		} );
	} );
} )();