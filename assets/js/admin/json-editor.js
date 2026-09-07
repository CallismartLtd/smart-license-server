/**
 * SmliserJsonEditor - Modern, Accessible JSON Editor
 * 
 * A comprehensive JSON editor with visual tree view, inline editing,
 * validation, import/export, undo/redo, and full keyboard navigation.
 *
 * Self-contained: no external dependency on SmliserModal, SmliserToast,
 * or anything else. Dialogs and notifications are exposed as part of the
 * editor's own API (`editor.notification.*`, `editor.dialog.*`), the
 * same way TinyMCE exposes `editor.notificationManager` / `editor.windowManager`.
 * Notifications render inside the editor's own content area; dialogs
 * (prompt/confirm/generic modal) render as page-level overlays since they
 * need to escape the editor's clipped, fixed-height container.
 * 
 * @author Callistus Nwachukwu
 * @version 1.3.0
 * @license MIT
 */
class SmliserJsonEditor {
    /**
     * @param {string|HTMLElement} target - CSS selector or DOM element
     * @param {Object} options - Configuration options
     * @param {*} options.data - Initial JSON data
     * @param {string} options.title - Editor title (optional)
     * @param {string} options.description - Editor description (optional)
     * @param {string} options.mode - Editor mode: 'tree' or 'code' (default: 'tree')
     * @param {string} options.theme - Theme: 'light' or 'dark' (default: 'light')
     * @param {boolean} options.readOnly - Read-only mode (default: false)
     * @param {boolean} options.showToolbar - Show toolbar (default: true)
     * @param {boolean} options.enableUndo - Enable undo/redo (default: true)
     * @param {number} options.maxHistorySize - Max undo history (default: 50)
     * @param {boolean} options.autoFormat - Auto-format on load (default: true)
     * @param {number} options.indentSize - Indentation size for code mode (default: 2)
     * @param {boolean} options.sortKeys - Sort object keys (default: false)
     * @param {boolean} options.enableSearch - Enable search (default: true)
     * @param {boolean} options.lineNumbers - Show line numbers in code mode (default: true)
     * @param {Function} options.validator - Custom validation function
     * @param {Function} options.onChange - Change callback
     * @param {Function} options.onError - Error callback
     * @param {Object} options.customButtons - Custom toolbar buttons
     * @param {boolean} options.verbose - Show automatic success notifications for
     *                                    routine actions (copy, paste, download, import).
     *                                    Errors are always shown regardless of this
     *                                    setting. (default: true)
     */
    constructor( target, options = {} ) {
        this.targetElement = typeof target === 'string'
            ? document.querySelector( target )
            : target;

        if ( ! this.targetElement ) {
            throw new Error('SmliserJsonEditor: Target element not found');
        }

        this.options = {
            data: null,
            title: null,
            description: null,
            mode: 'tree',
            theme: 'light',
            readOnly: false,
            showToolbar: true,
            enableUndo: true,
            maxHistorySize: 50,
            autoFormat: true,
            indentSize: 2,
            sortKeys: false,
            enableSearch: true,
            lineNumbers: true,
            validator: null,
            onChange: null,
            onError: null,
            customButtons: {},
            autoFocus: true,
            verbose: true,
            ...options
        };

        this.currentData        = null;
        this.currentMode        = this.options.mode;
        this.history            = [];
        this.historyIndex       = -1;
        this.isValid            = true;
        this.validationErrors   = [];
        this.searchTerm         = '';
        this.expandedPaths      = new Set();
        this.toolbarCollapsed   = false;
        this.isFullscreen       = false;
        this._uidCounter        = 0;

        // Drag-to-reorder state
        this._drag = {
            active:   false,
            fromPath: null,
            fromEl:   null
        };

        this.container          = null;
        this.toolbar            = null;
        this.treeView           = null;
        this.codeView           = null;
        this.statusBar          = null;

        this.eventHandlers = {
            change: [],
            error: [],
            validate: [],
            modeChange: [],
            fullscreenChange: []
        };

        this.id = 'smliser-json-editor-' + Date.now();

        this.notificationArea = null;

        /**
         * Public notification API - renders inline within the editor's own
         * content area (never escapes to the page). Mirrors the shape of
         * editor.notificationManager in TinyMCE.
         */
        this.notification = {
            success: ( message, options = {} ) => this._notify( message, 'success', options ),
            error:   ( message, options = {} ) => this._notify( message, 'error', options ),
            warning: ( message, options = {} ) => this._notify( message, 'warning', options ),
            info:    ( message, options = {} ) => this._notify( message, 'info', options )
        };

        /**
         * Public dialog API - page-level overlays (modal, prompt, confirm,
         * alert). These intentionally escape the editor's own container
         * since it is fixed-height and clips overflow. Mirrors the shape of
         * editor.windowManager in TinyMCE.
         */
        this.dialog = {
            open:    ( options ) => this._openDialog( options ),
            prompt:  ( options ) => this._dialogPrompt( options ),
            confirm: ( options ) => this._dialogConfirm( options ),
            alert:   ( options ) => this._dialogAlert( options )
        };

        this._init();
    }

    _init() {
        this._setInitialData();
        this._buildUI();
        this._setupEventListeners();
        this._render();

        if ( this.options.enableUndo ) {
            this._addToHistory( this.currentData );
        }
    }

    _setInitialData() {
        let data = this.options.data;

        if ( this.targetElement.tagName === 'TEXTAREA' && ! data ) {
            const textValue = this.targetElement.value.trim();
            if ( textValue ) {
                try {
                    data = JSON.parse( textValue );
                } catch (e) {
                    data = {};
                    this._handleError( 'Invalid JSON in textarea', e );
                }
            }
        }

        this.currentData = data !== null && data !== undefined ? data : {};
        this._validate();
    }

    _buildUI() {
        this.container              = document.createElement( 'div' );
        this.container.className    = `smliser-json-editor theme-${this.options.theme} mode-${this.currentMode}`;
        this.container.id           = this.id;
        this.container.tabIndex     = "0";
        this.container.setAttribute( 'role', 'application' );
        this.container.setAttribute( 'aria-label', 'JSON Editor' );

        if ( this.options.title || this.options.description ) {
            this._buildHeader();
        }

        if ( this.options.showToolbar ) {
            this._buildToolbar();
        }

        this._buildViews();
        this._buildStatusBar();
        this._buildNotificationArea();

        if ( this.targetElement.tagName === 'TEXTAREA' ) {
            this.targetElement.style.display = 'none';
            this.targetElement.parentNode.insertBefore( this.container, this.targetElement.nextSibling );
        } else {
            this.targetElement.innerHTML = '';
            this.targetElement.appendChild( this.container );
        }

        if ( this.options.autoFocus ) {
            this.container.focus();
        }
        this._setupResizeObserver();
    }

    _buildHeader() {
        const header = document.createElement( 'div' );
        header.className = 'json-editor-header';

        if ( this.options.title ) {
            const titleElement          = document.createElement( 'h2' );
            titleElement.className      = 'json-editor-header-title';
            titleElement.textContent    = this.options.title;
            header.appendChild( titleElement );
        }

        if ( this.options.description ) {
            const descElement       = document.createElement( 'p' );
            descElement.className   = 'json-editor-header-description';
            descElement.textContent = this.options.description;
            header.appendChild( descElement );
        }

        this.container.appendChild( header );
    }

    _buildToolbar() {
        this.toolbar            = document.createElement( 'div' );
        this.toolbar.className  = 'json-editor-toolbar';
        this.toolbar.setAttribute( 'role', 'toolbar' );
        this.toolbar.setAttribute( 'aria-label', 'Editor toolbar' );

        // NOTE: All buttons use type="button" to prevent accidental form submission
        const toolbarHTML = `
            <div class="toolbar-toggle-row">
                <button type="button" class="toolbar-toggle-btn" aria-label="Toggle toolbar" title="Toggle toolbar" aria-controls="${this.id}-toolbar-collapsible" aria-expanded="true">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                        <path d="M2 4h12M2 8h12M2 12h12"/>
                    </svg>
                    <span class="toolbar-toggle-label">Tools</span>
                </button>
                <div class="toolbar-mode-inline" role="radiogroup" aria-label="Editor view">
                    <button type="button" class="toolbar-btn ${this.currentMode === 'tree' ? 'active' : ''}" data-action="mode-tree" title="Tree view" role="radio" aria-checked="${this.currentMode === 'tree'}">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="2" y="2" width="5" height="3" rx="0.5"/>
                            <rect x="2" y="7" width="5" height="3" rx="0.5"/>
                            <rect x="9" y="7" width="5" height="3" rx="0.5"/>
                            <path d="M4.5 5v2M4.5 7h4.5M9 7v2.5"/>
                        </svg>
                        <span>Tree</span>
                    </button>
                    <button type="button" class="toolbar-btn ${this.currentMode === 'code' ? 'active' : ''}" data-action="mode-code" title="Code view" role="radio" aria-checked="${this.currentMode === 'code'}">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M5 4L2 8l3 4M11 4l3 4-3 4M9 2L7 14"/>
                        </svg>
                        <span>Code</span>
                    </button>
                </div>
            </div>

            <div class="toolbar-collapsible" id="${this.id}-toolbar-collapsible">
                <div class="toolbar-inner">
                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="import" title="Import JSON (Ctrl+O)" aria-label="Import JSON">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M8 3v7m0-7l-3 3m3-3l3 3"/><path d="M2 12v2h12v-2"/>
                            </svg>
                            <span>Import</span>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="export" title="Export JSON (Ctrl+S)" aria-label="Export JSON">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M8 11V3m0 8l-3-3m3 3l3-3"/><path d="M2 14h12"/>
                            </svg>
                            <span>Export</span>
                        </button>
                    </div>

                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="copy" title="Copy JSON to clipboard (Ctrl+C)" aria-label="Copy JSON">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <rect x="5" y="5" width="9" height="10" rx="1"/><path d="M3 11V3a1 1 0 0 1 1-1h6"/>
                            </svg>
                            <span>Copy</span>
                        </button>
                    </div>

                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="undo" title="Undo (Ctrl+Z)" aria-label="Undo" ${!this.options.enableUndo ? 'disabled' : ''}>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M4 8h8a3 3 0 0 1 0 6H9"/><path d="M4 8l3-3M4 8l3 3"/>
                            </svg>
                            <span>Undo</span>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="redo" title="Redo (Ctrl+Y)" aria-label="Redo" ${!this.options.enableUndo ? 'disabled' : ''}>
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M12 8H4a3 3 0 0 0 0 6h3"/><path d="M12 8l-3-3M12 8l-3 3"/>
                            </svg>
                            <span>Redo</span>
                        </button>
                    </div>

                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="format" title="Format JSON (prettify)" aria-label="Format JSON">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M2 4h12M2 8h9M2 12h12"/>
                            </svg>
                            <span>Format</span>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="compact" title="Compact JSON (minify)" aria-label="Compact JSON">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M2 5h12M2 8h12M2 11h12"/>
                            </svg>
                            <span>Compact</span>
                        </button>
                        <button type="button" class="toolbar-btn ${this.options.sortKeys ? 'active' : ''}" data-action="sort" title="Sort keys alphabetically" aria-label="Sort keys" aria-pressed="${this.options.sortKeys}">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                                <path d="M4 3v10m0 0l-2-2m2 2l2-2"/><path d="M12 13V3m0 0L10 5m2-2l2 2"/>
                            </svg>
                            <span>Sort</span>
                        </button>
                    </div>

                    <div class="toolbar-group">
                        <button type="button" class="toolbar-btn" data-action="expand-all" title="Expand all nodes" aria-label="Expand all">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                <path d="M8 3v10M3 8h10"/>
                            </svg>
                            <span>Expand</span>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="collapse-all" title="Collapse all nodes" aria-label="Collapse all">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                <path d="M3 8h10"/>
                            </svg>
                            <span>Collapse</span>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="theme" title="Toggle light/dark theme" aria-label="Toggle theme" aria-pressed="${this.options.theme === 'dark'}">
                            <svg width="16" height="16" viewBox="0 0 16 16" class="theme-icon" fill="none" stroke="currentColor" stroke-width="1.5">
                                ${this._getThemeIconSVG()}
                            </svg>
                        </button>
                        <button type="button" class="toolbar-btn" data-action="fullscreen" title="Toggle fullscreen" aria-label="Enter fullscreen" aria-pressed="false">
                            <svg width="16" height="16" viewBox="0 0 16 16" class="fullscreen-icon" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                ${this._getFullscreenIconSVG()}
                            </svg>
                            <span>Fullscreen</span>
                        </button>
                    </div>

                    ${this.options.enableSearch ? `
                    <div class="toolbar-group toolbar-search">
                        <label class="sje-visually-hidden" for="${this.id}-search-input">Search keys or values</label>
                        <input type="search" id="${this.id}-search-input" class="toolbar-search-input" placeholder="Search keys or values…" aria-label="Search JSON">
                    </div>
                    ` : ''}
                </div>
            </div>
        `;

        this.toolbar.innerHTML = toolbarHTML;
        this.container.appendChild( this.toolbar );
    }

    _getThemeIconSVG() {
        if ( this.options.theme === 'light' ) {
            return `<circle cx="8" cy="8" r="3.5" fill="currentColor" stroke="none"/>
                <line x1="8" y1="1" x2="8" y2="2.5" stroke-linecap="round"/>
                <line x1="8" y1="13.5" x2="8" y2="15" stroke-linecap="round"/>
                <line x1="1" y1="8" x2="2.5" y2="8" stroke-linecap="round"/>
                <line x1="13.5" y1="8" x2="15" y2="8" stroke-linecap="round"/>
                <line x1="3.2" y1="3.2" x2="4.2" y2="4.2" stroke-linecap="round"/>
                <line x1="11.8" y1="11.8" x2="12.8" y2="12.8" stroke-linecap="round"/>
                <line x1="12.8" y1="3.2" x2="11.8" y2="4.2" stroke-linecap="round"/>
                <line x1="4.2" y1="11.8" x2="3.2" y2="12.8" stroke-linecap="round"/>`;
        }
        return `<path d="M13 8c0 2.76-2.24 5-5 5A5 5 0 0 1 3 8a5 5 0 0 1 5-5 3.5 3.5 0 0 0 0 7A3.5 3.5 0 0 0 13 8z" fill="currentColor" stroke="none"/>`;
    }

    /**
     * @private
     */
    _getFullscreenIconSVG() {
        if ( ! this.isFullscreen ) {
            // Enter fullscreen - four corner brackets pointing outward
            return `<path d="M2 6V3a1 1 0 0 1 1-1h3M14 6V3a1 1 0 0 0-1-1h-3M2 10v3a1 1 0 0 0 1 1h3M14 10v3a1 1 0 0 1-1 1h-3"/>`;
        }
        // Exit fullscreen - four corner brackets pointing inward
        return `<path d="M6 2v3a1 1 0 0 1-1 1H2M10 2v3a1 1 0 0 0 1 1h3M6 14v-3a1 1 0 0 0-1-1H2M10 14v-3a1 1 0 0 1 1-1h3"/>`;
    }

    _buildViews() {
        const viewsContainer        = document.createElement( 'div' );
        viewsContainer.className    = 'json-editor-views';

        this.treeView           = document.createElement( 'div' );
        this.treeView.className = 'json-editor-tree-view';
        this.treeView.setAttribute( 'role', 'tree' );
        this.treeView.setAttribute( 'aria-label', 'JSON tree view' );
        viewsContainer.appendChild( this.treeView );

        this.codeView           = document.createElement( 'div' );
        this.codeView.className = 'json-editor-code-view';

        const codeTextarea      = document.createElement( 'textarea' );
        codeTextarea.className  = 'json-editor-code-textarea';
        codeTextarea.id         = `${this.id}-code-textarea`;
        codeTextarea.setAttribute( 'spellcheck', 'false' );
        codeTextarea.setAttribute( 'aria-label', 'JSON code editor' );
        codeTextarea.placeholder = 'Enter or paste JSON here…';
        if ( this.options.readOnly ) {
            codeTextarea.readOnly = true;
        }

        this.codeView.appendChild( codeTextarea );
        viewsContainer.appendChild( this.codeView );

        this.container.appendChild( viewsContainer );
    }

    _buildStatusBar() {
        this.statusBar              = document.createElement( 'div' );
        this.statusBar.className    = 'json-editor-status-bar';
        this.statusBar.setAttribute( 'role', 'status' );
        this.statusBar.setAttribute( 'aria-live', 'polite' );

        this.statusBar.innerHTML = `
            <span class="status-item status-valid"></span>
            <span class="status-item status-size"></span>
            <span class="status-item status-keys"></span>
        `;

        this.container.appendChild( this.statusBar );
        this._updateStatusBar();
    }

    /**
     * Build the container that in-editor notifications render into.
     * Lives inside the editor's own box (this.container is position:relative)
     * so notifications never escape onto the rest of the host page.
     * @private
     */
    _buildNotificationArea() {
        this.notificationArea = document.createElement( 'div' );
        this.notificationArea.className = 'sje-notification-area';
        this.notificationArea.setAttribute( 'role', 'status' );
        this.notificationArea.setAttribute( 'aria-live', 'polite' );

        this.container.appendChild( this.notificationArea );
    }

    /**
     * Setup ResizeObserver to handle narrow-width toolbar collapsing
     * @private
     */
    _setupResizeObserver() {
        if ( ! window.ResizeObserver ) return;

        this._resizeObserver = new ResizeObserver( entries => {
            for ( const entry of entries ) {
                const width = entry.contentRect.width;
                this.container.classList.toggle( 'toolbar-narrow', width < 540 );
                this.container.classList.toggle( 'toolbar-very-narrow', width < 360 );
            }
        });

        this._resizeObserver.observe( this.container );
    }

    _setupEventListeners() {
        // Toolbar toggle button
        const toggleBtn = this.toolbar?.querySelector( '.toolbar-toggle-btn' );
        if ( toggleBtn ) {
            toggleBtn.addEventListener( 'click', () => {
                this.toggleToolbar();
            });
        }

        // Toolbar actions — use type=button so no form submission
        this.toolbar?.addEventListener( 'click', (e) => {
            const btn = e.target.closest( '[data-action]' );
            if ( ! btn ) return;
            const action = btn.dataset.action;
            this._handleToolbarAction( action );
        });

        // Search input
        const searchInput = this.toolbar?.querySelector( '.toolbar-search-input' );
        if ( searchInput ) {
            searchInput.addEventListener( 'input', (e) => {
                this.searchTerm = e.target.value;
                this._render();
            });
        }

        // Code view textarea
        const codeTextarea = this.codeView?.querySelector( 'textarea' );
        if ( codeTextarea ) {
            codeTextarea.addEventListener( 'input', () => {
                this._handleCodeChange();
            });
        }

        // Keyboard shortcuts — only when editor is focused
        document.addEventListener( 'keydown', (e) => {
            if ( ! this.container.contains( document.activeElement ) ) return;
            this._handleKeyboardShortcuts( e );
        });

        // File drop
        this.container.addEventListener( 'dragover', (e) => {
            e.preventDefault();
            const files = e.dataTransfer?.files;

            if ( ! files || files.length === 0 ) return;

            this.container.classList.add( 'drag-over' );
        });

        this.container.addEventListener( 'dragleave', () => {
            this.container.classList.remove( 'drag-over' );
        });

        this.container.addEventListener( 'drop', (e) => {
            e.preventDefault();
            this.container.classList.remove( 'drag-over' );
            this._handleFileDrop( e );
        });
    }

    async _handleToolbarAction( action ) {
        switch ( action ) {
            case 'import':       await this.showImportDialog();  break;
            case 'export':       await this.showExportDialog();  break;
            case 'copy':         await this.copyToClipboard();   break;
            case 'undo':         this.undo();                    break;
            case 'redo':         this.redo();                    break;
            case 'format':       this.format();                  break;
            case 'compact':      this.compact();                 break;
            case 'sort':         this.toggleSortKeys();          break;
            case 'mode-tree':    this.setMode( 'tree' );         break;
            case 'mode-code':    this.setMode( 'code' );         break;
            case 'expand-all':   this.expandAll();               break;
            case 'collapse-all': this.collapseAll();             break;
            case 'theme':        this.toggleTheme();             break;
            case 'fullscreen':   this.toggleFullscreen();        break;
        }
    }

    _handleKeyboardShortcuts( e ) {
        const ctrl = e.ctrlKey || e.metaKey;

        if ( ctrl && e.key === 'z' && !e.shiftKey ) {
            e.preventDefault(); this.undo();
        } else if ( ctrl && ( e.key === 'y' || ( e.key === 'z' && e.shiftKey ) ) ) {
            e.preventDefault(); this.redo();
        } else if ( ctrl && e.key === 's' ) {
            e.preventDefault(); this.showExportDialog();
        } else if ( ctrl && e.key === 'o' ) {
            e.preventDefault(); this.showImportDialog();
        } else if ( ctrl && e.key === 'f' ) {
            e.preventDefault();
            this.toolbar?.querySelector( '.toolbar-search-input' )?.focus();
        } else if ( e.key === 'Escape' && this.isFullscreen ) {
            e.preventDefault();
            this.toggleFullscreen( false );
        }
    }

    _handleCodeChange() {
        const codeTextarea = this.codeView.querySelector( 'textarea' );
        const code = codeTextarea.value;

        try {
            const parsed = JSON.parse( code );
            this._updateData( parsed, true );
        } catch (e) {
            this.isValid            = false;
            this.validationErrors   = [e.message];
            this._updateStatusBar();
        }
    }

    async _handleFileDrop( e ) {
        const files = e.dataTransfer.files;
        if ( files.length === 0 ) return;

        const file = files[0];

        if ( ! file.type.includes( 'application/json' ) && ! file.name.endsWith( '.json' ) ) {
            this._handleError( 'Please drop a valid JSON file' );
            return;
        }

        await this.#_loadFile( file );
    }

    _render() {
        if ( this.currentMode === 'tree' ) {
            this._renderTreeView();
        } else {
            this._renderCodeView();
        }

        this._updateStatusBar();
        this._updateToolbarState();
    }

    _renderTreeView() {
        this.treeView.innerHTML = '';
        const tree = this._buildTreeNode( this.currentData, '', 'root' );
        this.treeView.appendChild( tree );
    }

    _buildTreeNode( value, path, key, isLast = true ) {
        const node          = document.createElement( 'div' );
        node.className      = 'json-tree-node';
        node.dataset.path   = path;

        const type          = this._getType( value );
        const isExpandable  = type === 'object' || type === 'array';
        const isExpanded    = this.expandedPaths.has( path );
        const matchesSearch = this.searchTerm ? this._matchesSearch( key, value ) : true;

        if ( ! matchesSearch && ! this._hasMatchingChildren( value ) ) {
            node.style.display = 'none';
        }

        const header        = document.createElement( 'div' );
        header.className    = `json-tree-header type-${type} ${matchesSearch && this.searchTerm ? 'highlight' : ''}`;
        header.setAttribute( 'role', 'treeitem' );
        if ( isExpandable ) {
            header.setAttribute( 'aria-expanded', String( isExpanded ) );
        }

        // Mark draggable on non-root nodes (drag disabled when search active)
        const isDraggable = key !== 'root' && ! this.options.readOnly && ! this.searchTerm;
        if ( isDraggable ) {
            node.setAttribute( 'draggable', 'true' );
            node.dataset.draggable = 'true';
        }

        let headerHTML = '';

        // Drag handle — only shown on draggable non-root nodes
        if ( isDraggable ) {
            headerHTML += `<span class="tree-drag-handle" title="Drag to reorder" aria-hidden="true">
                <svg width="10" height="14" viewBox="0 0 10 14" fill="currentColor">
                    <circle cx="3" cy="2.5" r="1.2"/><circle cx="7" cy="2.5" r="1.2"/>
                    <circle cx="3" cy="7"   r="1.2"/><circle cx="7" cy="7"   r="1.2"/>
                    <circle cx="3" cy="11.5" r="1.2"/><circle cx="7" cy="11.5" r="1.2"/>
                </svg>
            </span>`;
        } else {
            headerHTML += `<span class="tree-drag-spacer"></span>`;
        }

        if ( isExpandable ) {
            headerHTML += `
                <button type="button" class="tree-toggle" aria-label="${isExpanded ? 'Collapse' : 'Expand'}">
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="currentColor">
                        <path d="${isExpanded ? 'M1 3l4 4 4-4' : 'M3 1l4 4-4 4'}"/>
                    </svg>
                </button>
            `;
        } else {
            headerHTML += '<span class="tree-spacer"></span>';
        }

        if ( key !== 'root' ) {
            headerHTML += `<span class="tree-key">${this._escapeHtml( key )}</span>`;
            headerHTML += '<span class="tree-separator">:</span>';
        }

        headerHTML += `<span class="tree-value-wrap"><span class="tree-value ${type}">${this._getValuePreview( value, type )}</span></span>`;

        if ( ! this.options.readOnly ) {
            // Determine if parent is array (key is numeric string) — arrays lock key editing
            const parentPath    = path.split( '.' ).slice( 0, -1 ).join( '.' );
            const parentValue   = parentPath ? this._getValueAtPath( parentPath ) : null;
            const parentIsArray = Array.isArray( parentValue );
            const isRootNode    = key === 'root';

            headerHTML += `<div class="tree-actions">`;

            // Edit key — only for non-root object properties (not array indexes)
            if ( ! isRootNode && ! parentIsArray ) {
                headerHTML += `
                    <button type="button" class="tree-action-btn" data-action="edit-key" title="Rename key" aria-label="Rename key">
                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M9 2l3 3-6 6H3V8z"/><path d="M7 4l3 3"/>
                        </svg>
                    </button>
                `;
            }

            // Edit value
            headerHTML += `
                <button type="button" class="tree-action-btn" data-action="edit" title="Edit value" aria-label="Edit value">
                    <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                        <path d="M2 10l5.5-5.5 2 2L4 12H2z"/><path d="M8.5 3.5l2 2"/>
                    </svg>
                </button>
            `;

            // Delete — not on root
            if ( ! isRootNode ) {
                headerHTML += `
                    <button type="button" class="tree-action-btn delete" data-action="delete" title="Delete" aria-label="Delete">
                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M3 3l8 8M11 3l-8 8"/>
                        </svg>
                    </button>
                `;
            }

            // Add child — only for expandable nodes
            if ( isExpandable ) {
                headerHTML += `
                    <button type="button" class="tree-action-btn add" data-action="add" title="Add item" aria-label="Add item">
                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <path d="M7 2v10M2 7h10"/>
                        </svg>
                    </button>
                `;
            }

            headerHTML += `</div>`;
        }

        header.innerHTML = headerHTML;
        node.appendChild( header );

        if ( isExpandable && isExpanded ) {
            const children = document.createElement( 'div' );
            children.className = 'json-tree-children';
            children.setAttribute( 'role', 'group' );

            if ( type === 'array' ) {
                value.forEach( ( item, index ) => {
                    const childPath = path ? `${path}.${index}` : String( index );
                    const childNode = this._buildTreeNode( item, childPath, String( index ), index === value.length - 1 );
                    children.appendChild( childNode );
                });
            } else if ( type === 'object' ) {
                const keys = this.options.sortKeys ? Object.keys( value ).sort() : Object.keys( value );
                keys.forEach( ( k, index ) => {
                    const childPath = path ? `${path}.${k}` : k;
                    const childNode = this._buildTreeNode( value[k], childPath, k, index === keys.length - 1 );
                    children.appendChild( childNode );
                });
            }

            node.appendChild( children );
        }

        const toggleBtn = header.querySelector( '.tree-toggle' );
        if ( toggleBtn ) {
            toggleBtn.addEventListener( 'click', (e) => {
                e.stopPropagation();
                this._toggleNode( path );
            });
        }

        const actionBtns = header.querySelectorAll( '.tree-action-btn' );
        actionBtns.forEach( btn => {
            btn.addEventListener( 'click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                this._handleNodeAction( action, path, key, value );
            });
        });

        // Drag-to-reorder event listeners
        if ( isDraggable ) {
            node.addEventListener( 'dragstart', (e) => {
                e.stopPropagation();
                this._drag.active   = true;
                this._drag.fromPath = path;
                this._drag.fromEl   = node;
                node.classList.add( 'dragging' );
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData( 'text/plain', path );
            });

            node.addEventListener( 'dragend', (e) => {
                e.stopPropagation();
                this._drag.active   = false;
                this._drag.fromPath = null;
                this._drag.fromEl   = null;
                node.classList.remove( 'dragging' );
                // Clear all drop indicators
                this.treeView.querySelectorAll( '.drop-above, .drop-below' ).forEach( el => {
                    el.classList.remove( 'drop-above', 'drop-below' );
                });
            });

            node.addEventListener( 'dragover', (e) => {
                if ( ! this._drag.active || this._drag.fromEl === node ) return;
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'move';

                // Determine above/below from mouse position
                const rect = node.getBoundingClientRect();
                const mid  = rect.top + rect.height / 2;
                node.classList.toggle( 'drop-above', e.clientY < mid );
                node.classList.toggle( 'drop-below', e.clientY >= mid );
            });

            node.addEventListener( 'dragleave', (e) => {
                e.stopPropagation();
                if ( ! node.contains( e.relatedTarget ) ) {
                    node.classList.remove( 'drop-above', 'drop-below' );
                }
            });

            node.addEventListener( 'drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const dropAbove = node.classList.contains( 'drop-above' );
                node.classList.remove( 'drop-above', 'drop-below' );

                if ( ! this._drag.fromPath || this._drag.fromPath === path ) return;
                this._performReorder( this._drag.fromPath, path, dropAbove );
            });
        }

        return node;
    }

    _getValuePreview( value, type ) {
        switch ( type ) {
            case 'string':
                const str = this._escapeHtml( value );
                return `<span class="str-quote">"</span>${str}<span class="str-quote">"</span>`;
            case 'number':  return String( value );
            case 'boolean': return value ? 'true' : 'false';
            case 'null':    return 'null';
            case 'array':   return `<span class="preview-bracket">[</span><span class="preview-count">${value.length} item${value.length !== 1 ? 's' : ''}</span><span class="preview-bracket">]</span>`;
            case 'object':  return `<span class="preview-bracket">{</span><span class="preview-count">${Object.keys(value).length} key${Object.keys(value).length !== 1 ? 's' : ''}</span><span class="preview-bracket">}</span>`;
            default:        return String( value );
        }
    }

    _toggleNode( path ) {
        if ( this.expandedPaths.has( path ) ) {
            this.expandedPaths.delete( path );
        } else {
            this.expandedPaths.add( path );
        }
        this._render();
    }

    async _handleNodeAction( action, path, key, value ) {
        switch ( action ) {
            case 'edit':     await this._editNode( path, key, value );    break;
            case 'edit-key': await this._editNodeKey( path, key );        break;
            case 'delete':   await this._deleteNode( path );              break;
            case 'add':      await this._addToNode( path, value );        break;
        }
    }

    async _editNode( path, key, value ) {
        const type     = this._getType( value );
        const newValue = await this._showValueEditor( key, value, type );
        if ( newValue !== null && newValue !== undefined ) {
            this._setValueAtPath( path, newValue );
        }
    }

    async _editNodeKey( path, currentKey ) {
        const newKey = await this.dialog.prompt({
            title: 'Rename Property',
            message: 'Enter new property name:',
            defaultValue: currentKey,
            placeholder: 'propertyName',
            required: true,
            validator: (value) => {
                if ( ! value.trim() ) return 'Property name is required';
                if ( !/^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(value) ) {
                    return 'Invalid property name (use letters, numbers, _ or $)';
                }
                const parentPath = path.split('.').slice( 0, -1 ).join('.');
                const parent     = this._getValueAtPath( parentPath );
                if ( parent && typeof parent === 'object' && ! Array.isArray( parent ) ) {
                    if ( value !== currentKey && value in parent ) {
                        return 'Property name already exists';
                    }
                }
                return null;
            }
        });

        if ( newKey && newKey !== currentKey ) {
            const value      = this._getValueAtPath( path );
            const parentPath = path.split('.').slice(0, -1).join('.');
            const newData    = this._cloneDeep( this.currentData );
            const newParent  = parentPath ? this._getValueAtPath( parentPath, newData ) : newData;

            delete newParent[currentKey];
            newParent[newKey] = value;

            this._updateData( newData, true );
        }
    }

    /**
     * Show value editor dialog — with boolean dropdown and locked array indexes
     * @private
     */
    async _showValueEditor( key, currentValue, currentType ) {
        return new Promise( ( resolve ) => {
            const bodyContent     = document.createElement( 'div' );
            bodyContent.className = 'json-editor-edit-dialog';

            const fieldUid   = this._uid( 'value-editor' );
            const typeId     = `${fieldUid}-type`;
            const valueId    = `${fieldUid}-value`;

            // Build the value input depending on type
            const valueInputHTML = this._buildValueInputHTML( currentValue, currentType, valueId );

            bodyContent.innerHTML = `
                <div class="edit-field">
                    <label class="edit-label" for="${typeId}">Type</label>
                    <select class="edit-type-select" id="${typeId}">
                        <option value="string"  ${currentType === 'string'  ? 'selected' : ''}>String</option>
                        <option value="number"  ${currentType === 'number'  ? 'selected' : ''}>Number</option>
                        <option value="boolean" ${currentType === 'boolean' ? 'selected' : ''}>Boolean</option>
                        <option value="null"    ${currentType === 'null'    ? 'selected' : ''}>Null</option>
                        <option value="array"   ${currentType === 'array'   ? 'selected' : ''}>Array</option>
                        <option value="object"  ${currentType === 'object'  ? 'selected' : ''}>Object</option>
                    </select>
                </div>
                <div class="edit-field edit-value-field">
                    <label class="edit-label" for="${valueId}">Value</label>
                    <div class="edit-value-container">
                        ${valueInputHTML}
                    </div>
                </div>
                <div class="edit-error" role="alert" style="display:none;"></div>
            `;

            const footerContent     = document.createElement( 'div' );
            footerContent.className = 'sje-dialog-buttons';

            const cancelBtn         = document.createElement( 'button' );
            cancelBtn.type          = 'button';
            cancelBtn.className     = 'sje-btn sje-btn-secondary';
            cancelBtn.textContent   = 'Cancel';

            const saveBtn           = document.createElement( 'button' );
            saveBtn.type            = 'button';
            saveBtn.className       = 'sje-btn sje-btn-primary';
            saveBtn.textContent     = 'Save';

            footerContent.appendChild( cancelBtn );
            footerContent.appendChild( saveBtn );

            const modal = this.dialog.open({
                title: `Edit: ${key}`,
                body: bodyContent,
                footer: footerContent,
                width: '480px'
            });

            const typeSelect      = bodyContent.querySelector( '.edit-type-select' );
            const errorDiv        = bodyContent.querySelector( '.edit-error' );
            const valueContainer  = bodyContent.querySelector( '.edit-value-container' );

            // Swap input widget when type changes - the id stays the same
            // (valueId) so the "Value" label above stays correctly associated
            typeSelect.addEventListener( 'change', () => {
                const newType       = typeSelect.value;
                valueContainer.innerHTML = this._buildValueInputHTML( this._getDefaultForType( newType ), newType, valueId );
                errorDiv.style.display  = 'none';
            });

            const getInputValue = () => {
                const boolSelect = valueContainer.querySelector( '.edit-bool-select' );
                if ( boolSelect ) return boolSelect.value;
                const textarea = valueContainer.querySelector( 'textarea' );
                if ( textarea ) return textarea.value.trim();
                const input = valueContainer.querySelector( 'input' );
                if ( input ) return input.value.trim();
                return '';
            };

            const handleSave = async () => {
                const selectedType = typeSelect.value;
                const inputValue   = getInputValue();

                try {
                    const newValue = this._parseValueByType( inputValue, selectedType );
                    await modal.close();
                    resolve( newValue );
                } catch (e) {
                    errorDiv.textContent   = e.message;
                    errorDiv.style.display = 'block';
                }
            };

            saveBtn.addEventListener(   'click', handleSave );
            cancelBtn.addEventListener( 'click', async () => {
                await modal.close();
                resolve( null );
            });
        });
    }

    /**
     * Build the appropriate input widget HTML based on type
     * @private
     * @param {*} value
     * @param {string} type
     * @param {string} [id] - id to set on the generated field, so a <label for>
     *                        pointing at it keeps working across type swaps
     */
    _buildValueInputHTML( value, type, id = '' ) {
        const idAttr = id ? ` id="${id}"` : '';

        switch ( type ) {
            case 'boolean':
                return `<select class="edit-bool-select edit-input"${idAttr}>
                    <option value="true"  ${value === true  ? 'selected' : ''}>true</option>
                    <option value="false" ${value === false ? 'selected' : ''}>false</option>
                </select>`;

            case 'null':
                return `<input type="text" class="edit-input edit-null-input"${idAttr} value="null" readonly title="Null has no editable value"/>`;

            case 'number':
                return `<input type="number" class="edit-input"${idAttr} value="${value !== '' ? value : 0}" step="any"/>`;

            case 'array':
            case 'object':
                return `<textarea class="edit-input edit-textarea"${idAttr} rows="6" spellcheck="false">${this._escapeHtml( JSON.stringify( value, null, 2 ) )}</textarea>`;

            case 'string':
            default:
                return `<textarea class="edit-input edit-textarea"${idAttr} rows="4" spellcheck="false">${this._escapeHtml( String( value ) )}</textarea>`;
        }
    }

    _getDefaultForType( type ) {
        switch ( type ) {
            case 'string':  return '';
            case 'number':  return 0;
            case 'boolean': return true;
            case 'null':    return null;
            case 'array':   return [];
            case 'object':  return {};
        }
    }

    _formatValueForEdit( value, type ) {
        if ( type === 'string' ) return value;
        if ( type === 'null'   ) return '';
        if ( type === 'object' || type === 'array' ) return JSON.stringify( value, null, 2 );
        return String( value );
    }

    _parseValueByType( input, type ) {
        switch ( type ) {
            case 'string':  return input;
            case 'number':
                const num = Number( input );
                if ( isNaN( num ) ) throw new Error( 'Invalid number' );
                return num;
            case 'boolean':
                // From the dropdown the value is already 'true' or 'false' string
                if ( input === 'true'  || input === true  ) return true;
                if ( input === 'false' || input === false ) return false;
                throw new Error( 'Invalid boolean — use the dropdown to select true or false' );
            case 'null':    return null;
            case 'array':
            case 'object':  return JSON.parse( input );
            default:        throw new Error( 'Unknown type' );
        }
    }

    async _deleteNode( path ) {
        const confirmed = await this.dialog.confirm({
            message: 'Delete this item?',
            danger: true
        });

        if ( confirmed ) {
            this._deleteAtPath( path );
        }
    }

    async _addToNode( path, parentValue ) {
        const isArray = Array.isArray( parentValue );
        const key     = isArray ? String( parentValue.length ) : await this._promptForKey();
        if ( ! key && ! isArray ) return;

        const value = await this._showValueEditor( key, '', 'string' );
        if ( value !== null && value !== undefined ) {
            const newData  = this._cloneDeep( this.currentData );
            const parent   = this._getValueAtPath( path, newData );

            if ( isArray ) {
                parent.push( value );
            } else {
                parent[key] = value;
            }

            this._updateData( newData, true );
        }
    }

    async _promptForKey() {
        return await this.dialog.prompt({
            title: 'New Property',
            message: 'Enter property name:',
            placeholder: 'propertyName',
            validator: ( value ) => {
                if ( ! value.trim() ) return 'Property name is required';
                if ( !/^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test( value ) ) return 'Invalid property name';
                return null;
            }
        });
    }

    _renderCodeView() {
        const textarea  = this.codeView.querySelector( 'textarea' );
        textarea.value  = JSON.stringify( this.currentData, null, this.options.indentSize );
    }

    _updateStatusBar() {
        const validItem = this.statusBar.querySelector( '.status-valid' );
        const sizeItem  = this.statusBar.querySelector( '.status-size' );
        const keysItem  = this.statusBar.querySelector( '.status-keys' );

        if ( this.isValid ) {
            validItem.innerHTML = '<span class="status-icon">✓</span> Valid JSON';
            validItem.className = 'status-item status-valid valid';
        } else {
            validItem.innerHTML = '<span class="status-icon">✗</span> Invalid JSON';
            validItem.className = 'status-item status-valid invalid';
            validItem.title     = this.validationErrors.join( ', ' );
        }

        const jsonStr        = JSON.stringify( this.currentData );
        const bytes          = new Blob([jsonStr]).size;
        sizeItem.textContent = this._formatBytes( bytes );

        const count          = this._countKeys( this.currentData );
        keysItem.textContent = `${count} ${count === 1 ? 'key' : 'keys'}`;
    }

    _updateToolbarState() {
        const undoBtn = this.toolbar?.querySelector( '[data-action="undo"]' );
        const redoBtn = this.toolbar?.querySelector( '[data-action="redo"]' );
        if ( undoBtn ) undoBtn.disabled = this.historyIndex <= 0;
        if ( redoBtn ) redoBtn.disabled = this.historyIndex >= this.history.length - 1;
    }

    _updateData( newData, addToHistory = true ) {
        this.currentData = newData;
        this._validate();

        if ( addToHistory && this.options.enableUndo ) {
            this._addToHistory( newData );
        }

        if ( this.targetElement.tagName === 'TEXTAREA' ) {
            this.targetElement.value = JSON.stringify( newData, null, 2 );
        }

        this._triggerEvent( 'change', newData );
        this._render();
    }

    _validate() {
        this.validationErrors = [];
        this.isValid          = true;

        try {
            JSON.stringify( this.currentData );

            if ( this.options.validator ) {
                const result = this.options.validator( this.currentData );
                if ( result !== true && result !== null && result !== undefined ) {
                    this.isValid = false;
                    this.validationErrors.push( result );
                }
            }
        } catch (e) {
            this.isValid = false;
            this.validationErrors.push( e.message );
        }

        this._triggerEvent( 'validate', { isValid: this.isValid, errors: this.validationErrors });
    }

    _addToHistory( data ) {
        this.history = this.history.slice( 0, this.historyIndex + 1 );
        this.history.push( this._cloneDeep( data ) );

        if ( this.history.length > this.options.maxHistorySize ) {
            this.history.shift();
        } else {
            this.historyIndex++;
        }
    }

    /**
     * Perform drag-to-reorder: move fromPath to position relative to toPath
     * Works for both arrays (splice) and objects (key re-insertion)
     * @private
     */
    _performReorder( fromPath, toPath, insertBefore ) {
        // Paths must share the same parent
        const fromParts  = fromPath.split('.');
        const toParts    = toPath.split('.');

        const fromParentPath = fromParts.slice( 0, -1 ).join('.');
        const toParentPath   = toParts.slice( 0, -1 ).join('.');

        // Only reorder within the same parent container
        if ( fromParentPath !== toParentPath ) return;

        const fromKey = fromParts[fromParts.length - 1];
        const toKey   = toParts[toParts.length - 1];
        if ( fromKey === toKey ) return;

        const newData = this._cloneDeep( this.currentData );
        const parent  = fromParentPath
            ? this._getValueAtPath( fromParentPath, newData )
            : newData;

        if ( Array.isArray( parent ) ) {
            const fromIdx = parseInt( fromKey );
            const toIdx   = parseInt( toKey );
            const [item]  = parent.splice( fromIdx, 1 );
            // After splice, recalculate insertion index
            const insertIdx = fromIdx < toIdx
                ? ( insertBefore ? toIdx - 1 : toIdx )
                : ( insertBefore ? toIdx : toIdx + 1 );
            parent.splice( insertIdx, 0, item );
        } else if ( typeof parent === 'object' && parent !== null ) {
            // Rebuild object with new key order
            const keys      = Object.keys( parent );
            const fromIdx   = keys.indexOf( fromKey );
            const toIdx     = keys.indexOf( toKey );
            if ( fromIdx === -1 || toIdx === -1 ) return;

            keys.splice( fromIdx, 1 );
            const adjustedToIdx = fromIdx < toIdx ? toIdx - 1 : toIdx;
            const insertAt  = insertBefore ? adjustedToIdx : adjustedToIdx + 1;
            keys.splice( insertAt, 0, fromKey );

            const reordered = {};
            keys.forEach( k => { reordered[k] = parent[k]; });

            // Replace parent contents with reordered object
            if ( fromParentPath ) {
                const grandParentPath = fromParts.slice( 0, -2 ).join('.');
                const grandParent     = grandParentPath
                    ? this._getValueAtPath( grandParentPath, newData )
                    : newData;
                const parentKey = fromParts[fromParts.length - 2];
                grandParent[parentKey] = reordered;
            } else {
                // Root-level object — replace entire data
                Object.keys( newData ).forEach( k => delete newData[k] );
                Object.assign( newData, reordered );
            }
        }

        this._updateData( newData, true );
    }

    /*
    |------------------------------
    |NOTIFICATION & DIALOG SYSTEM
    |------------------------------
    | Backing implementation for the public `this.notification` / `this.dialog`
    | APIs set up in the constructor. Kept as private methods on the editor
    | itself rather than a separate class - there is nothing here a consumer
    | of the editor should instantiate or reach directly.
    */

    /**
     * Render an in-editor toast notification. Always scoped inside
     * this.notificationArea, which lives inside the themed editor
     * container - no separate theme handling needed here.
     * @private
     */
    _notify( message, type = 'info', options = {} ) {
        if ( ! this.notificationArea ) {
            return;
        }

        const duration = options.duration ?? ( type === 'error' ? 4500 : 3200 );

        const toast     = document.createElement( 'div' );
        toast.className = `sje-toast sje-toast-${type}`;
        toast.innerHTML = `
            <span class="sje-toast-icon" aria-hidden="true">${this._getNotificationIcon( type )}</span>
            <span class="sje-toast-message">${this._escapeHtml( message )}</span>
            <button type="button" class="sje-toast-close" aria-label="Dismiss notification">
                <svg width="10" height="10" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 2l10 10M12 2L2 12"/></svg>
            </button>
        `;

        let dismissTimer = null;
        const remove = () => {
            clearTimeout( dismissTimer );
            toast.classList.remove( 'open' );
            setTimeout( () => {
                if ( toast.parentNode ) {
                    toast.parentNode.removeChild( toast );
                }
            }, 180 );
        };

        toast.querySelector( '.sje-toast-close' ).addEventListener( 'click', remove );
        toast.addEventListener( 'mouseenter', () => clearTimeout( dismissTimer ) );
        toast.addEventListener( 'mouseleave', () => { dismissTimer = setTimeout( remove, 1200 ); });

        this.notificationArea.appendChild( toast );
        requestAnimationFrame( () => toast.classList.add( 'open' ) );

        dismissTimer = setTimeout( remove, duration );
    }

    /**
     * @private
     */
    _getNotificationIcon( type ) {
        switch ( type ) {
            case 'success': return '✓';
            case 'error':   return '✗';
            case 'warning': return '!';
            default:        return 'i';
        }
    }

    /**
     * Open a generic, page-level dialog (used directly for the value
     * editor / import / export screens, and internally by prompt/confirm/alert).
     * Escapes the editor's own container by mounting on document.body, since
     * the editor is fixed-height with clipped overflow.
     * @private
     * @returns {{ element: HTMLElement, close: () => Promise<void> }}
     */
    _openDialog( { title = '', body = null, footer = null, width = '480px', role = 'dialog' } = {} ) {
        const overlay     = document.createElement( 'div' );
        overlay.className = `sje-dialog-overlay theme-${this.options.theme}`;

        const modal           = document.createElement( 'div' );
        modal.className       = 'sje-dialog';
        modal.style.maxWidth  = width;
        modal.setAttribute( 'role', role );
        modal.setAttribute( 'aria-modal', 'true' );
        if ( title ) {
            modal.setAttribute( 'aria-label', title );
        }

        if ( title ) {
            const header       = document.createElement( 'div' );
            header.className   = 'sje-dialog-header';

            const titleElement       = document.createElement( 'h3' );
            titleElement.className   = 'sje-dialog-title';
            titleElement.textContent = title;
            header.appendChild( titleElement );

            const closeBtn        = document.createElement( 'button' );
            closeBtn.type         = 'button';
            closeBtn.className    = 'sje-dialog-close';
            closeBtn.setAttribute( 'aria-label', 'Close dialog' );
            closeBtn.innerHTML    = `<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M2 2l10 10M12 2L2 12"/></svg>`;
            closeBtn.addEventListener( 'click', () => close() );
            header.appendChild( closeBtn );

            modal.appendChild( header );
        }

        const bodyWrap     = document.createElement( 'div' );
        bodyWrap.className = 'sje-dialog-body';
        if ( body ) {
            bodyWrap.appendChild( body );
        }
        modal.appendChild( bodyWrap );

        if ( footer ) {
            const footerWrap     = document.createElement( 'div' );
            footerWrap.className = 'sje-dialog-footer';
            footerWrap.appendChild( footer );
            modal.appendChild( footerWrap );
        }

        overlay.appendChild( modal );
        document.body.appendChild( overlay );

        const previouslyFocused = document.activeElement;

        overlay.addEventListener( 'mousedown', ( e ) => {
            if ( e.target === overlay ) {
                close();
            }
        });

        const keydownHandler = ( e ) => {
            if ( e.key === 'Escape' ) {
                close();
            }
        };
        document.addEventListener( 'keydown', keydownHandler );

        let closed = false;
        const close = async () => {
            if ( closed ) {
                return;
            }
            closed = true;

            document.removeEventListener( 'keydown', keydownHandler );
            overlay.classList.remove( 'open' );

            await new Promise( resolve => setTimeout( resolve, 140 ) );

            if ( overlay.parentNode ) {
                overlay.parentNode.removeChild( overlay );
            }
            if ( previouslyFocused && typeof previouslyFocused.focus === 'function' ) {
                previouslyFocused.focus();
            }
        };

        requestAnimationFrame( () => {
            overlay.classList.add( 'open' );
            const focusable = modal.querySelector( 'input, textarea, select, button' );
            if ( focusable ) {
                focusable.focus();
            }
        });

        return { element: overlay, close };
    }

    /**
     * Single-line text prompt dialog.
     * @private
     * @returns {Promise<string|null>} the entered value, or null if cancelled
     */
    _dialogPrompt( { title = 'Input', message = '', defaultValue = '', placeholder = '', required = false, validator = null } = {} ) {
        return new Promise( ( resolve ) => {
            const inputId  = this._uid( 'prompt' );
            const body     = document.createElement( 'div' );
            body.className = 'sje-prompt-dialog';
            body.innerHTML = `
                ${message ? `<label class="sje-prompt-message" for="${inputId}">${this._escapeHtml( message )}</label>` : ''}
                <input type="text" id="${inputId}" class="edit-input sje-prompt-input" value="${this._escapeHtml( defaultValue )}" placeholder="${this._escapeHtml( placeholder )}"${message ? '' : ` aria-label="${this._escapeHtml( title )}"`}>
                <div class="edit-error sje-dialog-error" role="alert" style="display:none;"></div>
            `;

            const footer     = document.createElement( 'div' );
            footer.className = 'sje-dialog-buttons';

            const cancelBtn       = document.createElement( 'button' );
            cancelBtn.type        = 'button';
            cancelBtn.className   = 'sje-btn sje-btn-secondary';
            cancelBtn.textContent = 'Cancel';

            const okBtn       = document.createElement( 'button' );
            okBtn.type        = 'button';
            okBtn.className   = 'sje-btn sje-btn-primary';
            okBtn.textContent = 'OK';

            footer.appendChild( cancelBtn );
            footer.appendChild( okBtn );

            const modal = this._openDialog( { title, body, footer, width: '420px' } );

            const input    = body.querySelector( '.sje-prompt-input' );
            const errorDiv = body.querySelector( '.sje-dialog-error' );

            const showError = ( msg ) => {
                errorDiv.textContent   = msg;
                errorDiv.style.display = 'block';
            };

            const submit = async () => {
                const value = input.value;

                if ( required && ! value.trim() ) {
                    showError( 'This field is required' );
                    return;
                }

                if ( validator ) {
                    const err = validator( value );
                    if ( err ) {
                        showError( err );
                        return;
                    }
                }

                await modal.close();
                resolve( value );
            };

            okBtn.addEventListener( 'click', submit );
            cancelBtn.addEventListener( 'click', async () => {
                await modal.close();
                resolve( null );
            });
            input.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    submit();
                }
            });
            input.addEventListener( 'input', () => { errorDiv.style.display = 'none'; });
        });
    }

    /**
     * Yes/no confirmation dialog.
     * @private
     * @returns {Promise<boolean>}
     */
    _dialogConfirm( { title = 'Confirm', message = '', danger = false, confirmLabel = 'Confirm', cancelLabel = 'Cancel' } = {} ) {
        return new Promise( ( resolve ) => {
            const body     = document.createElement( 'div' );
            body.className = 'sje-confirm-dialog';
            body.innerHTML = `<p class="sje-confirm-message">${this._escapeHtml( message )}</p>`;

            const footer     = document.createElement( 'div' );
            footer.className = 'sje-dialog-buttons';

            const cancelBtn       = document.createElement( 'button' );
            cancelBtn.type        = 'button';
            cancelBtn.className   = 'sje-btn sje-btn-secondary';
            cancelBtn.textContent = cancelLabel;

            const confirmBtn       = document.createElement( 'button' );
            confirmBtn.type        = 'button';
            confirmBtn.className   = `sje-btn ${danger ? 'sje-btn-danger' : 'sje-btn-primary'}`;
            confirmBtn.textContent = confirmLabel;

            footer.appendChild( cancelBtn );
            footer.appendChild( confirmBtn );

            const modal = this._openDialog( { title, body, footer, width: '400px', role: 'alertdialog' } );

            confirmBtn.addEventListener( 'click', async () => { await modal.close(); resolve( true ); });
            cancelBtn.addEventListener( 'click', async () => { await modal.close(); resolve( false ); });
        });
    }

    /**
     * Single-button informational dialog (a styled window.alert()).
     * @private
     * @returns {Promise<void>}
     */
    _dialogAlert( { title = 'Notice', message = '', okLabel = 'OK' } = {} ) {
        return new Promise( ( resolve ) => {
            const body     = document.createElement( 'div' );
            body.className = 'sje-alert-dialog';
            body.innerHTML = `<p class="sje-alert-message">${this._escapeHtml( message )}</p>`;

            const footer     = document.createElement( 'div' );
            footer.className = 'sje-dialog-buttons';

            const okBtn       = document.createElement( 'button' );
            okBtn.type        = 'button';
            okBtn.className   = 'sje-btn sje-btn-primary';
            okBtn.textContent = okLabel;
            footer.appendChild( okBtn );

            const modal = this._openDialog( { title, body, footer, width: '380px', role: 'alertdialog' } );

            okBtn.addEventListener( 'click', async () => {
                await modal.close();
                resolve();
            });
        });
    }

    /*
    |------------
    |PUBLIC API
    |------------
    */

    async getData()          { return Promise.resolve( this._cloneDeep( this.currentData ) ); }
    async setData( data )    { this._updateData( data, true ); return Promise.resolve( true ); }
    async getJSON( pretty = true ) {
        return Promise.resolve( JSON.stringify( this.currentData, null, pretty ? this.options.indentSize : 0 ) );
    }
    async setJSON( jsonString ) {
        try {
            const parsed = JSON.parse( jsonString );
            return await this.setData( parsed );
        } catch (e) {
            this._handleError( 'Invalid JSON string', e );
        }
    }

    /**
     * Show the Import dialog (File / URL / Paste tabs)
     */
    async showImportDialog() {
        return new Promise( resolve => {
            const dialogUid = this._uid( 'import' );
            const fileInputId = `${dialogUid}-file`;
            const urlId        = `${dialogUid}-url`;
            const pasteId       = `${dialogUid}-paste`;

            const body     = document.createElement( 'div' );
            body.className = 'sje-io-dialog';
            body.innerHTML = `
                <div class="sje-tabs" role="tablist">
                    <button type="button" class="sje-tab active" id="${dialogUid}-tab-file" data-tab="file"  role="tab" aria-selected="true" aria-controls="${dialogUid}-panel-file">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M3 2h6l4 4v8H3z"/><path d="M9 2v4h4"/>
                        </svg>
                        From File
                    </button>
                    <button type="button" class="sje-tab" id="${dialogUid}-tab-url" data-tab="url" role="tab" aria-selected="false" aria-controls="${dialogUid}-panel-url">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M6 8a3 3 0 0 0 4.5.5l2-2a3 3 0 0 0-4.24-4.24l-1.14 1.13"/>
                            <path d="M10 8a3 3 0 0 0-4.5-.5l-2 2a3 3 0 0 0 4.24 4.24l1.13-1.13"/>
                        </svg>
                        From URL
                    </button>
                    <button type="button" class="sje-tab" id="${dialogUid}-tab-paste" data-tab="paste" role="tab" aria-selected="false" aria-controls="${dialogUid}-panel-paste">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M5.5 2h5M5.5 2a1 1 0 0 0-1 1v1h7V3a1 1 0 0 0-1-1m-5 0a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1"/><rect x="3" y="4" width="10" height="11" rx="1"/>
                        </svg>
                        Paste JSON
                    </button>
                </div>

                <div class="sje-tab-panels">
                    <div class="sje-panel active" id="${dialogUid}-panel-file" data-panel="file" role="tabpanel" aria-labelledby="${dialogUid}-tab-file">
                        <div class="sje-drop-zone">
                            <svg width="32" height="32" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                                <path d="M16 6v14m0-14l-5 5m5-5l5 5"/>
                                <path d="M4 22v2a2 2 0 0 0 2 2h20a2 2 0 0 0 2-2v-2"/>
                            </svg>
                            <p>Drop a <strong>.json</strong> file here</p>
                            <span>or</span>
                            <button type="button" class="sje-browse-btn">Browse files…</button>
                            <input type="file" id="${fileInputId}" class="sje-file-input" accept=".json,application/json" style="display:none" aria-label="Choose a JSON file to import">
                        </div>
                    </div>

                    <div class="sje-panel" id="${dialogUid}-panel-url" data-panel="url" role="tabpanel" aria-labelledby="${dialogUid}-tab-url">
                        <div class="sje-url-field">
                            <label class="edit-label" for="${urlId}">JSON endpoint URL</label>
                            <input type="url" id="${urlId}" class="edit-input sje-url-input" placeholder="https://api.example.com/data.json">
                            <p class="sje-hint">The URL must return a valid JSON response. CORS must be enabled on the server.</p>
                        </div>
                        <div class="sje-fetch-status" role="status" aria-live="polite" style="display:none"></div>
                    </div>

                    <div class="sje-panel" id="${dialogUid}-panel-paste" data-panel="paste" role="tabpanel" aria-labelledby="${dialogUid}-tab-paste">
                        <label class="edit-label" for="${pasteId}">Paste or type JSON</label>
                        <textarea id="${pasteId}" class="edit-input edit-textarea sje-paste-input" rows="8" spellcheck="false" placeholder='{ "key": "value" }'></textarea>
                        <button type="button" class="sje-clipboard-btn">
                            <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                                <path d="M5.5 2h5M5.5 2a1 1 0 0 0-1 1v1h7V3a1 1 0 0 0-1-1m-5 0a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1"/><rect x="3" y="4" width="10" height="11" rx="1"/>
                            </svg>
                            Paste from clipboard
                        </button>
                    </div>
                </div>
                <div class="sje-dialog-error" role="alert" style="display:none"></div>
            `;

            const footer     = document.createElement( 'div' );
            footer.className = 'sje-dialog-buttons';
            const cancelBtn  = document.createElement( 'button' );
            cancelBtn.type   = 'button'; cancelBtn.className = 'sje-btn sje-btn-secondary'; cancelBtn.textContent = 'Cancel';
            const importBtn  = document.createElement( 'button' );
            importBtn.type   = 'button'; importBtn.className = 'sje-btn sje-btn-primary'; importBtn.textContent = 'Import';
            footer.appendChild( cancelBtn ); footer.appendChild( importBtn );

            const modal = this.dialog.open({ title: 'Import JSON', body, footer, width: '520px' });

            // Tab switching
            const tabs   = body.querySelectorAll( '.sje-tab' );
            const panels = body.querySelectorAll( '.sje-panel' );
            let activeTab = 'file';

            tabs.forEach( tab => {
                tab.addEventListener( 'click', () => {
                    activeTab = tab.dataset.tab;
                    tabs.forEach( t   => { t.classList.toggle( 'active', t.dataset.tab === activeTab ); t.setAttribute('aria-selected', t.dataset.tab === activeTab); });
                    panels.forEach( p => p.classList.toggle( 'active', p.dataset.panel === activeTab ) );
                    errorDiv.style.display = 'none';
                });
            });

            const errorDiv     = body.querySelector( '.sje-dialog-error' );
            const showError    = msg => { errorDiv.textContent = msg; errorDiv.style.display = 'block'; };
            const clearError   = ()  => { errorDiv.style.display = 'none'; };

            // File tab — drop zone + browse
            const dropZone   = body.querySelector( '.sje-drop-zone' );
            const fileInput  = body.querySelector( '.sje-file-input' );
            const browseBtn  = body.querySelector( '.sje-browse-btn' );

            browseBtn.addEventListener( 'click', () => fileInput.click() );

            dropZone.addEventListener( 'dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
            dropZone.addEventListener( 'dragleave', () => dropZone.classList.remove('drag-over') );
            dropZone.addEventListener( 'drop', e => {
                e.preventDefault(); dropZone.classList.remove('drag-over');
                const file = e.dataTransfer.files[0];
                if ( file ) this._setDropZoneFile( dropZone, file );
            });
            fileInput.addEventListener( 'change', () => {
                if ( fileInput.files[0] ) this._setDropZoneFile( dropZone, fileInput.files[0] );
            });

            // Clipboard paste
            body.querySelector( '.sje-clipboard-btn' ).addEventListener( 'click', async () => {
                try {
                    const text = await navigator.clipboard.readText();
                    body.querySelector( '.sje-paste-input' ).value = text;
                    clearError();
                } catch {
                    showError( 'Clipboard access denied — paste manually into the box.' );
                }
            });

            // Import action
            importBtn.addEventListener( 'click', async () => {
                clearError();
                try {
                    if ( activeTab === 'file' ) {
                        const file = fileInput.files[0] || dropZone._selectedFile;
                        if ( ! file ) { showError( 'Please select or drop a JSON file.' ); return; }
                        const text   = await file.text();
                        const parsed = JSON.parse( text );
                        await modal.close();
                        await this.setData( parsed );
                        if ( this.options.verbose ) this.notification.success( `Imported "${file.name}" successfully` );

                    } else if ( activeTab === 'url' ) {
                        const url = body.querySelector( '.sje-url-input' ).value.trim();
                        if ( ! url ) { showError( 'Please enter a URL.' ); return; }
                        const statusEl = body.querySelector( '.sje-fetch-status' );
                        statusEl.textContent = 'Fetching…'; statusEl.style.display = 'block';
                        importBtn.disabled   = true;
                        const res    = await fetch( url );
                        if ( ! res.ok ) throw new Error( `HTTP ${res.status} ${res.statusText}` );
                        const parsed = await res.json();
                        await modal.close();
                        await this.setData( parsed );
                        if ( this.options.verbose ) this.notification.success( 'Imported from URL successfully' );

                    } else if ( activeTab === 'paste' ) {
                        const raw = body.querySelector( '.sje-paste-input' ).value.trim();
                        if ( ! raw ) { showError( 'Please paste or type some JSON.' ); return; }
                        const parsed = JSON.parse( raw );
                        await modal.close();
                        await this.setData( parsed );
                        if ( this.options.verbose ) this.notification.success( 'JSON imported successfully' );
                    }
                    resolve( true );
                } catch (err) {
                    importBtn.disabled = false;
                    showError( err.message || 'Failed to import JSON.' );
                }
            });

            cancelBtn.addEventListener( 'click', async () => { await modal.close(); resolve( false ); });
        });
    }

    /**
     * Set a selected file on the drop zone UI
     * @private
     */
    _setDropZoneFile( dropZone, file ) {
        dropZone._selectedFile = file;
        const p = dropZone.querySelector( 'p' );
        if ( p ) {
            p.innerHTML = `<strong>${this._escapeHtml( file.name )}</strong> <span style="opacity:0.6">(${this._formatBytes(file.size)})</span>`;
        }
        dropZone.classList.add( 'has-file' );
    }

    /**
     * Show the Export dialog (Download / Copy)
     */
    async showExportDialog() {
        return new Promise( resolve => {
            const body     = document.createElement( 'div' );
            body.className = 'sje-io-dialog';

            const dialogUid    = this._uid( 'export' );
            const filenameId   = `${dialogUid}-filename`;
            const formatId     = `${dialogUid}-format`;
            const copyFormatId = `${dialogUid}-copy-format`;
            const previewId    = `${dialogUid}-preview`;

            const currentFilename = (() => {
                const inp = this.toolbar?.querySelector( '.toolbar-filename-input' );
                return inp?.value?.trim() || 'data.json';
            })();

            body.innerHTML = `
                <div class="sje-tabs" role="tablist">
                    <button type="button" class="sje-tab active" id="${dialogUid}-tab-download" data-tab="download" role="tab" aria-selected="true" aria-controls="${dialogUid}-panel-download">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round">
                            <path d="M8 11V3m0 8l-3-3m3 3l3-3"/><path d="M2 14h12"/>
                        </svg>
                        Download
                    </button>
                    <button type="button" class="sje-tab" id="${dialogUid}-tab-copy" data-tab="copy" role="tab" aria-selected="false" aria-controls="${dialogUid}-panel-copy">
                        <svg width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="5" y="5" width="9" height="10" rx="1"/><path d="M3 11V3a1 1 0 0 1 1-1h6"/>
                        </svg>
                        Copy
                    </button>
                </div>

                <div class="sje-tab-panels">
                    <div class="sje-panel active" id="${dialogUid}-panel-download" data-panel="download" role="tabpanel" aria-labelledby="${dialogUid}-tab-download">
                        <div class="edit-field">
                            <label class="edit-label" for="${filenameId}">Filename</label>
                            <input type="text" id="${filenameId}" class="edit-input sje-filename-input" value="${this._escapeHtml(currentFilename)}" placeholder="data.json">
                        </div>
                        <div class="edit-field">
                            <label class="edit-label" for="${formatId}">Format</label>
                            <select id="${formatId}" class="edit-input sje-format-select">
                                <option value="pretty">Pretty-printed (indented)</option>
                                <option value="compact">Compact (minified)</option>
                            </select>
                        </div>
                    </div>

                    <div class="sje-panel" id="${dialogUid}-panel-copy" data-panel="copy" role="tabpanel" aria-labelledby="${dialogUid}-tab-copy">
                        <div class="edit-field">
                            <label class="edit-label" for="${copyFormatId}">Format</label>
                            <select id="${copyFormatId}" class="edit-input sje-copy-format-select">
                                <option value="pretty">Pretty-printed (indented)</option>
                                <option value="compact">Compact (minified)</option>
                            </select>
                        </div>
                        <div class="edit-field">
                            <label class="edit-label" for="${previewId}">Preview</label>
                            <textarea id="${previewId}" class="edit-input edit-textarea sje-copy-preview" rows="6" readonly spellcheck="false"></textarea>
                        </div>
                    </div>
                </div>
                <div class="sje-dialog-error" role="alert" style="display:none"></div>
            `;

            const footer    = document.createElement( 'div' );
            footer.className = 'sje-dialog-buttons';
            const cancelBtn = document.createElement( 'button' );
            cancelBtn.type  = 'button'; cancelBtn.className = 'sje-btn sje-btn-secondary'; cancelBtn.textContent = 'Cancel';
            const actionBtn = document.createElement( 'button' );
            actionBtn.type  = 'button'; actionBtn.className = 'sje-btn sje-btn-primary'; actionBtn.textContent = 'Download';
            footer.appendChild( cancelBtn ); footer.appendChild( actionBtn );

            const modal = this.dialog.open({ title: 'Export JSON', body, footer, width: '480px' });

            const tabs    = body.querySelectorAll( '.sje-tab' );
            const panels  = body.querySelectorAll( '.sje-panel' );
            let activeTab = 'download';

            const getJSON = ( format ) => JSON.stringify( this.currentData, null, format === 'pretty' ? this.options.indentSize : 0 );

            // Populate copy preview immediately
            const updateCopyPreview = () => {
                const fmt     = body.querySelector( '.sje-copy-format-select' ).value;
                const preview = body.querySelector( '.sje-copy-preview' );
                if ( preview ) preview.value = getJSON( fmt );
            };
            updateCopyPreview();

            body.querySelector( '.sje-copy-format-select' )?.addEventListener( 'change', updateCopyPreview );

            tabs.forEach( tab => {
                tab.addEventListener( 'click', () => {
                    activeTab = tab.dataset.tab;
                    tabs.forEach(  t => { t.classList.toggle( 'active', t.dataset.tab === activeTab ); t.setAttribute('aria-selected', t.dataset.tab === activeTab); });
                    panels.forEach( p => p.classList.toggle( 'active', p.dataset.panel === activeTab ) );
                    actionBtn.textContent = activeTab === 'download' ? 'Download' : 'Copy to Clipboard';
                });
            });

            actionBtn.addEventListener( 'click', async () => {
                try {
                    if ( activeTab === 'download' ) {
                        let filename = body.querySelector( '.sje-filename-input' ).value.trim() || 'data.json';
                        if ( ! filename.endsWith( '.json' ) ) filename += '.json';
                        const fmt        = body.querySelector( '.sje-format-select' ).value;
                        const jsonString = getJSON( fmt );
                        const blob       = new Blob( [jsonString], { type: 'application/json' } );
                        const url        = URL.createObjectURL( blob );
                        const a          = document.createElement( 'a' );
                        a.href = url; a.download = filename; a.click();
                        URL.revokeObjectURL( url );
                        await modal.close();
                        if ( this.options.verbose ) this.notification.success( `Downloaded "${filename}"` );

                    } else {
                        const fmt = body.querySelector( '.sje-copy-format-select' ).value;
                        await navigator.clipboard.writeText( getJSON( fmt ) );
                        await modal.close();
                        if ( this.options.verbose ) this.notification.success( 'Copied to clipboard' );
                    }
                    resolve( true );
                } catch (err) {
                    const errDiv = body.querySelector( '.sje-dialog-error' );
                    errDiv.textContent = err.message || 'Export failed.';
                    errDiv.style.display = 'block';
                }
            });

            cancelBtn.addEventListener( 'click', async () => { await modal.close(); resolve( false ); });
        });
    }

    async importFromFile() { return this.showImportDialog(); }

    async #_loadFile( file ) {
        try {
            const text   = await file.text();
            const parsed = JSON.parse( text );
            await this.setData( parsed );
            if ( this.options.verbose ) this.notification.success( 'File imported successfully' );
        } catch (err) {
            this._handleError( 'Failed to parse or load JSON file', err );
            throw err;
        }
    }

    async exportToFile( filename = null ) {
        if ( ! filename ) {
            return this.showExportDialog();
        }
        if ( ! filename.endsWith( '.json' ) ) filename += '.json';
        const jsonString = await this.getJSON( true );
        const blob       = new Blob( [jsonString], { type: 'application/json' } );
        const url        = URL.createObjectURL( blob );
        const a          = document.createElement( 'a' );
        a.href = url; a.download = filename; a.click();
        URL.revokeObjectURL( url );
        if ( this.options.verbose ) this.notification.success( `Downloaded "${filename}"` );
    }

    async copyToClipboard() {
        const jsonString = await this.getJSON( true );
        try {
            await navigator.clipboard.writeText( jsonString );
            if ( this.options.verbose ) this.notification.success( 'Copied to clipboard' );
        } catch (e) {
            this._handleError( 'Failed to copy to clipboard', e );
        }
    }

    async pasteFromClipboard() {
        try {
            const text    = await navigator.clipboard.readText();
            const success = await this.setJSON( text );
            if ( success && this.options.verbose ) this.notification.success( 'Pasted from clipboard' );
        } catch (e) {
            const msg = e?.name === 'NotAllowedError'
                ? 'Clipboard permission blocked. Please allow access or paste manually.'
                : 'Failed to paste from clipboard';
            this._handleError( msg, e );
        }
    }

    undo() {
        if ( this.historyIndex > 0 ) {
            this.historyIndex--;
            this.currentData = this._cloneDeep( this.history[this.historyIndex] );
            this._render();
            this._triggerEvent( 'change', this.currentData );
        }
    }

    redo() {
        if ( this.historyIndex < this.history.length - 1 ) {
            this.historyIndex++;
            this.currentData = this._cloneDeep( this.history[this.historyIndex] );
            this._render();
            this._triggerEvent( 'change', this.currentData );
        }
    }

    format() {
        if ( this.currentMode === 'code' ) this._renderCodeView();
    }

    compact() {
        if ( this.currentMode === 'code' ) {
            this.codeView.querySelector( 'textarea' ).value = JSON.stringify( this.currentData );
        }
    }

    toggleSortKeys() {
        this.options.sortKeys = ! this.options.sortKeys;
        const sortBtn = this.toolbar?.querySelector( '[data-action="sort"]' );
        if ( sortBtn ) sortBtn.classList.toggle( 'active', this.options.sortKeys );
        this._render();
    }

    setMode( mode ) {
        if ( mode !== 'tree' && mode !== 'code' ) return;

        this.currentMode         = mode;
        this.container.className = this.container.className.replace( /mode-\w+/, `mode-${mode}` );

        const treeBtn = this.toolbar?.querySelector( '[data-action="mode-tree"]' );
        const codeBtn = this.toolbar?.querySelector( '[data-action="mode-code"]' );
        if ( treeBtn ) { treeBtn.classList.toggle( 'active', mode === 'tree' ); treeBtn.setAttribute( 'aria-checked', mode === 'tree' ); }
        if ( codeBtn ) { codeBtn.classList.toggle( 'active', mode === 'code' ); codeBtn.setAttribute( 'aria-checked', mode === 'code' ); }

        this._render();
        this._triggerEvent( 'modeChange', mode );
    }

    expandAll()   { this._expandAllPaths( this.currentData, '' ); this._render(); }
    collapseAll() { this.expandedPaths.clear(); this._render(); }

    _expandAllPaths( obj, path ) {
        this.expandedPaths.add( path );

        if ( Array.isArray( obj ) ) {
            obj.forEach( ( item, index ) => {
                const childPath = path ? `${path}.${index}` : String(index);
                if ( typeof item === 'object' && item !== null ) this._expandAllPaths( item, childPath );
            });
        } else if ( typeof obj === 'object' && obj !== null ) {
            Object.keys( obj ).forEach( key => {
                const childPath = path ? `${path}.${key}` : key;
                if ( typeof obj[key] === 'object' && obj[key] !== null ) this._expandAllPaths( obj[key], childPath );
            });
        }
    }

    toggleTheme() {
        const newTheme       = this.options.theme === 'light' ? 'dark' : 'light';
        this.options.theme   = newTheme;
        this.container.className = this.container.className.replace( /theme-\w+/, `theme-${newTheme}` );

        const themeBtn = this.toolbar?.querySelector( '[data-action="theme"]' );
        if ( themeBtn ) {
            const icon = themeBtn.querySelector( '.theme-icon' );
            if ( icon ) icon.innerHTML = this._getThemeIconSVG();
            themeBtn.setAttribute( 'aria-pressed', String( newTheme === 'dark' ) );
        }
    }

    /**
     * Toggle toolbar collapsed state
     */
    toggleToolbar() {
        this.toolbarCollapsed = ! this.toolbarCollapsed;
        this.container.classList.toggle( 'toolbar-collapsed', this.toolbarCollapsed );

        const toggleBtn = this.toolbar?.querySelector( '.toolbar-toggle-btn' );
        if ( toggleBtn ) {
            toggleBtn.setAttribute( 'aria-expanded', String( ! this.toolbarCollapsed ) );
            toggleBtn.title = this.toolbarCollapsed ? 'Show toolbar' : 'Hide toolbar';
        }
    }

    /**
     * Toggle fullscreen mode - the editor takes over the full viewport,
     * above all other page content, until toggled off again or Escape
     * is pressed. Locks background scroll while active.
     * @param {boolean} [force] - pass true/false to set the state explicitly
     *                            instead of toggling it
     */
    toggleFullscreen( force ) {
        const next = typeof force === 'boolean' ? force : ! this.isFullscreen;
        if ( next === this.isFullscreen ) {
            return;
        }

        this.isFullscreen = next;
        this.container.classList.toggle( 'is-fullscreen', this.isFullscreen );
        document.body.classList.toggle( 'sje-fullscreen-lock', this.isFullscreen );

        const btn = this.toolbar?.querySelector( '[data-action="fullscreen"]' );
        if ( btn ) {
            btn.setAttribute( 'aria-pressed', String( this.isFullscreen ) );
            btn.setAttribute( 'aria-label', this.isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen' );
            btn.title = this.isFullscreen ? 'Exit fullscreen (Esc)' : 'Toggle fullscreen';

            const icon = btn.querySelector( '.fullscreen-icon' );
            if ( icon ) icon.innerHTML = this._getFullscreenIconSVG();
        }

        this._triggerEvent( 'fullscreenChange', this.isFullscreen );
    }

    async validate() {
        this._validate();
        return Promise.resolve({ isValid: this.isValid, errors: this.validationErrors });
    }

    setTitle( title ) {
        this.options.title = title;
        const titleElement = this.container.querySelector( '.json-editor-header-title' );

        if ( title ) {
            if ( titleElement ) {
                titleElement.textContent = title;
            } else {
                let header = this.container.querySelector( '.json-editor-header' );
                if ( ! header ) {
                    header = document.createElement( 'div' );
                    header.className = 'json-editor-header';
                    this.container.insertBefore( header, this.container.firstChild );
                }
                const titleEl       = document.createElement( 'h2' );
                titleEl.className   = 'json-editor-header-title';
                titleEl.textContent = title;
                header.insertBefore( titleEl, header.firstChild );
            }
        } else if ( titleElement ) {
            titleElement.remove();
            const header    = this.container.querySelector( '.json-editor-header' );
            const descElement = this.container.querySelector( '.json-editor-header-description' );
            if ( header && ! descElement ) header.remove();
        }

        return this;
    }

    setDescription( description ) {
        this.options.description = description;
        const descElement = this.container.querySelector( '.json-editor-header-description' );

        if ( description ) {
            if ( descElement ) {
                descElement.textContent = description;
            } else {
                let header = this.container.querySelector( '.json-editor-header' );
                if ( ! header ) {
                    header = document.createElement( 'div' );
                    header.className = 'json-editor-header';
                    this.container.insertBefore( header, this.container.firstChild );
                }
                const descEl       = document.createElement( 'p' );
                descEl.className   = 'json-editor-header-description';
                descEl.textContent = description;
                header.appendChild( descEl );
            }
        } else if ( descElement ) {
            descElement.remove();
            const header       = this.container.querySelector( '.json-editor-header' );
            const titleElement = this.container.querySelector( '.json-editor-header-title' );
            if ( header && ! titleElement ) header.remove();
        }

        return this;
    }

    on( event, handler ) {
        if ( this.eventHandlers[event] ) this.eventHandlers[event].push( handler );
        return this;
    }

    off( event, handler ) {
        if ( this.eventHandlers[event] ) {
            this.eventHandlers[event] = this.eventHandlers[event].filter( h => h !== handler );
        }
        return this;
    }

    _triggerEvent( eventName, data ) {
        if ( this.eventHandlers[eventName] ) {
            this.eventHandlers[eventName].forEach( handler => handler( data ) );
        }
        if ( eventName === 'change' && this.options.onChange ) this.options.onChange( data );
        if ( eventName === 'error'  && this.options.onError  ) this.options.onError( data );
    }

    async destroy() {
        if ( this.isFullscreen ) document.body.classList.remove( 'sje-fullscreen-lock' );
        if ( this.targetElement.tagName === 'TEXTAREA' ) this.targetElement.style.display = '';
        if ( this.container && this.container.parentNode ) this.container.parentNode.removeChild( this.container );
        if ( this._resizeObserver ) this._resizeObserver.disconnect();

        this.currentData   = null;
        this.history       = [];
        this.eventHandlers = {};

        return Promise.resolve();
    }

    /*
    |-----------
    |UTILITIES
    |-----------
    */

    _getValueAtPath( path, obj = this.currentData ) {
        if ( ! path ) return obj;
        return path.split('.').reduce( (cur, part) => cur == null ? undefined : cur[part], obj );
    }

    _setValueAtPath( path, value ) {
        const newData = this._cloneDeep( this.currentData );
        const parts   = path.split('.');
        let current   = newData;

        for ( let i = 0; i < parts.length - 1; i++ ) current = current[parts[i]];
        current[parts[parts.length - 1]] = value;

        this._updateData( newData, true );
    }

    _deleteAtPath( path ) {
        const newData = this._cloneDeep( this.currentData );
        const parts   = path.split('.');
        let current   = newData;

        for ( let i = 0; i < parts.length - 1; i++ ) current = current[parts[i]];
        const lastKey = parts[parts.length - 1];

        if ( Array.isArray( current ) ) {
            current.splice( parseInt( lastKey ), 1 );
        } else {
            delete current[lastKey];
        }

        this._updateData( newData, true );
    }

    _getType( value ) {
        if ( value === null )       return 'null';
        if ( Array.isArray(value) ) return 'array';
        return typeof value;
    }

    _cloneDeep( obj ) { return JSON.parse( JSON.stringify( obj ) ); }

    _countKeys( obj ) {
        let count = 0;
        if ( Array.isArray( obj ) ) {
            count += obj.length;
            obj.forEach( item => { if ( typeof item === 'object' && item !== null ) count += this._countKeys( item ); });
        } else if ( typeof obj === 'object' && obj !== null ) {
            const keys = Object.keys( obj );
            count += keys.length;
            keys.forEach( key => { if ( typeof obj[key] === 'object' && obj[key] !== null ) count += this._countKeys( obj[key] ); });
        }
        return count;
    }

    _formatBytes( bytes ) {
        if ( bytes === 0 ) return '0 B';
        const k     = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i     = Math.floor( Math.log( bytes ) / Math.log( k ) );
        return Math.round( bytes / Math.pow( k, i ) * 100 ) / 100 + ' ' + sizes[i];
    }

    _escapeHtml( text ) {
        const div       = document.createElement( 'div' );
        div.textContent = String( text );
        return div.innerHTML;
    }

    /**
     * Generate a unique, DOM-safe id for a form field, scoped to this editor
     * instance (this.id is already unique per instance) plus a running
     * counter so repeated dialog opens never collide.
     * @private
     */
    _uid( prefix = 'field' ) {
        this._uidCounter += 1;
        return `${this.id}-${prefix}-${this._uidCounter}`;
    }

    _matchesSearch( key, value ) {
        if ( ! this.searchTerm ) return true;
        const term       = this.searchTerm.toLowerCase();
        const keyMatch   = String( key ).toLowerCase().includes( term );
        const valueMatch = String( value ).toLowerCase().includes( term );
        return keyMatch || valueMatch;
    }

    _hasMatchingChildren( obj ) {
        if ( typeof obj !== 'object' || obj === null ) return false;
        if ( Array.isArray( obj ) ) {
            return obj.some( ( item, index ) => this._matchesSearch( index, item ) || this._hasMatchingChildren( item ) );
        }
        return Object.keys( obj ).some( key => this._matchesSearch( key, obj[key] ) || this._hasMatchingChildren( obj[key] ) );
    }

    _handleError( message, error = null ) {
        this._triggerEvent( 'error', { message, error } );
        this.notification.error( message );
    }
}