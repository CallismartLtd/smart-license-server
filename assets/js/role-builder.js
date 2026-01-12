class RoleBuilder {

    /**
     * Create a RoleBuilder instance.
     *
     * @param {HTMLElement} container Root container element.
     * @param {Object} data Role and capability definitions.
     */
    constructor( container, data ) {
        this.container  = container;
        this.data       = data;

        this.activeRole = null;
        this.isPreset   = false;

        this.render();
        this.bindEvents();
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
                           placeholder="Enter role name" id="role-name">
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
                                <input type="checkbox" value="${cap}">
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
            .addEventListener('change', e => {
                this.selectRole( e.target.value );
            });

        this.container
            .querySelector('.rb-role-name')
            .addEventListener('input', () => {
                this.enableCustomRole();
            });
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
            return;
        }

        const role = this.data.roles[ roleKey ];

        this.activeRole = roleKey;
        this.isPreset   = true;

        this.setRoleName( role.label );
        this.checkCapabilities( role.capabilities );
        this.lockCapabilities();
        this.setLockedState( true );
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
        const input = this.container.querySelector('.rb-role-name');
        input.value    = name;
        input.disabled = this.isPreset;
    }

    /**
     * Check a list of capability checkboxes.
     *
     * @param {string[]} capabilities
     */
    checkCapabilities( capabilities ) {
        capabilities.forEach( cap => {
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
            role: this.activeRole,
            name: this.container.querySelector('.rb-role-name').value,
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
}
