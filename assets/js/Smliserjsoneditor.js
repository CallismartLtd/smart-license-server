/**
 * SmliserJsonEditor - Modern, Accessible JSON Editor
 * 
 * A comprehensive JSON editor with visual tree view, inline editing,
 * validation, import/export, undo/redo, and full keyboard navigation.
 * 
 * Features:
 * - Visual tree editor with expand/collapse
 * - Inline editing of all JSON types (string, number, boolean, null, array, object)
 * - Dual-mode: Tree view & Raw JSON view
 * - Import/Export JSON files
 * - Copy/Paste JSON
 * - Undo/Redo with history
 * - Real-time validation
 * - Search & filter
 * - Keyboard shortcuts
 * - Drag & drop file support
 * - Dark/Light theme
 * - Fully accessible (WCAG 2.1 AA)
 * - Promise-based API
 * 
 * @author Callistus Nwachukwu
 * @version 1.0.0
 * @license MIT
 * 
 * @example
 * // Mount on textarea
 * const editor = new SmliserJsonEditor('#json-editor', {
 *     data: { name: 'John', age: 30 },
 *     theme: 'dark',
 *     readOnly: false
 * });
 * 
 * @example
 * // Get edited data
 * const data = await editor.getData();
 * console.log(data);
 * 
 * @example
 * // Listen to changes
 * editor.on('change', (data) => {
 *     console.log('JSON updated:', data);
 * });
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
     */
    constructor( target, options = {} ) {
        // Target element
        this.targetElement = typeof target === 'string' 
            ? document.querySelector( target ) 
            : target;

        if ( ! this.targetElement ) {
            throw new Error('SmliserJsonEditor: Target element not found');
        }

        // Options
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
            ...options
        };

        // State
        this.currentData        = null;
        this.currentMode        = this.options.mode;
        this.history            = [];
        this.historyIndex       = -1;
        this.isValid            = true;
        this.validationErrors   = [];
        this.searchTerm         = '';
        this.expandedPaths      = new Set();

        // UI Elements
        this.container          = null;
        this.toolbar            = null;
        this.treeView           = null;
        this.codeView           = null;
        this.statusBar          = null;

        // Event handlers
        this.eventHandlers = {
            change: [],
            error: [],
            validate: [],
            modeChange: []
        };

        // Unique ID
        this.id = 'smliser-json-editor-' + Date.now();

        // Initialize
        this._init();
    }

    /**
     * Initialize editor
     * @private
     */
    _init() {
        this._setInitialData();

        this._buildUI();

        this._setupEventListeners();

        this._render();

        // Add to history
        if ( this.options.enableUndo ) {
            this._addToHistory( this.currentData );
        }
    }

    /**
     * Set initial data
     * @private
     */
    _setInitialData() {
        let data = this.options.data;

        // Try to parse textarea value if target is textarea
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

        // Default to empty object
        this.currentData = data !== null && data !== undefined ? data : {};

        // Validate
        this._validate();
    }

    /**
     * Build UI structure
     * @private
     */
    _buildUI() {
        // Create container
        this.container              = document.createElement( 'div' );
        this.container.className    = `smliser-json-editor theme-${this.options.theme} mode-${this.currentMode}`;
        this.container.id           = this.id;
        this.container.tabIndex     = "0";
        this.container.setAttribute( 'role', 'application' );
        this.container.setAttribute( 'aria-label', 'JSON Editor' );

        // Build header (title and description)
        if ( this.options.title || this.options.description ) {
            this._buildHeader();
        }

        // Build toolbar
        if ( this.options.showToolbar ) {
            this._buildToolbar();
        }

        // Build views
        this._buildViews();

        // Build status bar
        this._buildStatusBar();

        // Replace target or append
        if ( this.targetElement.tagName === 'TEXTAREA' ) {
            this.targetElement.style.display = 'none';
            this.targetElement.parentNode.insertBefore( this.container, this.targetElement.nextSibling );
        } else {
            this.targetElement.innerHTML = '';
            this.targetElement.appendChild( this.container );
        }

        this.container.focus();
    }

    /**
     * Build header with title and description
     * @private
     */
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

    /**
     * Build toolbar
     * @private
     */
    _buildToolbar() {
        this.toolbar            = document.createElement( 'div' );
        this.toolbar.className  = 'json-editor-toolbar';
        this.toolbar.setAttribute( 'role', 'toolbar' );
        this.toolbar.setAttribute( 'aria-label', 'Editor toolbar' );

        const toolbarHTML = `
            <div class="toolbar-group">
                <button class="toolbar-btn" data-action="import" title="Upload JSON file (Ctrl+O)" aria-label="Upload JSON file">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M8 3v7m0-7l-3 3m3-3l3 3"/>
                        <path d="M2 12v2h12v-2"/>
                    </svg>
                    <span>Upload File</span>
                </button>
                <button class="toolbar-btn" data-action="export" title="Download JSON file (Ctrl+S)" aria-label="Download JSON file">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M8 11V3m0 8l-3-3m3 3l3-3"/>
                        <path d="M2 14h12"/>
                    </svg>
                    <span>Download</span>
                </button>
                <input type="text" class="toolbar-filename-input" value="data.json" title="Filename for export" aria-label="Output filename" placeholder="filename.json">
            </div>

            <div class="toolbar-group">
                <button class="toolbar-btn" data-action="copy" title="Copy JSON to clipboard (Ctrl+C)" aria-label="Copy JSON">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <rect x="5" y="5" width="9" height="10" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/>
                        <path d="M3 11V3a1 1 0 0 1 1-1h6" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span>Copy</span>
                </button>
                <button class="toolbar-btn" data-action="paste" title="Paste JSON from clipboard (Ctrl+V)" aria-label="Paste JSON">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M5.5 2h5M5.5 2a1 1 0 0 0-1 1v1h7V3a1 1 0 0 0-1-1m-5 0a1 1 0 0 1 1-1h3a1 1 0 0 1 1 1" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <rect x="3" y="4" width="10" height="11" rx="1" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    </svg>
                    <span>Paste</span>
                </button>
            </div>

            <div class="toolbar-group">
                <button class="toolbar-btn" data-action="undo" title="Undo (Ctrl+Z)" aria-label="Undo" ${!this.options.enableUndo ? 'disabled' : ''}>
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M4 8h8a3 3 0 0 1 0 6H9" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <path d="M4 8l3-3M4 8l3 3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span>Undo</span>
                </button>
                <button class="toolbar-btn" data-action="redo" title="Redo (Ctrl+Y)" aria-label="Redo" ${!this.options.enableUndo ? 'disabled' : ''}>
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M12 8H4a3 3 0 0 0 0 6h3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <path d="M12 8l-3-3M12 8l-3 3" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span>Redo</span>
                </button>
            </div>

            <div class="toolbar-group">
                <button class="toolbar-btn" data-action="format" title="Format JSON (prettify)" aria-label="Format JSON">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M2 4h12M2 8h9M2 12h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>Format</span>
                </button>
                <button class="toolbar-btn" data-action="compact" title="Compact JSON (minify)" aria-label="Compact JSON">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M2 5h12M2 8h12M2 11h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                    <span>Compact</span>
                </button>
                <button class="toolbar-btn ${this.options.sortKeys ? 'active' : ''}" data-action="sort" title="Sort keys alphabetically" aria-label="Sort keys">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M4 3v10m0 0l-2-2m2 2l2-2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                        <path d="M12 13V3m0 0L10 5m2-2l2 2" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span>Sort Keys</span>
                </button>
            </div>

            <div class="toolbar-group">
                <div class="toolbar-segmented" role="radiogroup" aria-label="Editor mode">
                    <button class="toolbar-btn ${this.currentMode === 'tree' ? 'active' : ''}" data-action="mode-tree" title="Tree view" role="radio" aria-checked="${this.currentMode === 'tree'}">
                        <svg width="16" height="16" viewBox="0 0 16 16">
                            <rect x="2" y="2" width="5" height="3" rx="0.5" fill="currentColor"/>
                            <rect x="2" y="7" width="5" height="3" rx="0.5" fill="currentColor"/>
                            <rect x="9" y="7" width="5" height="3" rx="0.5" fill="currentColor"/>
                            <path d="M4.5 5v2M4.5 7h4.5M9 7v2.5" stroke="currentColor" stroke-width="1" fill="none"/>
                        </svg>
                        <span>Tree</span>
                    </button>
                    <button class="toolbar-btn ${this.currentMode === 'code' ? 'active' : ''}" data-action="mode-code" title="Code view" role="radio" aria-checked="${this.currentMode === 'code'}">
                        <svg width="16" height="16" viewBox="0 0 16 16">
                            <path d="M5 4L2 8l3 4M11 4l3 4-3 4M9 2L7 14" stroke="currentColor" stroke-width="1.5" fill="none" stroke-linecap="round"/>
                        </svg>
                        <span>Code</span>
                    </button>
                </div>
            </div>

            ${this.options.enableSearch ? `
            <div class="toolbar-group toolbar-search">
                <input type="search" class="toolbar-search-input" placeholder="Search keys or values..." aria-label="Search JSON">
            </div>
            ` : ''}

            <div class="toolbar-group toolbar-actions">
                <button class="toolbar-btn" data-action="expand-all" title="Expand all nodes" aria-label="Expand all">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M8 3v10M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Expand</span>
                </button>
                <button class="toolbar-btn" data-action="collapse-all" title="Collapse all nodes" aria-label="Collapse all">
                    <svg width="16" height="16" viewBox="0 0 16 16">
                        <path d="M3 8h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <span>Collapse</span>
                </button>
                <button class="toolbar-btn" data-action="theme" title="Toggle light/dark theme" aria-label="Toggle theme">
                    <svg width="16" height="16" viewBox="0 0 16 16" class="theme-icon">
                        ${this.options.theme === 'light' ? `
                            <circle cx="8" cy="8" r="4" fill="currentColor"/>
                            <line x1="8" y1="1" x2="8" y2="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="8" y1="14" x2="8" y2="15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="1" y1="8" x2="2" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="14" y1="8" x2="15" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="3" y1="3" x2="4" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="12" y1="12" x2="13" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="13" y1="3" x2="12" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                            <line x1="4" y1="12" x2="3" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                        ` : `
                            <path d="M14 8c0-3.314-2.686-6-6-6C4.686 2 2 4.686 2 8s2.686 6 6 6c3.314 0 6-2.686 6-6z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M14 8c0 3.314-2.686 6-6 6V2c3.314 0 6 2.686 6 6z" fill="currentColor"/>
                        `}
                    </svg>
                </button>
            </div>
        `;

        this.toolbar.innerHTML = toolbarHTML;
        this.container.appendChild( this.toolbar );
    }

    /**
     * Build views (tree and code)
     * @private
     */
    _buildViews() {
        const viewsContainer        = document.createElement( 'div' );
        viewsContainer.className    = 'json-editor-views';

        // Tree view
        this.treeView           = document.createElement( 'div' );
        this.treeView.className = 'json-editor-tree-view';
        this.treeView.setAttribute( 'role', 'tree' );
        this.treeView.setAttribute( 'aria-label', 'JSON tree view' );
        viewsContainer.appendChild(this.treeView);

        // Code view
        this.codeView           = document.createElement( 'div' );
        this.codeView.className = 'json-editor-code-view';
        
        const codeTextarea      = document.createElement( 'textarea' );
        codeTextarea.className  = 'json-editor-code-textarea';
        codeTextarea.setAttribute( 'spellcheck', 'false' );
        codeTextarea.setAttribute( 'aria-label', 'JSON code editor' );
        codeTextarea.placeholder = 'Enter or paste JSON here...';
        // Allow editing unless explicitly read-only
        if ( this.options.readOnly ) {
            codeTextarea.readOnly = true;
        }
        
        this.codeView.appendChild( codeTextarea );
        viewsContainer.appendChild( this.codeView );

        this.container.appendChild( viewsContainer );
    }

    /**
     * Build status bar
     * @private
     */
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
     * Setup event listeners
     * @private
     */
    _setupEventListeners() {
        // Toolbar actions
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
        if (codeTextarea) {
            codeTextarea.addEventListener( 'input', () => {
                this._handleCodeChange();
            });
        }

        // Keyboard shortcuts
        document.addEventListener( 'keydown', (e) => {
            if (!this.container.contains( document.activeElement ) ) return;
            this._handleKeyboardShortcuts( e );
        });

        // File drop
        this.container.addEventListener( 'dragover', (e) => {
            e.preventDefault();
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

    /**
     * Handle toolbar actions
     * @private
     */
    async _handleToolbarAction( action ) {
        switch ( action ) {
            case 'import':
                await this.importFromFile();
                break;
            case 'export':
                await this.exportToFile();
                break;
            case 'copy':
                await this.copyToClipboard();
                break;
            case 'paste':
                await this.pasteFromClipboard();
                break;
            case 'undo':
                this.undo();
                break;
            case 'redo':
                this.redo();
                break;
            case 'format':
                this.format();
                break;
            case 'compact':
                this.compact();
                break;
            case 'sort':
                this.toggleSortKeys();
                break;
            case 'mode-tree':
                this.setMode( 'tree' );
                break;
            case 'mode-code':
                this.setMode( 'code' );
                break;
            case 'expand-all':
                this.expandAll();
                break;
            case 'collapse-all':
                this.collapseAll();
                break;
            case 'theme':
                this.toggleTheme();
                break;
        }
    }

    /**
     * Handle keyboard shortcuts
     * @private
     */
    _handleKeyboardShortcuts( e ) {
        const ctrl = e.ctrlKey || e.metaKey;

        if ( ctrl && e.key === 'z' && !e.shiftKey ) {
            e.preventDefault();
            this.undo();
        } else if ( ctrl && (e.key === 'y' || ( e.key === 'z' && e.shiftKey ) ) ) {
            e.preventDefault();
            this.redo();
        } else if ( ctrl && e.key === 's' ) {
            e.preventDefault();
            this.exportToFile();
        } else if ( ctrl && e.key === 'o' ) {
            e.preventDefault();
            this.importFromFile();
        } else if ( ctrl && e.key === 'f' ) {
            e.preventDefault();
            this.toolbar?.querySelector( '.toolbar-search-input' )?.focus();
        }
    }

    /**
     * Handle code view changes
     * @private
     */
    _handleCodeChange() {
        const codeTextarea = this.codeView.querySelector( 'textarea' );
        const code = codeTextarea.value;

        try {
            const parsed = JSON.parse( code );
            this._updateData( parsed, true );
        } catch (e) {
            // Invalid JSON - show error but don't update
            this.isValid            = false;
            this.validationErrors   = [e.message];
            this._updateStatusBar();
        }
    }

    /**
     * Handle file drop
     * @private
     */
    async _handleFileDrop(e) {
        const files = e.dataTransfer.files;
        if (files.length === 0) return;

        const file  = files[0];
        if ( ! file.name.endsWith( '.json' ) ) {
            this._handleError( 'Please drop a JSON file' );
            return;
        }

        await this.#_loadFile( file );
    }

    /**
     * Render current view
     * @private
     */
    _render() {
        if ( this.currentMode === 'tree' ) {
            this._renderTreeView();
        } else {
            this._renderCodeView();
        }

        this._updateStatusBar();
        this._updateToolbarState();
    }

    /**
     * Render tree view
     * @private
     */
    _renderTreeView() {
        this.treeView.innerHTML = '';
        
        const tree = this._buildTreeNode( this.currentData, '', 'root' );
        this.treeView.appendChild( tree );
    }

    /**
     * Build tree node recursively
     * @private
     */
    _buildTreeNode( value, path, key, isLast = true ) {
        const node          = document.createElement( 'div' );
        node.className      = 'json-tree-node';
        node.dataset.path   = path;

        const type          = this._getType( value );
        const isExpandable  = type === 'object' || type === 'array';
        const isExpanded    = this.expandedPaths.has( path );
        const matchesSearch = this.searchTerm ? this._matchesSearch( key, value ) : true;

        if ( ! matchesSearch && !this._hasMatchingChildren( value ) ) {
            node.style.display = 'none';
        }

        // Node header
        const header        = document.createElement( 'div' );
        header.className    = `json-tree-header type-${type} ${matchesSearch ? 'highlight' : ''}`;
        header.setAttribute( 'role', 'treeitem' );
        header.setAttribute( 'aria-expanded', isExpandable ? isExpanded : null );

        let headerHTML = '';

        // Expand/collapse button
        if ( isExpandable ) {
            headerHTML += `
                <button class="tree-toggle" aria-label="${isExpanded ? 'Collapse' : 'Expand'}">
                    <svg width="12" height="12" class="icon-${isExpanded ? 'expanded' : 'collapsed'}">
                        <path d="${isExpanded ? 'M2 4l4 4 4-4' : 'M4 2l4 4-4 4'}"/>
                    </svg>
                </button>
            `;
        } else {
            headerHTML += '<span class="tree-spacer"></span>';
        }

        // Key
        if (key !== 'root') {
            headerHTML += `<span class="tree-key">"${this._escapeHtml( key )}"</span>`;
            headerHTML += '<span class="tree-separator">:</span>';
        }

        // Value preview
        headerHTML += `<span class="tree-value ${type}">${this._getValuePreview( value, type )}</span>`;

        // Actions (only if not read-only)
        if ( ! this.options.readOnly ) {
            headerHTML += `
                <div class="tree-actions">
                    ${key !== 'root' && type !== 'array' ? `
                    <button class="tree-action-btn" data-action="edit-key" title="Edit key name" aria-label="Edit key">
                        <svg width="14" height="14">
                            <path d="M10 2l2 2-6 6H4V8z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                            <path d="M8 4l2 2" stroke="currentColor" stroke-width="1.5"/>
                        </svg>
                    </button>
                    ` : ''}
                    <button class="tree-action-btn" data-action="edit" title="Edit value" aria-label="Edit value">
                        <svg width="14" height="14"><path d="M2 10l6-6 2 2-6 6H2z"/></svg>
                    </button>
                    ${key !== 'root' ? `
                    <button class="tree-action-btn" data-action="delete" title="Delete" aria-label="Delete">
                        <svg width="14" height="14"><path d="M3 3l8 8M11 3l-8 8"/></svg>
                    </button>
                    ` : ''}
                    ${isExpandable ? `
                    <button class="tree-action-btn" data-action="add" title="Add item" aria-label="Add item">
                        <svg width="14" height="14"><path d="M7 3v8M3 7h8"/></svg>
                    </button>
                    ` : ''}
                </div>
            `;
        }

        header.innerHTML = headerHTML;
        node.appendChild( header );

        // Children (if expanded)
        if (isExpandable && isExpanded) {
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

        // Event handlers
        const toggleBtn = header.querySelector( '.tree-toggle' );
        if (toggleBtn) {
            toggleBtn.addEventListener( 'click', (e) => {
                e.stopPropagation();
                this._toggleNode( path );
            });
        }

        // Action buttons
        const actionBtns    = header.querySelectorAll( '.tree-action-btn' );
        actionBtns.forEach( btn => {
            btn.addEventListener( 'click', (e) => {
                e.stopPropagation();
                const action = btn.dataset.action;
                this._handleNodeAction( action, path, key, value );
            });
        });

        return node;
    }

    /**
     * Get value preview for tree view
     * @private
     */
    _getValuePreview( value, type ) {
        switch (type) {
            case 'string':
                return `"${this._escapeHtml( value )}"`;
            case 'number':
            case 'boolean':
                return String( value );
            case 'null':
                return 'null';
            case 'array':
                return `Array(${value.length})`;
            case 'object':
                return `Object{${Object.keys( value ).length}}`;
            default:
                return String( value );
        }
    }

    /**
     * Toggle node expand/collapse
     * @private
     */
    _toggleNode( path ) {
        if ( this.expandedPaths.has( path ) ) {
            this.expandedPaths.delete( path );
        } else {
            this.expandedPaths.add( path );
        }
        this._render();
    }

    /**
     * Handle node actions (edit, delete, add, edit-key)
     * @private
     */
    async _handleNodeAction( action, path, key, value ) {
        switch ( action ) {
            case 'edit':
                await this._editNode( path, key, value );
                break;
            case 'edit-key':
                await this._editNodeKey( path, key );
                break;
            case 'delete':
                await this._deleteNode( path );
                break;
            case 'add':
                await this._addToNode( path, value );
                break;
        }
    }

    /**
     * Edit node value
     * @private
     */
    async _editNode( path, key, value ) {
        const type = this._getType( value );
        
        // Create inline editor based on type
        const newValue = await this._showValueEditor( key, value, type );
        
        if ( newValue !== null && newValue !== undefined ) {
            this._setValueAtPath( path, newValue );
        }
    }

    /**
     * Edit node key (rename object property)
     * @private
     */
    async _editNodeKey( path, currentKey ) {
        const newKey = await SmliserModal.prompt({
            title: 'Rename Property',
            message: 'Enter new property name:',
            defaultValue: currentKey,
            placeholder: 'propertyName',
            required: true,
            validator: (value) => {
                if (!value.trim()) return 'Property name is required';
                if (!/^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test(value)) {
                    return 'Invalid property name (use letters, numbers, _ or $)';
                }
                
                // Check if key already exists in parent
                const parentPath = path.split( '.' ).slice( 0, -1 ).join( '.' );
                const parent = this._getValueAtPath( parentPath );
                
                if ( parent && typeof parent === 'object' && ! Array.isArray( parent ) ) {
                    if ( value !== currentKey && value in parent ) {
                        return 'Property name already exists';
                    }
                }
                
                return null;
            }
        });

        if ( newKey && newKey !== currentKey ) {
            // Get the value at current path
            const value         = this._getValueAtPath( path );
            const parentPath    = path.split('.').slice(0, -1).join('.');
            const parent        = parentPath ? this._getValueAtPath( parentPath ) : this.currentData;
            const newData       = this._cloneDeep( this.currentData );
            const newParent     = parentPath ? this._getValueAtPath( parentPath, newData ) : newData;
            
            // Delete old key and add new key
            delete newParent[currentKey];
            newParent[newKey] = value;
            
            // Update data
            this._updateData( newData, true );
        }
    }

    /**
     * Show value editor dialog
     * @private
     */
    async _showValueEditor( key, currentValue, currentType ) {
        return new Promise( (resolve ) => {
            // Use SmliserModal for editing
            const bodyContent       = document.createElement( 'div' );
            bodyContent.className   = 'json-editor-edit-dialog';
            
            bodyContent.innerHTML = `
                <div class="edit-field">
                    <label>Type:</label>
                    <select class="edit-type-select">
                        <option value="string" ${currentType === 'string' ? 'selected' : ''}>String</option>
                        <option value="number" ${currentType === 'number' ? 'selected' : ''}>Number</option>
                        <option value="boolean" ${currentType === 'boolean' ? 'selected' : ''}>Boolean</option>
                        <option value="null" ${currentType === 'null' ? 'selected' : ''}>Null</option>
                        <option value="array" ${currentType === 'array' ? 'selected' : ''}>Array</option>
                        <option value="object" ${currentType === 'object' ? 'selected' : ''}>Object</option>
                    </select>
                </div>
                <div class="edit-field">
                    <label>Value:</label>
                    <textarea class="edit-value-input" rows="4">${this._formatValueForEdit( currentValue, currentType )}</textarea>
                </div>
                <div class="edit-error" style="display: none;"></div>
            `;

            const footerContent     = document.createElement( 'div' );
            footerContent.className = 'smliser-dialog-buttons';
            
            const cancelBtn         = document.createElement( 'button' );
            cancelBtn.className     = 'smliser-btn smliser-btn-secondary';
            cancelBtn.textContent   = 'Cancel';
            
            const saveBtn           = document.createElement( 'button' );
            saveBtn.className       = 'smliser-btn smliser-btn-primary';
            saveBtn.textContent     = 'Save';
            
            footerContent.appendChild( cancelBtn );
            footerContent.appendChild( saveBtn );

            const modal = new SmliserModal({
                title: `Edit: ${key}`,
                body: bodyContent,
                footer: footerContent,
                width: '500px'
            });

            const typeSelect    = bodyContent.querySelector( '.edit-type-select' );
            const valueInput    = bodyContent.querySelector( '.edit-value-input' );
            const errorDiv      = bodyContent.querySelector( '.edit-error' );

            const handleSave = async () => {
                const selectedType  = typeSelect.value;
                const inputValue    = valueInput.value.trim();

                try {
                    const newValue = this._parseValueByType( inputValue, selectedType );
                    await modal.destroy();
                    resolve( newValue );
                } catch (e) {
                    errorDiv.textContent    = e.message;
                    errorDiv.style.display  = 'block';
                }
            };

            saveBtn.addEventListener( 'click', handleSave );
            cancelBtn.addEventListener( 'click', async () => {
                await modal.destroy();
                resolve(null);
            });

            modal.open();
        });
    }

    /**
     * Format value for editing
     * @private
     */
    _formatValueForEdit( value, type ) {
        if ( type === 'string' ) return value;
        if ( type === 'null' ) return '';
        if ( type === 'object' || type === 'array' ) {
            return JSON.stringify( value, null, 2 );
        }
        return String( value );
    }

    /**
     * Parse value by type
     * @private
     */
    _parseValueByType( input, type ) {
        switch ( type ) {
            case 'string':
                return input;
            case 'number':
                const num = Number( input );
                if ( isNaN( num ) ) throw new Error( 'Invalid number' );
                return num;
            case 'boolean':
                if ( input.toLowerCase() === 'true' ) return true;
                if ( input.toLowerCase() === 'false' ) return false;
                throw new Error( 'Invalid boolean (use true or false)' );
            case 'null':
                return null;
            case 'array':
            case 'object':
                return JSON.parse( input );
            default:
                throw new Error( 'Unknown type' );
        }
    }

    /**
     * Delete node
     * @private
     */
    async _deleteNode( path ) {
        const confirmed = await SmliserModal.confirm({
            message: 'Delete this item?',
            danger: true
        });

        if ( confirmed ) {
            this._deleteAtPath( path );
        }
    }

    /**
     * Add item to node
     * @private
     */
    async _addToNode( path, parentValue ) {
        const isArray = Array.isArray( parentValue );
        
        const key = isArray ? String( parentValue.length ) : await this._promptForKey();
        if ( ! key && ! isArray ) return;

        const value = await this._showValueEditor( key, '', 'string' );
        if ( value !== null && value !== undefined ) {
            const newData   = this._cloneDeep( this.currentData );
            const parent    = this._getValueAtPath( path, newData );
            
            if ( isArray ) {
                parent.push( value );
            } else {
                parent[key] = value;
            }

            this._updateData( newData, true );
        }
    }

    /**
     * Prompt for key name
     * @private
     */
    async _promptForKey() {
        return await SmliserModal.prompt({
            title: 'New Property',
            message: 'Enter property name:',
            placeholder: 'propertyName',
            validator: ( value ) => {
                if ( ! value.trim() ) return 'Property name is required';
                if ( !/^[a-zA-Z_$][a-zA-Z0-9_$]*$/.test( value ) ) {
                    return 'Invalid property name';
                }
                return null;
            }
        });
    }

    /**
     * Render code view
     * @private
     */
    _renderCodeView() {
        const textarea  = this.codeView.querySelector( 'textarea' );
        const formatted = JSON.stringify(
            this.currentData, 
            null, 
            this.options.indentSize
        );
        textarea.value = formatted;
    }

    /**
     * Update status bar
     * @private
     */
    _updateStatusBar() {
        const validItem = this.statusBar.querySelector( '.status-valid' );
        const sizeItem = this.statusBar.querySelector( '.status-size' );
        const keysItem = this.statusBar.querySelector( '.status-keys' );

        // Validation status
        if ( this.isValid ) {
            validItem.innerHTML = '<span class="status-icon status-success">✓</span> Valid JSON';
            validItem.className = 'status-item status-valid valid';
        } else {
            validItem.innerHTML = '<span class="status-icon status-error">✗</span> Invalid JSON';
            validItem.className = 'status-item status-valid invalid';
            validItem.title = this.validationErrors.join( ', ' );
        }

        // Size
        const jsonStr           = JSON.stringify( this.currentData );
        const bytes             = new Blob([jsonStr]).size;
        sizeItem.textContent    = this._formatBytes( bytes );

        // Key count
        const count             = this._countKeys( this .currentData);
        keysItem.textContent    = `${count} ${count === 1 ? 'key' : 'keys'}`;
    }

    /**
     * Update toolbar button states
     * @private
     */
    _updateToolbarState() {
        // Undo/redo
        const undoBtn = this.toolbar?.querySelector( '[data-action="undo"]' );
        const redoBtn = this.toolbar?.querySelector( '[data-action="redo"]' );

        if ( undoBtn ) undoBtn.disabled = this.historyIndex <= 0;
        if ( redoBtn ) redoBtn.disabled = this.historyIndex >= this.history.length - 1;
    }

    /**
     * Update data and trigger change event
     * @private
     */
    _updateData( newData, addToHistory = true ) {
        this.currentData = newData;
        this._validate();

        if ( addToHistory && this.options.enableUndo ) {
            this._addToHistory( newData );
        }

        // Update textarea if present
        if ( this.targetElement.tagName === 'TEXTAREA' ) {
            this.targetElement.value    = JSON.stringify( newData, null, 2 );
        }

        this._triggerEvent( 'change', newData );

        this._render();
    }

    /**
     * Validate current data
     * @private
     */
    _validate() {
        this.validationErrors   = [];
        this.isValid            = true;

        try {
            // Try to stringify (will fail for circular refs, etc)
            JSON.stringify( this.currentData );

            // Custom validator
            if (this.options.validator) {
                const result = this.options.validator(this.currentData);
                if (result !== true && result !== null && result !== undefined) {
                    this.isValid = false;
                    this.validationErrors.push(result);
                }
            }
        } catch (e) {
            this.isValid = false;
            this.validationErrors.push( e.message );
        }

        this._triggerEvent( 'validate', { 
            isValid: this.isValid, 
            errors: this.validationErrors 
        });
    }

    /**
     * Add to history
     * @private
     */
    _addToHistory(data) {
        // Remove any history after current index
        this.history = this.history.slice( 0, this.historyIndex + 1 );

        // Add new state
        this.history.push(this._cloneDeep( data ) );

        // Limit history size
        if (this.history.length > this.options.maxHistorySize) {
            this.history.shift();
        } else {
            this.historyIndex++;
        }
    }

    // =========================================================================
    // PUBLIC API METHODS
    // =========================================================================

    /**
     * Get current JSON data
     * @returns {Promise<*>}
     */
    async getData() {
        return Promise.resolve(this._cloneDeep( this.currentData ));
    }

    /**
     * Set JSON data
     * @param {*} data - New JSON data
     * @returns {Promise<void>}
     */
    async setData(data) {
        this._updateData(data, true);
        return Promise.resolve( true );
    }

    /**
     * Get data as JSON string
     * @param {boolean} pretty - Format with indentation
     * @returns {Promise<string>}
     */
    async getJSON( pretty = true) {
        const indent = pretty ? this.options.indentSize : 0;
        return Promise.resolve( JSON.stringify( this.currentData, null, indent ) );
    }

    /**
     * Set data from JSON string
     * @param {string} jsonString - JSON string
     * @returns {Promise<void>}
     */
    async setJSON(jsonString) {
        try {
            const parsed = JSON.parse( jsonString );
            return await this.setData( parsed );
        } catch (e) {
            this._handleError( 'Invalid JSON string', e );
        }
    }

    /**
     * Import from file
     * @returns {Promise<void>}
     */
    async importFromFile() {
        return new Promise( ( resolve, reject ) => {
            const input = document.createElement( 'input' );
            input.type = 'file';
            input.accept = '.json,application/json';

            input.addEventListener( 'change', async (e) => {
                const file = e.target.files[0];
                if ( ! file ) {
                    resolve();
                    return;
                }

                try {
                    await this.#_loadFile( file );
                    resolve();
                } catch (e) {
                    reject(e);
                }
            });

            input.click();
        });
    }

    /**
     * Load file
     * @private
     */
    async #_loadFile( file ) {
        try {
            // Blob.text() is generally more optimized than FileReader
            const text      = await file.text(); 
            const parsed    = JSON.parse( text );
            
            await this.setData( parsed );
            await SmliserModal.success( 'File imported successfully' );
        } catch (err) {
            this._handleError( 'Failed to parse or load JSON file', err);
            throw err;
        }
    }

    /**
     * Export to file
     * @param {string} filename - Output filename (optional, uses input field value if not provided)
     * @returns {Promise<void>}
     */
    async exportToFile(filename = null) {
        // Get filename from toolbar input if not provided
        if ( ! filename ) {
            const filenameInput = this.toolbar?.querySelector( '.toolbar-filename-input' );
            filename = filenameInput?.value?.trim() || 'data.json';
            
            // Ensure .json extension
            if ( ! filename.endsWith( '.json' ) ) {
                filename += '.json';
            }
        }

        const jsonString    = await this.getJSON(true);
        const blob          = new Blob( [jsonString], { type: 'application/json' } );
        const url           = URL.createObjectURL(blob);

        const a             = document.createElement( 'a' );
        a.href              = url;
        a.download          = filename;
        a.click();

        URL.revokeObjectURL( url );
        await SmliserModal.success( `File "${filename}" downloaded successfully` );
    }

    /**
     * Copy JSON to clipboard
     * @returns {Promise<void>}
     */
    async copyToClipboard() {
        const jsonString = await this.getJSON( true );

        try {
            await navigator.clipboard.writeText( jsonString );
            await SmliserModal.success( 'Copied to clipboard' );
        } catch (e) {            
            this._handleError( 'Failed to copy to clipboard', e );
        }
    }

    /**
     * Paste JSON from clipboard
     * @returns {Promise<void>}
     */
    async pasteFromClipboard() {
        try {
            const text      = await navigator.clipboard.readText();
            const success   = await this.setJSON( text );
            if ( success ) {
                await SmliserModal.success( 'Pasted from clipboard' );
            }
            
        } catch (e) {
            let errorMessage    = 'Failed to paste from clipboard';

            if ( e && e.name === 'NotAllowedError' ) {
                errorMessage = 'Clipboard permission was blocked. Please allow access or paste manually.';
            }
            
            this._handleError( errorMessage, e );
        }
    }

    /**
     * Undo last change
     */
    undo() {
        if ( this.historyIndex > 0 ) {
            this.historyIndex--;
            this.currentData = this._cloneDeep( this.history[this.historyIndex] );
            this._render();
            this._triggerEvent('change', this.currentData);
        }
    }

    /**
     * Redo last undone change
     */
    redo() {
        if ( this.historyIndex < this.history.length - 1 ) {
            this.historyIndex++;
            this.currentData = this._cloneDeep( this.history[this.historyIndex] );
            this._render();
            this._triggerEvent( 'change', this.currentData );
        }
    }

    /**
     * Format JSON (prettify)
     */
    format() {
        if ( this.currentMode === 'code' ) {
            this._renderCodeView();
        }
    }

    /**
     * Compact JSON (minimize)
     */
    compact() {
        if ( this.currentMode === 'code' ) {
            const textarea = this.codeView.querySelector( 'textarea' );
            textarea.value = JSON.stringify( this.currentData );
        }
    }

    /**
     * Toggle sort keys
     */
    toggleSortKeys() {
        this.options.sortKeys = !this.options.sortKeys;
        
        const sortBtn = this.toolbar?.querySelector( '[data-action="sort"]' );
        if ( sortBtn ) {
            sortBtn.classList.toggle( 'active', this.options.sortKeys );
        }

        this._render();
    }

    /**
     * Set editor mode
     * @param {string} mode - 'tree' or 'code'
     */
    setMode( mode ) {
        if ( mode !== 'tree' && mode !== 'code' ) return;

        this.currentMode            = mode;
        this.container.className    = this.container.className.replace( /mode-\w+/, `mode-${mode}` );

        // Update toolbar
        const treeBtn   = this.toolbar?.querySelector( '[data-action="mode-tree"]' );
        const codeBtn   = this.toolbar?.querySelector( '[data-action="mode-code"]' );

        if ( treeBtn ) {
            treeBtn.classList.toggle( 'active', mode === 'tree' );
            treeBtn.setAttribute( 'aria-checked', mode === 'tree' );
        }
        if ( codeBtn ) {
            codeBtn.classList.toggle( 'active', mode === 'code' );
            codeBtn.setAttribute( 'aria-checked', mode === 'code' );
        }

        this._render();
        this._triggerEvent( 'modeChange', mode );
    }

    /**
     * Expand all nodes
     */
    expandAll() {
        this._expandAllPaths( this.currentData, '' );
        this._render();
    }

    /**
     * Expand all paths recursively
     * @private
     */
    _expandAllPaths( obj, path ) {
        this.expandedPaths.add( path );

        if (Array.isArray(obj)) {
            obj.forEach((item, index) => {
                const childPath = path ? `${path}.${index}` : String(index);
                if (typeof item === 'object' && item !== null) {
                    this._expandAllPaths(item, childPath);
                }
            });
        } else if (typeof obj === 'object' && obj !== null) {
            Object.keys(obj).forEach(key => {
                const childPath = path ? `${path}.${key}` : key;
                if (typeof obj[key] === 'object' && obj[key] !== null) {
                    this._expandAllPaths(obj[key], childPath);
                }
            });
        }
    }

    /**
     * Collapse all nodes
     */
    collapseAll() {
        this.expandedPaths.clear();
        this._render();
    }

    /**
     * Toggle theme
     */
    toggleTheme() {
        const newTheme = this.options.theme === 'light' ? 'dark' : 'light';
        this.options.theme = newTheme;
        this.container.className = this.container.className.replace(/theme-\w+/, `theme-${newTheme}`);
        
        // Update theme icon
        const themeBtn = this.toolbar?.querySelector('[data-action="theme"]');
        if (themeBtn) {
            const icon = themeBtn.querySelector('.theme-icon');
            if (icon) {
                icon.innerHTML = newTheme === 'light' ? `
                    <circle cx="8" cy="8" r="4" fill="currentColor"/>
                    <line x1="8" y1="1" x2="8" y2="2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="8" y1="14" x2="8" y2="15" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="1" y1="8" x2="2" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="14" y1="8" x2="15" y2="8" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="3" y1="3" x2="4" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="12" y1="12" x2="13" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="13" y1="3" x2="12" y2="4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    <line x1="4" y1="12" x2="3" y2="13" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                ` : `
                    <path d="M14 8c0-3.314-2.686-6-6-6C4.686 2 2 4.686 2 8s2.686 6 6 6c3.314 0 6-2.686 6-6z" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M14 8c0 3.314-2.686 6-6 6V2c3.314 0 6 2.686 6 6z" fill="currentColor"/>
                `;
            }
        }
    }

    /**
     * Validate current data
     * @returns {Promise<{isValid: boolean, errors: Array}>}
     */
    async validate() {
        this._validate();
        return Promise.resolve({
            isValid: this.isValid,
            errors: this.validationErrors
        });
    }

    /**
     * Set editor title
     * @param {string} title - New title
     * @returns {SmliserJsonEditor}
     */
    setTitle(title) {
        this.options.title = title;
        
        const titleElement = this.container.querySelector('.json-editor-header-title');
        
        if (title) {
            if (titleElement) {
                // Update existing title
                titleElement.textContent = title;
            } else {
                // Create header if it doesn't exist
                let header = this.container.querySelector('.json-editor-header');
                if (!header) {
                    header = document.createElement('div');
                    header.className = 'json-editor-header';
                    this.container.insertBefore(header, this.container.firstChild);
                }
                
                const titleEl = document.createElement('h2');
                titleEl.className = 'json-editor-header-title';
                titleEl.textContent = title;
                header.insertBefore(titleEl, header.firstChild);
            }
        } else if (titleElement) {
            // Remove title if set to null/empty
            titleElement.remove();
            
            // Remove header if both title and description are gone
            const header = this.container.querySelector('.json-editor-header');
            const descElement = this.container.querySelector('.json-editor-header-description');
            if (header && !descElement) {
                header.remove();
            }
        }
        
        return this;
    }

    /**
     * Set editor description
     * @param {string} description - New description
     * @returns {SmliserJsonEditor}
     */
    setDescription(description) {
        this.options.description = description;
        
        const descElement = this.container.querySelector('.json-editor-header-description');
        
        if (description) {
            if (descElement) {
                // Update existing description
                descElement.textContent = description;
            } else {
                // Create header if it doesn't exist
                let header = this.container.querySelector('.json-editor-header');
                if (!header) {
                    header = document.createElement('div');
                    header.className = 'json-editor-header';
                    this.container.insertBefore(header, this.container.firstChild);
                }
                
                const descEl = document.createElement('p');
                descEl.className = 'json-editor-header-description';
                descEl.textContent = description;
                header.appendChild(descEl);
            }
        } else if (descElement) {
            // Remove description if set to null/empty
            descElement.remove();
            
            // Remove header if both title and description are gone
            const header = this.container.querySelector('.json-editor-header');
            const titleElement = this.container.querySelector('.json-editor-header-title');
            if (header && !titleElement) {
                header.remove();
            }
        }
        
        return this;
    }

    /**
     * Register event handler
     * @param {string} event - Event name
     * @param {Function} handler - Handler function
     */
    on(event, handler) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event].push(handler);
        }
        return this;
    }

    /**
     * Unregister event handler
     * @param {string} event - Event name
     * @param {Function} handler - Handler function
     */
    off(event, handler) {
        if (this.eventHandlers[event]) {
            this.eventHandlers[event] = this.eventHandlers[event].filter(h => h !== handler);
        }
        return this;
    }

    /**
     * Trigger event
     * @private
     */
    _triggerEvent(eventName, data) {
        if (this.eventHandlers[eventName]) {
            this.eventHandlers[eventName].forEach(handler => {
                handler(data);
            });
        }

        // Also trigger option callbacks
        if (eventName === 'change' && this.options.onChange) {
            this.options.onChange(data);
        }
        if (eventName === 'error' && this.options.onError) {
            this.options.onError(data);
        }
    }

    /**
     * Destroy editor
     */
    async destroy() {
        // Show original textarea if hidden
        if (this.targetElement.tagName === 'TEXTAREA') {
            this.targetElement.style.display = '';
        }

        // Remove container
        if (this.container && this.container.parentNode) {
            this.container.parentNode.removeChild(this.container);
        }

        // Clear references
        this.currentData = null;
        this.history = [];
        this.eventHandlers = {};

        return Promise.resolve();
    }

    // =========================================================================
    // UTILITY METHODS
    // =========================================================================

    /**
     * Get value at path
     * @private
     */
    _getValueAtPath(path, obj = this.currentData) {
        if (!path) return obj;

        const parts = path.split('.');
        let current = obj;

        for (const part of parts) {
            if (current === null || current === undefined) return undefined;
            current = current[part];
        }

        return current;
    }

    /**
     * Set value at path
     * @private
     */
    _setValueAtPath(path, value) {
        const newData = this._cloneDeep(this.currentData);
        const parts = path.split('.');
        let current = newData;

        for (let i = 0; i < parts.length - 1; i++) {
            current = current[parts[i]];
        }

        current[parts[parts.length - 1]] = value;
        this._updateData(newData, true);
    }

    /**
     * Delete at path
     * @private
     */
    _deleteAtPath(path) {
        const newData = this._cloneDeep(this.currentData);
        const parts = path.split('.');
        let current = newData;

        for (let i = 0; i < parts.length - 1; i++) {
            current = current[parts[i]];
        }

        const lastKey = parts[parts.length - 1];

        if (Array.isArray(current)) {
            current.splice(parseInt(lastKey), 1);
        } else {
            delete current[lastKey];
        }

        this._updateData(newData, true);
    }

    /**
     * Get type of value
     * @private
     */
    _getType(value) {
        if (value === null) return 'null';
        if (Array.isArray(value)) return 'array';
        return typeof value;
    }

    /**
     * Deep clone object
     * @private
     */
    _cloneDeep(obj) {
        return JSON.parse(JSON.stringify(obj));
    }

    /**
     * Count keys recursively
     * @private
     */
    _countKeys(obj) {
        let count = 0;

        if (Array.isArray(obj)) {
            count += obj.length;
            obj.forEach(item => {
                if (typeof item === 'object' && item !== null) {
                    count += this._countKeys(item);
                }
            });
        } else if (typeof obj === 'object' && obj !== null) {
            const keys = Object.keys(obj);
            count += keys.length;
            keys.forEach(key => {
                if (typeof obj[key] === 'object' && obj[key] !== null) {
                    count += this._countKeys(obj[key]);
                }
            });
        }

        return count;
    }

    /**
     * Format bytes
     * @private
     */
    _formatBytes( bytes ) {
        if ( bytes === 0 ) return '0 B';
        const k     = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i     = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
    }

    /**
     * Escape HTML
     * @private
     */
    _escapeHtml( text ) {
        const div       = document.createElement( 'div' );
        div.textContent = text;
        return div.innerHTML;
    }

    /**
     * Check if matches search
     * @private
     */
    _matchesSearch( key, value ) {
        if ( ! this.searchTerm ) return true;

        const term          = this.searchTerm.toLowerCase();
        const keyMatch      = String( key ).toLowerCase().includes( term );
        const valueMatch    = String(value).toLowerCase().includes( term );

        return keyMatch || valueMatch;
    }

    /**
     * Check if has matching children
     * @private
     */
    _hasMatchingChildren( obj ) {
        if ( typeof obj !== 'object' || obj === null ) return false;

        if ( Array.isArray( obj ) ) {
            return obj.some( ( item, index ) => 
                this._matchesSearch( index, item ) || this._hasMatchingChildren( item )
            );
        }

        return Object.keys( obj ).some( key => 
            this._matchesSearch( key, obj[key] ) || this._hasMatchingChildren( obj[key] )
        );
    }

    /**
     * Handle error
     * @private
     */
    _handleError( message, error = null ) {        
        this._triggerEvent( 'error', { message, error } );

        SmliserModal.error( message );
    }
}

// Export if module system available
// if (typeof module !== 'undefined' && module.exports) {
//     module.exports = SmliserJsonEditor;
// }