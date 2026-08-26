/**
 * Dashboard Shell — Behaviour
 *
 * Handles: left menu expand/collapse (desktop), mobile menu toggle,
 * submenu expand/collapse, and theme toggling. No dependencies.
 */
( function () {
	'use strict';

	var STORAGE_KEY_COLLAPSED = 'dashboard-menu-collapsed';
	var STORAGE_KEY_THEME     = 'dashboard-theme';
	var MOBILE_BREAKPOINT     = 782;

	document.addEventListener( 'DOMContentLoaded', function () {
		var wrapper				= document.querySelector( '#dashboard-wrapper' );
		var menuToggle          = document.querySelector( '#dashboard-menu-toggle' );
		var mobileMenuToggle    = document.querySelector( '#dashboard-mobile-menu-toggle' );
		var overlay             = document.querySelector( '#dashboard-overlay' );
		var themeToggle         = document.querySelector( '#dashboard-theme-toggle' );
		var leftMenu            = document.querySelector( '#dashboard-left-menu' );
		var logoutLink			= document.querySelector( 'a.smliser-logout-link-btn' );

		if ( ! wrapper ) {
			return;
		}

		restoreCollapsedState( wrapper );
		restoreTheme();

		if ( menuToggle ) {
			menuToggle.addEventListener( 'click', function () {
				toggleCollapsed( wrapper );
			} );
		}

		if ( mobileMenuToggle ) {
			mobileMenuToggle.addEventListener( 'click', function () {
				wrapper.classList.toggle( 'mobile-menu-open' );
			} );
		}

		if ( overlay ) {
			overlay.addEventListener( 'click', function () {
				wrapper.classList.remove( 'mobile-menu-open' );
			} );
		}

		if ( themeToggle ) {
			themeToggle.addEventListener( 'click', toggleTheme );
		}

		if ( leftMenu ) {
			leftMenu.addEventListener( 'click', function ( event ) {
				var toggle = event.target.closest( '[data-toggle="submenu"]' );

				if ( ! toggle ) {
					return;
				}

				var isCollapsed = wrapper.classList.contains( 'is-collapsed' ) && window.innerWidth > MOBILE_BREAKPOINT;

				// When collapsed to icon-only on desktop, the submenu isn't
				// visible to expand — do nothing rather than fight the CSS.
				if ( isCollapsed ) {
					return;
				}

				event.preventDefault();
				toggle.closest( '.dashboard-menu-item' ).classList.toggle( 'is-open' );
			} );
		}

		if ( logoutLink ) {
			logoutLink.addEventListener( 'click', async e => {
				e.preventDefault();

				if ( ! await SmliserModal.confirm( 'Are you sure you want to logout?' ) ) {
					return;
				}

				const url 	= logoutLink.href;

				try {
					let response = await smliserFetchJSON( url );
					if ( ! response.success ) {
						throw new Error( response.message ?? response.data.message ?? 'Unknown error occurred.' );	
					}

					await SmliserModal.success( response.message );
					window.location.reload();
					
				} catch (error) {
					await SmliserModal.error( error.message );
				}
				
			})
		}

		window.addEventListener( 'resize', function () {
			if ( window.innerWidth > MOBILE_BREAKPOINT ) {
				wrapper.classList.remove( 'mobile-menu-open' );
			}
		} );
	} );

	/**
	 * Toggle the desktop collapsed (icon-only) sidebar state and persist it.
	 *
	 * @param {HTMLElement} wrapper The dashboard wrapper element.
	 */
	function toggleCollapsed( wrapper ) {
		var isCollapsed = wrapper.classList.toggle( 'is-collapsed' );

		try {
			window.localStorage.setItem( STORAGE_KEY_COLLAPSED, isCollapsed ? '1' : '0' );
		} catch ( error ) {
			// Storage unavailable (private mode, disabled, etc). Ignore.
		}
	}

	/**
	 * Apply the collapsed state saved from a previous session, if any.
	 *
	 * @param {HTMLElement} wrapper The dashboard wrapper element.
	 */
	function restoreCollapsedState( wrapper ) {
		var stored;

		try {
			stored = window.localStorage.getItem( STORAGE_KEY_COLLAPSED );
		} catch ( error ) {
			stored = null;
		}

		if ( '1' === stored ) {
			wrapper.classList.add( 'is-collapsed' );
		}
	}

	/**
	 * Toggle between light and dark theme and persist the choice.
	 */
	function toggleTheme() {
		var root        = document.documentElement;
		var currentTheme = root.getAttribute( 'data-theme' );
		var nextTheme    = 'dark' === currentTheme ? 'light' : 'dark';

		root.setAttribute( 'data-theme', nextTheme );

		try {
			window.localStorage.setItem( STORAGE_KEY_THEME, nextTheme );
		} catch ( error ) {
			// Storage unavailable. Ignore.
		}
	}

	/**
	 * Apply the theme saved from a previous session, if any.
	 */
	function restoreTheme() {
		var stored;

		try {
			stored = window.localStorage.getItem( STORAGE_KEY_THEME );
		} catch ( error ) {
			stored = null;
		}

		if ( 'dark' === stored ) {
			document.documentElement.setAttribute( 'data-theme', 'dark' );
		}
	}
} )();