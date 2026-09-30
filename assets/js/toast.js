/*|---------------------------------------------------------------------|*/
/*| Smliser Toast — notification component                             |*/
/*|                                                                     |*/
/*| Requires smliser-toast.css to be enqueued alongside this file.      |*/
/*| No inline styling is applied by this script; all presentation      |*/
/*| lives in the stylesheet.                                           |*/
/*|                                                                     |*/
/*| @since 1.0.0                                                       |*/
/*|---------------------------------------------------------------------|*/

/**
 * SmliserToast
 *
 * Manages a stack of toast notifications per screen corner. Each corner
 * gets a single container element (created lazily) so repeated calls
 * stack toasts instead of overlapping them.
 *
 * @since 1.0.0
 */
class SmliserToast {

	/**
	 * One container element per position, keyed by position string.
	 *
	 * @since 1.0.0
	 * @type {Object.<string, HTMLElement>}
	 */
	static containers = {};

	/**
	 * Supported corner positions.
	 *
	 * @since 1.0.0
	 * @type {string[]}
	 */
	static POSITIONS = [ 'top-right', 'top-left', 'bottom-right', 'bottom-left' ];

	/**
	 * Supported semantic types, each mapped to an accent colour in CSS.
	 *
	 * @since 1.0.0
	 * @type {string[]}
	 */
	static TYPES = [ 'default', 'success', 'error', 'warning', 'info' ];

	/**
	 * Default duration, in milliseconds, before a toast auto-dismisses.
	 * Pass `duration: 0` (or `null`) to an options object to disable
	 * auto-dismiss for a specific toast.
	 *
	 * @since 1.0.0
	 * @type {number}
	 */
	static DEFAULT_DURATION = 4000;

	/**
	 * Time, in milliseconds, allowed for the exit transition to finish
	 * before a toast (and its container, if now empty) is removed from
	 * the DOM. Must stay in sync with the CSS transition duration.
	 *
	 * @since 1.0.0
	 * @type {number}
	 */
	static EXIT_DURATION = 220;

	/**
	 * Show a toast notification.
	 *
	 * @since 1.0.0
	 * @param {string} message Message to display.
	 * @param {Object} [options] Display options.
	 * @param {string}  [options.position='top-right'] One of SmliserToast.POSITIONS.
	 * @param {string}  [options.type='default']        One of SmliserToast.TYPES.
	 * @param {number}  [options.duration]               Auto-dismiss delay in ms. `0` or `null` disables auto-dismiss.
	 * @param {boolean} [options.dismissible=true]       Whether to show the close (&times;) button.
	 * @return {HTMLElement} The toast element, useful for manual dismissal via SmliserToast.dismiss().
	 */
	static show( message, options = {} ) {
		const {
			position    = 'top-right',
			type        = 'default',
			duration    = SmliserToast.DEFAULT_DURATION,
			dismissible = true,
		} = options;

		const safePosition = SmliserToast.POSITIONS.includes( position ) ? position : 'top-right';
		const safeType      = SmliserToast.TYPES.includes( type ) ? type : 'default';

		const container = SmliserToast.getContainer( safePosition );
		const toast      = SmliserToast.buildToast( message, safeType, dismissible );

		container.appendChild( toast );

		// Trigger the entrance transition on the next frame, after the
		// element has been painted in its initial (offscreen) state.
		requestAnimationFrame( () => {
			requestAnimationFrame( () => {
				toast.classList.add( 'is-visible' );
			} );
		} );

		if ( duration ) {
			toast.dataset.timeoutId = window.setTimeout( () => {
				SmliserToast.dismiss( toast );
			}, duration );
		}

		return toast;
	}

	/**
	 * Convenience wrapper for a success-styled toast.
	 *
	 * @since 1.0.0
	 * @param {string} message Message to display.
	 * @param {Object} [options] Same options as SmliserToast.show(), minus `type`.
	 * @return {HTMLElement}
	 */
	static success( message, options = {} ) {
		return SmliserToast.show( message, { ...options, type: 'success' } );
	}

	/**
	 * Convenience wrapper for an error-styled toast.
	 *
	 * @since 1.0.0
	 * @param {string} message Message to display.
	 * @param {Object} [options] Same options as SmliserToast.show(), minus `type`.
	 * @return {HTMLElement}
	 */
	static error( message, options = {} ) {
		return SmliserToast.show( message, { ...options, type: 'error' } );
	}

	/**
	 * Convenience wrapper for a warning-styled toast.
	 *
	 * @since 1.0.0
	 * @param {string} message Message to display.
	 * @param {Object} [options] Same options as SmliserToast.show(), minus `type`.
	 * @return {HTMLElement}
	 */
	static warning( message, options = {} ) {
		return SmliserToast.show( message, { ...options, type: 'warning' } );
	}

	/**
	 * Convenience wrapper for an info-styled toast.
	 *
	 * @since 1.0.0
	 * @param {string} message Message to display.
	 * @param {Object} [options] Same options as SmliserToast.show(), minus `type`.
	 * @return {HTMLElement}
	 */
	static info( message, options = {} ) {
		return SmliserToast.show( message, { ...options, type: 'info' } );
	}

	/**
	 * Dismiss a single toast, playing its exit transition first.
	 *
	 * @since 1.0.0
	 * @param {HTMLElement} toast Toast element as returned by SmliserToast.show().
	 */
	static dismiss( toast ) {
		if ( ! toast || ! toast.isConnected ) {
			return;
		}

		if ( toast.dataset.timeoutId ) {
			window.clearTimeout( Number( toast.dataset.timeoutId ) );
		}

		const container = toast.parentElement;

		toast.classList.remove( 'is-visible' );
		toast.classList.add( 'is-leaving' );

		window.setTimeout( () => {
			toast.remove();
			SmliserToast.cleanupContainerIfEmpty( container );
		}, SmliserToast.EXIT_DURATION );
	}

	/**
	 * Dismiss every currently visible toast, optionally limited to one
	 * corner.
	 *
	 * @since 1.0.0
	 * @param {string} [position] One of SmliserToast.POSITIONS. Omit to clear all corners.
	 */
	static dismissAll( position ) {
		const positions = position ? [ position ] : SmliserToast.POSITIONS;

		positions.forEach( ( pos ) => {
			const container = SmliserToast.containers[ pos ];
			if ( ! container ) {
				return;
			}

			Array.from( container.children ).forEach( ( toast ) => SmliserToast.dismiss( toast ) );
		} );
	}

	/**
	 * Get the container element for a position, creating it if needed.
	 *
	 * @since 1.0.0
	 * @param {string} position One of SmliserToast.POSITIONS.
	 * @return {HTMLElement}
	 */
	static getContainer( position ) {
		if ( SmliserToast.containers[ position ] ) {
			return SmliserToast.containers[ position ];
		}

		const [ vertical, horizontal ] = position.split( '-' );

		const container = document.createElement( 'div' );
		container.classList.add( 'smliser-toast-container', `is-${ vertical }`, `is-${ horizontal }` );
		container.setAttribute( 'role', 'status' );
		container.setAttribute( 'aria-live', 'polite' );

		document.body.appendChild( container );
		SmliserToast.containers[ position ] = container;

		return container;
	}

	/**
	 * Build a toast element. Internal helper — use show()/success()/etc.
	 *
	 * @since 1.0.0
	 * @param {string}  message     Message to display.
	 * @param {string}  type        One of SmliserToast.TYPES.
	 * @param {boolean} dismissible Whether to include a close button.
	 * @return {HTMLElement}
	 */
	static buildToast( message, type, dismissible ) {
		const toast = document.createElement( 'div' );
		toast.classList.add( 'smliser-toast', `smliser-toast--${ type }` );

		const content = document.createElement( 'div' );
		content.classList.add( 'smliser-toast__content' );

		const messageEl = document.createElement( 'p' );
		messageEl.classList.add( 'smliser-toast__message' );
		messageEl.textContent = message;

		content.appendChild( messageEl );
		toast.appendChild( content );

		if ( dismissible ) {
			const closeButton = document.createElement( 'button' );
			closeButton.type = 'button';
			closeButton.classList.add( 'smliser-toast__close' );
			closeButton.setAttribute( 'aria-label', 'Dismiss notification' );
			closeButton.innerHTML = '&times;';
			closeButton.addEventListener( 'click', () => SmliserToast.dismiss( toast ) );

			toast.appendChild( closeButton );
		}

		return toast;
	}

	/**
	 * Remove a container from the DOM and the cache once it has no
	 * toasts left in it, so a later call rebuilds it fresh rather than
	 * reusing a stale, detached reference.
	 *
	 * @since 1.0.0
	 * @param {HTMLElement} container Container element to check.
	 */
	static cleanupContainerIfEmpty( container ) {
		if ( ! container || 0 !== container.childElementCount ) {
			return;
		}

		for ( const position in SmliserToast.containers ) {
			if ( SmliserToast.containers[ position ] === container ) {
				delete SmliserToast.containers[ position ];
			}
		}

		container.remove();
	}
}

/*|---------------------------------------------------------------------|*/
/*| Backward-compatible wrapper.                                        |*/
/*|                                                                     |*/
/*| Existing call sites — smliserNotify( message, duration ) — keep    |*/
/*| working unchanged. New code should call SmliserToast directly for  |*/
/*| access to position, type, and dismissible options.                 |*/
/*|                                                                     |*/
/*| @since 1.0.0                                                       |*/
/*|---------------------------------------------------------------------|*/

/**
 * @deprecated Use SmliserToast.show() (or .success()/.error()/etc.) directly. Kept for backward compatibility.
 *
 * @since 1.0.0
 * @param {string} message  The message to display.
 * @param {number} [duration] Auto-dismiss delay in ms. Falls back to SmliserToast.DEFAULT_DURATION when omitted.
 * @return {HTMLElement} The created toast element.
 */
function smliserNotify( message, duration ) {
	return SmliserToast.show( message, {
		duration: undefined === duration ? SmliserToast.DEFAULT_DURATION : duration,
	} );
}