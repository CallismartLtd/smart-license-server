( function( env ) {
    'use strict';
    
    const docE  = document.documentElement;

    /**
	 * Apply the theme saved from a previous session, if any.
	 */
	function restoreTheme() {

        if ( 'auto' !== docE.getAttribute( 'data-theme' ) ) {
            return;
        }
        
		var stored;

		try {
			stored = window.localStorage.getItem( smliser_var.theme_storage_key );
		} catch ( error ) {
			stored = null;
		}

		if ( 'dark' === stored || 'light' === stored ) {
			docE.setAttribute( 'data-theme', stored );
		} else {
			// If nothing is stored (or storage is cleared), strip the attribute 
			// so CSS @media (prefers-color-scheme) takes over.
			docE.removeAttribute( 'data-theme' );
		}        
        
	}

    /**
	 * Apply the collapsed state saved from a previous session, if any.
	 *
	 * @param {HTMLElement} wrapper The dashboard wrapper element.
	 */
	function restoreCollapsedState() {
		var stored;

		try {
			stored = window.localStorage.getItem( smliser_var.sidebar_state_key );
		} catch ( error ) {
			stored = null;
		}

		if ( '1' === stored ) {
			wrapper.classList.add( 'is-collapsed' );
		}
	}

    restoreTheme();
    env.restoreCollapsedState   = restoreCollapsedState;
    
})( window )