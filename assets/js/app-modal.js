/**
 * SmliserModal - A highly customizable, event-driven modal component
 * Supports HTMLElement instances, template strings, and custom events
 * WITH FULL ACCESSIBILITY SUPPORT
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

        // Accessibility properties
        this.previousFocus = null;
        this.focusableElements = null;
        this.firstFocusable = null;
        this.lastFocusable = null;
        this._hiddenElements = null;
        this.titleId = 'smliser-modal-title-' + Date.now();
        this.bodyId = 'smliser-modal-body-' + Date.now();

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

        const title = document.createElement( 'h2' );
        title.className = 'smliser-modal-title';
        title.id = this.titleId;
        title.textContent = this.options.title;
        title.setAttribute( 'role', 'heading' );
        title.setAttribute( 'aria-level', '2' );

        this.header.appendChild( title );

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
        await this._triggerEvent( 'beforeOpen' );

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
    async destroy() {
        if ( this.isOpen ) {
            await this.close();
        }
        
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
                icon: options.icon || '❓'
            };

            // Create body content
            const bodyContent = document.createElement( 'div' );
            bodyContent.className = 'smliser-dialog-content';
            bodyContent.innerHTML = `
                <div class="smliser-dialog-icon ${config.danger ? 'danger' : 'info'}">
                    ${config.danger ? '⚠️' : config.icon}
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
            confirmBtn.setAttribute( 'autofocus', 'true' );
            
            footerContent.appendChild( cancelBtn );
            footerContent.appendChild( confirmBtn );

            // Create modal
            const modal = new SmliserModal({
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-confirm-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true
            });

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
            const iconMap = {
                info: 'ℹ️',
                success: '✅',
                warning: '⚠️',
                error: '❌'
            };

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
            okBtn.type = 'button';
            okBtn.setAttribute( 'autofocus', 'true' );
            
            footerContent.appendChild( okBtn );

            // Create modal
            const modal = new SmliserModal({
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: `smliser-dialog smliser-alert-dialog smliser-alert-${config.type}`,
                closeOnBackdropClick: false,
                closeOnEscape: true
            });

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

            // Create modal
            const modal = new SmliserModal({
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-prompt-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true
            });

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
                icon: options.icon || '❓'
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

            // Create modal
            const modal = new SmliserModal({
                title: config.title,
                body: bodyContent,
                footer: footerContent,
                width: '500px',
                customClass: 'smliser-dialog smliser-choice-dialog',
                closeOnBackdropClick: false,
                closeOnEscape: true
            });

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
            type: 'success'
        });
    };

    /**
     * Convenience method: Show error alert
     */
    static error( message, title = 'Error' ) {
        return SmliserModal.alert({
            title: title,
            message: message,
            type: 'error'
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

// if ( typeof module !== 'undefined' && module.exports ) {
//     module.exports = SmliserModal;
// }