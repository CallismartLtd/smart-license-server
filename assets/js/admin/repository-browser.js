/**
 * Repository browser.
 *
 * Shows what app storage holds, level by level: the app types, a type's
 * apps, then an app's files. Everything shown comes from
 * admin/json/repository-browser, including which actions each file and app
 * offers, so the page never offers what the server would refuse.
 *
 * Changes go through the same endpoints the edit pages use:
 *  - assets:    upload-app-assets (POST new, PATCH replace), app-asset (DELETE);
 *  - artifacts: app-artifacts (POST new, PATCH replace or rename), app-artifact (DELETE);
 *  - the slug:  repository-browser/rename-app (PATCH).
 *
 * The location is kept in the page URL (?type=&slug=), so reloading, the
 * back button and links from other pages open the same place.
 */
( function () {
	'use strict';

	/**
	 * Largest text file previewed in the page, in bytes.
	 *
	 * @type {number}
	 */
	const TEXT_PREVIEW_LIMIT = 512 * 1024;

	/**
	 * How an app's files are grouped, in display order.
	 *
	 * @type {Array<{ id: string, title: string, kinds: Array<string|null>, note: string }>}
	 */
	const GROUPS = [
		{
			id: 'canonical',
			title: 'Package and documents',
			kinds: [ 'package', 'manifest', 'document' ],
			note: 'Read-only. Upload a new version of the app to change these.',
		},
		{ id: 'assets', title: 'Assets', kinds: [ 'asset' ], note: '' },
		{ id: 'artifacts', title: 'Artifacts', kinds: [ 'artifact' ], note: '' },
		{
			id: 'other',
			title: 'Other files',
			kinds: [ null ],
			note: 'Stored with the app but not managed by it.',
		},
	];

	/**
	 * Readable names for file kinds.
	 *
	 * @type {Object<string, string>}
	 */
	const KIND_LABELS = {
		package: 'Main package',
		manifest: 'Manifest',
		document: 'Document',
		asset: 'Asset',
		artifact: 'Artifact',
	};

	/**
	 * Tabler icons for file extensions.
	 *
	 * A lookup rather than a switch, so a new extension is a one-line
	 * addition. Extensions not listed get the generic file icon.
	 *
	 * @type {Object<string, string>}
	 */
	const FILE_ICONS = {
		// Archives.
		zip: 'ti-file-zip', rar: 'ti-file-zip', '7z': 'ti-file-zip', tar: 'ti-file-zip', gz: 'ti-file-zip', bz2: 'ti-file-zip', xz: 'ti-file-zip',

		// PDF and office documents.
		pdf: 'ti-file-type-pdf', doc: 'ti-file-type-doc', docx: 'ti-file-type-docx', odt: 'ti-file-type-docx', rtf: 'ti-file-text', txt: 'ti-file-text', md: 'ti-markdown',

		// Spreadsheets and tabular data.
		xls: 'ti-file-type-xls', xlsx: 'ti-file-type-xls', ods: 'ti-file-type-xls', csv: 'ti-file-type-csv', tsv: 'ti-file-spreadsheet',

		// Presentations.
		ppt: 'ti-file-type-ppt', pptx: 'ti-file-type-ppt', odp: 'ti-presentation',

		// Images.
		jpg: 'ti-photo', jpeg: 'ti-photo', png: 'ti-photo', gif: 'ti-photo', webp: 'ti-photo', svg: 'ti-photo', bmp: 'ti-photo', ico: 'ti-photo', tif: 'ti-photo', tiff: 'ti-photo', avif: 'ti-photo',

		// Audio.
		mp3: 'ti-file-music', wav: 'ti-file-music', ogg: 'ti-file-music', oga: 'ti-file-music', flac: 'ti-file-music', m4a: 'ti-file-music', aac: 'ti-file-music', opus: 'ti-file-music',

		// Video. The bundled set has no ti-file-video.
		mp4: 'ti-movie', webm: 'ti-movie', mov: 'ti-movie', avi: 'ti-movie', mkv: 'ti-movie', m4v: 'ti-movie', wmv: 'ti-movie', mpg: 'ti-movie', mpeg: 'ti-movie',

		// Web.
		html: 'ti-brand-html5', htm: 'ti-brand-html5', css: 'ti-brand-css3', scss: 'ti-brand-sass', sass: 'ti-brand-sass', less: 'ti-brand-css3',

		// JavaScript and TypeScript.
		js: 'ti-brand-javascript', jsx: 'ti-brand-react', ts: 'ti-brand-typescript', tsx: 'ti-brand-react', vue: 'ti-brand-vue', svelte: 'ti-brand-svelte',

		// Server-side code. The bundled set has no ti-brand-java.
		php: 'ti-brand-php', phtml: 'ti-brand-php', py: 'ti-brand-python', pyw: 'ti-brand-python', java: 'ti-file-code', jar: 'ti-file-code', war: 'ti-file-code',

		// C, C++ and C#.
		c: 'ti-file-code', h: 'ti-file-code', cpp: 'ti-file-code', hpp: 'ti-file-code', cs: 'ti-file-code',

		// Shell.
		sh: 'ti-terminal-2', bash: 'ti-terminal-2', zsh: 'ti-terminal-2', fish: 'ti-terminal-2', ps1: 'ti-terminal-2',

		// Data and configuration.
		json: 'ti-file-code', xml: 'ti-file-code', yaml: 'ti-file-code', yml: 'ti-file-code', toml: 'ti-file-code', ini: 'ti-file-settings', conf: 'ti-file-settings', config: 'ti-file-settings', env: 'ti-file-settings',

		// Databases.
		sql: 'ti-database', db: 'ti-database', sqlite: 'ti-database', sqlite3: 'ti-database',

		// Fonts.
		ttf: 'ti-typography', otf: 'ti-typography', woff: 'ti-typography', woff2: 'ti-typography',

		// E-books.
		epub: 'ti-book', mobi: 'ti-book',

		// Disk images.
		iso: 'ti-disc', img: 'ti-disc',

		// Certificates and keys.
		pem: 'ti-certificate', crt: 'ti-certificate', cer: 'ti-certificate', der: 'ti-certificate', key: 'ti-key',

		// Logs and patches.
		log: 'ti-file-text', diff: 'ti-file-code', patch: 'ti-file-code',
	};

	/**
	 * Below this width the file panel opens in a modal instead of beside the list.
	 *
	 * Matches the narrow-view breakpoint in repository-browser.css.
	 *
	 * @type {string}
	 */
	const COMPACT_QUERY = '(max-width: 900px)';

	document.addEventListener( 'DOMContentLoaded', () => {
		const root = document.getElementById( 'smliser-repo-browser' );

		if ( root ) {
			new RepositoryBrowser( root ).start();
		}
	} );

	/**
	 * The browser on one page.
	 */
	class RepositoryBrowser {
		/**
		 * @param {HTMLElement} root
		 */
		constructor( root ) {
			this.root      = root;
			this.path      = root.querySelector( '.smliser-repo-browser__path' );
			this.appBar    = root.querySelector( '.smliser-repo-browser__app-actions' );
			this.listing   = root.querySelector( '.smliser-repo-browser__listing' );
			this.preview   = root.querySelector( '.smliser-repo-browser__preview' );
			this.type      = root.dataset.appType || '';
			this.slug      = root.dataset.appSlug || '';
			this.data      = null;
			this.selected  = null;
			this.requestId = 0;
			this.compact   = window.matchMedia( COMPACT_QUERY );
			this.modal     = null;
		}

		/**
		 * Load the first location and follow the back button.
		 */
		start() {
			window.addEventListener( 'popstate', () => {
				const params = new URLSearchParams( window.location.search );

				this.open( params.get( 'type' ) || '', params.get( 'slug' ) || '', { push: false } );
			} );

			this.compact.addEventListener( 'change', () => this.onLayoutChange() );

			this.open( this.type, this.slug, { push: false } );
		}

		/*
		|----------
		| LOADING
		|----------
		*/

		/**
		 * Open a location: the root, a type, or an app.
		 *
		 * @param {string} type
		 * @param {string} slug
		 * @param {Object} [options={}]
		 * @param {boolean} [options.push=true] - Add the location to the browser history.
		 * @param {string|null} [options.select=null] - Path of the file to select once loaded.
		 * @return {Promise<void>}
		 */
		async open( type, slug, { push = true, select = null } = {} ) {
			const requestId = ++this.requestId;

			this.type     = type;
			this.slug     = type ? slug : '';
			this.selected = null;

			if ( push ) {
				this.pushLocation();
			}

			this.renderPath();
			this.appBar.replaceChildren();
			this.hidePreview();
			this.listing.replaceChildren( this.message( 'Loading…', 'ti ti-loader-2 smliser-repo-browser__spin' ) );

			let response;

			try {
				response = await smliserFetchJSON( smliserAjaxUrl( 'repository-browser', {
					app_type: this.type || null,
					app_slug: this.slug || null,
				} ), { credentials: 'same-origin' } );
			} catch ( error ) {
				if ( requestId === this.requestId ) {
					this.listing.replaceChildren( this.message( error.message, 'ti ti-alert-triangle', true ) );
				}

				return;
			}

			// A newer location was opened while this one loaded.
			if ( requestId !== this.requestId ) {
				return;
			}

			this.data = response.data;
			this.renderPath();
			this.renderAppActions();
			this.renderListing();

			if ( 'app' !== this.data.level ) {
				return;
			}

			const entry = select ? this.data.entries.find( ( item ) => item.path === select ) : null;

			// Reselecting after a change marks the file; it does not reopen the modal.
			entry ? this.select( entry, { reveal: false } ) : this.showEmptyPanel();
		}

		/**
		 * Reload the current location, keeping the selection when it still exists.
		 *
		 * @return {Promise<void>}
		 */
		reload() {
			return this.open( this.type, this.slug, { push: false, select: this.selected?.path ?? null } );
		}

		/**
		 * Put the current location in the page URL.
		 */
		pushLocation() {
			const url = new URL( window.location.href );

			url.searchParams.delete( 'type' );
			url.searchParams.delete( 'slug' );

			if ( this.type ) {
				url.searchParams.set( 'type', this.type );
			}

			if ( this.slug ) {
				url.searchParams.set( 'slug', this.slug );
			}

			window.history.pushState( null, '', url );
		}

		/*
		|-------------
		| RENDERING
		|-------------
		*/

		/**
		 * Render the location as links back up the tree.
		 */
		renderPath() {
			const crumbs = [ { label: 'All types', type: '', slug: '' } ];

			if ( this.type ) {
				crumbs.push( { label: StringUtils.ucfirst( this.type ), type: this.type, slug: '' } );
			}

			if ( this.slug ) {
				crumbs.push( { label: this.data?.app?.name || this.slug, type: this.type, slug: this.slug } );
			}

			const items = crumbs.map( ( crumb, index ) => {
				const isLast = index === crumbs.length - 1;

				if ( isLast ) {
					return el( 'span', { class: 'smliser-repo-browser__crumb is-current', 'aria-current': 'page' }, crumb.label );
				}

				const button = el( 'button', { type: 'button', class: 'smliser-repo-browser__crumb' }, crumb.label );

				button.addEventListener( 'click', () => this.open( crumb.type, crumb.slug ) );

				return button;
			} );

			const parts = [];

			items.forEach( ( item, index ) => {
				if ( index > 0 ) {
					parts.push( el( 'i', { class: 'ti ti-chevron-right', 'aria-hidden': 'true' } ) );
				}

				parts.push( item );
			} );

			this.path.replaceChildren( ...parts );
		}

		/**
		 * Render the actions for the open app.
		 */
		renderAppActions() {
			const data    = this.data;
			const actions = data?.app_actions ?? [];
			const buttons = [];

			if ( actions.includes( 'upload_asset' ) ) {
				buttons.push( this.button( 'Upload assets', 'ti ti-photo-up', () => this.uploadAssets() ) );
			}

			if ( actions.includes( 'upload_artifact' ) ) {
				buttons.push( this.button( 'Upload artifact', 'ti ti-file-upload', () => this.uploadArtifact() ) );
			}

			if ( actions.includes( 'rename_app' ) ) {
				buttons.push( this.button( 'Change slug', 'ti ti-forms', () => this.renameApp() ) );
			}

			if ( data?.app?.view_url ) {
				buttons.push( el( 'a', { class: 'smliser-btn smliser-btn-glass', href: data.app.view_url },
					el( 'i', { class: 'ti ti-eye', 'aria-hidden': 'true' } ), 'Open app'
				) );
			}

			this.appBar.replaceChildren( ...buttons );
		}

		/**
		 * Render the entries of the current level.
		 */
		renderListing() {
			const { level, entries } = this.data;

			if ( 'app' !== level ) {
				this.listing.replaceChildren( this.renderFolders( entries, level ) );
				return;
			}

			const sections = [];

			if ( null === this.data.app ) {
				sections.push( this.message(
					'These files have no app record, so they can be viewed but not changed.',
					'ti ti-info-circle'
				) );
			}

			for ( const group of GROUPS ) {
				const items = entries.filter( ( entry ) => group.kinds.includes( entry.kind ) );

				if ( ! items.length && 'assets' !== group.id && 'artifacts' !== group.id ) {
					continue;
				}

				sections.push( this.renderGroup( group, items ) );
			}

			this.listing.replaceChildren( ...sections );
		}

		/**
		 * Render types or apps as folders to open.
		 *
		 * @param {Array<Object>} entries
		 * @param {string} level - "root" or "type".
		 * @return {HTMLElement}
		 */
		renderFolders( entries, level ) {
			if ( ! entries.length ) {
				return this.message(
					'root' === level ? 'No app types are stored yet.' : `No ${ this.type } has files in storage yet.`,
					'ti ti-folder-off'
				);
			}

			const list = el( 'ul', { class: 'smliser-repo-browser__folders' } );

			for ( const entry of entries ) {
				const button = el( 'button', { type: 'button', class: 'smliser-repo-browser__folder' },
					el( 'i', { class: 'root' === level ? 'ti ti-folders' : 'ti ti-folder', 'aria-hidden': 'true' } ),
					el( 'span', { class: 'smliser-repo-browser__folder-name' }, 'root' === level ? StringUtils.ucfirst( entry.name ) : entry.name ),
					el( 'span', { class: 'smliser-repo-browser__meta' }, entry.mtime ? `Changed ${ formatDate( entry.mtime ) }` : '' )
				);

				button.addEventListener( 'click', () => {
					'root' === level ? this.open( entry.name, '' ) : this.open( this.type, entry.name );
				} );

				list.append( el( 'li', {}, button ) );
			}

			return list;
		}

		/**
		 * Render one group of an app's files.
		 *
		 * @param {{ id: string, title: string, note: string }} group
		 * @param {Array<Object>} items
		 * @return {HTMLElement}
		 */
		renderGroup( group, items ) {
			const section = el( 'section', { class: `smliser-repo-browser__group is-${ group.id }` } );
			const heading = el( 'h3', { class: 'smliser-repo-browser__group-title' }, group.title,
				el( 'span', { class: 'smliser-repo-browser__count' }, String( items.length ) )
			);

			section.append( heading );

			if ( group.note ) {
				section.append( el( 'p', { class: 'smliser-repo-browser__note' }, group.note ) );
			}

			if ( ! items.length ) {
				section.append( el( 'p', { class: 'smliser-repo-browser__empty' },
					'assets' === group.id ? 'No assets yet.' : 'No artifacts yet.'
				) );

				return section;
			}

			if ( 'assets' === group.id ) {
				const grid = el( 'ul', { class: 'smliser-repo-browser__thumbs' } );

				items.forEach( ( entry ) => grid.append( el( 'li', {}, this.renderThumb( entry ) ) ) );
				section.append( grid );

				return section;
			}

			const list = el( 'ul', { class: 'smliser-repo-browser__files' } );

			items.forEach( ( entry ) => list.append( el( 'li', {}, this.renderRow( entry ) ) ) );
			section.append( list );

			return section;
		}

		/**
		 * Render an asset as a selectable thumbnail.
		 *
		 * @param {Object} entry
		 * @return {HTMLButtonElement}
		 */
		renderThumb( entry ) {
			const button = el( 'button', {
				type: 'button',
				class: 'smliser-repo-browser__thumb',
				'data-path': entry.path,
				'aria-pressed': 'false',
			},
			el( 'span', { class: 'smliser-repo-browser__thumb-frame' },
				el( 'img', { src: entry.file_url, alt: '', loading: 'lazy', decoding: 'async' } )
			),
			el( 'span', { class: 'smliser-repo-browser__thumb-name' }, entry.name ),
			el( 'span', { class: 'smliser-repo-browser__meta' }, this.assetTypeLabel( entry ) || 'Unrecognised name' )
			);

			button.addEventListener( 'click', () => this.select( entry ) );

			return button;
		}

		/**
		 * Render a file as a selectable row.
		 *
		 * @param {Object} entry
		 * @return {HTMLButtonElement}
		 */
		renderRow( entry ) {
			const button = el( 'button', {
				type: 'button',
				class: 'smliser-repo-browser__file',
				'data-path': entry.path,
				'aria-pressed': 'false',
			},
			el( 'i', { class: fileIcon( entry.name ), 'aria-hidden': 'true' } ),
			// Grouped files are named by their group; other files need their full path.
			el( 'span', { class: 'smliser-repo-browser__file-name' }, null === entry.kind ? entry.path : entry.name ),
			el( 'span', { class: 'smliser-repo-browser__meta' }, formatBytes( entry.size ) )
			);

			button.addEventListener( 'click', () => this.select( entry ) );

			return button;
		}

		/*
		|-----------
		| PREVIEW
		|-----------
		*/

		/**
		 * Select a file and show it in the file panel.
		 *
		 * The panel sits beside the list; on narrow screens it opens in a
		 * modal, so it is always in view and closed on purpose.
		 *
		 * @param {Object} entry
		 * @param {Object} [options={}]
		 * @param {boolean} [options.reveal=true] - On narrow screens, open the modal.
		 * @return {Promise<void>}
		 */
		async select( entry, { reveal = true } = {} ) {
			this.selected = entry;

			this.listing.querySelectorAll( '[aria-pressed]' ).forEach( ( button ) => {
				button.setAttribute( 'aria-pressed', button.dataset.path === entry.path ? 'true' : 'false' );
			} );

			if ( this.compact.matches ) {
				if ( reveal ) {
					const panel = this.renderPanel( entry, false );

					this.openModal( entry, panel.element );
					await this.renderPreviewBody( entry, panel.body );
				}

				return;
			}

			const panel = this.renderPanel( entry, true );

			this.preview.replaceChildren( ...panel.element.childNodes );
			this.preview.hidden = false;

			await this.renderPreviewBody( entry, panel.body );
		}

		/**
		 * Build the file panel's content.
		 *
		 * @param {Object} entry
		 * @param {boolean} withTitle - A modal carries the name in its own title.
		 * @return {{ element: HTMLElement, body: HTMLElement }}
		 */
		renderPanel( entry, withTitle ) {
			const body    = el( 'div', { class: 'smliser-repo-browser__preview-body' } );
			const details = el( 'dl', { class: 'smliser-repo-browser__details' } );

			const detail = ( term, value ) => {
				if ( value ) {
					details.append( el( 'dt', {}, term ), el( 'dd', {}, value ) );
				}
			};

			detail( 'Kind', entry.kind ? KIND_LABELS[ entry.kind ] : 'Not an app file' );
			detail( 'Asset type', this.assetTypeLabel( entry ) );
			detail( 'Size', formatBytes( entry.size ) );
			detail( 'Changed', formatDate( entry.mtime ) );
			detail( 'Path', entry.path );

			const element = el( 'div', { class: 'smliser-repo-browser__panel' },
				withTitle ? el( 'h3', { class: 'smliser-repo-browser__preview-title' }, entry.name ) : null,
				body,
				details,
				this.renderFileActions( entry )
			);

			return { element, body };
		}

		/**
		 * Show the panel with nothing selected.
		 */
		showEmptyPanel() {
			this.selected = null;

			this.preview.replaceChildren( el( 'div', { class: 'smliser-repo-browser__panel-empty' },
				el( 'i', { class: 'ti ti-hand-click', 'aria-hidden': 'true' } ),
				el( 'p', {}, 'No file selected' ),
				el( 'span', { class: 'smliser-repo-browser__meta' }, 'Select a file to preview it, see its details and manage it.' )
			) );
			this.preview.hidden = false;
		}

		/**
		 * Open the file panel in a modal.
		 *
		 * @param {Object} entry
		 * @param {HTMLElement} content
		 */
		openModal( entry, content ) {
			this.closeModal();

			const modal = new SmliserModal( {
				title: entry.name,
				body: content,
				width: '95%',
				maxWidth: '520px',
				customClass: 'smliser-repo-browser__modal',
			} );

			modal.on( 'afterClose', () => {
				modal.destroy();

				if ( this.modal === modal ) {
					this.modal = null;
				}
			} );

			this.modal = modal;
			modal.open();
		}

		/**
		 * Close the file panel's modal, if open.
		 *
		 * @return {Promise<void>}
		 */
		async closeModal() {
			const modal = this.modal;

			this.modal = null;

			if ( modal ) {
				await modal.close();
			}
		}

		/**
		 * Move the panel between the side and the modal when the screen width changes.
		 */
		onLayoutChange() {
			if ( 'app' !== this.data?.level ) {
				return;
			}

			if ( this.compact.matches ) {
				return; // The side panel is hidden by CSS; the modal opens on the next selection.
			}

			this.closeModal();
			this.selected ? this.select( this.selected, { reveal: false } ) : this.showEmptyPanel();
		}

		/**
		 * Fill the preview with the file itself, where it can be shown.
		 *
		 * @param {Object} entry
		 * @param {HTMLElement} body
		 * @return {Promise<void>}
		 */
		async renderPreviewBody( entry, body ) {
			if ( ! entry.file_url ) {
				body.append( this.message( 'This file is not one of the app\'s files, so it cannot be opened here.', 'ti ti-lock' ) );
				return;
			}

			if ( 'asset' === entry.kind ) {
				const zoom = el( 'button', { type: 'button', class: 'smliser-repo-browser__preview-zoom', 'aria-label': `View ${ entry.name } full size` },
					el( 'img', { class: 'smliser-repo-browser__preview-image', src: entry.file_url, alt: '' } )
				);

				zoom.addEventListener( 'click', () => viewImage( entry ) );
				body.append( zoom );
				return;
			}

			if ( 'document' !== entry.kind && 'manifest' !== entry.kind ) {
				body.append( el( 'div', { class: 'smliser-repo-browser__preview-icon' },
					el( 'i', { class: fileIcon( entry.name ), 'aria-hidden': 'true' } )
				) );
				return;
			}

			if ( entry.size > TEXT_PREVIEW_LIMIT ) {
				body.append( this.message( 'This file is too large to preview. Download it instead.', 'ti ti-file-alert' ) );
				return;
			}

			const pre = el( 'pre', { class: 'smliser-repo-browser__preview-text' }, 'Loading…' );

			body.append( pre );

			try {
				const text = await smliserFetchText( entry.file_url, { credentials: 'same-origin' } );

				pre.textContent = 'manifest' === entry.kind ? prettyJson( text ) : text;
			} catch ( error ) {
				pre.replaceWith( this.message( error.message, 'ti ti-alert-triangle', true ) );
			}
		}

		/**
		 * Render the buttons for what may be done with a file.
		 *
		 * @param {Object} entry
		 * @return {HTMLElement}
		 */
		renderFileActions( entry ) {
			const actions = entry.actions ?? [];
			const bar     = el( 'div', { class: 'smliser-repo-browser__file-actions' } );

			// Images are viewed on the page; other files are served as downloads, so they are offered as one.
			if ( actions.includes( 'view' ) && 'asset' === entry.kind ) {
				bar.append( this.button( 'View', 'ti ti-maximize', () => viewImage( entry ) ) );
			}

			if ( actions.includes( 'download' ) || ( actions.includes( 'view' ) && 'asset' !== entry.kind ) ) {
				bar.append( this.button( 'Download', 'ti ti-download', () => this.download( entry ) ) );
			}

			if ( actions.includes( 'replace' ) ) {
				bar.append( this.button( 'Replace', 'ti ti-replace', () => {
					'asset' === entry.kind ? this.replaceAsset( entry ) : this.replaceArtifact( entry );
				} ) );
			}

			if ( actions.includes( 'rename' ) ) {
				bar.append( this.button( 'Rename', 'ti ti-cursor-text', () => this.renameArtifact( entry ) ) );
			}

			if ( actions.includes( 'delete' ) ) {
				bar.append( this.button( 'Delete', 'ti ti-trash', () => this.deleteFile( entry ), 'smliser-btn-danger' ) );
			}

			if ( ! bar.children.length ) {
				bar.append( el( 'p', { class: 'smliser-repo-browser__note' }, 'You can view this file but not change it.' ) );
			}

			return bar;
		}

		/**
		 * Hide the preview pane.
		 */
		hidePreview() {
			this.closeModal();
			this.preview.hidden = true;
			this.preview.replaceChildren();
		}

		/*
		|-----------
		| ACTIONS
		|-----------
		*/

		/**
		 * Download a file.
		 *
		 * @param {Object} entry
		 * @return {Promise<void>}
		 */
		async download( entry ) {
			try {
				await smliserDownloadUrl( entry.file_url, { credentials: 'same-origin' } );
			} catch ( error ) {
				SmliserToast.error( `${ entry.name } could not be downloaded. ${ error.message }` );
			}
		}

		/**
		 * Upload new assets of one asset type.
		 *
		 * @return {Promise<void>}
		 */
		async uploadAssets() {
			const types = Object.entries( this.data.asset_types ?? {} );

			if ( ! types.length ) {
				return;
			}

			const assetType = 1 === types.length ? types[ 0 ][ 0 ] : await SmliserModal.choice( {
				title: 'Upload assets',
				message: 'What are you uploading?',
				buttons: [
					...types.map( ( [ value, text ], index ) => ( { text, value, primary: 0 === index } ) ),
					{ text: 'Cancel', value: '', class: 'smliser-btn-secondary' },
				],
			} );

			if ( ! assetType ) {
				return;
			}

			const files = await pickFiles( { accept: 'image/*', multiple: true } );

			if ( ! files.length ) {
				return;
			}

			const body = this.appPayload();

			body.set( 'asset_type', assetType );
			files.forEach( ( file ) => body.append( 'asset_file[]', file ) );

			await this.send( 'upload-app-assets', 'POST', body, ( response ) => {
				const uploaded = Object.keys( response.result?.uploaded ?? {} );
				const failed   = Object.entries( response.result?.failed ?? {} );

				if ( uploaded.length ) {
					SmliserToast.success( 1 === uploaded.length ? `Uploaded ${ uploaded[ 0 ]}.` : `Uploaded ${ uploaded.length } assets.` );
				}

				if ( failed.length ) {
					showError( failed.map( ( [ name, reasons ] ) => `${ name }: ${ flattenMessages( reasons ) }` ), 'Some assets were not uploaded' );
				}
			} );
		}

		/**
		 * Replace an asset with a new image, keeping its name.
		 *
		 * @param {Object} entry
		 * @return {Promise<void>}
		 */
		async replaceAsset( entry ) {
			const [ file ] = await pickFiles( { accept: 'image/*' } );

			if ( ! file ) {
				return;
			}

			const body = this.appPayload();

			body.set( 'asset_type', entry.asset_type );
			body.set( 'asset_name', entry.name );
			body.append( 'asset_file[]', file );

			await this.send( 'upload-app-assets', 'PATCH', body, () => {
				SmliserToast.success( `Replaced ${ entry.name }.` );
			}, entry.path );
		}

		/**
		 * Upload a new artifact.
		 *
		 * @return {Promise<void>}
		 */
		async uploadArtifact() {
			const [ file ] = await pickFiles();

			if ( ! file ) {
				return;
			}

			const name = await SmliserModal.prompt( {
				title: 'Upload artifact',
				message: 'Name the artifact as it will be downloaded.',
				defaultValue: file.name,
				confirmText: 'Upload artifact',
				required: true,
			} );

			if ( ! name ) {
				return;
			}

			const body = this.appPayload();

			body.set( 'canonical_filename', name.trim() );
			body.set( 'artifact_file', file );

			await this.send( 'app-artifacts', 'POST', body, ( response ) => {
				SmliserToast.success( `Uploaded ${ response.data?.filename ?? name }.` );
			}, `artifacts/${ name.trim() }` );
		}

		/**
		 * Replace an artifact's file, keeping its name.
		 *
		 * @param {Object} entry
		 * @return {Promise<void>}
		 */
		async replaceArtifact( entry ) {
			const [ file ] = await pickFiles();

			if ( ! file ) {
				return;
			}

			const body = this.appPayload();

			body.set( 'artifact_filename', entry.name );
			body.set( 'canonical_filename', entry.name );
			body.set( 'artifact_file', file );

			await this.send( 'app-artifacts', 'PATCH', body, () => {
				SmliserToast.success( `Replaced ${ entry.name }.` );
			}, entry.path );
		}

		/**
		 * Rename an artifact.
		 *
		 * @param {Object} entry
		 * @return {Promise<void>}
		 */
		async renameArtifact( entry ) {
			const name = await SmliserModal.prompt( {
				title: 'Rename artifact',
				message: `New name for ${ entry.name }`,
				defaultValue: entry.name,
				confirmText: 'Rename artifact',
				required: true,
			} );

			if ( ! name || name.trim() === entry.name ) {
				return;
			}

			const body = this.appPayload();

			body.set( 'artifact_filename', entry.name );
			body.set( 'canonical_filename', name.trim() );

			await this.send( 'app-artifacts', 'PATCH', body, ( response ) => {
				SmliserToast.success( `Renamed to ${ response.data?.filename ?? name }.` );
			}, `artifacts/${ name.trim() }` );
		}

		/**
		 * Delete an asset or an artifact.
		 *
		 * @param {Object} entry
		 * @return {Promise<void>}
		 */
		async deleteFile( entry ) {
			const confirmed = await SmliserModal.confirm( {
				title: `Delete ${ KIND_LABELS[ entry.kind ].toLowerCase() }`,
				message: `Delete <strong>${ StringUtils.escHtml( entry.name ) }</strong>? This cannot be undone.`,
				confirmText: 'Delete',
				danger: true,
			} );

			if ( ! confirmed ) {
				return;
			}

			if ( 'asset' === entry.kind ) {
				const body = this.appPayload();

				body.set( 'asset_name', entry.name );

				await this.send( 'app-asset', 'DELETE', body, () => SmliserToast.success( `Deleted ${ entry.name }.` ) );
				return;
			}

			// The artifact endpoint reads a JSON body on DELETE, as the edit page sends it.
			await this.send( 'app-artifact', 'DELETE', JSON.stringify( {
				app_type: this.type,
				app_slug: this.slug,
				artifact_filename: entry.name,
			} ), () => SmliserToast.success( `Deleted ${ entry.name }.` ), null, { 'Content-Type': 'application/json' } );
		}

		/**
		 * Give the app a new slug.
		 *
		 * @return {Promise<void>}
		 */
		async renameApp() {
			const slug = await SmliserModal.prompt( {
				title: 'Change slug',
				message: `The slug is the app's public name in download and update URLs. Licenses, tokens, analytics and messages move with it, but copies already installed elsewhere keep asking for "${ this.slug }" and stop receiving updates.`,
				defaultValue: this.slug,
				confirmText: 'Change slug',
				required: true,
			} );

			if ( ! slug || slug.trim() === this.slug ) {
				return;
			}

			const newSlug = slug.trim();
			const body    = this.appPayload();

			body.set( 'new_slug', newSlug );

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( 'repository-browser/rename-app' ), {
					method: 'PATCH',
					body,
					credentials: 'same-origin',
				} );

				SmliserToast.success( response.data?.message ?? `Slug changed to ${ newSlug }.` );
				this.open( this.type, response.data?.slug ?? newSlug );
			} catch ( error ) {
				showError( error.message, 'The slug was not changed' );
			}
		}

		/*
		|-----------
		| HELPERS
		|-----------
		*/

		/**
		 * Send a change and reload the listing.
		 *
		 * @param {string} route - admin/json route.
		 * @param {string} method
		 * @param {FormData|string} body
		 * @param {( response: Object ) => void} onSuccess
		 * @param {string|null} [select=null] - Path to select after reloading; defaults to the current selection.
		 * @param {Object<string, string>} [headers={}]
		 * @return {Promise<void>}
		 */
		async send( route, method, body, onSuccess, select = null, headers = {} ) {
			const spinner = showSpinner( this.listing, true );

			this.root.setAttribute( 'aria-busy', 'true' );

			try {
				const response = await smliserFetchJSON( smliserAjaxUrl( route ), {
					method,
					body,
					headers,
					credentials: 'same-origin',
				} );

				if ( false === response?.success ) {
					throw new Error( response?.data?.message ?? 'The request was refused.' );
				}

				await this.closeModal();
				onSuccess( response );
				await this.open( this.type, this.slug, { push: false, select } );
			} catch ( error ) {
				showError( error.message );
			} finally {
				removeSpinner( spinner );
				this.root.removeAttribute( 'aria-busy' );
			}
		}

		/**
		 * Start a request body naming the open app.
		 *
		 * @return {FormData}
		 */
		appPayload() {
			const body = new FormData();

			body.set( 'app_type', this.type );
			body.set( 'app_slug', this.slug );

			return body;
		}

		/**
		 * Get the label of an asset's type.
		 *
		 * @param {Object} entry
		 * @return {string}
		 */
		assetTypeLabel( entry ) {
			return entry.asset_type ? ( this.data?.asset_types?.[ entry.asset_type ] ?? entry.asset_type ) : '';
		}

		/**
		 * Build a toolbar button.
		 *
		 * @param {string} label
		 * @param {string} icon
		 * @param {() => void} onClick
		 * @param {string} [variant='smliser-btn-glass']
		 * @return {HTMLButtonElement}
		 */
		button( label, icon, onClick, variant = 'smliser-btn-glass' ) {
			const button = el( 'button', { type: 'button', class: `smliser-btn ${ variant }` },
				el( 'i', { class: icon, 'aria-hidden': 'true' } ), label
			);

			button.addEventListener( 'click', onClick );

			return button;
		}

		/**
		 * Build a status message.
		 *
		 * @param {string} text
		 * @param {string} icon
		 * @param {boolean} [isError=false]
		 * @return {HTMLElement}
		 */
		message( text, icon, isError = false ) {
			return el( 'p', { class: `smliser-repo-browser__message${ isError ? ' is-error' : '' }`, role: isError ? 'alert' : 'status' },
				el( 'i', { class: icon, 'aria-hidden': 'true' } ), text
			);
		}
	}

	/*
	|-------------
	| UTILITIES
	|-------------
	*/

	/**
	 * Create an element. Text children are set as text, never parsed as HTML.
	 *
	 * @param {string} tag
	 * @param {Object<string, string>} [attributes={}]
	 * @param {...(Node|string)} children
	 * @return {HTMLElement}
	 */
	function el( tag, attributes = {}, ...children ) {
		const element = document.createElement( tag );

		for ( const [ name, value ] of Object.entries( attributes ) ) {
			element.setAttribute( name, value );
		}

		element.append( ...children.filter( ( child ) => null !== child && undefined !== child && '' !== child ) );

		return element;
	}

	/**
	 * Show an error in a modal.
	 *
	 * SmliserModal renders the message as HTML, and messages can carry
	 * names an admin typed, so every line is escaped first.
	 *
	 * @param {string|string[]} lines
	 * @param {string} [title='Error']
	 * @return {Promise<void>}
	 */
	function showError( lines, title = 'Error' ) {
		const html = ( Array.isArray( lines ) ? lines : [ lines ] )
			.map( ( line ) => StringUtils.escHtml( String( line ) ) )
			.join( '<br>' );

		return SmliserModal.error( html, title );
	}

	/**
	 * Show an image at full size over the page, in full screen where the browser allows it.
	 *
	 * The overlay works on its own, so browsers without element full screen
	 * (iPhone Safari) still get the full-size view. Closed by its button,
	 * Escape, a click outside the image, or leaving full screen.
	 *
	 * @param {Object} entry
	 * @return {Promise<void>}
	 */
	async function viewImage( entry ) {
		const opener = document.activeElement;
		const close  = el( 'button', { type: 'button', class: 'smliser-repo-browser__viewer-close', 'aria-label': 'Close' },
			el( 'i', { class: 'ti ti-x', 'aria-hidden': 'true' } )
		);
		const viewer = el( 'div', { class: 'smliser-repo-browser__viewer', role: 'dialog', 'aria-modal': 'true', 'aria-label': entry.name },
			close,
			el( 'img', { src: entry.file_url, alt: entry.name } ),
			el( 'p', { class: 'smliser-repo-browser__viewer-name' }, entry.name )
		);

		const dismiss = () => {
			window.removeEventListener( 'keydown', onKey, true );
			document.removeEventListener( 'fullscreenchange', onFullscreen );

			if ( document.fullscreenElement === viewer ) {
				document.exitFullscreen().catch( () => {} );
			}

			viewer.remove();
			opener?.focus?.();
		};

		// Captured first and stopped, so Escape closes only the viewer, not a SmliserModal under it.
		const onKey = ( event ) => {
			if ( 'Escape' === event.key ) {
				event.preventDefault();
				event.stopImmediatePropagation();
				dismiss();
			}
		};
		const onFullscreen = () => ! document.fullscreenElement && viewer.isConnected && dismiss();

		close.addEventListener( 'click', dismiss );
		viewer.addEventListener( 'click', ( event ) => event.target === viewer && dismiss() );
		window.addEventListener( 'keydown', onKey, true );

		document.body.append( viewer );
		close.focus();

		if ( viewer.requestFullscreen && document.fullscreenEnabled ) {
			try {
				await viewer.requestFullscreen();
				document.addEventListener( 'fullscreenchange', onFullscreen );
			} catch ( error ) {
				// Refused: the overlay is the view.
			}
		}
	}

	/**
	 * Get the Tabler icon for a file name.
	 *
	 * @param {string} name
	 * @return {string} Icon classes, e.g. "ti ti-file-zip".
	 */
	function fileIcon( name ) {
		const dot       = name.lastIndexOf( '.' );
		const extension = dot > 0 ? name.slice( dot + 1 ).toLowerCase() : '';

		return `ti ${ FILE_ICONS[ extension ] || 'ti-file' }`;
	}

	/**
	 * Ask the user for files.
	 *
	 * @param {Object} [options={}]
	 * @param {string} [options.accept='']
	 * @param {boolean} [options.multiple=false]
	 * @return {Promise<File[]>} The chosen files; empty when cancelled.
	 */
	function pickFiles( { accept = '', multiple = false } = {} ) {
		return new Promise( ( resolve ) => {
			const input = el( 'input', { type: 'file', hidden: '' } );

			input.accept   = accept;
			input.multiple = multiple;

			input.addEventListener( 'change', () => {
				resolve( Array.from( input.files ?? [] ) );
				input.remove();
			} );

			input.addEventListener( 'cancel', () => {
				resolve( [] );
				input.remove();
			} );

			document.body.append( input );
			input.click();
		} );
	}

	/**
	 * Format a size in bytes.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatBytes( bytes ) {
		return StringUtils.formatBytes( bytes, 1 );
	}

	/**
	 * Format a Unix time with the app's date and time format.
	 *
	 * @param {number} seconds
	 * @return {string}
	 */
	function formatDate( seconds ) {
		return seconds ? StringUtils.formatDate( seconds, smliser_var?.datetime_format || 'Y-m-d g:i a' ) : '';
	}

	/**
	 * Indent JSON for reading, leaving anything else as it is.
	 *
	 * @param {string} text
	 * @return {string}
	 */
	function prettyJson( text ) {
		try {
			return JSON.stringify( JSON.parse( text ), null, 2 );
		} catch {
			return text;
		}
	}

	/**
	 * Join the messages of a failed upload result.
	 *
	 * @param {*} reasons
	 * @return {string}
	 */
	function flattenMessages( reasons ) {
		if ( 'string' === typeof reasons ) {
			return reasons;
		}

		return Object.values( reasons ?? {} ).map( flattenMessages ).join( ' ' );
	}
} )();