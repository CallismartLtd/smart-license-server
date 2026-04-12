/**
 * SmartLicenseServer Auth SPA
 *
 * Single-page authentication flow with fragment-based routing.
 * Mirrors SmliserClientDashboard architecture.
 *
 * Flow:
 * 1. User lands on /dashboard (no session)
 * 2. Shell renders auth.index skeleton
 * 3. JS boots, reads URL fragment (#login, #signup, #forgot-password, #2fa)
 * 4. JS fetches {rest_base}auth/{fragment}
 * 5. Server returns { success: true, html: "<form>..." }
 * 6. JS injects HTML into #smlag-content
 * 7. Form is interactive (login, signup, etc.)
 * 8. On form submission, JS POSTs to {rest_base}auth/{action}
 * 9. Server authenticates, returns { success: true, redirect: "/dashboard" }
 * 10. JS redirects to dashboard
 *
 * @package SmartLicenseServer\ClientDashboard\Auth
 */

class SmliserAuth {

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

        this.REPO_NAME = document.querySelector(
            'meta[name="smliser-repo-name"]'
        )?.content ?? 'Dashboard';

        /*
        |--------------------------------------------------
        | ELEMENTS
        |--------------------------------------------------
        */
        this.container = document.getElementById( 'smlag-container' );
        this.card      = document.getElementById( 'smlag-card' );
        this.content   = document.getElementById( 'smlag-content' );
        this.loader    = document.getElementById( 'smlag-loader' );
        this.error     = document.getElementById( 'smlag-error' );
        this.errorMsg  = document.getElementById( 'smlag-error-message' );
        this.retryBtn  = document.getElementById( 'smlag-error-retry' );

        /*
        |--------------------------------------------------
        | DEFAULT FORM
        |--------------------------------------------------
        */
        this.DEFAULT_FORM = 'login';

        this.init();
    }

    /*
    |--------------------------------------------------
    | INIT
    |--------------------------------------------------
    */
    init() {
        this.bindEvents();

        const formSlug = this.getFormSlugFromHash();
        const slug = formSlug || this.DEFAULT_FORM;

        if ( slug ) {
            this.loadForm( slug );
        }
    }

    /*
    |--------------------------------------------------
    | HASH MANAGEMENT
    |--------------------------------------------------
    */
    getFormSlugFromHash() {
        const hash = window.location.hash.replace( /^#/, '' );
        return hash || null;
    }

    setFormHash( slug ) {
        window.location.hash = slug;
    }

    /*
    |--------------------------------------------------
    | FORM LOADING (with cache)
    |--------------------------------------------------
    */
    async loadForm( slug, force = false ) {
        if ( ! slug || ! this.REST_BASE ) return;

        this.setLoading( true );

        // Update URL fragment unless explicitly suppressed
        this.setFormHash( slug );

        try {
            const html = await this.fetchFormHTML( slug, force );

            // Render header with repo name
            const header = `
                <div class="smlag-header">
                    <span class="smlag-brand">${ this.escapeHtml( this.REPO_NAME ) }</span>
                </div>
            `;

            // Inject header + form HTML
            this.content.innerHTML = header + html;
            this.setLoading( false );

            // Re-bind event listeners to form
            this.bindFormEvents();

        } catch ( err ) {
            this.showError(
                err?.message || 'Failed to load form.',
                slug
            );
        }
    }

    /*
    |--------------------------------------------------
    | FETCH (with cache)
    |--------------------------------------------------
    */
    async fetchFormHTML( slug, force = false ) {
        const url = this.REST_BASE + slug;

        if ( ! force && this.cache.has( url ) ) {
            return this.cache.get( url );
        }

        const response = await smliserFetchJSON( url, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
        } );

        if ( ! response.success ) {
            throw new Error( response.message || 'Failed to load form' );
        }

        this.cache.set( url, response.html );

        return response.html;
    }

    /*
    |--------------------------------------------------
    | CONTENT STATE
    |--------------------------------------------------
    */
    setLoading( loading ) {
        this.content.setAttribute( 'aria-busy', String( loading ) );
        this.loader.hidden = ! loading;
        this.content.hidden = loading;
        this.error.hidden = true;
    }

    showError( message, slug ) {
        this.content.setAttribute( 'aria-busy', 'false' );
        this.loader.hidden = true;
        this.content.hidden = true;
        this.error.hidden = false;

        this.errorMsg.textContent = message || 'Something went wrong. Please try again.';

        this.retryBtn.onclick = () => this.loadForm( slug, true );
    }

    /*
    |--------------------------------------------------
    | FORM SUBMISSION
    |--------------------------------------------------
    */
    async handleFormSubmit( e ) {
        e.preventDefault();

        const form = e.target;
        const formType = this.getFormType( form );

        if ( ! formType ) {
            return;
        }

        // Extract form data
        const formData = new FormData( form );
        const payload = Object.fromEntries( formData );

        try {
            const response = await this.submitForm( formType, payload );

            if ( response.success ) {
                this.handleFormSuccess( response, formType );
            } else {
                this.showFormError( response.message || 'Form submission failed.' );
            }

        } catch ( err ) {
            this.showFormError( err.message );
            console.error( 'Form submission error:', err );
        }
    }

    /**
     * Determine form type from form element.
     * Looks for id="smlag-{type}-form"
     */
    getFormType( form ) {
        const id = form.id || '';
        const match = id.match( /smlag-(.+)-form/ );
        return match ? match[1] : null;
    }

    /*
    |--------------------------------------------------
    | FORM SUBMISSION
    |--------------------------------------------------
    */
    async submitForm( formType, payload ) {
        const url = this.REST_BASE + formType;

        const response = await smliserFetchJSON( url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify( payload ),
        } );

        return response;
    }

    /*
    |--------------------------------------------------
    | SUCCESS HANDLING
    |--------------------------------------------------
    */
    handleFormSuccess( response, formType ) {
        if ( formType === 'login' ) {
            // Redirect to dashboard
            const redirect = response.redirect || '/dashboard';
            setTimeout( () => {
                window.location.href = redirect;
            }, 300 );
        } else if ( formType === 'signup' ) {
            // Show success message, redirect to login
            this.showFormSuccess( response.message || 'Account created successfully!' );
            setTimeout( () => {
                this.loadForm( 'login' );
            }, 2000 );
        } else if ( formType === 'forgot-password' ) {
            // Show success message
            this.showFormSuccess( response.message || 'Check your email for password reset link.' );
        }
    }

    /*
    |--------------------------------------------------
    | ERROR/SUCCESS DISPLAY
    |--------------------------------------------------
    */
    showFormError( message ) {
        const form = this.content.querySelector( 'form' );
        if ( ! form ) return;

        let alert = form.querySelector( '.smlag-form-alert-error' );

        if ( ! alert ) {
            alert = document.createElement( 'div' );
            alert.className = 'smlag-form-alert smlag-form-alert-error';
            alert.setAttribute( 'role', 'alert' );
            form.insertBefore( alert, form.firstChild );
        }

        alert.textContent = message;
        alert.hidden = false;
        alert.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
    }

    showFormSuccess( message ) {
        const form = this.content.querySelector( 'form' );
        if ( ! form ) return;

        let alert = form.querySelector( '.smlag-form-alert-success' );

        if ( ! alert ) {
            alert = document.createElement( 'div' );
            alert.className = 'smlag-form-alert smlag-form-alert-success';
            alert.setAttribute( 'role', 'status' );
            form.insertBefore( alert, form.firstChild );
        }

        alert.textContent = message;
        alert.hidden = false;
        alert.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
    }

    /*
    |--------------------------------------------------
    | UTILITY: HTML ESCAPE
    |--------------------------------------------------
    */
    escapeHtml( text ) {
        const div = document.createElement( 'div' );
        div.textContent = text;
        return div.innerHTML;
    }

    /*
    |--------------------------------------------------
    | EVENTS
    |--------------------------------------------------
    */
    bindEvents() {
        // Hash change listener for back/forward buttons
        window.addEventListener( 'hashchange', () => {
            const slug = this.getFormSlugFromHash();
            if ( slug ) {
                this.loadForm( slug, false );
            }
        } );
    }

    bindFormEvents() {
        // Form submission
        const form = this.content.querySelector( 'form' );
        if ( form ) {
            form.addEventListener( 'submit', ( e ) => this.handleFormSubmit( e ) );
        }

        // Link navigation to other forms (e.g., "Create account" → #signup)
        this.content.querySelectorAll( 'a[href^="#"]' ).forEach( link => {
            link.addEventListener( 'click', ( e ) => {
                e.preventDefault();
                const href = link.getAttribute( 'href' ).replace( /^#/, '' );
                this.loadForm( href );
            } );
        } );

        // Clear errors on input focus
        this.content.querySelectorAll( 'input, textarea' ).forEach( input => {
            input.addEventListener( 'focus', () => {
                const alert = this.content.querySelector( '.smlag-alert-error' );
                if ( alert ) {
                    alert.hidden = true;
                }
            } );
        } );

        // Form-specific handlers
        this.bindSignupFormEvents();
        this.bind2FAFormEvents();
    }

    /*
    |--------------------------------------------------
    | SIGNUP FORM HANDLERS
    |--------------------------------------------------
    */
    bindSignupFormEvents() {
        const passwordInput = this.content.querySelector( 'input[data-password-meter]' );
        if ( ! passwordInput ) return;

        passwordInput.addEventListener( 'input', () => {
            this.updatePasswordStrength( passwordInput.value );
        } );
    }

    updatePasswordStrength( password ) {
        const fill = this.content.querySelector( '#smlag-strength-fill' );
        const text = this.content.querySelector( '#smlag-strength-text' );

        if ( ! fill || ! text ) return;

        let strength = 0;
        let label = 'Weak';

        // Length check
        if ( password.length >= 8 ) strength += 1;
        if ( password.length >= 12 ) strength += 1;

        // Character type checks
        if ( /[a-z]/.test( password ) ) strength += 1;
        if ( /[A-Z]/.test( password ) ) strength += 1;
        if ( /[0-9]/.test( password ) ) strength += 1;
        if ( /[^a-zA-Z0-9]/.test( password ) ) strength += 1;

        // Update visual and text
        fill.className = 'smlag-password-strength-fill';

        if ( strength <= 2 ) {
            fill.classList.add( 'smlag-strength--weak' );
            label = 'Weak';
        } else if ( strength <= 4 ) {
            fill.classList.add( 'smlag-strength--medium' );
            label = 'Medium';
        } else {
            fill.classList.add( 'smlag-strength--strong' );
            label = 'Strong';
        }

        text.innerHTML = `
            <i class="ti ti-check" aria-hidden="true"></i>
            ${label} password
        `;
    }

    /*
    |--------------------------------------------------
    | 2FA FORM HANDLERS
    |--------------------------------------------------
    */
    bind2FAFormEvents() {
        const otpInputs = this.content.querySelectorAll( '.smlag-otp-input' );
        const backupToggle = this.content.querySelector( '#smlag-backup-toggle' );
        const backupForm = this.content.querySelector( '#smlag-backup-code-form' );

        if ( ! otpInputs.length ) return;

        // OTP auto-advance to next field
        otpInputs.forEach( ( input, index ) => {
            input.addEventListener( 'input', ( e ) => {
                if ( e.target.value.length === 1 ) {
                    // Move to next input
                    if ( index < otpInputs.length - 1 ) {
                        otpInputs[ index + 1 ].focus();
                    } else {
                        // All filled, submit automatically
                        this.updateOTPCode( otpInputs );
                    }
                }
            } );

            // Allow backspace to go to previous field
            input.addEventListener( 'keydown', ( e ) => {
                if ( e.key === 'Backspace' && e.target.value === '' ) {
                    if ( index > 0 ) {
                        otpInputs[ index - 1 ].focus();
                    }
                }
            } );

            // Only allow numbers
            input.addEventListener( 'keypress', ( e ) => {
                if ( ! /[0-9]/.test( e.key ) ) {
                    e.preventDefault();
                }
            } );
        } );

        // Backup code toggle
        if ( backupToggle && backupForm ) {
            backupToggle.addEventListener( 'click', ( e ) => {
                e.preventDefault();
                const isVisible = backupForm.hidden;
                backupForm.hidden = ! isVisible;
                backupToggle.setAttribute( 'aria-expanded', isVisible );
            } );
        }
    }

    updateOTPCode( otpInputs ) {
        const code = Array.from( otpInputs ).map( input => input.value ).join( '' );
        const hiddenField = this.content.querySelector( '#smlag-verification-code' );
        if ( hiddenField ) {
            hiddenField.value = code;
        }
    }
}

/*
|--------------------------------------------------
| BOOTSTRAP
|--------------------------------------------------
*/
document.addEventListener( 'DOMContentLoaded', () => new SmliserAuth() );