class RoleBuilder {

    /**
     * Create a RoleBuilder instance.
     *
     * @param {Element} container
     *     Root DOM element where the role builder UI will be rendered.
     *     This element MUST exist in the document before instantiation.
     *
     * @param {Object} data
     *     Canonical role and capability definitions.
     *
     *     Structure:
     *     {
     *         roles: {
     *             [roleKey: string]: {
     *                 label: string,
     *                 capabilities: string[]
     *             }
     *         },
     *         capabilities: {
     *             [domain: string]: {
     *                 [capability: string]: string   // capability => label
     *             }
     *         }
     *     }
     *
     * @param {Object|null} [initialRole=null]
     *     Optional existing role to preload into the UI.
     *     Used when editing an existing role.
     *
     *     Structure:
     *     {
     *         key?: string|null,          // Existing role key (if canonical)
     *         name: string,               // Human-readable role name
     *         capabilities: string[]      // List of assigned capability identifiers
     *     }
     *
     *     Behavior:
     *     - If `key` matches a predefined role, that preset is selected and locked.
     *     - If no matching preset exists, the role is treated as a custom role.
     */
    constructor( container, data, initialRole = null ) {
        this.container  = container;
        this.data       = data;

        this.activeRole = null;
        this.isPreset   = false;

        this.render();
        this.bindEvents();

        if ( initialRole ) {
            this.loadRole( initialRole );
        }

    }

    /**
     * Render the entire role builder UI.
     */
    render() {
        this.container.classList.add('smliser-role-builder');

        this.container.innerHTML = `
            <div class="rb-header">
                <div class="rb-field">
                    <label for="role-select">Role preset</label>
                    <select class="rb-role-select" id="role-select">
                        <option value="">Custom role</option>
                        ${this.renderRoleOptions()}
                    </select>
                </div>

                <div class="rb-field">
                    <label for="role-name">Role name</label>
                    <input type="text"
                        class="rb-role-name"
                        placeholder="Enter role name" id="role-name"
                        autocomplete="off" spellcheck="off" />
                </div>

                <div class="rb-field">
                    <label for="role-slug">Role Slug</label>
                    <input type="text"
                        class="rb-role-slug"
                        placeholder="Enter role slug" id="role-slug"
                        autocomplete="off" spellcheck="off" />
                </div>
            </div>

            <div class="rb-capabilities-wrapper">
                <h3>Capabilities</h3>
                <div class="rb-capabilities">
                    ${this.renderCapabilities()}
                </div>
            </div>
        `;
    }

    /**
     * Render role preset <option> elements.
     *
     * @return {string}
     */
    renderRoleOptions() {
        return Object.entries( this.data.roles )
            .map( ([ key, role ]) =>
                `<option value="${key}">${role.label}</option>`
            )
            .join('');
    }

    /**
     * Render all capability groups and checkboxes.
     *
     * @return {string}
     */
    renderCapabilities() {
        return Object.entries( this.data.capabilities )
            .map( ([ domain, caps ]) => `
                <fieldset class="rb-domain" data-domain="${domain}">
                    <legend>${this.humanize(domain)}</legend>
                    ${Object.entries( caps ).map(
                        ([ cap, label ]) => `
                            <label class="rb-cap">
                                <input type="checkbox" autocomplete="off" spellcheck="off" value="${cap}" />
                                <span>${label}</span>
                            </label>
                        `
                    ).join('')}
                </fieldset>
            `).join('');
    }

    /**
     * Bind UI event listeners.
     */
    bindEvents() {
        this.container
            .querySelector('.rb-role-select')
            .addEventListener('change', e => this.selectRole( e.target.value ) );

        this.container
            .querySelector('.rb-role-name')
            .addEventListener('input', this.enableCustomRole.bind(this) );

        this.container
            .querySelector('.rb-role-slug')
            .addEventListener('input', e => e.target.value = e.target.value.toLowerCase() );
        this.container
            .querySelector('.rb-role-slug')
            .addEventListener('blur', e => this.formatRoleSlug( e.target.value, true ) );
    }

    /**
     * Select a predefined role.
     *
     * @param {string} roleKey Role identifier.
     */
    selectRole( roleKey ) {
        this.resetCapabilities();        

        if ( ! roleKey ) {
            this.enableCustomRole();
            this.setRoleName( '' );
            
            this.formatRoleSlug( '' );
            return;
        }

        const role = this.data.roles[ roleKey ];

        this.activeRole = roleKey;
        this.isPreset   = true;

        this.setRoleName( role.label );
        this.checkCapabilities( role.capabilities );
        this.lockCapabilities();
        this.setLockedState( true );
        this.formatRoleSlug( roleKey );
    }

    /**
     * Switch UI to custom role mode.
     */
    enableCustomRole() {
        this.activeRole = null;
        this.isPreset   = false;

        this.unlockCapabilities();
        this.setLockedState( false );        
    }

    /**
     * Set the role name field value and lock state.
     *
     * @param {string} name
     */
    setRoleName( name ) {
        /** @type {HTMLInputElement} input */
        const input         = this.container.querySelector('.rb-role-name');
        input.value         = name ? name : '';
        input.disabled      = this.isPreset;
    }

    /**
     * Check a list of capability checkboxes.
     *
     * @param {string[]} capabilities
     */
    checkCapabilities( capabilities ) {
        capabilities?.forEach( cap => {
            const checkbox = this.container.querySelector(
                `input[value="${cap}"]`
            );
            if ( checkbox ) {
                checkbox.checked = true;
            }
        });
    }

    /**
     * Uncheck all capability checkboxes.
     */
    resetCapabilities() {
        this.container
            .querySelectorAll('.rb-capabilities input[type=checkbox]')
            .forEach( cb => cb.checked = false );
    }

    /**
     * Disable all capability checkboxes.
     */
    lockCapabilities() {
        this.toggleCapabilities( true );
    }

    /**
     * Enable all capability checkboxes.
     */
    unlockCapabilities() {
        this.toggleCapabilities( false );
    }

    /**
     * Toggle capability checkbox disabled state.
     *
     * @param {boolean} disabled
     */
    toggleCapabilities( disabled ) {
        this.container
            .querySelectorAll('.rb-capabilities input[type=checkbox]')
            .forEach( cb => cb.disabled = disabled );
    }

    /**
     * Set locked state on the root element (for styling).
     *
     * @param {boolean} locked
     */
    setLockedState( locked ) {
        this.container.dataset.locked = locked ? 'true' : 'false';
    }

    /**
     * Get the current role configuration.
     *
     * @return {{role: string|null, name: string, capabilities: string[]}}
     */
    getValue() {
        return {
            roleSlug: this.container.querySelector( '.rb-role-slug' ).value,
            roleLabel: this.container.querySelector( '.rb-role-name' ).value,
            capabilities: Array.from(
                this.container.querySelectorAll(
                    '.rb-capabilities input:checked'
                )
            ).map( cb => cb.value )
        };
    }

    /**
     * Convert snake_case identifiers to human-readable text.
     *
     * @param {string} str
     * @return {string}
     */
    humanize( str ) {
        return str
            .replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase());
    }

    /**
     * Format role slug
     * 
     * @param {string} slug The value of the role slug.
     * 
     */
    formatRoleSlug( slug, isFinal = false ) {
        const input = this.container.querySelector( '.rb-role-slug' );

        if ( slug ) {
            slug = slug
                .toLowerCase()
                .replace(/[\s-]+/g, '_')    // Replace spaces/hyphens with _
                .replace(/[^a-z0-9_]/g, ''); // Remove illegal chars

            // Only collapse and trim if we are done typing (on blur)
            if ( isFinal ) {
                slug = slug
                    .replace(/_+/g, '_')
                    .replace(/[\s]+/i, '_')
                    .replace(/^_+|_+$/g, '');
            }
        }

        input.value     = slug ? slug: '';
        input.disabled  = this.isPreset;        
    }

    /**
     * Load an existing role into the builder (edit mode).
     *
     * If the role exactly matches a canonical preset, the preset
     * is selected and locked. Otherwise, the role is treated as custom.
     *
     * @param {{
     *   name: string,
     *   capabilities: string[]
     * }} roleData
     */
    loadRole( roleData ) {
        this.resetCapabilities();

        /**@type {HTMLSelectElement} */
        const roleSelect    = this.container.querySelector( '.rb-role-select' );

        if ( roleData.is_canonical ) {
            const presetKey     = this.findMatchingPreset( roleData.capabilities );
            if ( presetKey ) {
                roleSelect.value = presetKey;
                this.selectRole( presetKey );            
                return;
            }
        }

        // Custom / non-canonical role.
        if ( roleSelect.querySelector( `option[value="${roleData.slug}"]` ) ) {
            this.selectRole( roleData.slug );
            roleSelect.value = roleData.slug;
            return;
        }

        roleSelect.value = '';
        this.enableCustomRole();

        this.setRoleName( roleData.label );
        this.formatRoleSlug( roleData.slug );
        this.checkCapabilities( roleData.capabilities );
    }

    /**
     * Find a preset role that exactly matches a capability set.
     *
     * @param {string[]} capabilities
     * @return {string|null} Matching role key or null
     */
    findMatchingPreset( capabilities ) {
        const sorted = [ ...capabilities ].sort().join('|');

        for ( const [ key, role ] of Object.entries( this.data.roles ) ) {
            const presetCaps = [ ...role.capabilities ].sort().join('|');

            if ( presetCaps === sorted ) {
                return key;
            }
        }

        return null;
    }
}
