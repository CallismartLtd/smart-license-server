class SmliserClientDashboard {

    constructor() {

        /*
        |--------------------------------------------------
        | CACHE
        |--------------------------------------------------
        */
        this.cache = new Map();

        /*
        |--------------------------------------------------
        | CONFIGURATION
        |--------------------------------------------------
        */
        this.REST_BASE = document.querySelector(
            'meta[name="smliser-rest-base"]'
        )?.content ?? '';

        this.ACTIVE_SLUG = document.querySelector(
            'meta[name="smliser-active-slug"]'
        )?.content ?? '';

        this.ALLOWED_SLUGS  = document.querySelector(
            'meta[name="smliser-allowed-slugs"]'
        )?.content ?? '';

        /*
        |--------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------
        */
        this.layout     = document.querySelector( '#smlcd-layout' );
        this.sidebar    = document.querySelector( '#smlcd-sidebar' );
        this.backdrop   = document.querySelector( '#smlcd-backdrop' );
        this.toggleBtn  = document.querySelector( '#smlcd-sidebar-toggle' );
        this.themeBtn   = document.querySelector( '#smlcd-theme-toggle' );
        this.area       = document.querySelector( '#smlcd-content-area' );
        this.content    = document.querySelector( '#smlcd-content' );
        this.loader     = document.querySelector( '#smlcd-loader' );
        this.error      = document.querySelector( '#smlcd-error' );
        this.errorMsg   = document.querySelector( '#smlcd-error-message' );
        this.retryBtn   = document.querySelector( '#smlcd-error-retry' );
        this.themeIcon  = document.querySelector( '#smlcd-theme-icon' );
        this.logoutBtn  = document.querySelector( '#smlcd-logout' );

        if ( ! this.area ) return;

        this.init();
    }

    /*
    |--------------------------------------------------
    | INIT
    |--------------------------------------------------
    */
    init() {
        this.bindEvents();

        const slug = this.getSlug();

        if ( slug ) {
            this.loadSection( slug, false, false );
        }
    }

    /*
    |-----------------------
    | SLUG MANAGEMENT
    |-----------------------
    */
    getSlugFromHash() {
        const hash = window.location.hash.replace( /^#/, '' );
        return hash || null;
    }

    getSlug() {
        const allowedSlugs  = this.ALLOWED_SLUGS.split( '|' );
        const slugFromHash  = this.getSlugFromHash();

        return allowedSlugs.includes( slugFromHash ) ? slugFromHash : this.ACTIVE_SLUG;        
    }

    setSlugHash( slug ) {
        window.location.hash = slug;
    }

    /*
    |-------------------------
    | HTTP HELPERS
    |-------------------------
    */
   
    /**
     * Save dashboard preference.
     * 
     * @param {String} key The name of the preference to save.
     * @param {any} value The value
     */
    async savePreference( key, value ) {

        try {
            const url = `${this.REST_BASE}user-preference`
            await smliserFetchJSON( url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify( {
                    key,
                    value
                } )
            } );

        } catch ( err ) {
            console.warn( 'Preference sync failed:', err );
        }
    }

    /**
     * 
     * @param {String} url The dashboard content URL.
     * @param {Object} options  
     */
    async dashboardFetch( url, options = {} ) {

        const force = options.force === true;

        if ( ! force && this.cache.has( url ) ) {
            return this.cache.get( url );
        }

        const response = await smliserFetchJSON( url, {
            method: 'GET'
        } );

        if ( ! response.success ) {
            throw new Error( response.html || 'Request failed' );
        }

        this.cache.set( url, response.html );

        return response.html;
    }

    async submitForm( formSlug, payload ) {
        const url = this.REST_BASE + formSlug;

        const response = await smliserFetchJSON( url, {
            method: 'POST',
            headers: {
                'credentials': 'same-origin'
            },
            body: payload,
        } );

        return response;
    }

    /**
     * 
     * @param {MouseEvent} event 
     */
    async logoutHandler( event ) {
        event.preventDefault();
        const confirmed = await SmliserModal.confirm({
            'title': 'Confirm Logout',
            'message': 'Are you sure you want to logout? You\'ll need to sign in again to access your account.',
            'cancelText': 'Stay Logged in',
            'confirmText': 'Logout'
        });

        if ( ! confirmed ) return;

        const payLoad   = new FormData;
        try {
            const response  = await this.submitForm( 'logout', payLoad );
            
            if ( response.success ) {
                await SmliserModal.success( response.message ?? 'Logout successful' );
                window.location.reload();
            } else {
                throw {message: response.message ?? 'Something went wrong' }
            }
        } catch( err ) {
            await SmliserModal.error( err.message, 'Logout failed' );
        }
        


        
    }

    /*
    |----------------------------
    | THEME (SERVER CONTROLLED)
    |----------------------------
    */
    applyTheme( theme ) {

        this.layout.setAttribute( 'data-theme', theme );

        this.savePreference( 'theme', theme );

        if ( this.themeIcon ) {
            this.themeIcon.className =
                theme === 'dark'
                    ? 'ti ti-sun'
                    : 'ti ti-moon';
        }

        if ( this.themeBtn ) {
            this.themeBtn.setAttribute(
                'aria-label',
                theme === 'dark'
                    ? 'Switch to light mode'
                    : 'Switch to dark mode'
            );
        }
    }

    toggleTheme() {

        const current = this.layout.getAttribute( 'data-theme' ) || 'dark';

        this.applyTheme(
            current === 'dark' ? 'light' : 'dark'
        );
    }

    /*
    |------------------------------------
    | SIDEBAR (SERVER CONTROLLED STATE)
    |------------------------------------
    */
    collapseSidebar() {

        this.layout.classList.add( 'smlcd-layout--collapsed' );
        this.layout.classList.remove( 'smlcd-layout--open' );

        this.toggleBtn?.setAttribute( 'aria-expanded', 'false' );

        if ( this.isMobile() ) {
            this.backdrop?.classList.remove( 'smlcd-backdrop--visible' );
            document.body.style.overflow = '';
            return;
        }

        // Sidebar preference for non-mobile.
        this.savePreference( 'sidebar_collapsed', true );
    }

    expandSidebar() {

        this.layout.classList.remove( 'smlcd-layout--collapsed' );
        this.layout.classList.add( 'smlcd-layout--open' );

        this.toggleBtn?.setAttribute( 'aria-expanded', 'true' );

        if ( this.isMobile() ) {
            this.backdrop?.classList.add( 'smlcd-backdrop--visible' );
            document.body.style.overflow = 'hidden';
            return;
        }

        // Sidebar preference for non-mobile.
        this.savePreference( 'sidebar_collapsed', false );
    }

    toggleSidebar() {

        if ( this.layout.classList.contains( 'smlcd-layout--collapsed' ) ) {
            this.expandSidebar();
        } else {
            this.collapseSidebar();
        }
    }

    /*
    |--------------------------------------------------
    | DEVICE HELPERS
    |--------------------------------------------------
    */
    isMobile() {
        return window.innerWidth < 768;
    }

    /*
    |--------------------------------------------------
    | CONTENT STATE
    |--------------------------------------------------
    */
    setLoading( loading ) {
        if ( ! this.area ) return;

        this.area.setAttribute( 'aria-busy', String( loading ) );

        this.loader.hidden  = ! loading;
        this.content.hidden = loading;
        this.error.hidden   = true;
    }

    showError( message, slug ) {

        this.area.setAttribute( 'aria-busy', 'false' );

        this.loader.hidden  = true;
        this.content.hidden = true;
        this.error.hidden   = false;

        this.errorMsg.textContent =
            message || 'Something went wrong. Please try again.';

        this.retryBtn.onclick = () => this.loadSection( slug, true );
    }

    /*
    |--------------------------------------------------
    | ACTIVE MENU
    |--------------------------------------------------
    */
    setActive( slug ) {

        document.querySelectorAll( '.smlcd-nav-item' )
            .forEach( item => {

                const active = item.dataset.slug === slug;

                item.classList.toggle(
                    'smlcd-nav-item--active',
                    active
                );

                item.setAttribute(
                    'aria-current',
                    active ? 'page' : 'false'
                );
            } );
    }

    /*
    |--------------------------------------------------
    | SECTION LOADER
    |--------------------------------------------------
    */
    async loadSection( slug, force = false, updateHash = true ) {

        if ( ! slug || ! this.REST_BASE ) return;

        this.setLoading( true );
        this.setActive( slug );

        // Update URL fragment unless explicitly suppressed
        if ( updateHash ) {
            this.setSlugHash( slug );
        }

        if ( this.isMobile() ) {
            this.collapseSidebar();
        }

        try {

            const html = await this.dashboardFetch(
                this.REST_BASE + slug,
                { force }
            );

            this.content.innerHTML = html;
            this.setLoading( false );

        } catch ( err ) {

            this.showError(
                err?.message || 'Failed to load section.',
                slug
            );
        }
    }

    /*
    |--------------------------------------------------
    | EVENTS
    |--------------------------------------------------
    */
    bindEvents() {

        this.themeBtn?.addEventListener(
            'click',
            () => this.toggleTheme()
        );

        this.toggleBtn?.addEventListener(
            'click',
            () => this.toggleSidebar()
        );

        this.backdrop?.addEventListener(
            'click',
            () => this.collapseSidebar()
        );

        this.sidebar?.addEventListener( 'click', ( e ) => {

            const item = e.target.closest(
                '.smlcd-nav-item[data-slug]'
            );

            if ( ! item ) return;

            e.preventDefault();

            this.loadSection( item.dataset.slug );
        } );

        this.logoutBtn?.addEventListener( 'click', this.logoutHandler.bind(this) );

        /*
        |------------------------------------
        | HASH CHANGE LISTENER
        | Handles browser back/forward buttons
        | and direct hash navigation
        |------------------------------------
        */
        window.addEventListener( 'hashchange', () => {

            const slug = this.getSlugFromHash();

            if ( slug ) {
                // Don't update hash again (updateHash = false)
                this.loadSection( slug, false, false );
            }
        } );

        window.addEventListener( 'resize', () => {

            if ( ! this.isMobile() ) {
                this.backdrop?.classList.remove(
                    'smlcd-backdrop--visible'
                );

                document.body.style.overflow = '';
            }
        } );
    }
}

/*
|--------------------------------------------------
| BOOTSTRAP
|--------------------------------------------------
*/
document.addEventListener( 'DOMContentLoaded', () => new SmliserClientDashboard() );