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

        /*
        |--------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------
        */
        this.layout    = document.getElementById( 'smlcd-layout' );
        this.sidebar   = document.getElementById( 'smlcd-sidebar' );
        this.backdrop  = document.getElementById( 'smlcd-backdrop' );
        this.toggleBtn = document.getElementById( 'smlcd-sidebar-toggle' );
        this.themeBtn  = document.getElementById( 'smlcd-theme-toggle' );
        this.area      = document.getElementById( 'smlcd-content-area' );
        this.content   = document.getElementById( 'smlcd-content' );
        this.loader    = document.getElementById( 'smlcd-loader' );
        this.error     = document.getElementById( 'smlcd-error' );
        this.errorMsg  = document.getElementById( 'smlcd-error-message' );
        this.retryBtn  = document.getElementById( 'smlcd-error-retry' );
        this.themeIcon = document.getElementById( 'smlcd-theme-icon' );

        this.init();
    }

    /*
    |--------------------------------------------------
    | INIT
    |--------------------------------------------------
    */
    init() {
        this.bindEvents();

        const slugFromHash = this.getSlugFromHash();
        const slug = slugFromHash || this.ACTIVE_SLUG;

        if ( slug ) {
            this.loadSection( slug, false, false );
        }
    }

    /*
    |--------------------------------------------------
    | HASH MANAGEMENT
    |--------------------------------------------------
    */
    getSlugFromHash() {
        const hash = window.location.hash.replace( /^#/, '' );
        return hash || null;
    }

    setSlugHash( slug ) {
        window.location.hash = slug;
    }

    /*
    |--------------------------------------------------
    | SERVER PREFERENCE SYNC
    |--------------------------------------------------
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

    /*
    |--------------------------------------------------
    | FETCH (with cache)
    |--------------------------------------------------
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