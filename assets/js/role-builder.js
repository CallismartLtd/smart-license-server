class RoleBuilder {
    /**
     * Create a RoleBuilder instance.
     *
     * @param {Element} container
     *     Root DOM element where the role builder UI will be rendered.
     *     This element MUST exist in the document before instantiation.
     *
     * @param {Object} data
     *     Role and capability definitions.
     *
     *     Structure:
     *     {
     *         roles: {
     *             [roleKey: string]: {
     *                 label: string,
     *                 is_canonical: boolean,
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
     *         slug: string,           // Machine-readable role identifier
     *         label: string,          // Human-readable role name
     *         is_canonical: boolean,  // Whether this role is a locked system role
     *         capabilities: string[]  // List of assigned capability identifiers
     *     }
     *
     *     Behavior:
     *     - If `is_canonical` is true, the UI is locked: name, slug, and
     *       capability checkboxes are all disabled.
     *     - If `is_canonical` is false, the role is fully editable regardless
     *       of whether its slug matches a dropdown option.
     */
    constructor( container, data, initialRole = null ) {
        this.container = container;
        this.data      = data;

        /**
         * Whether the currently loaded role is canonical (locked).
         * This is the single authoritative flag driving all lock/unlock
         * decisions in the UI. It is derived exclusively from `is_canonical`
         * on the role data — never inferred from dropdown state or other
         * heuristics.
         *
         * @type {boolean}
         */
        this.isLocked = false;

        this.render();
        this.bindEvents();

        if ( initialRole ) {
            this.loadRole( initialRole );
        }
    }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    render() {
        this.container.classList.add('smliser-role-builder');

        this.container.setAttribute('role', 'application');
        this.container.setAttribute('aria-label', 'Role Builder');

        this.container.innerHTML = `
            <div class="rb-header" role="region" aria-labelledby="rb-header-title">
                <h2 id="rb-header-title" class="screen-reader-text">Role Configuration</h2>

                <div class="rb-field">
                    <label for="role-select">Role preset</label>
                    <select
                        class="rb-role-select"
                        id="role-select"
                        aria-label="Select role preset"
                        aria-controls="rb-capabilities-area"
                    >
                        <option value="">Custom role</option>
                        ${this.renderRoleOptions()}
                    </select>
                </div>

                <div class="rb-field">
                    <label for="role-name">Role name</label>
                    <input
                        type="text"
                        class="rb-role-name"
                        id="role-name"
                        placeholder="Enter role name"
                        autocomplete="off"
                        spellcheck="off"
                        aria-required="true"
                        aria-describedby="role-name-desc"
                    />
                    <span id="role-name-desc" class="screen-reader-text">
                        Human readable name for the role
                    </span>
                </div>

                <div class="rb-field">
                    <label for="role-slug">Role Slug</label>
                    <input
                        type="text"
                        class="rb-role-slug"
                        id="role-slug"
                        placeholder="Enter role slug"
                        autocomplete="off"
                        spellcheck="off"
                        aria-required="true"
                        aria-describedby="role-slug-desc"
                    />
                    <span id="role-slug-desc" class="screen-reader-text">
                        Machine readable identifier for the role
                    </span>
                </div>
            </div>

            <div
                id="rb-capabilities-area"
                class="rb-capabilities-wrapper"
                role="region"
                aria-labelledby="rb-capabilities-title"
                aria-live="polite"
            >
                <div class="rb-capabilities-header">
                    <h3 id="rb-capabilities-title">Capabilities</h3>
                    <label class="rb-select-all-global" for="rb-select-all">
                        <input
                            type="checkbox"
                            id="rb-select-all"
                            class="rb-select-all-checkbox"
                            aria-label="Select all capabilities"
                            autocomplete="off"
                        />
                        <span>Select all</span>
                    </label>
                </div>

                <div class="rb-capabilities">
                    ${this.renderCapabilities()}
                </div>
            </div>
        `;
    }

    renderRoleOptions() {
        return Object.entries( this.data.roles )
            .map( ([ key, role ]) =>
                `<option value="${key}">${role.label}</option>`
            )
            .join('');
    }

    renderCapabilities() {
        return Object.entries( this.data.capabilities )
            .map( ([ domain, caps ]) => {

                const fieldsetId  = `rb-domain-${domain}`;
                const selectAllId = `rb-select-all-${domain}`;

                return `
                <fieldset
                    class="rb-domain"
                    id="${fieldsetId}"
                    data-domain="${domain}"
                    aria-labelledby="${fieldsetId}-legend"
                >
                    <legend id="${fieldsetId}-legend">
                        <span>${this.humanize(domain)}</span>
                        <label class="rb-select-all-domain" for="${selectAllId}">
                            <input
                                type="checkbox"
                                id="${selectAllId}"
                                class="rb-select-all-domain-checkbox"
                                data-domain="${domain}"
                                aria-label="Select all ${this.humanize(domain)} capabilities"
                                autocomplete="off"
                            />
                            <span>All</span>
                        </label>
                    </legend>

                    ${Object.entries( caps ).map( ([ cap, label ]) => {
                        const inputId = `cap-${cap}`;
                        return `
                            <label class="rb-cap" for="${inputId}">
                                <input
                                    id="${inputId}"
                                    type="checkbox"
                                    value="${cap}"
                                    autocomplete="off"
                                    spellcheck="off"
                                    aria-checked="false"
                                />
                                <span>${label}</span>
                            </label>
                        `;
                    }).join('')}
                </fieldset>
            `;
        }).join('');
    }

    // -------------------------------------------------------------------------
    // Events
    // -------------------------------------------------------------------------

    bindEvents() {
        this.container
            .querySelector('.rb-role-select')
            .addEventListener('change', e => this.onPresetChange( e.target.value ) );

        this.container
            .querySelector('.rb-role-name')
            .addEventListener('input', () => {
                // Typing in the name field always means the user is customising,
                // so reset the preset dropdown to "Custom role".
                this.container.querySelector('.rb-role-select').value = '';
            });

        this.container
            .querySelector('.rb-role-slug')
            .addEventListener('input', e => {
                e.target.value = e.target.value.toLowerCase();
            });

        this.container
            .querySelector('.rb-role-slug')
            .addEventListener('blur', e => this.setRoleSlug( e.target.value, true ) );

        this.container
            .querySelector('#rb-select-all')
            .addEventListener('change', e => this.handleSelectAll( e.target.checked ) );

        // Delegated listener for domain select-all and individual capability checkboxes.
        this.container.addEventListener('change', e => {
            if ( e.target.matches('.rb-select-all-domain-checkbox') ) {
                this.handleDomainSelectAll( e.target.dataset.domain, e.target.checked );
                return;
            }

            if ( e.target.matches('input[type="checkbox"]') ) {
                e.target.setAttribute(
                    'aria-checked',
                    e.target.checked ? 'true' : 'false'
                );

                this.updateSelectAllStates();
            }
        });
    }

    // -------------------------------------------------------------------------
    // Preset dropdown handler
    // -------------------------------------------------------------------------

    /**
     * Handle a change on the preset dropdown.
     * Looks up the selected role and applies it; locks only if canonical.
     *
     * @param {string} roleKey  Selected option value. Empty string = custom role.
     */
    onPresetChange( roleKey ) {
        this.resetCapabilities();

        if ( ! roleKey ) {
            this.applyLock( false );
            this.setRoleName( '' );
            this.setRoleSlug( '' );
            return;
        }

        const role = this.data.roles[ roleKey ];

        this.setRoleName( role.label );
        this.setRoleSlug( roleKey );
        this.checkCapabilities( role.capabilities );
        this.applyLock( role.is_canonical );
        this.updateSelectAllStates();
    }

    // -------------------------------------------------------------------------
    // Lock / unlock — single unified point of control
    // -------------------------------------------------------------------------

    /**
     * Apply or remove the locked state across the entire UI.
     *
     * This is the **only** method that should mutate `this.isLocked` or
     * touch disabled/aria-disabled on fields and capabilities. All other
     * methods that previously had lock-related side-effects now delegate
     * here instead.
     *
     * @param {boolean} locked  True to lock (canonical role), false to unlock.
     */
    applyLock( locked ) {
        this.isLocked = locked;

        // Fields
        const nameInput = this.container.querySelector('.rb-role-name');
        const slugInput = this.container.querySelector('.rb-role-slug');

        [ nameInput, slugInput ].forEach( input => {
            input.disabled = locked;
            input.setAttribute('aria-disabled', locked ? 'true' : 'false');
        });

        // Capability checkboxes (including select-all controls)
        this.container
            .querySelectorAll('.rb-capabilities input[type="checkbox"]')
            .forEach( cb => {
                cb.disabled = locked;
                cb.setAttribute('aria-disabled', locked ? 'true' : 'false');
            });

        // Container state attribute (drives CSS locked overlay)
        this.container.dataset.locked = locked ? 'true' : 'false';
        this.container.setAttribute('aria-busy', locked ? 'true' : 'false');
    }

    // -------------------------------------------------------------------------
    // Field setters
    // -------------------------------------------------------------------------

    /**
     * Set the role name input value.
     * Does not touch disabled state — that is exclusively managed by applyLock.
     *
     * @param {string} name
     */
    setRoleName( name ) {
        this.container.querySelector('.rb-role-name').value = name ?? '';
    }

    /**
     * Set and format the role slug input value.
     * Does not touch disabled state — that is exclusively managed by applyLock.
     *
     * @param {string}  slug
     * @param {boolean} [isFinal=false]  When true, strips leading/trailing/duplicate underscores.
     */
    setRoleSlug( slug, isFinal = false ) {
        if ( slug ) {
            slug = slug
                .toLowerCase()
                .replace(/[\s-]+/g, '_')
                .replace(/[^a-z0-9_]/g, '');

            if ( isFinal ) {
                slug = slug
                    .replace(/_+/g, '_')
                    .replace(/^_+|_+$/g, '');
            }
        }

        this.container.querySelector('.rb-role-slug').value = slug ?? '';
    }

    // -------------------------------------------------------------------------
    // Capabilities
    // -------------------------------------------------------------------------

    /**
     * Check capability checkboxes matching the given list.
     *
     * @param {string[]} capabilities
     */
    checkCapabilities( capabilities ) {
        capabilities?.forEach( cap => {
            const checkbox = this.container.querySelector( `input[value="${cap}"]` );

            if ( checkbox ) {
                checkbox.checked = true;
                checkbox.setAttribute('aria-checked', 'true');
            }
        });
    }

    /**
     * Uncheck all capability checkboxes and clear indeterminate state.
     */
    resetCapabilities() {
        this.container
            .querySelectorAll('.rb-capabilities input[type="checkbox"]')
            .forEach( cb => {
                cb.checked       = false;
                cb.indeterminate = false;
                cb.setAttribute('aria-checked', 'false');
            });
    }

    /**
     * Toggle all capability checkboxes (excluding select-all controls).
     *
     * @param {boolean} checked
     */
    handleSelectAll( checked ) {
        this.container
            .querySelectorAll('.rb-capabilities input[type="checkbox"]:not(.rb-select-all-domain-checkbox)')
            .forEach( cb => {
                if ( ! cb.disabled ) {
                    cb.checked = checked;
                    cb.setAttribute('aria-checked', checked ? 'true' : 'false');
                }
            });

        this.container
            .querySelectorAll('.rb-select-all-domain-checkbox')
            .forEach( cb => {
                if ( ! cb.disabled ) {
                    cb.checked       = checked;
                    cb.indeterminate = false;
                }
            });
    }

    /**
     * Toggle all capability checkboxes within a specific domain.
     *
     * @param {string}  domain
     * @param {boolean} checked
     */
    handleDomainSelectAll( domain, checked ) {
        const fieldset = this.container.querySelector( `.rb-domain[data-domain="${domain}"]` );

        if ( ! fieldset ) return;

        fieldset
            .querySelectorAll('input[type="checkbox"]:not(.rb-select-all-domain-checkbox)')
            .forEach( cb => {
                if ( ! cb.disabled ) {
                    cb.checked = checked;
                    cb.setAttribute('aria-checked', checked ? 'true' : 'false');
                }
            });

        this.updateSelectAllStates();
    }

    /**
     * Recalculate checked/indeterminate states for all select-all controls
     * based on the current state of the capability checkboxes.
     */
    updateSelectAllStates() {
        let totalCaps    = 0;
        let totalChecked = 0;

        this.container.querySelectorAll('.rb-domain').forEach( fieldset => {
            const caps     = Array.from(
                fieldset.querySelectorAll('input[type="checkbox"]:not(.rb-select-all-domain-checkbox)')
            );
            const checked  = caps.filter( cb => cb.checked );
            const domainCb = fieldset.querySelector('.rb-select-all-domain-checkbox');

            totalCaps    += caps.length;
            totalChecked += checked.length;

            if ( domainCb ) {
                domainCb.checked       = checked.length === caps.length && caps.length > 0;
                domainCb.indeterminate = checked.length > 0 && checked.length < caps.length;
            }
        });

        const globalCb = this.container.querySelector('#rb-select-all');

        if ( globalCb ) {
            globalCb.checked       = totalChecked === totalCaps && totalCaps > 0;
            globalCb.indeterminate = totalChecked > 0 && totalChecked < totalCaps;
        }
    }

    // -------------------------------------------------------------------------
    // Load existing role
    // -------------------------------------------------------------------------

    /**
     * Populate the UI from an existing role object.
     *
     * Lock state is determined solely by `roleData.is_canonical`. A role
     * whose slug matches a dropdown option but is not canonical will be
     * loaded as fully editable.
     *
     * @param {Object}   roleData
     * @param {string}   roleData.slug
     * @param {string}   roleData.label
     * @param {boolean}  roleData.is_canonical
     * @param {string[]} roleData.capabilities
     */
    loadRole( roleData ) {
        this.resetCapabilities();

        const roleSelect = this.container.querySelector('.rb-role-select');

        // If canonical, try to match a preset by capabilities so the dropdown
        // reflects the correct option even if slugs differ.
        if ( roleData.is_canonical ) {
            const presetKey = this.findMatchingPreset( roleData.capabilities );

            if ( presetKey ) {
                roleSelect.value = presetKey;
                this.setRoleName( this.data.roles[ presetKey ].label );
                this.setRoleSlug( presetKey );
                this.checkCapabilities( roleData.capabilities );
                this.applyLock( true );
                this.updateSelectAllStates();
                return;
            }
        }

        // Point the dropdown at the matching option if one exists, otherwise
        // fall back to "Custom role". Either way, lock state comes from is_canonical.
        const matchingOption = roleSelect.querySelector( `option[value="${roleData.slug}"]` );
        roleSelect.value     = matchingOption ? roleData.slug : '';

        this.setRoleName( roleData.label );
        this.setRoleSlug( roleData.slug );
        this.checkCapabilities( roleData.capabilities );
        this.applyLock( roleData.is_canonical );
        this.updateSelectAllStates();
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    /**
     * Return the current form values.
     *
     * @returns {{ roleSlug: string, roleLabel: string, capabilities: string[] }}
     */
    getValue() {
        return {
            roleSlug:     this.container.querySelector('.rb-role-slug').value,
            roleLabel:    this.container.querySelector('.rb-role-name').value,
            capabilities: Array.from(
                this.container.querySelectorAll(
                    '.rb-capabilities input:checked:not(.rb-select-all-domain-checkbox):not(#rb-select-all)'
                )
            ).map( cb => cb.value )
        };
    }

    /**
     * Convert a snake_case string to Title Case.
     *
     * @param   {string} str
     * @returns {string}
     */
    humanize( str ) {
        return str
            .replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase());
    }

    /**
     * @deprecated Use setRoleSlug() directly.
     *
     * Retained as a thin wrapper so any external callers that previously
     * referenced formatRoleSlug() continue to work without modification.
     *
     * @param {string}  slug
     * @param {boolean} [isFinal=false]
     */
    formatRoleSlug( slug, isFinal = false ) {
        this.setRoleSlug( slug, isFinal );
    }

    /**
     * Find a preset role key whose capability set exactly matches the given list.
     *
     * @param   {string[]} capabilities
     * @returns {string|null}  Matching preset key, or null if none found.
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