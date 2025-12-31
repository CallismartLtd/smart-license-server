/**
 * Application Uploader Class
 * 
 * Handles plugin/theme ZIP uploads and asset management (banners, icons, screenshots).
 * 
 * @class AppUploader
 */
class AppUploader {
    /**
     * Initialize the app uploader.
     */
    constructor() {
        // Main form elements
        this.appUploaderForm    = document.querySelector( '#appUploaderForm' );
        this.queryParam         = new URLSearchParams( window.location.search );
        
        // Asset modal elements
        this.appAssetUploadModal    = document.querySelector( '.smliser-admin-modal.app-asset-uploader' );
        this.assetsContainer        = this.appUploaderForm?.querySelector( '.app-uploader-below-section_assets' );
        this.uploadToRepoButton     = this.appAssetUploadModal?.querySelector( '#upload-image' );
        
        // Asset uploader elements
        this.imageFileInput                 = document.querySelector( '#app-uploader-asset-file-input' );
        this.imageUrlInput                  = document.querySelector( '#app-uploader-asset-url-input' );
        this.assetImageUploaderContainer    = document.querySelector( '.app-asset-uploader-body_uploaded-asset' );
        this.imagePreview                   = document.querySelector( '#currentImage' );
        this.appJosnTextarea                = this.appUploaderForm.querySelector( '.smliser-json-textarea' );
        
        // State management
        this.currentImage   = null;
        this.currentConfig  = new Map();
        
        // Initialize
        this.init();
    }
    
    /**
     * Initialize all event listeners and features.
     */
    init() {
        if ( this.appUploaderForm ) {
            this.initFileUploader();
        }
        
        this.initAssetUploader();

        if ( this.appJosnTextarea ) {
            this.mountNanoEditor();
        }
        
    }
    
    /**
     * Initialize the main file uploader (ZIP files for plugins/themes).
     */
    initFileUploader() {
        const uploadBtn = document.querySelector( '.smliser-upload-btn' );
        const fileInfo = document.querySelector( '.smliser-file-info' );
        const submitBtn = this.appUploaderForm.querySelector( 'button[type="submit"]' );
        const fileInput = document.querySelector( '#smliser-file-input' );
        const originalText = fileInfo.textContent;
        const appFileDropZone = document.querySelector( '.smliser-form-file-row' );
        const clearFileInputBtn = document.querySelector( '.smliser-file-remove' );
        
        // Click upload button
        uploadBtn.addEventListener( 'click', () => fileInput.click() );
        
        // Clear file input
        clearFileInputBtn.addEventListener( 'click', () => {
            fileInput.value = '';
            fileInfo.innerHTML = '<span>No file selected.</span>';
            clearFileInputBtn.classList.add( 'smliser-hide' );
            uploadBtn.classList.remove( 'smliser-hide' );
        });
        
        // Set accept attribute
        fileInput.setAttribute( 'accept', '.zip' );
        
        // File input change
        fileInput.addEventListener( 'change', ( event ) => {
            const file = event.target.files[0];
            if ( file ) {
                const maxUploadSize = parseFloat( fileInfo.getAttribute( 'wp-max-upload-size' ) );
                const fileSizeMB = ( file.size / 1024 / 1024 ).toFixed( 2 );
                
                fileInfo.innerHTML = `
                    <table class="widefat fixed striped">
                        <tr>
                            <th>File Name:</th>
                            <td>${file.name}</td>
                        </tr>
                        <tr>
                            <th>File Size:</th>
                            <td>
                                ${fileSizeMB} MB 
                                ${maxUploadSize < fileSizeMB
                                    ? `<span class="dashicons dashicons-no" style="color: red;" title="File size exceeds the maximum upload limit of ${maxUploadSize} MB"></span>`
                                    : `<span class="dashicons dashicons-yes" style="color: green;" title="File size is within the acceptable limit"></span>`
                                }
                            </td>
                        </tr>
                    </table>
                `;
                
                clearFileInputBtn.classList.remove( 'smliser-hide' );
                uploadBtn.classList.add( 'smliser-hide' );
                
                if ( maxUploadSize < fileSizeMB ) {
                    smliserNotify( 'The uploaded file is higher than the max_upload_size, this server cannot process this request', 6000 );
                }
            } else {
                fileInfo.innerHTML = originalText;
            }
        });
        
        // Submit validation
        submitBtn.addEventListener( 'click', (e) => {
            if ( this.queryParam.get( 'tab' ) === 'add-new' && fileInput.files.length === 0 ) {
                e.preventDefault();
                const appType = StringUtils.ucfirst( this.queryParam.get( 'type' ) );
                smliserNotify( `A ${appType} file is required.`, 5000 );
            }
        });
        
        // Form submission
        this.appUploaderForm.addEventListener( 'submit', (e) => this.handleFormSubmit( e ) );
        
        // Drag and drop
        this.initDragAndDrop( appFileDropZone, fileInput, fileInfo, originalText );
    }
    
    /**
     * Initialize drag and drop functionality.
     */
    initDragAndDrop( dropZone, fileInput, fileInfo, originalText ) {
        dropZone.addEventListener( 'dragover', (e) => {
            e.preventDefault();
            
            if ( e.dataTransfer.types.includes( 'Files' ) ) {
                e.dataTransfer.dropEffect = 'copy';
                dropZone.classList.add( 'active' );
                fileInfo.innerHTML = 'Drop file here';
            } else {
                e.dataTransfer.dropEffect = 'none';
                fileInfo.innerHTML = originalText;
            }
        });
        
        dropZone.addEventListener( 'dragleave', () => {
            dropZone.classList.remove( 'active' );
            fileInfo.innerHTML = originalText;
        });
        
        dropZone.addEventListener( 'drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove( 'active' );
            
            if (e.dataTransfer.types.includes( 'Files' ) ) {
                const files = e.dataTransfer.files;
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add( files[0] );
                fileInput.files = dataTransfer.files;
                fileInput.dispatchEvent( new Event( 'change' ) );
            }
        });
    }
    
    /**
     * Handle form submission.
     * 
     * @param {Event} e The event object.
     */
    async handleFormSubmit(e) {
        e.preventDefault();

        const submitter = e.submitter;

        if ( ! submitter?.classList.contains( 'authoritatively' ) ) {
            return;
        }
        
        const payLoad = new FormData( e.currentTarget );
        payLoad.set( 'security', smliser_var.nonce );        
        
        if ( this.editor ) {
            const jsonBlob = new Blob( [this.editor.json], { type: 'application/json' } );
            const jsonFile = new File( [jsonBlob], 'app.json', { type: 'application/json' } );    
            
            payLoad.set( 'app_json_file', jsonFile );         
        }

        const spinner = showSpinner( '.smliser-spinner', true );
        
        try {
            const response = await fetch( smliser_var.smliser_ajax_url, {
                method: 'POST',
                credentials: 'same-origin',
                body: payLoad
            });
            
            if ( ! response.ok ) {
                const contentType = response.headers.get( 'Content-Type' );
                let errorMessage = 'Something went wrong!';
                
                if ( contentType.includes( 'application/json' ) ) {
                    const data = await response.json();
                    errorMessage = data?.data?.message || errorMessage;
                } else {
                    errorMessage = await response.text();
                }
                
                throw new Error( errorMessage );
            }
            
            const responseJson = await response.json();
            
            if ( ! responseJson.success ) {
                throw new Error( responseJson.data.message );
            }
            
            smliserNotify( responseJson?.data?.message ?? 'Saved', 6000 );
            setTimeout(() => {
                window.location.href = responseJson.data.redirect_url;
            }, 6000 );
        } catch ( error ) {
            smliserNotify( error.message, 10000 );
        } finally {
            removeSpinner( spinner );
        }
    }
    
    /**
     * Initialize asset uploader (banners, icons, screenshots).
     */
    initAssetUploader() {
        if ( ! this.appAssetUploadModal ) return;
        
        // Modal click handler.
        this.appAssetUploadModal.addEventListener( 'click', (e) => this.handleClickAction(e) );
        
        // Assets container click handler.
        this.assetsContainer?.addEventListener( 'click', (e) => this.handleClickAction(e) );
        
        // Image file input change.
        this.imageFileInput?.addEventListener( 'change', (e) => this.processUploadedImage(e) );
        
        // Image URL input events.
        this.imageUrlInput?.addEventListener( 'input', (e) => e.target.setCustomValidity( '' ) );
        this.imageUrlInput?.addEventListener( 'blur', (e) => this.manageInputFocus(e) );
        this.imageUrlInput?.addEventListener( 'focus', (e) => this.manageInputFocus(e) );
        
        // Image preview src change observer.
        this.imagePreview?.addEventListener( 'srcChanged', (e) => {
            const { oldSrc, newSrc } = e.detail;
            if ( oldSrc !== newSrc ) {
                this.uploadToRepoButton?.removeAttribute( 'disabled' );
            }
        });
    }
    
    /**
     * Handle click actions on buttons.
     */
    handleClickAction(e) {
        if (e.target === this.appAssetUploadModal) {
            this.closeModal();
            return;
        }
        
        const clickedBtn = e.target.closest( '.delete-image, .remove-modal, .clear-uploaded, #upload-image, #upload-from-device, #upload-from-wp, #upload-from-url, .smliser-uploader-add-image, .edit-image' );
        const action = clickedBtn ? clickedBtn.getAttribute( 'data-action' ) : null;
        
        if ( ! action ) return;
        
        const config = JSON.parse( decodeURIComponent( clickedBtn.getAttribute( 'data-config' ) ) );
        
        // Call the appropriate method
        switch ( action) {
            case 'openModal':
                this.openModal( config );
                break;
            case 'closeModal':
                this.closeModal();
                break;
            case 'uploadFromDevice':
                this.uploadFromDevice();
                break;
            case 'uploadFromWpGallery':
                this.uploadFromWpGallery();
                break;
            case 'uploadFromUrl':
                this.uploadFromUrl( config, clickedBtn );
                break;
            case 'uploadToRepository':
                this.uploadToRepository( config, clickedBtn );
                break;
            case 'deleteImage':
                this.deleteImage( config, clickedBtn );
                break;
        }
    }
    
    /**
     * Open the asset upload modal.
     */
    openModal( json ) {
        this.appAssetUploadModal.classList.remove( 'hidden' );
        
        const totalImages = document.querySelectorAll( `.app-uploader-asset-container.${json?.asset_type} img` ).length;
        const addButton = document.querySelector( `.app-uploader-asset-container.${json?.asset_type} .smliser-uploader-add-image` );
        
        this.currentConfig.set( 'total_images', totalImages );
        this.currentConfig.set( 'add_button', addButton );
        
        if ( json ) {
            this.currentConfig.set( 'app_slug', json.app_slug );
            this.currentConfig.set( 'app_type', json.app_type );
            this.currentConfig.set( 'asset_name', json.asset_name ?? '' );
            this.currentConfig.set( 'asset_type', json.asset_type ?? '' );
            this.currentConfig.set( 'limit', json.limit );
            
            if ( json.asset_url ) {
                this.assetImageUploaderContainer.classList.add( 'has-image' );
                this.imagePreview.src = json.asset_url;
                this.uploadToRepoButton.setAttribute( 'disabled', true );
                this.currentConfig.set( 'observer', this.observeImageSrcChange( this.imagePreview ) );
            }
            
            if ( totalImages >= json.limit ) {
                addButton.classList.add( 'smliser-hide' );
                this.closeModal();
                smliserNotify( `limit for ${StringUtils.ucfirst( json.asset_type )} has been reached.`, 5000 );
            } else {
                addButton.classList.remove( 'smliser-hide' );
            }
        }
    }
    
    /**
     * Close the asset upload modal.
     */
    closeModal() {
        this.resetModal( true );
        this.appAssetUploadModal.classList.add( 'hidden' );
    }
    
    /**
     * Reset modal state.
     */
    resetModal( all = false ) {
        this.assetImageUploaderContainer.classList.remove( 'has-image' );
        this.currentImage = null;
        this.imageFileInput.value = '';
        this.imageUrlInput.value = '';
        
        if ( all ) {
            this.currentConfig.get( 'observer' )?.disconnect();
            this.currentConfig.clear();
        }
    }
    
    /**
     * Upload from device.
     */
    uploadFromDevice() {
        this.imageFileInput.click();
    }
    
    /**
     * Upload from WordPress media gallery.
     */
    uploadFromWpGallery() {
        const frame = wp.media({
            title: 'Select an Image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false
        });
        
        frame.on( 'select', async () => {
            const attachment = frame.state().get( 'selection' ).first().toJSON();
            
            if ( ! attachment || ! attachment.url ) {
                smliserNotify( 'No image selected', 5000 );
                return;
            }
            
            const image = await this.processFromUrl( attachment.url );
            
            if ( ! image ) {
                console.warn( 'Image not processed' );
                return;
            }
            
            this.currentImage = image;
            this.showImagePreview( this.currentImage );
        });
        
        frame.open();
    }
    
    /**
     * Upload from URL.
     */
    async uploadFromUrl( json, clickedBtn ) {
        if ( ! this.imageUrlInput.classList.contains( 'is-active' )) {
            this.imageUrlInput.focus();
            return;
        }
        
        if ( ! this.imageUrlInput.value.trim().length ) {
            return;
        }
        
        try {
            new URL( this.imageUrlInput.value );
        } catch ( error ) {
            // Invalid URL
        }
        
        if ( ! this.imageUrlInput.checkValidity() ) {
            this.imageUrlInput.reportValidity();
            return;
        }
        
        clickedBtn.setAttribute( 'disabled', true );
        
        try {
            const image = await this.processFromUrl( this.imageUrlInput.value.trim() );
            if (!image) {
                console.warn( 'Image not fetched' );
                return;
            }
            
            this.currentImage = image;
            this.showImagePreview( this.currentImage );
        } finally {
            clickedBtn.removeAttribute( 'disabled' );
        }
    }
    
    /**
     * Process uploaded image.
     */
    async processUploadedImage(e) {
        let image = e.target.files[0];
        if (!image) {
            this.resetModal();
            return;
        }
        
        const shouldConvert = () => {
            const assetType = this.currentConfig.get( 'asset_type' );
            const appType   = this.currentConfig.get( 'app_type' );
            return ( appType === 'theme' ) && ( assetType === 'screenshot' );
        };
        
        if ( ! image.type.includes( 'image/png' ) && shouldConvert() ) {
            image = await this.convertToPng( image );
            smliserNotify( 'Image has been converted to PNG', 5000 );
        }
        
        this.currentImage = image;
        this.showImagePreview( this.currentImage );
    }
    
    /**
     * Process image from URL.
     */
    async processFromUrl( imageUrl ) {
        const ajaxEndpoint = new URL( `${smliser_var.admin_url}admin-post.php` );
        ajaxEndpoint.searchParams.set( 'action', 'smliser_download_image' );
        ajaxEndpoint.searchParams.set( 'image_url', imageUrl );
        ajaxEndpoint.searchParams.set( 'security', smliser_var.nonce );
        
        const spinner = showSpinner( '.smliser-spinner.modal' );
        try {
            const response = await fetch( ajaxEndpoint.href );
            const contentType = response.headers.get( 'Content-Type' );
            
            if ( ! response.ok ) {
                let errorMessage = response.statusText;
                let text = await response.text();
                
                if ( text.length < 5000 ) {
                    errorMessage = text;
                }
                
                throw new Error( `Image fetch failed: ${errorMessage}` );
            }
            
            const blob = await response.blob();
            const fileName = imageUrl.split( '/' ).pop() ?? 'image.png';
            
            const shouldConvert = () => {
                const assetType = this.currentConfig.get( 'asset_type' );
                const appType = this.currentConfig.get( 'app_type' );
                return ( appType === 'theme' ) && ( assetType === 'screenshot' );
            };
            
            if ( ! contentType.includes( 'image/png' ) && shouldConvert() ) {
                const image = await this.convertToPng( blob, fileName );
                smliserNotify( 'Image has been converted to PNG', 5000 );
                return image;
            }
            
            return new File( [blob], fileName, { type: 'image/png' } );
        } catch ( error ) {
            smliserNotify( error.message, 10000 );
        } finally {
            removeSpinner( spinner );
        }
        
        return null;
    }
    
    /**
     * Show image preview.
     */
    showImagePreview( file ) {
        const fileReader = new FileReader();
        fileReader.onload = (e) => {
            this.imagePreview.src = e.target.result;
            this.assetImageUploaderContainer.classList.add( 'has-image' );
            this.uploadToRepoButton.removeAttribute( 'disabled' );
        };
        
        fileReader.readAsDataURL( file );
    }
    
    /**
     * Convert image to PNG.
     */
    convertToPng( blob, outputName = 'converted.png' ) {
        return new Promise(( resolve, reject ) => {
            const img = new Image();
            const url = URL.createObjectURL( blob );
            
            img.onload = () => {
                const canvas = document.createElement( 'canvas' );
                canvas.width = img.width;
                canvas.height = img.height;
                
                const ctx = canvas.getContext( '2d' );
                ctx.drawImage( img, 0, 0 );
                
                canvas.toBlob(( pngBlob ) => {
                    if ( ! pngBlob ) {
                        reject( new Error( 'Canvas export failed' ) );
                        return;
                    }
                    
                    resolve( new File( [pngBlob], outputName, { type: 'image/png' } ) );
                }, 'image/png' );
                
                URL.revokeObjectURL( url );
            };
            
            img.onerror = () => reject( new Error( 'Invalid image file' ) );
            img.src = url;
        });
    }
    
    /**
     * Upload to repository.
     */
    async uploadToRepository( json, clickedBtn ) {
        if ( ! this.currentImage ) {
            smliserNotify( 'Please upload an image.', 3000 );
            return;
        }
        
        clickedBtn.setAttribute( 'disabled', true );
        const container = document.querySelector( `.app-uploader-asset-container.${this.currentConfig.get( 'asset_type' )}` );
        
        const url = new URL( smliser_var.smliser_ajax_url );
        url.searchParams.set( 'action', 'smliser_app_asset_upload' );
        url.searchParams.set( 'security', smliser_var.nonce );
        
        const payLoad = new FormData();
        payLoad.set( 'app_slug', this.currentConfig?.get( 'app_slug' ) );
        payLoad.set( 'app_type', this.currentConfig?.get( 'app_type' ) );
        payLoad.set( 'asset_name', this.currentConfig?.get( 'asset_name' ) );
        payLoad.set( 'asset_type', this.currentConfig?.get( 'asset_type' ) );
        payLoad.set( 'asset_file', this.currentImage );
        
        const spinner = showSpinner( '.smliser-spinner.modal', true );
        try {
            const response = await fetch( url, {
                method: 'POST',
                body: payLoad,
                credentials: 'same-origin'
            });
            
            if ( ! response.ok ) {
                const contentType = response.headers.get( 'Content-Type' );
                let errorMessage = 'Something went wrong!';
                
                if ( contentType.includes( 'application/json' ) ) {
                    const data = await response.json();
                    errorMessage = data?.data?.message || errorMessage;
                } else {
                    errorMessage = await response.text();
                }
                
                throw new Error( errorMessage );
            }
            
            const data = await response.json();
            
            if ( ! data.success ) {
                throw new Error( data?.data?.message || 'Unable to upload image' );
            }
            
            const configJson    = data.data.config;
            const new_image_url = configJson.asset_url;
            const existingImage = this.currentConfig.get( 'asset_name' );
            
            if (existingImage) {
                const imageEl = document.querySelector( `#${existingImage.split( '.' )[0]}` );
                imageEl.setAttribute( 'src', new_image_url );
            } else {
                this.addNewImageToContainer( container, configJson, new_image_url );
            }
            
            this.resetModal();
            this.closeModal();
        } catch (error) {
            smliserNotify( error.message, 20000 );
            console.error( error );
        } finally {
            removeSpinner( spinner );
            clickedBtn.removeAttribute( 'disabled' );
        }
    }
    
    /**
     * Add new image to container.
     */
    addNewImageToContainer( container, configJson, new_image_url ) {
        const imageContainer        = document.createElement( 'div' );
        imageContainer.className    = 'app-uploader-image-preview';
        const imageName = new_image_url.split( '/' ).pop();
        
        imageContainer.innerHTML = `
            <img src="${new_image_url}" alt="${imageName}" title="${imageName}">
            <div class="app-uploader-image-preview_edit">
                <span class="dashicons dashicons-edit edit-image" data-config="${encodeURIComponent( JSON.stringify( configJson ))}" data-action="openModal" title="Edit"></span>
                <span class="dashicons dashicons-trash delete-image" data-config="${encodeURIComponent( JSON.stringify( configJson ))}" data-action="deleteImage" title="Delete"></span>
            </div>
        `;
        
        const addButtonMore = this.currentConfig.get( 'add_button' );
        const title = this.currentConfig.get( 'asset_type' );
        const limit = this.currentConfig.get( 'limit' );
        
        setTimeout(() => {
            const target    = container.querySelector( '.app-uploader-asset-container_images' );
            const addButton = target.querySelector( '.smliser-uploader-add-image' );
            
            target.insertBefore( imageContainer, addButton );
            const totalImages = document.querySelectorAll( `.app-uploader-asset-container.${title} img` ).length;
            
            if ( totalImages >= limit ) {
                addButtonMore.classList.add( 'smliser-hide' );
                this.closeModal();
                smliserNotify( `limit for ${StringUtils.ucfirst( title )} has been reached.`, 5000 );
            } else {
                addButtonMore.classList.remove( 'smliser-hide' );
            }
        }, 400);
    }
    
    /**
     * Delete image.
     */
    async deleteImage( json, clickedBtn ) {
        const confirmed = confirm( `Are you sure to delete ${json.asset_name ?? 'this image'}? This action cannot be reversed` );
        if ( ! confirmed ) return;
        
        const payLoad = new FormData();
        
        for ( const key in json ) {
            payLoad.set( key, json[key] );
        }
        
        payLoad.set( 'action', 'smliser_app_asset_delete' );
        payLoad.set( 'security', smliser_var.nonce );
        
        clickedBtn.setAttribute( 'disabled', true );
        
        try {
            const response = await fetch( smliser_var.smliser_ajax_url, {
                method: 'POST',
                body: payLoad,
                credentials: 'same-origin'
            });
            
            const contentType = response.headers.get( 'Content-Type' );
            
            if ( ! response.ok ) {
                const isJson = contentType.includes( 'application/json' );
                const data = isJson ? await response.json() : await response.text();
                const errorMessage = data?.data?.message ?? data;
                
                throw new Error( errorMessage );
            }
            
            const responseJson = await response.json();
            
            if ( ! responseJson.success ) {
                throw new Error( responseJson.data?.message ?? `unable to delete ${json.asset_name}` );
            }
            
            smliserNotify( responseJson.data?.message, 3000 );
            
            const target = clickedBtn.closest( '.app-uploader-image-preview' );
            jQuery( target).fadeOut( 'slow', () => {
                target.remove();
            });
        } catch ( error ) {
            smliserNotify( error.message, 10000 );
        }
        
        const totalImages = document.querySelectorAll( `.app-uploader-asset-container.${json?.asset_type} img` ).length;
        const addButton = document.querySelector( `.app-uploader-asset-container.${json?.asset_type} .smliser-uploader-add-image` );
        
        if ( totalImages >= json.limit ) {
            addButton.classList.add( 'smliser-hide' );
            this.closeModal();
            smliserNotify( `limit for ${StringUtils.ucfirst( json.asset_type )} has been reached.`, 3000 );
        } else {
            addButton.classList.remove( 'smliser-hide' );
        }
    }
    
    /**
     * Manage input focus state.
     */
    manageInputFocus(e) {
        const input = e.target;
        
        if ( input.value.trim().length ) {
            input.classList.add( 'is-active' );
        } else {
            input.classList.remove( 'is-active' );
        }
    }
    
    /**
     * Observe image src changes.
     */
    observeImageSrcChange( img ) {
        const observer = new MutationObserver( mutations => {
            for ( const mutation of mutations ) {
                if ( mutation.type === "attributes" && mutation.attributeName === "src" ) {
                    const event = new CustomEvent( "srcChanged", {
                        detail: {
                            oldSrc: mutation.oldValue,
                            newSrc: img.src
                        }
                    });
                    img.dispatchEvent( event );
                }
            }
        });
        
        observer.observe( img, {
            attributes: true,
            attributeFilter: ["src"],
            attributeOldValue: true
        });
        
        return observer;
    }

    /**
     * Mount nanoeditor
     */
    mountNanoEditor() {
        const textarea          = this.appJosnTextarea;
        const textareaParent    = textarea.parentElement;
        const editorFrame       = document.createElement( 'div' );

        editorFrame.id          = 'smliser-appjson-editor';

        let jsonData            = {};

        try {
            jsonData = JSON.parse( textarea.value );
        } catch (error) {
            jsonData = {};
        }

        textareaParent.parentElement.appendChild( editorFrame );

        const preventButtonFormSubmission = () => {
            editorFrame.querySelectorAll( 'button' ).forEach( btn => btn.setAttribute( 'type', 'button' ) );
        }

        this.editor = new JSONEditor({
            id: editorFrame.id,
            title: "APP JSON Editor",
            description: "Edit your application's JSON file (app.json). Values in this file will be served in the REST API response.",
            json: jsonData,
            when: {
                rendered: preventButtonFormSubmission,
                updated: preventButtonFormSubmission
            }
        });
        
        preventButtonFormSubmission();
        textarea.setAttribute( 'disabled', true );
        textareaParent.classList.add( 'json-mounted' );
    }
}

// Initialize when DOM is ready
document.addEventListener( 'DOMContentLoaded', () => {
    new AppUploader();
});