/**
 * SmliserModal - A highly customizable, event-driven modal component
 * Supports HTMLElement instances, template strings, and custom events
 * WITH FULL ACCESSIBILITY SUPPORT AND GLOBAL THEME SUPPORT
 *
 * Theming: every instance reads SmliserModal's shared global theme
 * ('auto' | 'light' | 'dark') by default — no per-call changes needed
 * anywhere. To go dark app-wide, call SmliserModal.setGlobalTheme('dark')
 * once. To live-sync with a host app's own theme toggle, call
 * SmliserModal.watchTheme(...) once at bootstrap — the modal never reads
 * document.documentElement or guesses an attribute on its own; the host
 * must opt in explicitly via that call. An individual modal can still
 * override the global theme by passing its own `theme` option, in which
 * case that instance stops following later global changes.
 */
class SmliserModal {
    constructor( options = {} ) {
        const hasExplicitTheme = Object.prototype.hasOwnProperty.call( options, 'theme' );

        this.options = {
            title: options.title || 'Modal Title',
            body: options.body || '',
            footer: options.footer || null,
            showCloseButton: options.showCloseButton !== false,
            closeOnBackdropClick: options.closeOnBackdropClick !== false,
            closeOnEscape: options.closeOnEscape !== false,
            customClass: options.customClass || '',
            animation: options.animation !== false,
            animationDuration: options.animationDuration || 300,
            width: options.width || '70%',
            maxWidth: options.maxWidth || '90vw',
            zIndex: options.zIndex || 9999,
            autoFocus: options.autoFocus || true,
            theme: hasExplicitTheme ? options.theme : SmliserModal._globalTheme,
            themeWatch: options.themeWatch || null,
            ...options
        };

        // True unless this instance was given its own explicit `theme`
        // option — in which case it opts out of following later
        // SmliserModal.setGlobalTheme() calls.
        this._followsGlobalTheme = ! hasExplicitTheme;

        this.isOpen         = false;
        this.backdrop       = null;
        this.modal          = null;
        this.header         = null;
        this.bodyElement    = null;
        this.footerElement  = null;

        // Accessibility properties
        this.previousFocus = null;
        this.focusableElements = null;
        this.firstFocusable = null;
        this.lastFocusable = null;
        this._hiddenElements = null;
        this.titleId = 'smliser-modal-title-' + Date.now();
        this.bodyId = 'smliser-modal-body-' + Date.now();

        // Per-instance theme watcher (opt-in, rarely needed now that
        // SmliserModal.watchTheme() covers the global case).
        this._themeObserver = null;

        // Event handlers storage.
        this.eventHandlers = {
            beforeOpen: [],
            afterOpen: [],
            beforeClose: [],
            afterClose: [],
            onClick: [],
            onSubmit: [],
            onClose: []
        };

        /**
         * @var {HTMLElement|null} target - The current event target element.
         */
        this.target = null;

        SmliserModal._instances.add( this );

        this._init();
    }

    /**
     * Initialize the modal structure
     * @private
     */
    _init() {
        this._createBackdrop();
        this._createModal();
        this._setupEventListeners();
        this._applyTheme( this.options.theme );
        this._setupThemeWatcher();
    }

    /**
     * Create backdrop element
     * @private
     */
    _createBackdrop() {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'smliser-modal-backdrop';
        this.backdrop.style.zIndex = this.options.zIndex;
    }

    /**
     * Create modal structure
     * @private
     */
    _createModal() {
        this.modal = document.createElement('div');
        this.modal.className = `smliser-modal ${this.options.customClass}`;
        this.modal.style.width = this.options.width;
        this.modal.style.maxWidth = this.options.maxWidth;
        this.modal.setAttribute( 'role', 'dialog' );
        this.modal.setAttribute( 'aria-modal', 'true' );
        this.modal.setAttribute( 'aria-labelledby', this.titleId );
        this.modal.setAttribute( 'aria-describedby', this.bodyId );

        // Create header.
        this._createHeader();

        // Create body.
        this._createBody();

        // Create footer if provided.
        if ( this.options.footer !== null ) {
            this._createFooter();
        }

        this.backdrop.appendChild( this.modal );
    }

    /**
     * Create modal header
     * @private
     */
    _createHeader() {
        this.header = document.createElement( 'div' );
        this.header.className = 'smliser-modal-header';

        this.headerTitle = document.createElement( 'h2' );
        this.headerTitle.className = 'smliser-modal-title';
        this.headerTitle.id = this.titleId;
        this.headerTitle.textContent = this.options.title;
        this.headerTitle.setAttribute( 'role', 'heading' );
        this.headerTitle.setAttribute( 'aria-level', '2' );

        this.header.appendChild( this.headerTitle );

        if ( this.options.showCloseButton ) {
            const closeBtn = document.createElement( 'button' );
            closeBtn.className = 'smliser-modal-close';
            closeBtn.setAttribute( 'type', 'button' );
            closeBtn.setAttribute(
                'aria-label',
                `Close ${this.options.title} dialog`
            );

            closeBtn.innerHTML = '&times;';
            closeBtn.addEventListener( 'click', this.close.bind(this) );
            this.header.appendChild(closeBtn);
        }

        this.modal.appendChild( this.header );
    }

    /**
     * Create modal body
     * @private
     */
    _createBody() {
        this.bodyElement = document.createElement( 'div' );
        this.bodyElement.className = 'smliser-modal-body';
        this.bodyElement.id = this.bodyId;
        this._setContent( this.bodyElement, this.options.body );
        this.modal.appendChild( this.bodyElement );
    }

    /**
     * Create modal footer
     * @private
     */
    _createFooter() {
        this.footerElement = document.createElement( 'div' );
        this.footerElement.className = 'smliser-modal-footer';
        this._setContent( this.footerElement, this.options.footer );
        this.modal.appendChild( this.footerElement );
    }

    /**
     * Set content for an element (supports HTMLElement and string)
     * @private
     */
    _setContent(element, content) {
        if ( content instanceof HTMLElement ) {
            element.appendChild( content );
        } else if ( typeof content === 'string' ) {
            element.innerHTML = this.sanitize( content );
        } else if ( content !== null && content !== undefined ) {
            element.textContent = String( content );
        }
    }

    sanitize(html) {
        const div = document.createElement('div');
        div.textContent = html; // Use textContent to prevent XSS
        return div.innerHTML;
    }

    /**
     * Apply a theme to this modal instance by writing the single
     * attribute the CSS keys off (`data-smliser-theme`). 'auto'
     * clears the attribute so prefers-color-scheme governs instead.
     * @private
     * @param {'auto'|'light'|'dark'} theme
     */
    _applyTheme( theme ) {
        if ( ! this.backdrop ) {
            return;
        }

        if ( 'dark' === theme || 'light' === theme ) {
            this.backdrop.setAttribute( 'data-smliser-theme', theme );
        } else {
            this.backdrop.removeAttribute( 'data-smliser-theme' );
        }
    }

    /**
     * Override this specific instance's theme. After calling this, the
     * instance stops following SmliserModal.setGlobalTheme() — it's
     * been given an explicit instruction and won't be silently
     * overridden by a later app-wide change.
     * @param {'auto'|'light'|'dark'} theme
     * @returns {SmliserModal}
     */
    setTheme( theme ) {
        this.options.theme = theme;
        this._followsGlobalTheme = false;
        this._applyTheme( theme );
        return this;
    }

    /**
     * Optionally sync THIS instance's theme with a host-defined signal,
     * independent of the global theme. Rarely needed — prefer the
     * static SmliserModal.watchTheme() for app-wide syncing, which
     * needs to be set up only once. Use this only when one specific
     * modal must track a different signal than the rest of the app.
     *
     * The modal never reads document.documentElement or any other host
     * element on its own; a host that wants this must opt in explicitly:
     *
     *   themeWatch: {
     *     target: someElement,
     *     attribute: 'data-theme',
     *     resolve: (value) => value === 'dark' ? 'dark' : 'light'
     *   }
     * @private
     */
    _setupThemeWatcher() {
        const watch = this.options.themeWatch;

        if ( ! watch || ! watch.target || ! watch.attribute ) {
            return;
        }

        this._followsGlobalTheme = false;

        const resolve = typeof watch.resolve === 'function'
            ? watch.resolve
            : ( value ) => value;

        const sync = () => {
            const raw = watch.target.getAttribute( watch.attribute );
            this.setTheme( resolve( raw ) );
        };

        sync(); // Initial sync so the modal opens already matching the host.

        this._themeObserver = new MutationObserver( sync );
        this._themeObserver.observe( watch.target, {
            attributes: true,
            attributeFilter: [ watch.attribute ],
        } );
    }

    /**
     * Stop this instance's own theme watcher, if one was set up.
     * @private
     */
    _teardownThemeWatcher() {
        if ( this._themeObserver ) {
            this._themeObserver.disconnect();
            this._themeObserver = null;
        }
    }

    /**
     * Set the theme used by every SmliserModal instance that hasn't
     * been given its own explicit `theme` option or its own
     * `themeWatch`. Already-created, still-tracked instances are
     * updated immediately; any instance created afterwards inherits
     * this value automatically, with no per-call changes needed.
     *
     * @param {'auto'|'light'|'dark'} theme
     */
    static setGlobalTheme( theme ) {
        SmliserModal._globalTheme = theme;

        SmliserModal._instances.forEach( ( instance ) => {
            if ( instance._followsGlobalTheme ) {
                instance._applyTheme( theme );
            }
        } );
    }

    /**
     * @returns {'auto'|'light'|'dark'} The current global theme.
     */
    static getGlobalTheme() {
        return SmliserModal._globalTheme;
    }

    /**
     * Opt the whole app into live theme syncing, ONCE, globally —
     * instead of wiring `themeWatch` into every individual modal call.
     * Observes a host element/attribute and pushes resolved changes
     * through setGlobalTheme(), which then reaches every tracked
     * instance (open or yet to be created).
     *
     * The modal has no opinion on how a host app represents its theme
     * (a data-attribute, a class, etc.), so it never reads any host
     * element unless told to via this call.
     *
     * @param {Object} config
     * @param {HTMLElement} config.target - Element to observe (e.g. document.documentElement).
     * @param {string} config.attribute - Attribute to watch (e.g. 'data-theme').
     * @param {(value: string|null) => ('auto'|'light'|'dark')} [config.resolve] - Maps the raw attribute value to a theme. Defaults to passing the raw value through.
     *
     * @example
     * SmliserModal.watchTheme({
     *     target: document.documentElement,
     *     attribute: 'data-theme',
     *     resolve: ( value ) => value === 'dark' ? 'dark' : 'light',
     * });
     * // Every SmliserModal.alert(), .confirm(), new SmliserModal(...)
     * // call anywhere in the app now follows this automatically.
     */
    static watchTheme( config ) {
        if ( ! config || ! config.target || ! config.attribute ) {
            return;
        }

        SmliserModal.unwatchTheme();

        const resolve = typeof config.resolve === 'function'
            ? config.resolve
            : ( value ) => value;

        const sync = () => {
            const raw = config.target.getAttribute( config.attribute );
            SmliserModal.setGlobalTheme( resolve( raw ) );
        };

        sync(); // Initial sync so the current global theme matches the host right away.

        SmliserModal._globalThemeObserver = new MutationObserver( sync );
        SmliserModal._globalThemeObserver.observe( config.target, {
            attributes: true,
            attributeFilter: [ config.attribute ],
        } );
    }

    /**
     * Stop the global theme watcher started by watchTheme(), if any.
     */
    static unwatchTheme() {
        if ( SmliserModal._globalThemeObserver ) {
            SmliserModal._globalThemeObserver.disconnect();
            SmliserModal._globalThemeObserver = null;
        }
    }

    /**
     * Setup global event listeners
     * @private
     */
    _setupEventListeners() {
        if ( this.options.closeOnEscape ) {
            this._handleEscape = (e) => {
                if ( e.key === 'Escape' && this.isOpen ) {
                    this.close();
                }
            };
        }

        if (this.options.closeOnBackdropClick) {
            this.backdrop.addEventListener( 'click', (e) => e.target === this.backdrop && this.close() );
        }

        this.backdrop.addEventListener( 'submit', async (e) => {
            e.preventDefault()
            await this._triggerEvent( 'onSubmit', e.target );
        });

        // Bind click events.
        this.backdrop.addEventListener( 'click', async (e) => {
            await this._triggerEvent( 'onClick', e.target );
        });


    }

    /**
     * Get all focusable elements within the modal
     * @private
     */
    _getFocusableElements() {
        const focusableSelectors = [
            'a[href]',
            'button:not([disabled])',
            'textarea:not([disabled])',
            'input:not([disabled])',
            'select:not([disabled])',
            '[tabindex]:not([tabindex="-1"])'
        ].join(', ');
        
        return this.modal.querySelectorAll( focusableSelectors );
    }

    /**
     * Trap focus within modal
     * @private
     */
    _trapFocus() {
        this.focusableElements = this._getFocusableElements();
        
        if ( this.focusableElements.length === 0 ) return;
        
        this.firstFocusable = this.focusableElements[0];
        this.lastFocusable = this.focusableElements[ this.focusableElements.length - 1 ];
        
        this._handleTabKey = ( e ) => {
            if ( e.key !== 'Tab' || ! this.isOpen ) return;
            
            if ( e.shiftKey ) {
                if ( document.activeElement === this.firstFocusable ) {
                    e.preventDefault();
                    this.lastFocusable.focus();
                }
            } else {
                if ( document.activeElement === this.lastFocusable ) {
                    e.preventDefault();
                    this.firstFocusable.focus();
                }
            }
        };
        
        document.addEventListener( 'keydown', this._handleTabKey );
    }

    /**
     * Remove focus trap
     * @private
     */
    _removeFocusTrap() {
        if ( this._handleTabKey ) {
            document.removeEventListener( 'keydown', this._handleTabKey );
        }
    }

    /**
     * Manage focus on modal open
     * @private
     */
    _manageFocus() {
        this.previousFocus = document.activeElement;
        this.previousFocus.blur(); // So aria-live regions can announce the modal opening.
        
        if ( ! this.options.autoFocus ) return;

        const focusableElements = this._getFocusableElements();
        if ( focusableElements.length > 0 ) {
            setTimeout( () => focusableElements[0].focus(), 100 );
        } else {
            this.modal.setAttribute( 'tabindex', '-1' );
            setTimeout( () => this.modal.focus(), 100 );
        }
    }

    /**
     * Restore focus to previously focused element
     * @private
     */
    _restoreFocus() {
        if ( this.previousFocus && typeof this.previousFocus.focus === 'function' ) {
            this.previousFocus.focus();
        }
        this.previousFocus = null;
    }

    /**
     * Hide external content from screen readers
     * @private
     */
    _hideExternalContent() {
        const bodyChildren = Array.from( document.body.children ).filter( 
            child => child !== this.backdrop 
        );
        
        this._hiddenElements = bodyChildren.map( element => {
            const originalAriaHidden = element.getAttribute( 'aria-hidden' );
            element.setAttribute( 'aria-hidden', 'true' );
            return { element, originalAriaHidden };
        });
    }

    /**
     * Restore external content visibility
     * @private
     */
    _restoreExternalContent() {
        if ( ! this._hiddenElements ) return;
        
        this._hiddenElements.forEach( ({ element, originalAriaHidden }) => {
            if ( originalAriaHidden === null ) {
                element.removeAttribute( 'aria-hidden' );
            } else {
                element.setAttribute( 'aria-hidden', originalAriaHidden );
            }
        });
        
        this._hiddenElements = null;
    }

    /**
     * Open the modal asynchronously
     * @returns {Promise<SmliserModal>}
     */
    async open() {
        if ( this.isOpen ) return this;
        // Trigger beforeOpen event.
        await this._triggerEvent( 'beforeOpen', null );

        // Append to body.
        document.body.appendChild( this.backdrop );
        
        // Add open class with slight delay for animation.
        if ( this.options.animation ) {
            await this._delay(10);
        }
        
        this.backdrop.classList.add( 'smliser-modal-open' );
        this.isOpen = true;

        // Accessibility enhancements
        this._hideExternalContent();
        this._trapFocus();
        this._manageFocus();

        // Add escape key listener.
        if ( this.options.closeOnEscape ) {
            document.addEventListener( 'keydown', this._handleEscape );
        }

        // Trigger afterOpen event.
        if (this.options.animation) {
            await this._delay( this.options.animationDuration );
        }
        await this._triggerEvent( 'afterOpen', this.backdrop );
        
        return this;
    }

    /**
     * Close the modal asynchronously
     * @returns {Promise<SmliserModal>}
     */
    async close() {
        if ( ! this.isOpen ) return this;

        // Trigger beforeClose event.
        await this._triggerEvent( 'beforeClose', this.backdrop );

        // Remove open class.
        this.backdrop.classList.remove( 'smliser-modal-open' );

        if (this.options.animation) {
            await this._delay( this.options.animationDuration );
        }

        // Remove from DOM.
        if ( this.backdrop.parentNode ) {
            document.body.removeChild( this.backdrop );
        }

        // Accessibility cleanup
        this._removeFocusTrap();
        this._restoreExternalContent();
        this._restoreFocus();

        this.isOpen = false;

        // Remove escape key listener.
        if ( this.options.closeOnEscape ) {
            document.removeEventListener( 'keydown', this._handleEscape );
        }

        // Trigger afterClose event.
        await this._triggerEvent( 'afterClose', null );

        return this;
    }

    /**
     * Toggle modal state
     * @returns {Promise<SmliserModal>}
     */
    async toggle() {
        return this.isOpen ? this.close() : this.open();
    }

    /**
     * Update modal title
     * @param {string} title - New title
     */
    setTitle( title ) {
        const titleElement = this.header.querySelector( '.smliser-modal-title' );
        if ( titleElement ) {
            titleElement.textContent = title;
        }
        return this;
    }

    /**
     * Update modal body content
     * @param {string|HTMLElement} content - New body content
     */
    setBody( content ) {
        this.bodyElement.innerHTML = '';
        this._setContent( this.bodyElement, content );
        return this;
    }

    /**
     * Get the body element.
     * 
     * @param {string} selector - The selector for any element in the body, 
     * the entire body element will be returned if nothing is passed. 
     */
    getBody( selector = '' ) {
        if ( ! selector ) {
            return this.bodyElement;
        }

        try {
            return this.bodyElement.querySelector( selector );
        } catch (error) {
            return null;
            
        }
    }

    /**
     * Update modal footer content
     * @param {string|HTMLElement|null} content - New footer content
     */
    setFooter( content ) {
        if ( content === null ) {
            if ( this.footerElement ) {
                this.footerElement.remove();
                this.footerElement = null;
            }
        } else {
            if ( ! this.footerElement ) {
                this._createFooter();
            } else {
                this.footerElement.innerHTML = '';
                this._setContent( this.footerElement, content );
            }
        }
        return this;
    }

    /**
     * Set the header title text.
     * 
     * @param {HTMLElement|String} text
     */
    setHeaderTitle( text ) {
        if ( text instanceof HTMLElement ) {
            const closeBtn  = this.query( 'header', '.smliser-modal-close' );
            this.headerTitle.remove();
            this.header.insertBefore( text, closeBtn );

            this.headerTitle    = text;
        } else {
            this.headerTitle.textContent = text;
        }
        
        return this;
    }

    /**
     * Query elements within modal sections
     * @param {string} section - Section name: 'header', 'body', 'footer', or 'modal'
     * @param {string} selector - CSS selector
     * @returns {Element|null}
     */
    query( section, selector ) {
        const sectionMap = {
            header: this.header,
            body: this.bodyElement,
            footer: this.footerElement,
            modal: this.modal
        };

        const element = sectionMap[section];
        return element ? element.querySelector(selector) : null;
    }

    /**
     * Query all elements within modal sections
     * @param {string} section - Section name: 'header', 'body', 'footer', or 'modal'
     * @param {string} selector - CSS selector
     * @returns {NodeList}
     */
    queryAll( section, selector ) {
        const sectionMap = {
            header: this.header,
            body: this.bodyElement,
            footer: this.footerElement,
            modal: this.modal
        };

        const element = sectionMap[ section ];
        return element ? element.querySelectorAll( selector ) : [];
    }

    /**
     * Get direct reference to modal sections
     * @param {string} section - Section name: 'header', 'body', 'footer', 'modal', or 'backdrop'
     * @returns {HTMLElement|null}
     */
    getSection( section ) {
        const sectionMap = {
            header: this.header,
            body: this.bodyElement,
            footer: this.footerElement,
            modal: this.modal,
            backdrop: this.backdrop
        };

        return sectionMap[section] || null;
    }

    /**
     * Register event handler
     * @param {string} event - Event name: 'beforeOpen', 'afterOpen', 'beforeClose', 'afterClose'
     * @param {Function} handler - Event handler function
     */
    on(event, handler) {
        if ( this.eventHandlers[event] ) {
            this.eventHandlers[event].push( handler );
        }
        return this;
    }

    /**
     * Unregister event handler
     * @param {string} event - Event name
     * @param {Function} handler - Event handler function to remove
     */
    off( event, handler ) {
        if ( this.eventHandlers[event] ) {
            this.eventHandlers[event] = this.eventHandlers[event].filter( h => h !== handler );
        }
        return this;
    }

    /**
     * Trigger event.
     * @private
     * @param {string} eventName - Event name
     * @param {HTMLElement|null} target - The target element that triggered the event
     */
    async _triggerEvent( eventName, target = null ) {

        if ( this.eventHandlers[eventName] ) {
            this.target = target;
            for ( const handler of this.eventHandlers[eventName] ) {
                await handler(this);
            }

            this.target = null;
        }
    }

    /**
     * Utility delay function
     * @private
     */
    _delay(ms) {
        return new Promise(resolve => setTimeout(resolve, ms));
    }

    /**
     * Destroy modal and clean up
     */
    async destroy() {
        if ( this.isOpen ) {
            await this.close();
        }

        this._teardownThemeWatcher();
        SmliserModal._instances.delete( this );
        
        if ( this.backdrop && this.backdrop.parentNode ) {
            document.body.removeChild( this.backdrop );
        }

        if ( this.options.closeOnEscape ) {
            document.removeEventListener( 'keydown', this._handleEscape );
        }

        // Accessibility cleanup
        this._removeFocusTrap();
        this._restoreExternalContent();
        this._restoreFocus();

        this.eventHandlers  = {};
        this.backdrop       = null;
        this.modal          = null;
        this.header         = null;
        this.bodyElement    = null;
        this.footerElement  = null;
        this.previousFocus  = null;
        this.focusableElements = null;
        this._hiddenElements = null;
    }

    /**
     * Show confirmation dialog (use instead of window.confirm).
     * 
     * @param {string|Object} options - Message string or options object
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Confirmation message
     * @param {string} options.confirmText - Confirm button text (default: 'OK')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {string} options.confirmClass - CSS class for confirm button (default: 'btn-primary')
     * @param {string} options.cancelClass - CSS class for cancel button (default: 'btn-secondary')
     * @param {boolean} options.danger - Use danger styling (default: false)
     * @param {'auto'|'light'|'dark'} [options.theme] - Optional per-call theme override. Omit to follow the global theme.
     * @returns {Promise<boolean>} True if confirmed, false if cancelled
     * 
     * @example
     * const confirmed = await SmliserModal.confirm('Are you sure?');
     * if (confirmed) {
     *     console.log('User confirmed');
     * }
     * 
     * @example
     * const result = await SmliserModal.confirm({
     *     title: 'Delete Item',
     *     message: 'This action cannot be undone. Continue?',
     *     confirmText: 'Delete',
     *     cancelText: 'Keep',
     *     danger: true
     * });
     */
    static confirm( options ) {
        return new Promise( ( resolve ) => {
            // Handle string parameter
            if ( typeof options === 'string' ) {
                options = { message: options };
            }

            const config = {
                title: options.title || 'Confirm',
                message: options.message || 'Are you sure?',
                confirmText: options.confirmText || 'OK',
                cancelText: options.cancelText || 'Cancel',
                confirmClass: options.confirmClass || 'smliser-btn-primary',
                cancelClass: options.cancelClass || 'smliser-btn-secondary',
                danger: options.danger || false,
                icon: options.icon || ( options.danger ? SmliserModal.icons.warning : SmliserModal.icons.question )
            };

            // Create body content
            const bodyContent = document.createElement( 'div' );
            bodyContent.className = 'smliser-dialog-content';
            bodyContent.innerHTML = `
                <div class="smliser-dialog-icon ${config.danger ? 'danger' : 'info'}">
                    ${config.icon}
                </div>
                <div class="smliser-dialog-message">${config.message}</div>
            `;

            // Create footer buttons
            const footerContent = document.createElement( 'div' );
            footerContent.className = 'smliser-dialog-buttons';
            
            const cancelBtn = document.createElement( 'button' );
            cancelBtn.className = `smliser-btn ${config.cancelClass}`;
            cancelBtn.textContent = config.cancelText;
            cancelBtn.type = 'button';
            
            const confirmBtn = document.createElement( 'button' );
            confirmBtn.className = `smliser-btn ${config.danger ? 'smliser-btn-danger' : config.confirmClass}`;
            confirmBtn.textContent = config.confirmText;
            confirmBtn.type = 'button';
            
            footerContent.appendChild( cancelBtn );
            footerContent.appendChild( confirmBtn );

            // Create modal. Theme is intentionally NOT set here unless the
            // caller explicitly passed one — omitting it lets the instance
            // follow SmliserModal's global theme automatically.
            const modal = new SmliserModal( {
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-confirm-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true,
                ...( 'theme' in options ? { theme: options.theme } : {} ),
                ...( 'themeWatch' in options ? { themeWatch: options.themeWatch } : {} )
            } );

            let handled = false;
            // Event handlers
            const handleConfirm = async () => {
                handled = true;
                await modal.destroy();
                resolve( true );
            };

            const handleCancel = async () => {
                handled = true;
                await modal.destroy();
                resolve( false );
            };

            // Attach event listeners
            confirmBtn.addEventListener( 'click', handleConfirm );
            cancelBtn.addEventListener( 'click', handleCancel );
            
            modal.on( 'afterClose', () => {
                if ( ! handled ) {
                    resolve( false );
                }
            });

            // Open modal and focus confirm button
            modal.open().then( () => {
                setTimeout( () => confirmBtn.focus(), 100 );
            });
        });
    };

    /**
     * Show alert dialog (replaces window.alert)
     * 
     * @param {string|Object} options - Message string or options object
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Alert message
     * @param {string} options.buttonText - Button text (default: 'OK')
     * @param {string} options.type - Alert type: 'info', 'success', 'warning', 'error' (default: 'info')
     * @param {'auto'|'light'|'dark'} [options.theme] - Optional per-call theme override. Omit to follow the global theme.
     * @returns {Promise<void>}
     * 
     * @example
     * await SmliserModal.alert('Operation completed successfully!');
     * 
     * @example
     * await SmliserModal.alert({
     *     title: 'Success',
     *     message: 'Your changes have been saved.',
     *     type: 'success',
     *     buttonText: 'Great!'
     * });
     */
    static alert( options ) {
        return new Promise( ( resolve ) => {
            // Handle string parameter
            if ( typeof options === 'string' ) {
                options = { message: options };
            }

            const config = {
                title: options.title || 'Alert',
                message: options.message || '',
                buttonText: options.buttonText || 'OK',
                type: options.type || 'info',
                buttonClass: options.buttonClass || 'smliser-btn-primary'
            };

            // Icon mapping
            const iconMap = SmliserModal.icons;

            // Create body content
            const bodyContent = document.createElement( 'div' );
            bodyContent.className = 'smliser-dialog-content';
            bodyContent.innerHTML = `
                <div class="smliser-dialog-icon ${config.type}">
                    ${iconMap[ config.type ] || iconMap.info}
                </div>
                <div class="smliser-dialog-message">${config.message}</div>
            `;

            // Create footer button
            const footerContent     = document.createElement( 'div' );
            footerContent.className = 'smliser-dialog-buttons smliser-dialog-single-button';
            
            const okBtn         = document.createElement( 'button' );
            okBtn.className     = `smliser-btn ${config.buttonClass}`;
            okBtn.textContent   = config.buttonText;
            okBtn.type          = 'button';
            
            footerContent.appendChild( okBtn );

            // Create modal. As with confirm(), theme is only set if the
            // caller explicitly passed one — otherwise the global theme
            // is followed automatically.
            const modal = new SmliserModal( {
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: `smliser-dialog smliser-alert-dialog smliser-alert-${config.type}`,
                closeOnBackdropClick: false,
                closeOnEscape: true,
                ...( 'theme' in options ? { theme: options.theme } : {} ),
                ...( 'themeWatch' in options ? { themeWatch: options.themeWatch } : {} )
            } );

            // Event handler
            const handleOk = async () => {
                await modal.destroy();
                resolve();
            };

            // Attach event listener
            okBtn.addEventListener( 'click', handleOk );
            
            modal.on( 'afterClose', async () => resolve() );

            // Open modal and focus button
            modal.open().then( () => {
                setTimeout( () => okBtn.focus(), 100 );
            });
        });
    };

    /**
     * Show prompt dialog (replaces window.prompt)
     * 
     * @param {string|Object} options - Message string or options object
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Prompt message
     * @param {string} options.defaultValue - Default input value
     * @param {string} options.placeholder - Input placeholder
     * @param {string} options.inputType - Input type: 'text', 'email', 'password', 'number', 'textarea' (default: 'text')
     * @param {string} options.confirmText - Confirm button text (default: 'OK')
     * @param {string} options.cancelText - Cancel button text (default: 'Cancel')
     * @param {Function} options.validator - Validation function, return error message or null
     * @param {boolean} options.required - Input is required (default: false)
     * @param {'auto'|'light'|'dark'} [options.theme] - Optional per-call theme override. Omit to follow the global theme.
     * @returns {Promise<string|null>} Input value or null if cancelled
     * 
     * @example
     * const name = await SmliserModal.prompt('Enter your name:');
     * if (name) {
     *     console.log('Hello', name);
     * }
     * 
     * @example
     * const email = await SmliserModal.prompt({
     *     title: 'Email Required',
     *     message: 'Please enter your email address:',
     *     placeholder: 'user@example.com',
     *     inputType: 'email',
     *     required: true,
     *     validator: (value) => {
     *         if (!value.includes('@')) {
     *             return 'Please enter a valid email address';
     *         }
     *         return null;
     *     }
     * });
     */
    static prompt( options ) {
        return new Promise( ( resolve ) => {
            // Handle string parameter
            if ( typeof options === 'string' ) {
                options = { message: options };
            }

            const config = {
                title: options.title || 'Input',
                message: options.message || 'Please enter a value:',
                defaultValue: options.defaultValue || '',
                placeholder: options.placeholder || '',
                inputType: options.inputType || 'text',
                confirmText: options.confirmText || 'OK',
                cancelText: options.cancelText || 'Cancel',
                validator: options.validator || null,
                required: options.required || false,
                confirmClass: options.confirmClass || 'smliser-btn-primary',
                cancelClass: options.cancelClass || 'smliser-btn-secondary'
            };

            // Create body content
            const bodyContent = document.createElement( 'div' );
            bodyContent.className = 'smliser-dialog-content smliser-prompt-content';
            
            const messageDiv = document.createElement( 'div' );
            messageDiv.className = 'smliser-dialog-message';
            messageDiv.textContent = config.message;
            
            let inputElement;
            if ( config.inputType === 'textarea' ) {
                inputElement = document.createElement( 'textarea' );
                inputElement.className = 'smliser-prompt-input smliser-prompt-textarea';
                inputElement.rows = 4;
            } else {
                inputElement = document.createElement( 'input' );
                inputElement.className = 'smliser-prompt-input';
                inputElement.type = config.inputType;
            }
            
            inputElement.value = config.defaultValue;
            inputElement.placeholder = config.placeholder;
            if ( config.required ) {
                inputElement.setAttribute( 'required', 'true' );
            }
            
            const errorDiv = document.createElement( 'div' );
            errorDiv.className = 'smliser-prompt-error';
            errorDiv.style.display = 'none';
            
            bodyContent.appendChild( messageDiv );
            bodyContent.appendChild( inputElement );
            bodyContent.appendChild( errorDiv );

            // Create footer buttons
            const footerContent = document.createElement( 'div' );
            footerContent.className = 'smliser-dialog-buttons';
            
            const cancelBtn = document.createElement( 'button' );
            cancelBtn.className = `smliser-btn ${config.cancelClass}`;
            cancelBtn.textContent = config.cancelText;
            cancelBtn.type = 'button';
            
            const confirmBtn = document.createElement( 'button' );
            confirmBtn.className = `smliser-btn ${config.confirmClass}`;
            confirmBtn.textContent = config.confirmText;
            confirmBtn.type = 'button';
            
            footerContent.appendChild( cancelBtn );
            footerContent.appendChild( confirmBtn );

            // Create modal. Theme follows the global default unless the
            // caller explicitly passed one.
            const modal = new SmliserModal( {
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-prompt-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true,
                ...( 'theme' in options ? { theme: options.theme } : {} ),
                ...( 'themeWatch' in options ? { themeWatch: options.themeWatch } : {} )
            } );

            // Validation helper
            const validateInput = ( value ) => {
                // Required check
                if ( config.required && ! value.trim() ) {
                    return 'This field is required';
                }
                
                // Custom validator
                if ( config.validator ) {
                    return config.validator( value );
                }
                
                return null;
            };

            // Show error message
            const showError = ( message ) => {
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                inputElement.classList.add( 'error' );
            };

            // Clear error message
            const clearError = () => {
                errorDiv.style.display = 'none';
                inputElement.classList.remove( 'error' );
            };

            let handled = false;
            // Event handlers
            const handleConfirm = async () => {
                const value = inputElement.value;
                const error = validateInput( value );
                
                if ( error ) {
                    showError( error );
                    inputElement.focus();
                    return;
                }

                handled = true;
                await modal.destroy();
                resolve( value );
            };

            const handleCancel = async () => {
                handled = true;
                await modal.destroy();
                resolve( null );
            };

            // Attach event listeners
            confirmBtn.addEventListener( 'click', handleConfirm );
            cancelBtn.addEventListener( 'click', handleCancel );
            
            // Clear error on input
            inputElement.addEventListener( 'input', clearError );
            
            // Handle Enter key (submit)
            inputElement.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Enter' && config.inputType !== 'textarea' ) {
                    e.preventDefault();
                    handleConfirm();
                }
            });
            
            // Handle modal close (ESC key or X button)
            modal.on( 'afterClose', () => {
                if ( ! handled ) {
                    resolve( null );
                }
            });

            // Open modal and focus input
            modal.open().then( () => {
                setTimeout( () => {
                    inputElement.focus();
                    inputElement.select();
                }, 100 );
            });
        });
    };

    /**
     * Show custom dialog with multiple choices
     * 
     * @param {Object} options - Dialog options
     * @param {string} options.title - Dialog title
     * @param {string} options.message - Dialog message
     * @param {Array} options.buttons - Array of button configurations
     * @param {string} options.buttons[].text - Button text
     * @param {string} options.buttons[].value - Button return value
     * @param {string} options.buttons[].class - Button CSS class
     * @param {boolean} options.buttons[].primary - Is primary button
     * @param {'auto'|'light'|'dark'} [options.theme] - Optional per-call theme override. Omit to follow the global theme.
     * @returns {Promise<string|null>} Selected button value or null if closed
     * 
     * @example
     * const choice = await SmliserModal.choice({
     *     title: 'Save Changes?',
     *     message: 'You have unsaved changes. What would you like to do?',
     *     buttons: [
     *         { text: 'Save', value: 'save', class: 'smliser-btn-primary', primary: true },
     *         { text: 'Discard', value: 'discard', class: 'smliser-btn-danger' },
     *         { text: 'Cancel', value: 'cancel', class: 'smliser-btn-secondary' }
     *     ]
     * });
     * 
     * if (choice === 'save') {
     *     // Save changes
     * } else if (choice === 'discard') {
     *     // Discard changes
     * }
     */
    static choice( options ) {
        return new Promise( ( resolve ) => {
            const config = {
                title: options.title || 'Choose',
                message: options.message || 'Please select an option:',
                buttons: options.buttons || [],
                icon: options.icon || SmliserModal.icons.question,
            };

            // Create body content
            const bodyContent = document.createElement( 'div' );
            bodyContent.className = 'smliser-dialog-content';
            bodyContent.innerHTML = `
                <div class="smliser-dialog-icon info">
                    ${config.icon}
                </div>
                <div class="smliser-dialog-message">${config.message}</div>
            `;

            // Create footer buttons
            const footerContent = document.createElement( 'div' );
            footerContent.className = 'smliser-dialog-buttons smliser-choice-buttons';
            
            const buttonElements = [];
            
            config.buttons.forEach( ( btnConfig, index ) => {
                const btn = document.createElement( 'button' );
                btn.className = `smliser-btn ${btnConfig.class || 'smliser-btn-secondary'}`;
                btn.textContent = btnConfig.text || `Option ${index + 1}`;
                btn.type = 'button';
                btn.dataset.value = btnConfig.value || btnConfig.text;
                
                if ( btnConfig.primary ) {
                    btn.setAttribute( 'autofocus', 'true' );
                }
                
                footerContent.appendChild( btn );
                buttonElements.push( btn );
            });

            // Create modal. Theme follows the global default unless the
            // caller explicitly passed one.
            const modal = new SmliserModal( {
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-choice-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true,
                ...( 'theme' in options ? { theme: options.theme } : {} ),
                ...( 'themeWatch' in options ? { themeWatch: options.themeWatch } : {} )
            } );

            let handled = false;
            // Event handler.
            const handleChoice = async ( value ) => {
                handled = true;
                await modal.destroy();
                resolve( value );
            };

            // Attach event listeners to all buttons
            buttonElements.forEach( btn => {
                btn.addEventListener( 'click', () => handleChoice( btn.dataset.value ) );
            });
            
            // Handle modal close (ESC key or X button)
            modal.on( 'afterClose', () => {
                if ( ! handled ) {
                    resolve( null );
                }
            });

            // Open modal
            modal.open().then( () => {
                const primaryBtn = buttonElements.find( btn => btn.hasAttribute( 'autofocus' ) );
                if ( primaryBtn ) {
                    setTimeout( () => primaryBtn.focus(), 100 );
                }
            });
        });
    };

    /**
     * Convenience method: Show success alert
     */
    static success( message, title = 'Success' ) {
        return SmliserModal.alert({
            title: title,
            message: message,
            type: 'success',
            buttonText: 'Close'
        });
    };

    /**
     * Convenience method: Show error alert
     */
    static error( message, title = 'Error', buttonText = 'Close' ) {
        return SmliserModal.alert({
            title: title,
            message: message,
            type: 'error',
            buttonText: buttonText
        });
    };

    /**
     * Convenience method: Show warning alert
     */
    static warning( message, title = 'Warning' ) {
        return SmliserModal.alert({
            title: title,
            message: message,
            type: 'warning'
        });
    };

    /**
     * Convenience method: Show info alert
     */
    static info( message, title = 'Information' ) {
        return SmliserModal.alert({
            title: title,
            message: message,
            type: 'info'
        });
    };

}

// Global theme state — shared across every SmliserModal instance.
// Set once, anywhere, via SmliserModal.setGlobalTheme() or
// SmliserModal.watchTheme(); every existing call site across the app
// picks it up automatically, with no per-call changes required.
SmliserModal._globalTheme = 'auto';
SmliserModal._instances = new Set();
SmliserModal._globalThemeObserver = null;
// Built-in SVG icon set used by the confirm/alert/choice dialog helpers.
// currentColor lets each icon inherit its wrapper's color rule
// (.smliser-dialog-icon.info / .success / .warning / .error), so no
// per-icon color logic is needed here. Callers can still pass their
// own `icon` string (emoji, SVG, whatever) to override any of these.
SmliserModal.icons = {
    question: '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.25"/><path d="M9.5 9.5a2.5 2.5 0 0 1 4.83-.9c.35.98-.1 1.6-.85 2.2-.7.56-1.48 1.05-1.48 2.2"/><circle cx="12" cy="17" r="0.75" fill="currentColor" stroke="none"/></svg>',

    info: '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.25"/><line x1="12" y1="11" x2="12" y2="16.5"/><circle cx="12" cy="7.5" r="0.75" fill="currentColor" stroke="none"/></svg>',

    success: '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.25"/><path d="M8 12.5l2.5 2.5 5.5-6"/></svg>',

    warning: '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3.5l9.25 16.25a1 1 0 0 1-.87 1.5H3.62a1 1 0 0 1-.87-1.5L12 3.5z"/><line x1="12" y1="10" x2="12" y2="14.25"/><circle cx="12" cy="17.25" r="0.75" fill="currentColor" stroke="none"/></svg>',

    error: '<svg viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9.25"/><line x1="8.5" y1="8.5" x2="15.5" y2="15.5"/><line x1="15.5" y1="8.5" x2="8.5" y2="15.5"/></svg>',
};