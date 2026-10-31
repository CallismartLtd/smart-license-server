/**
 * Modules pages (Themes, Extensions, Marketplace): module card actions.
 *
 * Every module is drawn as a module card ([data-module-card], with
 * data-module-kind and data-slug). Its buttons [data-module-action] post to
 * admin/json/<kind>s/<action> with the card's slug. Page buttons
 * [data-module-page-action] and forms [data-module-form] post to the same
 * endpoints, for the page's kind (#smliser-modules[data-module-kind]).
 *
 * After an action the page reloads, so the server-rendered cards stay the
 * single source of what is shown. "check" is the exception: it writes what
 * the marketplace has into each card's status line.
 *
 * The Marketplace page ([data-marketplace]) is the other exception: its
 * server-rendered part is a shell. This script reads the modules from
 * admin/json/<kind>s/marketplace and draws them as cards (buildCard()
 * mirrors card.php), for the search term, the page and the section (the
 * kind of module), without reloading the page. An action on one of its
 * cards (Install, Update) refreshes the list in place.
 */
( function () {
	'use strict';

	const TIMEOUT_MS = 180000;
	const SPINNER    = 'ti ti-loader-2 smliser-health-spin';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-modules' );

		if ( ! root ) {
			return;
		}

		// Delegated: the Marketplace page adds its cards after the page loads.
		root.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-module-card] [data-module-action]' );

			if ( ! button || ! root.contains( button ) ) {
				return;
			}

			const card = button.closest( '[data-module-card]' );

			run( root, button, card.dataset.moduleKind, button.dataset.moduleAction, { slug: card.dataset.slug } );
		} );

		root.querySelectorAll( '[data-module-page-action]' ).forEach( ( button ) => {
			button.addEventListener( 'click', () => run( root, button, root.dataset.moduleKind, button.dataset.modulePageAction, {} ) );
		} );

		root.querySelectorAll( '[data-module-form]' ).forEach( ( form ) => {
			form.addEventListener( 'submit', ( event ) => {
				event.preventDefault();
				submitForm( root, form );
			} );
		} );

		if ( root.hasAttribute( 'data-marketplace' ) ) {
			initMarketplace( root );
		}
	} );

	/**
	 * Run an action, after its button's confirmation.
	 *
	 * @param {HTMLElement}           root
	 * @param {HTMLButtonElement}     button
	 * @param {string}                kind   Module kind, e.g. "theme".
	 * @param {string}                action Endpoint action, e.g. "activate".
	 * @param {Object<string,string>} fields Fields to post.
	 * @return {Promise<void>}
	 */
	async function run( root, button, kind, action, fields ) {
		if ( button.dataset.confirm && ! await SmliserModal.confirm( { message: button.dataset.confirm, danger: '1' === button.dataset.danger } ) ) {
			return;
		}

		const body = new FormData();

		Object.entries( fields ).forEach( ( [ key, value ] ) => body.append( key, value ) );

		const restore = setBusy( root, button );

		try {
			const response = await post( kind, action, body );

			if ( 'check' === action ) {
				restore();
				showUpdates( root, response?.data?.updates || {} );
				SmliserToast.show( response?.data?.message || 'Done.', { type: 'success', duration: 10000 } );
				return;
			}

			done( response, restore );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The request failed.', { type: 'error' } );
		}
	}

	/**
	 * Submit a module form (e.g. the theme package upload).
	 *
	 * @param {HTMLElement}     root
	 * @param {HTMLFormElement} form
	 * @return {Promise<void>}
	 */
	async function submitForm( root, form ) {
		const body = new FormData( form );

		if ( body.get( 'trust_unsigned' ) ) {
			const ok = await SmliserModal.confirm( {
				title: 'Trust an unsigned package',
				message: 'The package will be installed even if its signature is missing or invalid. Continue only for a package you built or received directly.',
				confirmText: 'Install anyway',
				danger: true,
			} );

			if ( ! ok ) {
				return;
			}
		}

		const restore = setBusy( root, form.querySelector( '[type="submit"]' ) );

		try {
			done( await post( root.dataset.moduleKind, form.dataset.moduleForm, body ), restore );
		} catch ( error ) {
			restore();
			SmliserToast.show( error?.message || 'The module could not be installed.', { type: 'error', duration: 15000 } );
		}
	}

	/**
	 * What to do once an action succeeded, besides showing its outcome: the
	 * Marketplace page sets it to refresh its list in place. Without it the
	 * page reloads.
	 *
	 * @type {?Function}
	 */
	let afterAction = null;

	/**
	 * Show the outcome of an action, then reload the page (or refresh the Marketplace list).
	 *
	 * @param {Object}   response
	 * @param {Function} restore  Restores the buttons the action disabled.
	 * @return {void}
	 */
	function done( response, restore ) {
		( response?.data?.warnings || [] ).forEach( ( warning ) => SmliserToast.show( warning, { type: 'warning', duration: 10000 } ) );
		SmliserToast.show( response?.data?.message || 'Done.', { type: 'success', duration: 10000 } );

		if ( afterAction ) {
			restore();
			afterAction();
			return;
		}

		setTimeout( () => window.location.reload(), 1500 );
	}

	/**
	 * Write the marketplace's answer into each card's status line.
	 *
	 * @param {HTMLElement} root
	 * @param {Object<string, {installed: ?string, latest: ?string, update: boolean, error: ?string}>} updates
	 * @return {void}
	 */
	function showUpdates( root, updates ) {
		Object.entries( updates ).forEach( ( [ slug, result ] ) => {
			const status = root.querySelector( `[data-module-card][data-slug="${ CSS.escape( slug ) }"] [data-module-status]` );

			if ( ! status ) {
				return;
			}

			status.hidden       = false;
			status.dataset.tone = result.error ? 'danger' : ( result.update ? 'info' : 'active' );
			status.textContent  = result.error
				? `Could not check: ${ result.error }`
				: ( result.update ? `Version ${ result.latest } is available.` : 'Up to date.' );
		} );
	}

	/*
	|---------
	| REQUEST
	|---------
	*/

	/**
	 * POST to admin/json/<kind>s/<action>. smliserFetchJSON() adds the CSRF
	 * header and throws { message, code, status } on failure.
	 *
	 * @param {string}       kind
	 * @param {string}       action
	 * @param {FormData}     body
	 * @param {?AbortSignal} signal  Aborts the request when it aborts (a newer request replaced it).
	 * @return {Promise<Object>}
	 */
	async function post( kind, action, body, signal = null ) {
		const controller = new AbortController();
		const timer      = setTimeout( () => controller.abort(), TIMEOUT_MS );

		signal?.addEventListener( 'abort', () => controller.abort(), { once: true } );
		const url        = new URL( smliser_var.ajaxURL, window.location.href );

		url.pathname = `${ url.pathname.replace( /\/+$/, '' ) }/${ kind }s/${ action }`;

		try {
			return await smliserFetchJSON( url, {
				method: 'POST',
				body,
				credentials: 'same-origin',
				signal: controller.signal,
			} );
		} finally {
			clearTimeout( timer );
		}
	}

	/*
	|-------------
	| MARKETPLACE
	|-------------
	*/

	/**
	 * Draw the Marketplace page from the update server's listing.
	 *
	 * State is the section (themes, extensions), the search term and the page
	 * number, kept in the address (?section=, ?search=, ?paged=) so a reload,
	 * the back button and a shared link show the same list.
	 *
	 * @param {HTMLElement} root #smliser-modules[data-marketplace]
	 * @return {void}
	 */
	function initMarketplace( root ) {
		const kinds   = JSON.parse( root.dataset.kinds || '{}' );
		const form    = root.querySelector( '[data-marketplace-search]' );
		const input   = form.querySelector( 'input[name="search"]' );
		const results = root.querySelector( '[data-marketplace-results]' );
		const tabs    = [ ...root.querySelectorAll( '.smliser-quick-actions [data-section]' ) ];
		const state   = {
			section: root.dataset.section,
			search: input.value.trim(),
			paged: parseInt( root.dataset.paged, 10 ) || 1,
		};

		let controller = null;

		/**
		 * Show the state in the tabs and the search form.
		 *
		 * @return {void}
		 */
		const show = () => {
			const kind = kinds[ state.section ];

			root.dataset.section    = state.section;
			root.dataset.moduleKind = kind;
			input.value             = state.search;
			input.placeholder       = 'Search by name, slug or author';
			form.querySelector( 'input[name="section"]' ).value = state.section;

			tabs.forEach( ( tab ) => tab.classList.toggle( 'active', tab.dataset.section === state.section ) );
		};

		/**
		 * Put the state in the address.
		 *
		 * @param {boolean} replace Replace the current entry instead of adding one.
		 * @return {void}
		 */
		const sync = ( replace = false ) => {
			const tab = tabs.find( ( node ) => node.dataset.section === state.section );
			const url = new URL( tab ? tab.href : window.location.href, window.location.href );

			url.search = '';

			if ( tab ) {
				new URL( tab.href, window.location.href ).searchParams.forEach( ( value, key ) => url.searchParams.set( key, value ) );
			}

			if ( state.search ) {
				url.searchParams.set( 'search', state.search );
			}

			if ( state.paged > 1 ) {
				url.searchParams.set( 'paged', String( state.paged ) );
			}

			window.history[ replace ? 'replaceState' : 'pushState' ]( { ...state }, '', url );
		};

		/**
		 * Read the listing for the state and draw it. A newer call cancels an older one.
		 *
		 * @return {Promise<void>}
		 */
		const load = async () => {
			const kind = kinds[ state.section ];

			controller?.abort();

			const current = new AbortController();

			controller = current;

			show();
			results.replaceChildren( messageNode( `${ SPINNER }`, 'Loading the marketplace…' ) );

			const body = new FormData();

			body.append( 'search', state.search );
			body.append( 'paged', String( state.paged ) );

			try {
				const response = await post( kind, 'marketplace', body, current.signal );

				if ( current.signal.aborted ) {
					return;
				}

				renderListing( results, kind, state, response?.data?.modules || [], response?.data?.pagination || null, ( paged ) => {
					state.paged = paged;
					sync();
					load();
				} );
			} catch ( error ) {
				if ( current.signal.aborted ) {
					return;
				}

				results.replaceChildren( messageNode( 'ti ti-cloud-off', `The marketplace could not be read: ${ error?.message || 'the request failed.' }` ) );
			}
		};

		tabs.forEach( ( tab ) => {
			tab.addEventListener( 'click', ( event ) => {
				event.preventDefault();

				if ( tab.dataset.section === state.section ) {
					return;
				}

				state.section = tab.dataset.section;
				state.search  = '';
				state.paged   = 1;
				sync();
				load();
			} );
		} );

		form.addEventListener( 'submit', ( event ) => {
			event.preventDefault();

			state.search = input.value.trim();
			state.paged  = 1;
			sync();
			load();
		} );

		window.addEventListener( 'popstate', ( event ) => {
			if ( ! event.state?.section ) {
				return;
			}

			Object.assign( state, event.state );
			load();
		} );

		// After Install or Update: read the list again, in place.
		afterAction = load;

		sync( true );
		load();
	}

	/**
	 * Draw a listing: its cards and pagination, or why there are none.
	 *
	 * @param {HTMLElement} results    [data-marketplace-results]
	 * @param {string}      kind       "theme" or "extension".
	 * @param {Object}      state      {section, search, paged}
	 * @param {Object[]}    modules    {slug, name, version, summary, author, icon, homepage, installed, update}
	 * @param {?Object}     pagination {total, page, limit, total_pages}
	 * @param {Function}    goTo       Called with the page number a pagination button asks for.
	 * @return {void}
	 */
	function renderListing( results, kind, state, modules, pagination, goTo ) {
		if ( 0 === modules.length ) {
			results.replaceChildren( messageNode(
				'ti ti-mood-empty',
				'' === state.search ? `The marketplace publishes no ${ kind }s yet.` : `No ${ kind } matches "${ state.search }".`
			) );
			return;
		}

		const grid = el( 'div', 'smliser-module-grid' );

		modules.forEach( ( module ) => grid.append( buildCard( marketplaceCard( kind, module ) ) ) );

		const nodes = [ grid ];

		if ( pagination && pagination.total_pages > 1 ) {
			const pager = el( 'nav', 'smliser-modules-pagination' );

			pager.setAttribute( 'aria-label', 'Pages' );

			if ( pagination.page > 1 ) {
				pager.append( pageButton( 'ti ti-chevron-left', 'Previous', pagination.page - 1, goTo, true ) );
			}

			pager.append( el( 'span', '', `Page ${ pagination.page } of ${ pagination.total_pages }` ) );

			if ( pagination.page < pagination.total_pages ) {
				pager.append( pageButton( 'ti ti-chevron-right', 'Next', pagination.page + 1, goTo, false ) );
			}

			nodes.push( pager );
		}

		results.replaceChildren( ...nodes );
	}

	/**
	 * A marketplace module as card data (the shape of ModulesPage::card()).
	 *
	 * @param {string} kind   "theme" or "extension".
	 * @param {Object} module {slug, name, version, summary, author, icon, homepage, installed, update}
	 * @return {Object}
	 */
	function marketplaceCard( kind, module ) {
		const badges  = [ [ kind.charAt( 0 ).toUpperCase() + kind.slice( 1 ), 'muted' ] ];
		const actions = [];
		const homepage = safeUrl( module.homepage );

		if ( null === module.installed ) {
			actions.push( { action: 'install', label: 'Install', icon: 'ti ti-download', confirm: '', danger: false } );
		} else if ( module.update ) {
			badges.push( [ `Installed ${ module.installed }`, 'info' ] );
			actions.push( {
				action: 'install',
				label: `Update to ${ module.version }`,
				icon: 'ti ti-download',
				confirm: `Update ${ module.name } from ${ module.installed } to ${ module.version }? Its files are replaced.`,
				danger: false,
			} );
		} else {
			badges.push( [ 'Installed', 'active' ] );
		}

		return {
			slug: module.slug,
			name: module.name,
			kind,
			version: module.version,
			description: module.summary,
			image: safeUrl( module.icon ),
			badges,
			meta: module.author ? [ [ 'ti ti-user', module.author ] ] : [],
			actions,
			links: homepage ? [ [ 'Details', homepage ] ] : [],
			highlight: false,
		};
	}

	/**
	 * Draw a module card. Mirrors templates/admin/contents/modules/card.php:
	 * change one, change the other.
	 *
	 * Everything the update server sent is set as text or as an attribute of an
	 * element made here, never as markup.
	 *
	 * @param {Object} card See ModulesPage::card().
	 * @return {HTMLElement}
	 */
	function buildCard( card ) {
		const article = el( 'article', 'smliser-module-card' + ( card.highlight ? ' is-highlighted' : '' ) );

		article.setAttribute( 'data-module-card', '' );
		article.dataset.moduleKind = card.kind;
		article.dataset.slug       = card.slug;

		const media = el( 'div', 'smliser-module-card-media' );

		if ( card.image ) {
			const image = document.createElement( 'img' );

			image.src     = card.image;
			image.alt     = '';
			image.loading = 'lazy';
			media.append( image );
		} else {
			const initial = el( 'span', 'smliser-module-card-initial', ( Array.from( card.name )[ 0 ] || '' ).toUpperCase() );

			initial.setAttribute( 'aria-hidden', 'true' );
			media.append( initial );
		}

		const body   = el( 'div', 'smliser-module-card-body' );
		const header = el( 'header', 'smliser-module-card-header' );

		header.append( el( 'h3', 'smliser-module-card-title', card.name ) );

		if ( card.version ) {
			header.append( el( 'code', 'smliser-module-card-version', card.version ) );
		}

		body.append( header, el( 'code', 'smliser-module-card-slug', card.slug ) );

		if ( card.badges.length ) {
			const badges = el( 'ul', 'smliser-module-card-badges' );

			card.badges.forEach( ( [ label, tone ] ) => badges.append( el( 'li', `smliser-module-badge smliser-module-badge--${ tone }`, label ) ) );
			body.append( badges );
		}

		if ( card.description ) {
			body.append( el( 'p', 'smliser-module-card-description', card.description ) );
		}

		if ( card.meta.length ) {
			const meta = el( 'ul', 'smliser-module-card-meta' );

			card.meta.forEach( ( [ icon, text ] ) => {
				const item = document.createElement( 'li' );

				item.append( el( 'i', icon ), ` ${ text }` );
				meta.append( item );
			} );
			body.append( meta );
		}

		const status = el( 'p', 'smliser-module-card-status' );

		status.setAttribute( 'data-module-status', '' );
		status.hidden = true;
		body.append( status );

		article.append( media, body );

		if ( card.actions.length || card.links.length ) {
			const footer = el( 'footer', 'smliser-module-card-actions' );

			card.actions.forEach( ( action ) => {
				const button = el( 'button', 'smliser-btn smliser-btn-glass' + ( action.danger ? ' smliser-module-action--danger' : '' ) );

				button.type = 'button';
				button.dataset.moduleAction = action.action;

				if ( action.confirm ) {
					button.dataset.confirm = action.confirm;
				}

				if ( action.danger ) {
					button.dataset.danger = '1';
				}

				button.append( el( 'i', action.icon ), ` ${ action.label }` );
				footer.append( button );
			} );

			card.links.forEach( ( [ label, url ] ) => {
				const link = el( 'a', 'smliser-module-card-link' );

				link.href   = url;
				link.target = '_blank';
				link.rel    = 'noopener';
				link.append( `${ label } `, el( 'i', 'ti ti-external-link' ) );
				footer.append( link );
			} );

			article.append( footer );
		}

		return article;
	}

	/**
	 * A pagination button.
	 *
	 * @param {string}   icon
	 * @param {string}   label
	 * @param {number}   paged  The page it goes to.
	 * @param {Function} goTo
	 * @param {boolean}  before Icon before the label.
	 * @return {HTMLButtonElement}
	 */
	function pageButton( icon, label, paged, goTo, before ) {
		const button = el( 'button', 'smliser-btn smliser-btn-glass' );

		button.type = 'button';
		button.append( ...( before ? [ el( 'i', icon ), ` ${ label }` ] : [ `${ label } `, el( 'i', icon ) ] ) );
		button.addEventListener( 'click', () => goTo( paged ) );

		return button;
	}

	/**
	 * A message in the place of the list: loading, empty, or an error.
	 *
	 * @param {string} icon
	 * @param {string} text
	 * @return {HTMLElement}
	 */
	function messageNode( icon, text ) {
		const node = el( 'p', 'smliser-modules-empty' );

		node.append( el( 'i', icon ), ` ${ text }` );

		return node;
	}

	/**
	 * An element with a class and text.
	 *
	 * @param {string}  tag
	 * @param {string}  className
	 * @param {?string} text
	 * @return {HTMLElement}
	 */
	function el( tag, className = '', text = null ) {
		const node = document.createElement( tag );

		if ( className ) {
			node.className = className;
		}

		if ( null !== text ) {
			node.textContent = text;
		}

		return node;
	}

	/**
	 * A URL the page may load or link to: http(s) only.
	 *
	 * @param {*} value
	 * @return {string} The URL; empty when it is not an http(s) URL.
	 */
	function safeUrl( value ) {
		return 'string' === typeof value && /^https?:\/\//i.test( value ) ? value : '';
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Disable every action while one runs, and show a spinner on its button.
	 *
	 * @param {HTMLElement}       root
	 * @param {HTMLButtonElement} button
	 * @return {Function} Restores the buttons.
	 */
	function setBusy( root, button ) {
		const buttons = [ ...root.querySelectorAll( 'button' ) ];
		const states  = buttons.map( ( node ) => node.disabled );
		const icon    = button?.querySelector( 'i' );
		const iconWas = icon?.className;

		buttons.forEach( ( node ) => {
			node.disabled = true;
		} );

		if ( icon ) {
			icon.className = SPINNER;
		}

		return () => {
			buttons.forEach( ( node, index ) => {
				node.disabled = states[ index ];
			} );

			if ( icon ) {
				icon.className = iconWas;
			}
		};
	}
}() );