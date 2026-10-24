/**
 * File uploader.
 *
 * Turns a native file input into a drop zone with a list of the chosen
 * files, the same in every browser. Only inputs that ask for it are
 * changed, through a markup contract any page or theme can follow:
 *
 *   input[type="file"][data-smliser-uploader]  The input to enhance. It stays in the
 *                                              form (visually hidden), so FormData,
 *                                              `required` and form reset work as usual.
 *     accept="…"                               Types accepted: MIME types (image/png),
 *                                              wildcards (image/*) or extensions (.pdf).
 *     multiple                                 Several files may be chosen.
 *     data-max-size="5242880"                  Largest file accepted, in bytes.
 *     data-label="…"                           The drop zone's prompt.
 *
 * Files that do not match `accept` or `data-max-size` are refused in the
 * browser with a message; the server still checks every upload.
 *
 * Inputs added to the page later are enhanced with
 * SmliserUploader.init( container ).
 *
 * Styles: smliser-uploader.css, themed through the --smliser-uploader-* tokens.
 */
( function () {
	'use strict';

	const SELECTOR = 'input[type="file"][data-smliser-uploader]';

	/*
	|-----------
	|Formatting
	|-----------
	*/

	/**
	 * A byte count as people read it.
	 *
	 * @param {number} bytes
	 * @return {string}
	 */
	function formatSize( bytes ) {
		if ( bytes < 1024 ) {
			return `${ bytes } B`;
		}

		const units = [ 'KB', 'MB', 'GB' ];
		let size    = bytes / 1024;
		let unit    = 0;

		while ( size >= 1024 && unit < units.length - 1 ) {
			size /= 1024;
			unit++;
		}

		// Number() drops a trailing ".0": 5 MB, not 5.0 MB.
		return `${ Number( size.toFixed( size < 10 ? 1 : 0 ) ) } ${ units[ unit ] }`;
	}

	/**
	 * The accept attribute as people read it: ".pdf, image/png" → "PDF, PNG".
	 *
	 * @param {string} accept
	 * @return {string}
	 */
	function formatAccept( accept ) {
		return accept.split( ',' )
			.map( ( type ) => type.trim() )
			.filter( Boolean )
			.map( ( type ) => {
				if ( type.startsWith( '.' ) ) {
					return type.slice( 1 ).toUpperCase();
				}

				const [ group, sub ] = type.split( '/' );

				return '*' === sub ? `${ group } files` : sub.replace( /^x-/, '' ).toUpperCase();
			} )
			.filter( ( label, index, all ) => all.indexOf( label ) === index )
			.join( ', ' );
	}

	/*
	|-----------
	|Validation
	|-----------
	*/

	/**
	 * Whether a file matches the input's accept attribute.
	 *
	 * @param {File}   file
	 * @param {string} accept
	 * @return {boolean}
	 */
	function isAccepted( file, accept ) {
		const rules = accept.split( ',' ).map( ( rule ) => rule.trim().toLowerCase() ).filter( Boolean );

		if ( ! rules.length ) {
			return true;
		}

		const name = file.name.toLowerCase();
		const type = ( file.type || '' ).toLowerCase();

		return rules.some( ( rule ) => {
			if ( rule.startsWith( '.' ) ) {
				return name.endsWith( rule );
			}

			if ( rule.endsWith( '/*' ) ) {
				return type.startsWith( rule.slice( 0, -1 ) );
			}

			return type === rule;
		} );
	}

	/*
	|---------
	|Uploader
	|---------
	*/

	/**
	 * One enhanced input.
	 */
	class Uploader {
		/**
		 * @param {HTMLInputElement} input
		 */
		constructor( input ) {
			this.input    = input;
			this.accept   = input.getAttribute( 'accept' ) || '';
			this.maxSize  = parseInt( input.dataset.maxSize || '0', 10 ) || 0;
			this.multiple = input.multiple;
			this.files    = [];
			this.previews = [];

			this.build();
			this.bind();
		}

		/**
		 * Create the drop zone, the file list and the message line around the input.
		 */
		build() {
			const hints = [];

			if ( this.accept ) {
				hints.push( formatAccept( this.accept ) );
			}

			if ( this.maxSize ) {
				hints.push( `up to ${ formatSize( this.maxSize ) }${ this.multiple ? ' each' : '' }` );
			}

			this.root = document.createElement( 'div' );
			this.root.className = 'smliser-uploader';

			this.zone = document.createElement( 'div' );
			this.zone.className = 'smliser-uploader__zone';
			this.zone.tabIndex  = 0;
			this.zone.setAttribute( 'role', 'button' );
			this.zone.setAttribute( 'aria-label', this.input.dataset.label || ( this.multiple ? 'Choose files' : 'Choose a file' ) );

			const prompt = document.createElement( 'span' );
			prompt.className   = 'smliser-uploader__prompt';
			prompt.textContent = this.input.dataset.label || ( this.multiple ? 'Drop files here or click to choose' : 'Drop a file here or click to choose' );
			this.zone.appendChild( prompt );

			if ( hints.length ) {
				const hint = document.createElement( 'span' );
				hint.className   = 'smliser-uploader__hint';
				hint.textContent = hints.join( ', ' );
				this.zone.appendChild( hint );
			}

			this.list = document.createElement( 'ul' );
			this.list.className = 'smliser-uploader__files';
			this.list.hidden    = true;

			this.message = document.createElement( 'p' );
			this.message.className = 'smliser-uploader__message';
			this.message.setAttribute( 'role', 'alert' );
			this.message.hidden = true;

			this.input.classList.add( 'smliser-uploader__input' );
			this.input.parentNode.insertBefore( this.root, this.input );
			this.root.append( this.input, this.zone, this.list, this.message );
		}

		/**
		 * Listen to the zone, the input and the form.
		 */
		bind() {
			this.zone.addEventListener( 'click', () => this.input.click() );
			this.zone.addEventListener( 'keydown', ( event ) => {
				if ( 'Enter' === event.key || ' ' === event.key ) {
					event.preventDefault();
					this.input.click();
				}
			} );

			[ 'dragenter', 'dragover' ].forEach( ( type ) => {
				this.zone.addEventListener( type, ( event ) => {
					event.preventDefault();
					this.zone.classList.add( 'is-dragging' );
				} );
			} );

			[ 'dragleave', 'drop' ].forEach( ( type ) => {
				this.zone.addEventListener( type, () => this.zone.classList.remove( 'is-dragging' ) );
			} );

			this.zone.addEventListener( 'drop', ( event ) => {
				event.preventDefault();
				this.add( Array.from( event.dataTransfer?.files || [] ) );
			} );

			// Files chosen through the browser's own dialog.
			this.input.addEventListener( 'change', () => {
				if ( this.syncing ) {
					return;
				}

				this.add( Array.from( this.input.files || [] ), true );
			} );

			this.input.form?.addEventListener( 'reset', () => {
				// The form clears the input after this event.
				setTimeout( () => this.set( [] ), 0 );
			} );

			// A required input is hidden, so point the browser's complaint at the zone.
			this.input.addEventListener( 'invalid', () => {
				this.say( this.multiple ? 'Choose at least one file.' : 'Choose a file.' );
				this.zone.focus();
			} );
		}

		/**
		 * Take new files, refusing those that do not fit.
		 *
		 * @param {File[]}  incoming
		 * @param {boolean} fromDialog The dialog replaces the selection instead of adding to it.
		 */
		add( incoming, fromDialog = false ) {
			const accepted = [];
			const refused  = [];

			incoming.forEach( ( file ) => {
				if ( this.accept && ! isAccepted( file, this.accept ) ) {
					refused.push( `${ file.name } is not an accepted type (${ formatAccept( this.accept ) }).` );
				} else if ( this.maxSize && file.size > this.maxSize ) {
					refused.push( `${ file.name } is larger than ${ formatSize( this.maxSize ) }.` );
				} else {
					accepted.push( file );
				}
			} );

			let files;

			if ( this.multiple ) {
				files = fromDialog ? accepted : this.files.concat( accepted );
			} else {
				// One file: a refused choice keeps the earlier one.
				files = accepted.length ? [ accepted[ accepted.length - 1 ] ] : this.files;
			}

			this.set( files );
			this.say( refused.join( ' ' ) );
		}

		/**
		 * Make these the chosen files: in the input, for the form, and in the list.
		 *
		 * @param {File[]} files
		 */
		set( files ) {
			this.files = files;

			const transfer = new DataTransfer();
			files.forEach( ( file ) => transfer.items.add( file ) );

			this.syncing     = true;
			this.input.files = transfer.files;
			this.syncing     = false;

			this.input.dispatchEvent( new Event( 'smliser:files', { bubbles: true } ) );
			this.render();
		}

		/**
		 * Remove one chosen file.
		 *
		 * @param {number} index
		 */
		remove( index ) {
			this.set( this.files.filter( ( _, i ) => i !== index ) );
			this.say( '' );
			this.zone.focus();
		}

		/**
		 * Draw the list of chosen files.
		 */
		render() {
			this.previews.forEach( ( url ) => URL.revokeObjectURL( url ) );
			this.previews = [];
			this.list.replaceChildren();
			this.list.hidden = ! this.files.length;
			this.root.classList.toggle( 'has-files', this.files.length > 0 );

			this.files.forEach( ( file, index ) => {
				const item = document.createElement( 'li' );
				item.className = 'smliser-uploader__file';

				if ( file.type.startsWith( 'image/' ) ) {
					const url = URL.createObjectURL( file );
					const img = document.createElement( 'img' );

					this.previews.push( url );
					img.className = 'smliser-uploader__thumb';
					img.src       = url;
					img.alt       = '';
					item.appendChild( img );
				} else {
					const badge = document.createElement( 'span' );
					badge.className   = 'smliser-uploader__thumb smliser-uploader__thumb--file';
					badge.textContent = ( file.name.split( '.' ).pop() || 'file' ).slice( 0, 4 ).toUpperCase();
					badge.setAttribute( 'aria-hidden', 'true' );
					item.appendChild( badge );
				}

				const meta = document.createElement( 'span' );
				meta.className = 'smliser-uploader__meta';

				const name = document.createElement( 'span' );
				name.className   = 'smliser-uploader__name';
				name.textContent = file.name;

				const size = document.createElement( 'span' );
				size.className   = 'smliser-uploader__size';
				size.textContent = formatSize( file.size );

				meta.append( name, size );

				const remove = document.createElement( 'button' );
				remove.type        = 'button';
				remove.className   = 'smliser-uploader__remove';
				remove.textContent = 'Remove';
				remove.setAttribute( 'aria-label', `Remove ${ file.name }` );
				remove.addEventListener( 'click', () => this.remove( index ) );

				item.append( meta, remove );
				this.list.appendChild( item );
			} );
		}

		/**
		 * Show a message under the zone; an empty one hides it.
		 *
		 * @param {string} text
		 */
		say( text ) {
			this.message.textContent = text;
			this.message.hidden      = '' === text;
		}
	}

	/*
	|-------
	|Public
	|-------
	*/

	/**
	 * Enhance every opted-in file input inside a container, once.
	 *
	 * @param {ParentNode} [container=document]
	 */
	function init( container = document ) {
		container.querySelectorAll( SELECTOR ).forEach( ( input ) => {
			if ( ! input.smliserUploader ) {
				input.smliserUploader = new Uploader( input );
			}
		} );
	}

	window.SmliserUploader = { init };

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => init() );
	} else {
		init();
	}
}() );