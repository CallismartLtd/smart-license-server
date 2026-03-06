/**
 * Email Template Editor
 *
 * Manages the full-page visual editor for a single email template type.
 *
 * Architecture:
 *   EmailEditor     — root controller, owns state, coordinates modules
 *   BlockCanvas     — renders and manages the block list
 *   BlockSorter     — drag and drop reordering via HTML5 Drag API
 *   BlockEditor     — sidebar panel for editing a selected block
 *   StylePanel      — sidebar panel for editing global style tokens
 *   PreviewPane     — iframe live preview, debounced refresh on state change
 *   Toolbar         — save, reset, enable/disable button handlers
 *
 * Data flow:
 *   All state lives in EmailEditor.$state (blocks + styles).
 *   Every module receives a reference to EmailEditor and calls
 *   editor.markDirty() after mutations to trigger a preview refresh
 *   and show the unsaved changes badge.
 *
 * @package SmartLicenseServer
 * @since   0.2.0
 */

( function() {

    'use strict';

    // =========================================================================
    // EmailEditor — root controller
    // =========================================================================

    class EmailEditor {

        constructor( config ) {
            this.config = config;

            /**
             * Mutable editor state.
             * Both arrays are deep-cloned from config so the originals
             * remain untouched for reset comparison.
             */
            this.$state = {
                blocks: JSON.parse( JSON.stringify( config.blocks ) ),
                styles: JSON.parse( JSON.stringify( config.styles ) ),
            };

            this.dirty       = false;
            this.activeBlock = null;

            // Module instances — initialised in init()
            this.stylePanel  = null;
            this.blockCanvas = null;
            this.blockEditor = null;
            this.previewPane = null;
            this.toolbar     = null;
        }

        /**
         * Initialise all modules and render the initial state.
         */
        init() {
            this.stylePanel  = new StylePanel( this );
            this.blockCanvas = new BlockCanvas( this );
            this.blockEditor = new BlockEditor( this );
            this.previewPane = new PreviewPane( this );
            this.toolbar     = new Toolbar( this );

            this.stylePanel.init();
            this.blockCanvas.init();
            this.blockEditor.init();
            this.previewPane.init();
            this.toolbar.init();

            // Warn on navigation away with unsaved changes.
            window.addEventListener( 'beforeunload', ( e ) => {
                if ( this.dirty ) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            } );
        }

        /**
         * Mark state as dirty — show unsaved badge and schedule preview refresh.
         */
        markDirty() {
            this.dirty = true;
            document.getElementById( 'smliser-unsaved-badge' ).style.display = '';
            this.previewPane.scheduleRefresh();
        }

        /**
         * Clear dirty state — called after a successful save.
         */
        clearDirty() {
            this.dirty = false;
            document.getElementById( 'smliser-unsaved-badge' ).style.display = 'none';
        }

        /**
         * Set the active block by ID and open the block editor panel.
         *
         * @param {string|null} blockId
         */
        setActiveBlock( blockId ) {
            this.activeBlock = blockId;
            this.blockCanvas.highlightActive( blockId );
            this.blockEditor.open( blockId );

            // Switch sidebar to block tab.
            if ( blockId ) {
                this.switchSidebarTab( 'block' );
            }
        }

        /**
         * Switch the sidebar to a named tab.
         *
         * @param {string} tab — 'styles' | 'block'
         */
        switchSidebarTab( tab ) {
            document.querySelectorAll( '.smliser-sidebar-tab' ).forEach( btn => {
                btn.classList.toggle( 'is-active', btn.dataset.tab === tab );
            } );
            document.querySelectorAll( '.smliser-sidebar-panel' ).forEach( panel => {
                panel.classList.toggle( 'is-active', panel.dataset.panel === tab );
            } );
        }

        /**
         * Get a block from state by ID.
         *
         * @param  {string} id
         * @return {object|null}
         */
        getBlock( id ) {
            return this.$state.blocks.find( b => b.id === id ) || null;
        }

        /**
         * Update a block in state by ID, then mark dirty.
         *
         * @param {string} id
         * @param {object} changes — partial block properties to merge
         */
        updateBlock( id, changes ) {
            const index = this.$state.blocks.findIndex( b => b.id === id );

            if ( index === -1 ) {
                return;
            }

            this.$state.blocks[ index ] = Object.assign(
                {},
                this.$state.blocks[ index ],
                changes
            );

            this.blockCanvas.renderItem( id );
            this.markDirty();
        }

        /**
         * Update a style token, then mark dirty.
         *
         * @param {string} token — e.g. 'header_bg'
         * @param {string} value — hex color string
         */
        updateStyle( token, value ) {
            this.$state.styles[ token ] = value;
            this.markDirty();
        }

        /**
         * Toggle a block's visible state, then mark dirty.
         *
         * @param {string} id
         */
        toggleBlockVisibility( id ) {
            const block = this.getBlock( id );

            if ( ! block ) {
                return;
            }

            this.updateBlock( id, { visible: block.visible === false ? true : false } );
        }

        /**
         * Reorder blocks array after a drag-drop operation.
         *
         * @param {number} fromIndex
         * @param {number} toIndex
         */
        reorderBlocks( fromIndex, toIndex ) {
            if ( fromIndex === toIndex ) {
                return;
            }

            const blocks  = this.$state.blocks;
            const [ item ] = blocks.splice( fromIndex, 1 );
            blocks.splice( toIndex, 0, item );

            this.blockCanvas.render();
            this.markDirty();
        }
    }

    // =========================================================================
    // StylePanel — sidebar styles tab
    // =========================================================================

    class StylePanel {

        constructor( editor ) {
            this.editor = editor;
        }

        init() {
            this._populateInputs();
            this._bindEvents();
            this._bindTabSwitch();
        }

        /**
         * Populate all color pickers and hex inputs from current state.
         */
        _populateInputs() {
            const styles = this.editor.$state.styles;

            document.querySelectorAll( '.smliser-color-picker' ).forEach( picker => {
                const token = picker.dataset.style;
                if ( styles[ token ] ) {
                    picker.value = styles[ token ];
                }
            } );

            document.querySelectorAll( '.smliser-color-hex' ).forEach( input => {
                const token = input.dataset.style;
                if ( styles[ token ] ) {
                    input.value = styles[ token ];
                }
            } );
        }

        _bindEvents() {
            // Color picker → update state + sync hex input.
            document.querySelectorAll( '.smliser-color-picker' ).forEach( picker => {
                picker.addEventListener( 'input', ( e ) => {
                    const token = e.target.dataset.style;
                    const value = e.target.value;

                    this._syncHexInput( token, value );
                    this.editor.updateStyle( token, value );
                } );
            } );

            // Hex input → validate + update state + sync color picker.
            document.querySelectorAll( '.smliser-color-hex' ).forEach( input => {
                input.addEventListener( 'input', ( e ) => {
                    const token = e.target.dataset.style;
                    const value = e.target.value.trim();

                    if ( /^#[0-9a-fA-F]{6}$/.test( value ) ) {
                        this._syncColorPicker( token, value );
                        this.editor.updateStyle( token, value );
                    }
                } );
            } );
        }

        _bindTabSwitch() {
            document.querySelectorAll( '.smliser-sidebar-tab' ).forEach( btn => {
                btn.addEventListener( 'click', () => {
                    this.editor.switchSidebarTab( btn.dataset.tab );
                } );
            } );
        }

        _syncHexInput( token, value ) {
            const input = document.querySelector(
                `.smliser-color-hex[data-style="${ token }"]`
            );
            if ( input ) input.value = value;
        }

        _syncColorPicker( token, value ) {
            const picker = document.querySelector(
                `.smliser-color-picker[data-style="${ token }"]`
            );
            if ( picker ) picker.value = value;
        }
    }

    // =========================================================================
    // BlockCanvas — block list rendering and item interaction
    // =========================================================================

    class BlockCanvas {

        constructor( editor ) {
            this.editor  = editor;
            this.listEl  = document.getElementById( 'smliser-block-list' );
            this.sorter  = new BlockSorter( editor, this.listEl );
        }

        init() {
            this.render();
            this.sorter.init();
        }

        /**
         * Re-render the entire block list from current state.
         */
        render() {
            this.listEl.innerHTML = '';

            this.editor.$state.blocks.forEach( ( block, index ) => {
                this.listEl.appendChild( this._buildItem( block, index ) );
            } );
        }

        /**
         * Re-render a single block item in-place.
         *
         * @param {string} id
         */
        renderItem( id ) {
            const existing = this.listEl.querySelector(
                `[data-block-id="${ id }"]`
            );

            if ( ! existing ) {
                return;
            }

            const block = this.editor.getBlock( id );
            const index = this.editor.$state.blocks.findIndex( b => b.id === id );
            const fresh = this._buildItem( block, index );

            this.listEl.replaceChild( fresh, existing );

            if ( this.editor.activeBlock === id ) {
                fresh.classList.add( 'is-active' );
            }
        }

        /**
         * Add or remove the is-active class from block items.
         *
         * @param {string|null} activeId
         */
        highlightActive( activeId ) {
            this.listEl.querySelectorAll( '.smliser-block-item' ).forEach( item => {
                item.classList.toggle(
                    'is-active',
                    item.dataset.blockId === activeId
                );
            } );
        }

        /**
         * Build a single block list item element.
         *
         * @param  {object} block
         * @param  {number} index
         * @return {HTMLElement}
         */
        _buildItem( block, index ) {
            const isHidden = block.visible === false;
            const item     = document.createElement( 'div' );

            item.className   = 'smliser-block-item' + ( isHidden ? ' is-hidden' : '' );
            item.dataset.blockId    = block.id;
            item.dataset.blockIndex = index;
            item.draggable   = true;

            // Drag handle.
            const handle = document.createElement( 'span' );
            handle.className = 'smliser-block-drag-handle ti ti-grip-vertical';
            handle.title     = 'Drag to reorder';

            // Type badge.
            const type = document.createElement( 'span' );
            type.className   = 'smliser-block-item__type';
            type.textContent = block.type.replace( '_', ' ' );

            // Preview text.
            const preview = document.createElement( 'span' );
            preview.className   = 'smliser-block-item__preview';
            preview.textContent = this._getPreviewText( block );

            // Actions.
            const actions = document.createElement( 'div' );
            actions.className = 'smliser-block-item__actions';

            // Visibility toggle.
            const visBtn = document.createElement( 'button' );
            visBtn.type      = 'button';
            visBtn.className = 'smliser-block-action-btn is-visible-btn';
            visBtn.title     = isHidden ? 'Show block' : 'Hide block';
            visBtn.innerHTML = `<span class="ti ${ isHidden ? 'ti-eye' : 'ti-eye-off' }"></span>`;

            visBtn.addEventListener( 'click', ( e ) => {
                e.stopPropagation();
                this.editor.toggleBlockVisibility( block.id );
            } );

            actions.appendChild( visBtn );

            // Remove button — only for removable blocks.
            if ( block.removable !== false ) {
                const removeBtn = document.createElement( 'button' );
                removeBtn.type      = 'button';
                removeBtn.className = 'smliser-block-action-btn';
                removeBtn.title     = 'Remove block';
                removeBtn.innerHTML = '<span class="ti ti-x"></span>';

                removeBtn.addEventListener( 'click', ( e ) => {
                    e.stopPropagation();
                    this._removeBlock( block.id );
                } );

                actions.appendChild( removeBtn );
            }

            item.appendChild( handle );
            item.appendChild( type );
            item.appendChild( preview );
            item.appendChild( actions );

            // Click to select block.
            item.addEventListener( 'click', () => {
                this.editor.setActiveBlock( block.id );
            } );

            return item;
        }

        /**
         * Get a short preview string for a block to show in the list.
         *
         * @param  {object} block
         * @return {string}
         */
        _getPreviewText( block ) {
            switch ( block.type ) {
                case 'greeting':
                case 'text':
                case 'banner':
                case 'closing':
                    return ( block.content || '' ).substring( 0, 60 );

                case 'button':
                    return block.label || 'Button';

                case 'detail_card':
                    return block.rows
                        ? block.rows.map( r => r.label ).join( ', ' ).substring( 0, 60 )
                        : 'Detail card';

                default:
                    return block.id || block.type;
            }
        }

        /**
         * Remove a block from state and re-render.
         *
         * @param {string} id
         */
        _removeBlock( id ) {
            const index = this.editor.$state.blocks.findIndex( b => b.id === id );

            if ( index === -1 ) {
                return;
            }

            this.editor.$state.blocks.splice( index, 1 );

            if ( this.editor.activeBlock === id ) {
                this.editor.setActiveBlock( null );
            }

            this.render();
            this.editor.markDirty();
        }
    }

    // =========================================================================
    // BlockSorter — HTML5 drag-and-drop reordering
    // =========================================================================

    class BlockSorter {

        constructor( editor, listEl ) {
            this.editor   = editor;
            this.listEl   = listEl;
            this.dragFrom = null;
        }

        init() {
            this.listEl.addEventListener( 'dragstart', ( e ) => {
                const item = e.target.closest( '.smliser-block-item' );
                if ( ! item ) return;

                this.dragFrom = parseInt( item.dataset.blockIndex, 10 );
                item.classList.add( 'is-dragging' );
                this.listEl.classList.add( 'drag-active' );
                e.dataTransfer.effectAllowed = 'move';
            } );

            this.listEl.addEventListener( 'dragend', ( e ) => {
                const item = e.target.closest( '.smliser-block-item' );
                if ( item ) item.classList.remove( 'is-dragging' );
                this.listEl.classList.remove( 'drag-active' );
                this.listEl.querySelectorAll( '.drag-over' ).forEach(
                    el => el.classList.remove( 'drag-over' )
                );
                this.dragFrom = null;
            } );

            this.listEl.addEventListener( 'dragover', ( e ) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';

                const target = e.target.closest( '.smliser-block-item' );

                this.listEl.querySelectorAll( '.drag-over' ).forEach(
                    el => el.classList.remove( 'drag-over' )
                );

                if ( target ) {
                    target.classList.add( 'drag-over' );
                }
            } );

            this.listEl.addEventListener( 'drop', ( e ) => {
                e.preventDefault();

                const target = e.target.closest( '.smliser-block-item' );
                if ( ! target || this.dragFrom === null ) return;

                const toIndex = parseInt( target.dataset.blockIndex, 10 );

                this.editor.reorderBlocks( this.dragFrom, toIndex );
            } );
        }
    }

    // =========================================================================
    // BlockEditor — sidebar block editing panel
    // =========================================================================

    class BlockEditor {

        constructor( editor ) {
            this.editor    = editor;
            this.activeId  = null;

            this.emptyEl   = document.getElementById( 'smliser-block-editor-empty' );
            this.fieldsEl  = document.getElementById( 'smliser-block-editor-fields' );
            this.typeLabel = document.getElementById( 'smliser-active-block-type' );
            this.closeBtn  = document.getElementById( 'smliser-block-editor-close' );
        }

        init() {
            this.closeBtn.addEventListener( 'click', () => {
                this.editor.setActiveBlock( null );
                this.close();
            } );

            this._bindContentField();
            this._bindTonePicker();
            this._bindButtonFields();
        }

        /**
         * Open the editor for a block by ID.
         * Passing null closes the editor and shows the empty state.
         *
         * @param {string|null} id
         */
        open( id ) {
            if ( ! id ) {
                this.close();
                return;
            }

            const block = this.editor.getBlock( id );
            if ( ! block ) {
                this.close();
                return;
            }

            this.activeId = id;

            // Update type label.
            this.typeLabel.textContent = block.type.replace( '_', ' ' );

            // Show fields panel, hide empty state.
            this.emptyEl.style.display  = 'none';
            this.fieldsEl.style.display = '';

            // Show/hide fields based on block type.
            this._showFieldsForType( block );

            // Populate field values.
            this._populateFields( block );
        }

        close() {
            this.activeId = null;
            this.emptyEl.style.display  = '';
            this.fieldsEl.style.display = 'none';
        }

        /**
         * Show only the fields relevant to this block type.
         *
         * @param {object} block
         */
        _showFieldsForType( block ) {
            const showContent     = [ 'greeting', 'text', 'banner', 'closing' ].includes( block.type );
            const showTone        = block.type === 'banner';
            const showButtonLabel = block.type === 'button';
            const showButtonUrl   = block.type === 'button';
            const showRows        = block.type === 'detail_card';

            this._toggle( 'smliser-field-content',      showContent );
            this._toggle( 'smliser-field-tone',         showTone );
            this._toggle( 'smliser-field-button-label', showButtonLabel );
            this._toggle( 'smliser-field-button-url',   showButtonUrl );
            this._toggle( 'smliser-field-rows',         showRows );
        }

        /**
         * Populate field inputs from block data.
         *
         * @param {object} block
         */
        _populateFields( block ) {
            if ( [ 'greeting', 'text', 'banner', 'closing' ].includes( block.type ) ) {
                document.getElementById( 'smliser-block-content' ).value =
                    block.content || '';
            }

            if ( block.type === 'banner' ) {
                this._setActiveTone( block.tone || 'info' );
            }

            if ( block.type === 'button' ) {
                document.getElementById( 'smliser-block-button-label' ).value =
                    block.label || '';
                document.getElementById( 'smliser-block-button-url' ).value =
                    block.url || '';
            }

            if ( block.type === 'detail_card' ) {
                this._renderCardRows( block.rows || [] );
            }
        }

        _bindContentField() {
            const textarea = document.getElementById( 'smliser-block-content' );

            textarea.addEventListener( 'input', () => {
                if ( ! this.activeId ) return;
                this.editor.updateBlock( this.activeId, { content: textarea.value } );
            } );
        }

        _bindTonePicker() {
            document.querySelectorAll( '.smliser-tone-btn' ).forEach( btn => {
                btn.addEventListener( 'click', () => {
                    if ( ! this.activeId ) return;
                    this._setActiveTone( btn.dataset.tone );
                    this.editor.updateBlock( this.activeId, { tone: btn.dataset.tone } );
                } );
            } );
        }

        _bindButtonFields() {
            const labelInput = document.getElementById( 'smliser-block-button-label' );
            const urlInput   = document.getElementById( 'smliser-block-button-url' );

            labelInput.addEventListener( 'input', () => {
                if ( ! this.activeId ) return;
                this.editor.updateBlock( this.activeId, { label: labelInput.value } );
            } );

            urlInput.addEventListener( 'input', () => {
                if ( ! this.activeId ) return;
                this.editor.updateBlock( this.activeId, { url: urlInput.value } );
            } );
        }

        /**
         * Render the card rows editor for a detail_card block.
         *
         * @param {Array} rows
         */
        _renderCardRows( rows ) {
            const container = document.getElementById( 'smliser-card-rows' );
            container.innerHTML = '';

            rows.forEach( ( row, index ) => {
                const rowEl = document.createElement( 'div' );
                rowEl.className = 'smliser-card-row';

                const labelInput = document.createElement( 'input' );
                labelInput.type        = 'text';
                labelInput.value       = row.label || '';
                labelInput.placeholder = 'Label';

                const valueInput = document.createElement( 'input' );
                valueInput.type        = 'text';
                valueInput.value       = row.value || '';
                valueInput.placeholder = '{{token}} or value';

                labelInput.addEventListener( 'input', () => {
                    this._updateCardRow( index, 'label', labelInput.value );
                } );

                valueInput.addEventListener( 'input', () => {
                    this._updateCardRow( index, 'value', valueInput.value );
                } );

                rowEl.appendChild( labelInput );
                rowEl.appendChild( valueInput );
                container.appendChild( rowEl );
            } );
        }

        /**
         * Update a single row in a detail_card block.
         *
         * @param {number} rowIndex
         * @param {string} field     — 'label' | 'value'
         * @param {string} newValue
         */
        _updateCardRow( rowIndex, field, newValue ) {
            if ( ! this.activeId ) return;

            const block = this.editor.getBlock( this.activeId );
            if ( ! block || ! block.rows ) return;

            const rows = JSON.parse( JSON.stringify( block.rows ) );
            rows[ rowIndex ][ field ] = newValue;

            this.editor.updateBlock( this.activeId, { rows } );
        }

        _setActiveTone( tone ) {
            document.querySelectorAll( '.smliser-tone-btn' ).forEach( btn => {
                btn.classList.toggle( 'is-active', btn.dataset.tone === tone );
            } );
        }

        _toggle( id, show ) {
            const el = document.getElementById( id );
            if ( el ) el.style.display = show ? '' : 'none';
        }
    }

    // =========================================================================
    // PreviewPane — debounced iframe live preview
    // =========================================================================

    class PreviewPane {

        constructor( editor ) {
            this.editor     = editor;
            this.frame      = document.getElementById( 'smliser-preview-frame' );
            this.loaderEl   = document.getElementById( 'smliser-preview-loader' );
            this._timer     = null;
            this._delay     = 600; // ms debounce
        }

        init() {
            // Write the initial preview HTML directly — no AJAX needed on load.
            this._writeToFrame( this.editor.config.previewHTML );
        }

        /**
         * Schedule a debounced preview refresh.
         * Cancels any pending refresh before scheduling a new one.
         */
        scheduleRefresh() {
            clearTimeout( this._timer );
            this._timer = setTimeout( () => this._refresh(), this._delay );
        }

        /**
         * Immediately refresh the preview by posting current state to the server.
         */
        _refresh() {
            this.loaderEl.style.display = '';

            const payload = new FormData();
            payload.set( 'action',       'smliser_preview_email_template' );
            payload.set( 'security',     smliserEmailEditor.nonce );
            payload.set( 'template_key', this.editor.config.key );
            payload.set( 'blocks',       JSON.stringify( this.editor.$state.blocks ) );
            payload.set( 'styles',       JSON.stringify( this.editor.$state.styles ) );

            smliserFetchJSON( new URL( smliserEmailEditor.ajaxURL ), {
                method:      'POST',
                credentials: 'same-origin',
                body:        payload,
            } )
            .then( response => {
                if ( response.success && response.data?.html ) {
                    this._writeToFrame( response.data.html );
                }
            } )
            .catch( () => {
                // Silent fail on preview — do not disrupt editing flow.
            } )
            .finally( () => {
                this.loaderEl.style.display = 'none';
            } );
        }

        /**
         * Write an HTML string into the preview iframe.
         *
         * @param {string} html
         */
        _writeToFrame( html ) {
            const doc = this.frame.contentDocument
                     || this.frame.contentWindow.document;

            doc.open();
            doc.write( html );
            doc.close();

            // Adjust iframe height to content after render settles.
            setTimeout( () => {
                if ( doc.body ) {
                    this.frame.style.height = doc.body.scrollHeight + 'px';
                }
            }, 100 );
        }
    }

    // =========================================================================
    // Toolbar — save, reset, enable/disable
    // =========================================================================

    class Toolbar {

        constructor( editor ) {
            this.editor   = editor;
            this.saveBtn  = document.getElementById( 'smliser-save-btn' );
            this.resetBtn = document.getElementById( 'smliser-reset-btn' );
            this.toggleBtn= document.getElementById( 'smliser-toggle-btn' );
        }

        init() {
            this.saveBtn.addEventListener(   'click', () => this._save() );
            this.resetBtn.addEventListener(  'click', () => this._reset() );
            this.toggleBtn.addEventListener( 'click', () => this._toggle() );
        }

        _save() {
            const label     = document.getElementById( 'smliser-save-label' );
            const icon      = document.getElementById( 'smliser-save-icon' );
            const key       = this.editor.config.key;

            this.saveBtn.disabled = true;
            label.textContent     = 'Saving...';
            icon.className        = 'ti ti-loader-2 smliser-spin';
            
            const payload = new FormData();
            payload.set( 'action',       'smliser_save_email_template' );
            payload.set( 'security',     smliserEmailEditor.nonce );
            payload.set( 'template_key', key );
            payload.set( 'blocks', JSON.stringify( this.editor.$state.blocks ) );
            payload.set( 'styles', JSON.stringify( this.editor.$state.styles ) );

            smliserFetchJSON( new URL( smliserEmailEditor.ajaxURL ), {
                method:      'POST',
                credentials: 'same-origin',
                body:        payload,
            } )
            .then( async response => {
                if ( response.success ) {
                    this.editor.clearDirty();

                    // Show custom badge if not already shown.
                    document.getElementById( 'smliser-custom-badge' ).style.display = '';

                    // Enable reset button now that a custom template exists.
                    this.resetBtn.disabled = false;

                    label.textContent = 'Saved';
                    icon.className    = 'ti ti-circle-check';

                    await SmliserModal.success(
                        response?.data?.message || 'Template saved successfully.'
                    );

                    label.textContent = 'Save Template';
                    icon.className    = 'ti ti-cloud-upload';
                }
            } )
            .catch( err => {
                SmliserModal.error( err.message, 'Save Failed' );
                label.textContent = 'Save Template';
                icon.className    = 'ti ti-cloud-upload';
            } )
            .finally( () => {
                this.saveBtn.disabled = false;
            } );
        }

        async _reset() {
            const confirmed = await SmliserModal.confirm(
                'This will permanently remove your custom template and restore the system default. Are you sure?',
                'Reset Template'
            );

            if ( ! confirmed ) return;

            const key     = this.editor.config.key;
            const payload = new FormData();
            payload.set( 'action',       'smliser_reset_email_template' );
            payload.set( 'security',     smliserEmailEditor.nonce );
            payload.set( 'template_key', key );

            this.resetBtn.disabled = true;

            smliserFetchJSON( new URL( smliserEmailEditor.ajaxURL ), {
                method:      'POST',
                credentials: 'same-origin',
                body:        payload,
            } )
            .then( async response => {
                if ( response.success ) {
                    await SmliserModal.success(
                        response?.data?.message || 'Template reset to default.'
                    );
                    // Reload to reflect system default state.
                    window.location.reload();
                }
            } )
            .catch( err => {
                SmliserModal.error( err.message, 'Reset Failed' );
                this.resetBtn.disabled = false;
            } );
        }

        _toggle() {
            const btn       = this.toggleBtn;
            const key       = btn.dataset.key;
            const enabled   = btn.dataset.enabled;
            const enabling  = enabled === '0';
            const payload   = new FormData();

            payload.set( 'action',       'smliser_toggle_email_template' );
            payload.set( 'security',     smliserEmailEditor.nonce );
            payload.set( 'template_key', key );
            payload.set( 'new_state',    enabling ? '1' : '0' );

            btn.disabled = true;

            smliserFetchJSON( new URL( smliserEmailEditor.ajaxURL ), {
                method:      'POST',
                credentials: 'same-origin',
                body:        payload,
            } )
            .then( async response => {
                if ( response.success ) {
                    btn.dataset.enabled = enabling ? '1' : '0';

                    // Update toggle button appearance.
                    const icon  = document.getElementById( 'smliser-toggle-icon' );
                    const label = document.getElementById( 'smliser-toggle-label' );
                    icon.className    = enabling ? 'ti ti-eye-off' : 'ti ti-eye';
                    label.textContent = enabling ? 'Disable' : 'Enable';

                    // Update status badge.
                    const badge      = document.getElementById( 'smliser-status-badge' );
                    const statusText = document.getElementById( 'smliser-status-label' );
                    badge.className  = 'smliser-editor-badge ' + ( enabling
                        ? 'smliser-editor-badge--enabled'
                        : 'smliser-editor-badge--disabled' );
                    statusText.textContent = enabling ? 'Enabled' : 'Disabled';

                    await SmliserModal.success(
                        response?.data?.message || 'Template status updated.'
                    );
                }
            } )
            .catch( err => {
                SmliserModal.error( err.message, 'Error' );
            } )
            .finally( () => {
                btn.disabled = false;
            } );
        }
    }

    // =========================================================================
    // Bootstrap — initialise when DOM is ready
    // =========================================================================

    document.addEventListener( 'DOMContentLoaded', function() {

        if ( typeof window.smliserEmailEditor === 'undefined' ) {
            console.error( 'smliserEmailEditor config not found.' );
            return;
        }

        const editor = new EmailEditor( window.smliserEmailEditor );
        editor.init();

        // Expose instance for debugging.
        window._smliserEditorInstance = editor;
    } );

} )();