/**
 * SmliserModal - A highly customizable, event-driven modal component
 * Supports HTMLElement instances, template strings, and custom events
 */
class SmliserModal {
    constructor( options = {} ) {
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
            ...options
        };

        this.isOpen         = false;
        this.backdrop       = null;
        this.modal          = null;
        this.header         = null;
        this.bodyElement    = null;
        this.footerElement  = null;

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
    }

    /**
     * Create backdrop element
     * @private
     */
    _createBackdrop() {
        this.backdrop = document.createElement('div');
        this.backdrop.className = 'smliser-modal-backdrop';
        this.backdrop.style.zIndex = this.options.zIndex;
        
        if (this.options.closeOnBackdropClick) {
            this.backdrop.addEventListener( 'click', (e) => e.target === this.backdrop && this.close() );
        }
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

        const title = document.createElement( 'h2' );
        title.className = 'smliser-modal-title';
        title.textContent = this.options.title;
        this.header.appendChild( title );

        if ( this.options.showCloseButton ) {
            const closeBtn = document.createElement( 'button' );
            closeBtn.className = 'smliser-modal-close';
            closeBtn.setAttribute( 'aria-label', 'Close modal' );
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
            element.innerHTML = content;
        } else if ( content !== null && content !== undefined ) {
            element.textContent = String( content );
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
    }

    /**
     * Open the modal asynchronously
     * @returns {Promise<SmliserModal>}
     */
    async open() {
        if ( this.isOpen ) return this;

        // Trigger beforeOpen event.
        await this._triggerEvent( 'beforeOpen' );

        // Append to body.
        document.body.appendChild( this.backdrop );
        
        // Add open class with slight delay for animation.
        if ( this.options.animation ) {
            await this._delay(10);
        }
        
        this.backdrop.classList.add( 'smliser-modal-open' );
        this.isOpen = true;

        // Add escape key listener.
        if ( this.options.closeOnEscape ) {
            document.addEventListener( 'keydown', this._handleEscape );
        }

        // Trigger afterOpen event.
        if (this.options.animation) {
            await this._delay( this.options.animationDuration );
        }
        await this._triggerEvent( 'afterOpen' );

        return this;
    }

    /**
     * Close the modal asynchronously
     * @returns {Promise<SmliserModal>}
     */
    async close() {
        if ( ! this.isOpen ) return this;

        // Trigger beforeClose event.
        await this._triggerEvent( 'beforeClose' );

        // Remove open class.
        this.backdrop.classList.remove( 'smliser-modal-open' );

        if (this.options.animation) {
            await this._delay( this.options.animationDuration );
        }

        // Remove from DOM.
        if ( this.backdrop.parentNode ) {
            document.body.removeChild( this.backdrop );
        }

        this.isOpen = false;

        // Remove escape key listener.
        if ( this.options.closeOnEscape ) {
            document.removeEventListener( 'keydown', this._handleEscape );
        }

        // Trigger afterClose event.
        await this._triggerEvent( 'afterClose' );

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
     */
    async _triggerEvent( eventName ) {
        if ( this.eventHandlers[eventName] ) {
            for ( const handler of this.eventHandlers[eventName] ) {
                await handler(this);
            }
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
    destroy() {
        if ( this.isOpen ) {
            this.close();
        }
        
        if ( this.backdrop && this.backdrop.parentNode ) {
            document.body.removeChild( this.backdrop );
        }

        if ( this.options.closeOnEscape ) {
            document.removeEventListener( 'keydown', this._handleEscape );
        }

        this.eventHandlers  = {};
        this.backdrop       = null;
        this.modal          = null;
        this.header         = null;
        this.bodyElement    = null;
        this.footerElement  = null;
    }
}

// if ( typeof module !== 'undefined' && module.exports ) {
//     module.exports = SmliserModal;
// }