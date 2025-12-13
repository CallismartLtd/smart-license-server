/**
 * Script for admin repository page.
 */
class SmliserAdminRepository {
    /**
     * Class constructor
     * 
     * @param {HTMLElement} baseEl The repository base element.
     */
    constructor( baseEl ) {
        this.baseEl = baseEl;

        this._cacheElements();
        this._bindEvents();
        
    }

    /**
     * Cache elements
     */
    _cacheElements() {
        if ( ! this.baseEl ) return;

        this.galleryPreviewSection  = this.baseEl.querySelector( '.smliser-screenshot-gallery' );
        this.allPreviewImages       = this.galleryPreviewSection.querySelectorAll( 'img.repo-image-preview' );
        this.currentPreviewImage    = this.galleryPreviewSection.querySelector( '.smliser-gallery-preview_image img' );
        this.currentImageTitle      = this.galleryPreviewSection.querySelector( '.smliser-gallery-preview_title' );
    }

    /**
     * Bind eventlisteners
     */
    _bindEvents() {
        this.galleryPreviewSection?.addEventListener( 'click', this.handleGalleryClicks.bind(this) );
        this.galleryPreviewSection?.addEventListener( 'dblclick', this.toggleImageFullScreen.bind(this) );
    }

    /**
     * Handle clicks on the gallery preview section of the repository
     * 
     * @param {Event} ev The event object.
     */
    handleGalleryClicks( ev ) {
        const clickedImage = ev.target.closest( 'img.repo-image-preview' );
        if ( ! clickedImage ) return;
        
        this.allPreviewImages.forEach( img => img.classList.remove( 'active' ) );
        this.currentPreviewImage?.setAttribute( 'src', clickedImage.src );
        this.currentImageTitle.textContent = clickedImage.getAttribute( 'data-repo-image-title' );
        clickedImage.classList.add( 'active' );

        
    }

    /**
     * Toggle image fullscreen mode
     * 
     * @param {Event} ev
     */
    toggleImageFullScreen( ev ) {
        const mainImage = ev.target.closest( '.smliser-request-fullscreen' );

        if ( mainImage ) {
            mainImage.requestFullscreen();
        }
    }

}

document.addEventListener( 'DOMContentLoaded', (e) => {
    const repoBase = document.querySelector( '.smliser-admin-repository-template.repo-page' );

    if ( repoBase ) {
        new SmliserAdminRepository( repoBase );
    }
    
    
});