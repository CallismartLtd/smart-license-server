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
                <h3 id="rb-capabilities-title">Capabilities</h3>

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

                const fieldsetId = `rb-domain-${domain}`;

                return `
                <fieldset
                    class="rb-domain"
                    id="${fieldsetId}"
                    data-domain="${domain}"
                    aria-labelledby="${fieldsetId}-legend"
                >
                    <legend id="${fieldsetId}-legend">
                        ${this.humanize(domain)}
                    </legend>

                    ${Object.entries( caps ).map(
                        ([ cap, label ]) => {

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
                        }
                    ).join('')}
                </fieldset>
            `;
            }).join('');
    }

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

        this.container.addEventListener('change', e => {
            if ( e.target.matches('input[type="checkbox"]') ) {
                e.target.setAttribute(
                    'aria-checked',
                    e.target.checked ? 'true' : 'false'
                );
            }
        });
    }

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

    enableCustomRole() {
        this.activeRole = null;
        this.isPreset   = false;

        this.unlockCapabilities();
        this.setLockedState( false );
    }

    setRoleName( name ) {
        const input = this.container.querySelector('.rb-role-name');

        input.value    = name ? name : '';
        input.disabled = this.isPreset;

        input.setAttribute(
            'aria-disabled',
            this.isPreset ? 'true' : 'false'
        );
    }

    checkCapabilities( capabilities ) {
        capabilities?.forEach( cap => {
            const checkbox = this.container.querySelector(
                `input[value="${cap}"]`
            );

            if ( checkbox ) {
                checkbox.checked = true;
                checkbox.setAttribute('aria-checked', 'true');
            }
        });
    }

    resetCapabilities() {
        this.container
            .querySelectorAll('.rb-capabilities input[type=checkbox]')
            .forEach( cb => {
                cb.checked = false;
                cb.setAttribute('aria-checked', 'false');
            });
    }

    lockCapabilities() {
        this.toggleCapabilities( true );
    }

    unlockCapabilities() {
        this.toggleCapabilities( false );
    }

    toggleCapabilities( disabled ) {
        this.container
            .querySelectorAll('.rb-capabilities input[type=checkbox]')
            .forEach( cb => {
                cb.disabled = disabled;
                cb.setAttribute(
                    'aria-disabled',
                    disabled ? 'true' : 'false'
                );
            });
    }

    setLockedState( locked ) {
        this.container.dataset.locked = locked ? 'true' : 'false';

        this.container.setAttribute(
            'aria-busy',
            locked ? 'true' : 'false'
        );
    }

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

    humanize( str ) {
        return str
            .replace(/_/g, ' ')
            .replace(/\b\w/g, c => c.toUpperCase());
    }

    formatRoleSlug( slug, isFinal = false ) {
        const input = this.container.querySelector( '.rb-role-slug' );

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

        input.value = slug ? slug: '';
        input.disabled = this.isPreset;

        input.setAttribute(
            'aria-disabled',
            this.isPreset ? 'true' : 'false'
        );
    }

    loadRole( roleData ) {
        this.resetCapabilities();

        const roleSelect = this.container.querySelector( '.rb-role-select' );

        if ( roleData.is_canonical ) {
            const presetKey = this.findMatchingPreset( roleData.capabilities );

            if ( presetKey ) {
                roleSelect.value = presetKey;
                this.selectRole( presetKey );
                return;
            }
        }

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
