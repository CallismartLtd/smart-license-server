( function () {
	'use strict';

	const STORAGE_KEY = 'smliser:security-tab';

	const readStored = () => {
		try {
			return window.sessionStorage.getItem( STORAGE_KEY );
		} catch ( e ) {
			return null;
		}
	};

	const writeStored = ( name ) => {
		try {
			window.sessionStorage.setItem( STORAGE_KEY, name );
		} catch ( e ) {
			// Storage unavailable (privacy mode, quota). Tabs still work, just unremembered.
		}
	};

	document.addEventListener( 'DOMContentLoaded', () => {
		document.querySelectorAll( '[data-security-tabs]' ).forEach( ( tabs ) => {
			const buttons = Array.from( tabs.querySelectorAll( '[data-security-tab]' ) );
			const panels = Array.from( tabs.querySelectorAll( '[data-security-panel]' ) );
			if ( ! buttons.length || ! panels.length ) return;

			const names = buttons.map( ( button ) => button.dataset.securityTab );

			const activate = ( name, focus = false ) => {
				buttons.forEach( ( button ) => {
					const selected = button.dataset.securityTab === name;
					button.setAttribute( 'aria-selected', selected ? 'true' : 'false' );
					button.tabIndex = selected ? 0 : -1;
					if ( selected && focus ) button.focus();
				} );
				panels.forEach( ( panel ) => panel.hidden = panel.dataset.securityPanel !== name );
				writeStored( name );
			};

			buttons.forEach( ( button, index ) => {
				button.addEventListener( 'click', () => activate( button.dataset.securityTab ) );
				button.addEventListener( 'keydown', ( event ) => {
					let next = index;
					if ( 'ArrowRight' === event.key ) next = ( index + 1 ) % buttons.length;
					else if ( 'ArrowLeft' === event.key ) next = ( index - 1 + buttons.length ) % buttons.length;
					else if ( 'Home' === event.key ) next = 0;
					else if ( 'End' === event.key ) next = buttons.length - 1;
					else return;
					event.preventDefault();
					activate( buttons[next].dataset.securityTab, true );
				} );
			} );

			// Restore the last tab chosen in this browser tab, if it is still a valid one.
			const stored = readStored();
			if ( stored && names.includes( stored ) && stored !== tabs.dataset.activeTab ) {
				activate( stored );
			}
		} );
	} );
} )();