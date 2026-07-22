/**
 * Application Uploader Class
 *
 * Handles plugin/theme ZIP uploads and asset management (banners, icons, screenshots).
 * Supports single and multiple file uploads.
 *
 * @class AppUploader
 */
class AppUploader {

    /*
    |------------------
    |Static Constants
    |------------------
    */

    static ACTIONS = Object.freeze({
        OPEN_MODAL          : 'openModal',
        CLOSE_MODAL         : 'closeModal',
        RESET_MODAL         : 'resetModal',
        UPLOAD_FROM_DEVICE  : 'uploadFromDevice',
        UPLOAD_FROM_WP      : 'uploadFromWpGallery',
        UPLOAD_FROM_URL     : 'uploadFromUrl',
        UPLOAD_TO_REPO      : 'uploadToRepository',
        DELETE_IMAGE        : 'deleteImage',
        EDIT_IMAGE          : 'editImage',
    });

    static SELECTORS = Object.freeze({
        FORM                    : '#appUploaderForm',
        MODAL                   : '.smliser-admin-modal.app-asset-uploader',
        ASSETS_CONTAINER        : '.app-uploader-below-section_assets',
        UPLOAD_TO_REPO_BTN      : '#upload-image',
        FILE_INPUT              : '#app-uploader-asset-file-input',
        URL_INPUT               : '#app-uploader-asset-url-input',
        IMAGE_UPLOADER_BODY     : '.app-asset-uploader-body_uploaded-asset',
        IMAGE_PREVIEW           : '#currentImage',
        JSON_TEXTAREA           : '.smliser-json-textarea',
        ZIP_UPLOAD_BTN          : '.smliser-upload-btn',
        ZIP_FILE_INFO           : '.smliser-file-info',
        ZIP_SUBMIT_BTN          : 'button[type="submit"]',
        ZIP_FILE_INPUT          : '#smliser-file-input',
        ZIP_DROP_ZONE           : '.smliser-form-file-row',
        ZIP_CLEAR_BTN           : '.smliser-file-remove',
        CLICKABLE_ACTIONS       : '.delete-image, .remove-modal, .clear-uploaded, #upload-image, #upload-from-device, #upload-from-wp, #upload-from-url, .smliser-uploader-add-image, .edit-image',
        ARTIFACT_UPLOADER       : '.application-uploader-page.artifact-editor',
        ARTIFACT_DOWNLOAD_BTN   : '.smliser-download-artifact',
        ARTIFACT_ADD_NEW_BTN    : '.smliser-add-new-artifact',
        ARTIFACT_DELETE_BTN     : '.smliser-delete-artifact',
        ARTIFACT_EDIT_BTN       : '.smliser-edit-artifact',

    });

    /*
    |------------------------------
    |Constructor & Initialization
    |------------------------------
    */

    constructor() {
        this._bindElements();
        this._initState();
        this.init();
    }

    /**
     * Bind all DOM elements to instance properties.
     */
    _bindElements() {
        const qs = ( sel, ctx = document ) => ctx.querySelector( sel );

        this.appUploaderForm            = qs( AppUploader.SELECTORS.FORM );
        this.queryParam                 = new URLSearchParams( window.location.search );

        // Asset modal
        this.appAssetUploadModal        = qs( AppUploader.SELECTORS.MODAL );
        this.assetsContainer            = this.appUploaderForm?.querySelector( AppUploader.SELECTORS.ASSETS_CONTAINER );
        this.uploadToRepoButton         = this.appAssetUploadModal?.querySelector( AppUploader.SELECTORS.UPLOAD_TO_REPO_BTN );

        // Asset uploader inputs & preview
        this.imageFileInput             = qs( AppUploader.SELECTORS.FILE_INPUT );
        this.imageUrlInput              = qs( AppUploader.SELECTORS.URL_INPUT );
        this.assetImageUploaderContainer= qs( AppUploader.SELECTORS.IMAGE_UPLOADER_BODY );
        this.imagePreview               = qs( AppUploader.SELECTORS.IMAGE_PREVIEW );
        this.appJsonTextarea            = this.appUploaderForm?.querySelector( AppUploader.SELECTORS.JSON_TEXTAREA );

        // Artifact uploader.
        this.artifactUploader           = qs( AppUploader.SELECTORS.ARTIFACT_UPLOADER );
    }

    /**
     * Initialize reactive state.
     */
    _initState() {
        this.currentFiles   = [];   // Supports multiple files
        this.currentConfig  = new Map();
        this.editor         = null;
        this.artifactModal  = null;
    }

    /**
     * Entry point — wire up all subsystems.
     */
    init() {
        if ( this.appUploaderForm ) {
            this._initFileUploader();
        }

        this._initAssetUploader();

        if ( this.appJsonTextarea ) {
            this.#mountJsonEditor();
        }

        if ( this.artifactUploader ) {
            this._initArtifactUploader();
        }
    }

    /*
    |-------------------
    |ZIP File Uploader
    |-------------------
    */

    /**
     * Initialize the main ZIP file uploader.
     */
    _initFileUploader() {
        const uploadBtn     = document.querySelector( AppUploader.SELECTORS.ZIP_UPLOAD_BTN );
        const fileInfo      = document.querySelector( AppUploader.SELECTORS.ZIP_FILE_INFO );
        const submitBtn     = this.appUploaderForm.querySelector( AppUploader.SELECTORS.ZIP_SUBMIT_BTN );
        const fileInput     = document.querySelector( AppUploader.SELECTORS.ZIP_FILE_INPUT );
        const dropZone      = document.querySelector( AppUploader.SELECTORS.ZIP_DROP_ZONE );
        const clearBtn      = document.querySelector( AppUploader.SELECTORS.ZIP_CLEAR_BTN );
        const originalText  = fileInfo.textContent;

        fileInput.setAttribute( 'accept', 'application/zip' );

        uploadBtn.addEventListener( 'click', () => fileInput.click() );

        clearBtn.addEventListener( 'click', () => {
            fileInput.value     = '';
            fileInfo.innerHTML  = '<span>No file selected.</span>';
            clearBtn.classList.add( 'smliser-hide' );
            uploadBtn.classList.remove( 'smliser-hide' );
        });

        fileInput.addEventListener( 'change', ( e ) => {
            this._handleZipFileChange( e, fileInfo, clearBtn, uploadBtn );
        });

        submitBtn.addEventListener( 'click', ( e ) => {
            this._validateZipSubmit( e, fileInput );
        });

        this.appUploaderForm.addEventListener( 'submit', ( e ) => this._handleFormSubmit( e ) );

        this._initDragAndDrop( dropZone, fileInput, fileInfo, originalText );
    }

    /**
     * Handle ZIP file selection change.
     */
    _handleZipFileChange( e, fileInfo, clearBtn, uploadBtn ) {
        const file = e.target.files[0];

        if ( ! file ) {
            fileInfo.innerHTML = '<span>No file selected.</span>';
            return;
        }

        const maxUploadSize = smliser_var.uploads.max_upload_size;
        const exceedsLimit  = file.size > maxUploadSize;
        const fileSizeMB    = StringUtils.formatBytes( file.size, 2 );        

        fileInfo.innerHTML = `
            <table class="widefat fixed striped">
                <tr>
                    <th>Selected File Name:</th>
                    <td>${ file.name }</td>
                </tr>
                <tr>
                    <th>File Size:</th>
                    <td>
                        ${ fileSizeMB }
                        <span
                            class="ti ti-${ exceedsLimit ? 'x' : 'check' }"
                            style="color: ${ exceedsLimit ? 'red' : 'green' };"
                            title="${ exceedsLimit
                                ? `File size exceeds the maximum upload limit of ${ maxUploadSize } MB`
                                : 'File size is within the acceptable limit'
                            }"
                        ></span>
                    </td>
                </tr>
            </table>
        `;

        clearBtn.classList.remove( 'smliser-hide' );
        uploadBtn.classList.add( 'smliser-hide' );

        if ( exceedsLimit ) {
            SmliserToast.show( 'The uploaded file exceeds the max_upload_size; this server cannot process this request.', 6000 );
        }
    }

    /**
     * Validate ZIP form before submission.
     */
    _validateZipSubmit( e, fileInput ) {
        if ( this.queryParam.get( 'tab' ) === 'add-new' && fileInput.files.length === 0 ) {
            e.preventDefault();
            const appType = StringUtils.ucfirst( this.queryParam.get( 'type' ) );
            SmliserToast.show( `A ${ appType } file is required.`, 5000 );
        }
    }

    /**
     * Initialize drag-and-drop for ZIP upload zone.
     */
    _initDragAndDrop( dropZone, fileInput, fileInfo, originalText ) {
        dropZone.addEventListener( 'dragover', ( e ) => {
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

        dropZone.addEventListener( 'drop', ( e ) => {
            e.preventDefault();
            dropZone.classList.remove( 'active' );

            if ( e.dataTransfer.types.includes( 'Files' ) ) {
                const dataTransfer = new DataTransfer();
                dataTransfer.items.add( e.dataTransfer.files[0] );
                fileInput.files = dataTransfer.files;
                fileInput.dispatchEvent( new Event( 'change' ) );
            }
        });
    }

    /*
    |-----------------
    |Form Submission
    |-----------------
    */

    /**
     * Handle main form AJAX submission.
     */
    async _handleFormSubmit( e ) {
        e.preventDefault();

        if ( ! e.submitter?.classList.contains( 'authoritatively' ) ) return;

        const payLoad = new FormData( e.currentTarget );
        payLoad.set( 'security', smliser_var.csrf_token );

        if ( this.editor ) {
            const jsonString    = await this.editor.getJSON( true );
            const jsonFile      = new File( [jsonString], 'app.json', { 
                type: 'application/json',
                lastModified: this.editor.lastModified ?? Date.now(),
            });
            payLoad.set( 'app_json_file', jsonFile );
        }

        const spinner = showSpinner( '.smliser-spinner', true );

        try {
            const response = await fetch( smliser_var.ajaxURL, {
                method      : 'POST',
                credentials : 'same-origin',
                body        : payLoad,
            });

            const data = await this._parseResponse( response );

            if ( ! data.success ) {
                throw new Error( data.data.message );
            }

            SmliserToast.show( data?.data?.message ?? 'Saved', 6000 );

            setTimeout( () => {
                window.location.href = data.data.redirect_url;
            }, 6000 );

        } catch ( error ) {
            SmliserToast.show( error.message, 10000 );
        } finally {
            removeSpinner( spinner );
        }
    }

    /*
    |---------------------------------
    |Asset Uploader — Event Wiring
    |---------------------------------
    */

    /**
     * Wire up all asset-uploader related events.
     */
    _initAssetUploader() {
        if ( ! this.appAssetUploadModal ) return;

        // Delegated click handlers
        this.appAssetUploadModal.addEventListener( 'click', ( e ) => this._handleClickAction( e ) );
        this.assetsContainer?.addEventListener( 'click', ( e ) => this._handleClickAction( e ) );

        // File input — supports multiple files
        this.imageFileInput?.addEventListener( 'change', ( e ) => this._processUploadedImages( e ) );

        // URL input lifecycle
        this.imageUrlInput?.addEventListener( 'input',  ( e ) => e.target.setCustomValidity( '' ) );
        this.imageUrlInput?.addEventListener( 'blur',   ( e ) => this._manageInputFocus( e ) );
        this.imageUrlInput?.addEventListener( 'focus',  ( e ) => this._manageInputFocus( e ) );

        // Fullscreen preview on double-click
        this.imagePreview?.addEventListener( 'dblclick', () => this.imagePreview.requestFullscreen() );

        // Re-enable upload button when image src changes
        this.imagePreview?.addEventListener( 'srcChanged', ( e ) => {
            if ( e.detail.oldSrc !== e.detail.newSrc ) {
                this.uploadToRepoButton?.removeAttribute( 'disabled' );
            }
        });
    }

    /**
     * Delegated click dispatcher for all action buttons.
     */
    _handleClickAction( e ) {
        // Clicking the modal backdrop closes it
        if ( e.target === this.appAssetUploadModal ) {
            this.closeModal();
            return;
        }

        const btn       = e.target.closest( AppUploader.SELECTORS.CLICKABLE_ACTIONS );
        const action    = btn?.getAttribute( 'data-action' );

        if ( ! action ) return;

        const config = StringUtils.JSONparse( StringUtils.rawUrlDecode( btn.getAttribute( 'data-config' ) ) );

        const actionMap = {
            [ AppUploader.ACTIONS.OPEN_MODAL        ]: () => this.openModal( config ),
            [ AppUploader.ACTIONS.CLOSE_MODAL       ]: () => this.closeModal(),
            [ AppUploader.ACTIONS.RESET_MODAL       ]: () => this.resetModal(),
            [ AppUploader.ACTIONS.UPLOAD_FROM_DEVICE]: () => this._uploadFromDevice(),
            [ AppUploader.ACTIONS.UPLOAD_FROM_WP    ]: () => this._uploadFromWpGallery(),
            [ AppUploader.ACTIONS.UPLOAD_FROM_URL   ]: () => this._uploadFromUrl( config, btn ),
            [ AppUploader.ACTIONS.UPLOAD_TO_REPO    ]: () => this.uploadToRepository( config, btn ),
            [ AppUploader.ACTIONS.DELETE_IMAGE      ]: () => this._deleteImage( config, btn ),
        };

        actionMap[ action ]?.();
    }

    /*
    |-----------------
    |Modal Lifecycle
    |-----------------
    */

    /**
     * Open the asset upload modal and configure it for the given asset type.
     */
    openModal( config ) {
        if ( ! config ) return;

        const totalImages   = document.querySelectorAll( `.app-uploader-asset-container.${ config.asset_type } img` ).length;
        const addButton     = document.querySelector( `.app-uploader-asset-container.${ config.asset_type } .smliser-uploader-add-image` );
        
        // Persist config
        this.currentConfig.set( 'total_images', totalImages );
        this.currentConfig.set( 'add_button', addButton );
        this.currentConfig.set( 'app_slug', config.app_slug );
        this.currentConfig.set( 'app_type', config.app_type );
        this.currentConfig.set( 'asset_name', config.asset_name ?? '' );
        this.currentConfig.set( 'asset_type', config.asset_type ?? '' );
        this.currentConfig.set( 'context', config.context );

        this.imageFileInput.setAttribute( 'multiple', true );

        // Pre-populate if editing an existing asset
        if ( config.asset_url ) {
            this.assetImageUploaderContainer.classList.add( 'has-image' );
            this.imagePreview.src = config.asset_url;
            this.uploadToRepoButton.setAttribute( 'disabled', true );
            this.currentConfig.set( 'observer', this._observeImageSrcChange( this.imagePreview ) );
        }        
        
        this.appAssetUploadModal.classList.remove( 'hidden' );
    }

    /**
     * Close the modal and fully reset state.
     */
    closeModal() {
        this.resetModal( true );
        this.appAssetUploadModal.classList.add( 'hidden' );
    }

    /**
     * Reset modal UI and optionally clear configuration state.
     *
     * @param {boolean} all  When true, also clears the currentConfig map.
     */
    resetModal( all = false ) {
        this._clearPreviewState();
        this.currentFiles           = [];
        this.imageFileInput.value   = '';
        this.imageUrlInput.value    = '';

        if ( all ) {
            this.currentConfig.get( 'observer' )?.disconnect();
            this.currentConfig.clear();
        }
    }

    /*
    |----------------------
    |Asset Upload Sources
    |----------------------
    */

    /**
     * Trigger the hidden file input (supports multiple files).
     */
    _uploadFromDevice() {
        this.imageFileInput.click();
    }

    /**
     * Open the WordPress media gallery picker.
     */
    _uploadFromWpGallery() {
        const frame = wp.media({
            title   : 'Select an Image',
            button  : { text: 'Use this image' },
            library : { type: 'image' },
            multiple: false,
        });

        frame.on( 'select', async () => {
            const attachment = frame.state().get( 'selection' ).first().toJSON();

            if ( ! attachment?.url ) {
                SmliserToast.show( 'No image selected', 5000 );
                return;
            }

            const image = await this._processFromUrl( attachment.url );
            if ( ! image ) return;

            this.currentFiles = [ image ];
            this._showImagePreview( image );
        });

        frame.open();
    }

    /**
     * Fetch and stage an image from a remote URL.
     */
    async _uploadFromUrl( config, btn ) {
        if ( ! this.imageUrlInput.classList.contains( 'is-active' ) ) {
            this.imageUrlInput.focus();
            return;
        }

        const urlValue = this.imageUrlInput.value.trim();
        if ( ! urlValue ) return;

        try {
            new URL( urlValue );
        } catch {
            this.imageUrlInput.setCustomValidity( 'Please enter a valid URL.' );
            this.imageUrlInput.reportValidity();
            return;
        }

        if ( ! this.imageUrlInput.checkValidity() ) {
            this.imageUrlInput.reportValidity();
            return;
        }

        btn.setAttribute( 'disabled', true );

        try {
            const image = await this._processFromUrl( urlValue );
            if ( ! image ) return;

            this.currentFiles = [ image ];
            this._showImagePreview( image );
        } finally {
            btn.removeAttribute( 'disabled' );
        }
    }

    /*
    |-----------------------
    |Multi-File Processing
    |-----------------------
    */

    /**
     * Process one or more uploaded image files from the file input.
     */
    async _processUploadedImages( e ) {
        const files = Array.from( e.target.files );
        if ( ! files.length ) {
            this.resetModal();
            return;
        }

        const filesToProcess    = files;

        // Always wipe previous preview state before rendering the new selection
        // so stale images / strip thumbnails never bleed through.
        this._clearPreviewState();

        const processed = await Promise.all(
            filesToProcess.map( ( file ) => this._maybeConvertToPng( file ) )
        );

        this.currentFiles = processed.filter( Boolean );

        if ( this.currentFiles.length === 1 ) {
            this._showImagePreview( this.currentFiles[0] );
        } else if ( this.currentFiles.length > 1 ) {
            this._showMultiFilePreview( this.currentFiles );
        }
    }

    /**
     * Wipe all preview UI back to a clean slate without touching currentConfig.
     * Called before rendering a fresh file selection so no stale state bleeds through.
     */
    _clearPreviewState() {
        // Hide the single-preview area and blank its <img>
        this.assetImageUploaderContainer.classList.remove( 'has-image' );
        this.imagePreview.src = '';
        this.imagePreview.removeAttribute( 'src' );

        // Clear the multi-file strip
        const strip = document.querySelector( '.smliser-multi-preview' );
        if ( strip ) strip.innerHTML = '';

        // Disable the upload button until something is staged again
        this.uploadToRepoButton?.setAttribute( 'disabled', true );
    }

    /**
     * Convert a file to PNG if required by asset type/app type rules.
     *
     * @param {File|Blob} file
     * @returns {Promise<File>}
     */
    async _maybeConvertToPng( file ) {
        const assetType = this.currentConfig.get( 'asset_type' );
        const appType   = this.currentConfig.get( 'app_type' );
        const needsPng  = appType === 'theme' && assetType === 'screenshot';

        if ( needsPng && ! file.type.includes( 'image/png' ) ) {
            const converted = await this._convertToPng( file );
            SmliserToast.show( `"${ file.name }" has been converted to PNG.`, 4000 );
            return converted;
        }

        return file;
    }

    /**
     * Fetch an image from a URL via the server-side proxy.
     *
     * @param {string} imageUrl
     * @returns {Promise<File|null>}
     */
    async _processFromUrl( imageUrl ) {
        const endpoint = new URL( smliser_var.admin_url );
        endpoint.searchParams.set( 'action',    'smliser_download_image' );
        endpoint.searchParams.set( 'image_url', imageUrl );
        endpoint.searchParams.set( 'security',  smliser_var.csrf_token );

        const spinner = showSpinner( '.smliser-spinner.modal' );

        try {
            const response      = await fetch( endpoint.href );
            const contentType   = response.headers.get( 'Content-Type' );

            if ( ! response.ok ) {
                const text          = await response.text();
                const errorMessage  = text.length < 5000 ? text : response.statusText;
                throw new Error( `Image fetch failed: ${ errorMessage }` );
            }

            const blob      = await response.blob();
            const fileName  = imageUrl.split( '/' ).pop() ?? 'image.png';

            const assetType = this.currentConfig.get( 'asset_type' );
            const appType   = this.currentConfig.get( 'app_type' );
            const needsPng  = appType === 'theme' && assetType === 'screenshot';

            if ( needsPng && ! contentType.includes( 'image/png' ) ) {
                const converted = await this._convertToPng( blob, fileName );
                SmliserToast.show( 'Image has been converted to PNG.', 5000 );
                return converted;
            }

            return new File( [blob], fileName, { type: blob.type || 'image/png' } );

        } catch ( error ) {
            await SmliserModal.error( error.message );
            return null;
        } finally {
            removeSpinner( spinner );
        }
    }

    /*
    |---------------
    |Image Preview
    |---------------
    */

    /**
     * Render a single-image preview in the modal.
     *
     * @param {File} file
     */
    _showImagePreview( file ) {

        // Release the previous preview URL.
        if ( this.imagePreview.dataset.objectUrl ) {
            URL.revokeObjectURL( this.imagePreview.dataset.objectUrl );
        }

        const objectUrl = URL.createObjectURL( file );

        this.imagePreview.dataset.objectUrl = objectUrl;
        this.imagePreview.src               = objectUrl;

        this.assetImageUploaderContainer.classList.add( 'has-image' );
        this.uploadToRepoButton.removeAttribute( 'disabled' );
    }

    /**
     * Render a multi-file thumbnail strip when more than one file is staged.
     *
     * - The main preview always shows the currently selected thumb (first by default).
     * - Clicking any thumb swaps it into the main preview (with fullscreen support).
     * - The active thumb is highlighted with an `is-active` class.
     *
     * @param {File[]} files
     */
    _showMultiFilePreview( files ) {
        let strip = document.querySelector( '.smliser-multi-preview' );

        if ( ! strip ) {
            strip           = document.createElement( 'div' );
            strip.className = 'smliser-multi-preview';
            this.assetImageUploaderContainer.after( strip );
        }

        strip.innerHTML = '';

        /**
         * Swap the main preview to show the given file and mark its thumb active.
         *
         * @param {File}        file
         * @param {HTMLElement} thumbEl
         */
        const activateThumb = ( file, thumbEl ) => {
            // Update main preview
            this._showImagePreview( file );

            // Shift the active marker
            strip.querySelectorAll( '.smliser-multi-preview_thumb' ).forEach( ( t ) => {
                t.classList.remove( 'is-active' );
            });
            thumbEl.classList.add( 'is-active' );
        };

    files.forEach( ( file, renderIndex ) => {
        const objectUrl = URL.createObjectURL( file );

        const thumb = document.createElement( 'div' );
        thumb.className = 'smliser-multi-preview_thumb';
        thumb.title = 'Click to preview';

        thumb.innerHTML = `
            <img src="${ objectUrl }" alt="${ file.name }" loading="lazy">
            <span class="smliser-multi-preview_name">${ file.name }</span>
            <button type="button" class="smliser-multi-preview_remove" title="Remove this file">✕</button>
        `;

        // Release the object URL once the thumbnail has loaded.
        thumb.querySelector( 'img' ).addEventListener( 'load', () => {
            URL.revokeObjectURL( objectUrl );
        }, { once: true } );

        // Click anywhere on the thumb (except the remove button)
        thumb.addEventListener( 'click', ( e ) => {
            if ( e.target.closest( '.smliser-multi-preview_remove' ) ) {
                return;
            }

            activateThumb( file, thumb );
        } );

        // Remove button.
        thumb.querySelector( '.smliser-multi-preview_remove' ).addEventListener( 'click', ( e ) => {
            e.stopPropagation();

            const liveIndex = this.currentFiles.indexOf( file );

            if ( liveIndex === -1 ) {
                return;
            }

            this.currentFiles.splice( liveIndex, 1 );
            thumb.remove();

            if ( 0 === this.currentFiles.length ) {
                this.resetModal();
                return;
            }

            if ( 1 === this.currentFiles.length ) {
                strip.innerHTML = '';
                this._showImagePreview( this.currentFiles[0] );
                return;
            }

            if ( thumb.classList.contains( 'is-active' ) ) {
                const firstThumb = strip.querySelector( '.smliser-multi-preview_thumb' );

                if ( firstThumb ) {
                    activateThumb( this.currentFiles[0], firstThumb );
                }
            }
        } );

        strip.appendChild( thumb );

        if ( 0 === renderIndex ) {
            activateThumb( file, thumb );
        }
    } );

        this.uploadToRepoButton.removeAttribute( 'disabled' );
    }

    /*
    |--------------------------------------
    |Repository Upload & Asset Management
    |--------------------------------------
    */

    /**
     * Upload all staged files to the server in a single batch request.
     *
     * Server response shape:
     * {
     *   success : true,
     *   result  : {
     *     uploaded : { [filename]: { app_slug, app_type, asset_name, asset_url } },
     *     failed   : { [filename]: { [error_key]: "message" } }  // or []
     *   }
     * }
     */
    async uploadToRepository( config, btn ) {
        if ( ! this.currentFiles.length ) {
            SmliserToast.show( 'Please select at least one image.', 3000 );
            return;
        }

        btn.setAttribute( 'disabled', true );

        const assetType = this.currentConfig.get( 'asset_type' );

        if ( ! assetType ) {
            await SmliserModal.error( 'Asset type was not set correctly.' );
            return;
        }

        const container = document.querySelector( `.app-uploader-asset-container.${ assetType }` );
        const spinner   = showSpinner( '.smliser-spinner.modal', true );

        try {
            const url = new URL( smliser_var.ajaxURL );
            url.searchParams.set( 'action',   'smliser_app_asset_upload' );
            url.searchParams.set( 'security', smliser_var.csrf_token );

            const payLoad = new FormData();
            payLoad.set( 'app_slug',   this.currentConfig.get( 'app_slug' ) );
            payLoad.set( 'app_type',   this.currentConfig.get( 'app_type' ) );
            payLoad.set( 'asset_name', this.currentConfig.get( 'asset_name' ) );
            payLoad.set( 'asset_type', assetType );

            // Append all files under the same key so the server receives an array
            this.currentFiles.forEach( ( file ) => payLoad.append( 'asset_file[]', file ) );
            const context   = this.currentConfig.get( 'context' );
            const method    = 'edit' === context ? 'PATCH' : 'POST';

            const response  = await fetch( url.href, {
                method      : method,
                body        : payLoad,
                credentials : 'same-origin',
            });

            const data = await this._parseResponse( response );

            if ( ! data.success ) {
                throw new Error( data?.result?.message ?? 'Upload request failed.' );
            }

            this._processBatchResult( data.result, container );

        } catch ( error ) {
            SmliserToast.show( error.message, 20000 );
            console.error( error );
        } finally {
            removeSpinner( spinner );
            btn.removeAttribute( 'disabled' );
        }
    }

    /**
     * Process the batch upload result: inject successful uploads into the DOM
     * and surface per-file failure messages to the user.
     *
     * @param {{ uploaded: Object, failed: Object|Array }} result
     * @param {HTMLElement} container
     */
    _processBatchResult( result, container ) {
        const uploaded  = result.uploaded ?? {};
        const failed    = result.failed ?? {};

        const uploadedEntries       = Object.entries( uploaded );
        const failedEntries         = Object.entries( failed );
        const existingName          = this.currentConfig.get( 'asset_name' );
        const existingImageSelector = `#${ existingName.split( '.' )[0] }`;

        // ── Bulk Successes ──────────────────────────────────────────────────────────
        uploadedEntries.forEach( ( [ , assetConfig ] ) => {
            const { asset_url: newImageUrl } = assetConfig;
            assetConfig.context = 'edit';

            if ( existingName ) {
                // Edit mode: swap the src of the existing <img> in place
                const imageEl = document.querySelector( existingImageSelector );
                imageEl?.setAttribute( 'src', newImageUrl );
            } else {
                // New upload, make an ID.
                assetConfig.imageID = assetConfig.asset_name?.split( '.' )[0];
                this._addNewImageToContainer( container, assetConfig, newImageUrl );
            }
        });

        // ── Bulk Failures ───────────────────────────────────────────────────────────
        failedEntries.forEach( ( [ filename, errors ] ) => {
            const messages = Object.values( errors ).join( ' ' );
            SmliserToast.show( `"${ filename }": ${ messages }`, 12000 );
            console.warn( `Asset upload failed — ${ filename }:`, errors );
        });

        // ── Summary & modal teardown ───────────────────────────────────────────
        const uploadCount   = uploadedEntries.length;
        const failCount     = failedEntries.length;

        if ( uploadCount > 0 && failCount === 0 ) {
            // All succeeded — close silently
            this.resetModal();
            this.closeModal();
        } else if ( uploadCount > 0 && failCount > 0 ) {
            // Partial success — close but leave the user informed via the notices above
            SmliserToast.show( `${ uploadCount } uploaded, ${ failCount } failed. See details above.`, 8000 );
            this.resetModal();
            this.closeModal();
        }
        
        if ( 'edit' === this.currentConfig.get( 'context' ) ) {
            // This is a PATCH request.
            const { asset_url: newImageUrl } = result;
            
            const imageEl = document.querySelector( existingImageSelector );            
            
            imageEl?.setAttribute( 'src', newImageUrl );
            const configEl  = imageEl?.parentElement.querySelector( '.edit-image[data-config]' );
            if ( configEl ) {
                const assetConfig   = result;
                assetConfig.context = 'edit';
                configEl.setAttribute( 'data-config', encodeURIComponent( JSON.stringify( assetConfig ) ));
            }

            this.resetModal();
            this.closeModal();
        }
        // If uploadCount === 0 (all failed), keep the modal open so the user can correct and retry
    }

    /**
     * Inject a newly uploaded image card into the assets container.
     */
    _addNewImageToContainer( container, configJson, imageUrl ) {
        const imageName     = imageUrl.split( '/' ).pop();
        const card          = document.createElement( 'div' );
        const imageID       = configJson.imageID;
        card.className      = 'app-uploader-image-preview';        

        card.innerHTML = `
            <img src="${ imageUrl }" alt="${ imageName }" title="${ imageName }" id="${imageID}">
            <div class="app-uploader-image-preview_edit">
                <span
                    class="ti ti-edit edit-image"
                    data-config="${ encodeURIComponent( JSON.stringify( configJson ) ) }"
                    data-action="openModal"
                    title="Edit"
                ></span>
                <span
                    class="ti ti-trash delete-image"
                    data-config="${ encodeURIComponent( JSON.stringify( configJson ) ) }"
                    data-action="deleteImage"
                    title="Delete"
                ></span>
            </div>
        `;

        setTimeout( () => {
            const target    = container.querySelector( '.app-uploader-asset-container_images' );
            const addBtn    = target?.querySelector( '.smliser-uploader-add-image' );
            target?.insertBefore( card, addBtn );
           
        }, 400 );
    }

    /**
     * Delete an existing asset image.
     */
    async _deleteImage( config, btn ) {
        const confirmed = await SmliserModal.confirm( `Are you sure you want to delete ${ config.asset_name ?? 'this image' }? This action cannot be reversed.` );
        if ( ! confirmed ) return;

        const payLoad = new FormData();
        Object.entries( config ).forEach( ( [key, val] ) => payLoad.set( key, val ) );
        payLoad.set( 'action',   'smliser_app_asset_delete' );
        payLoad.set( 'security', smliser_var.csrf_token );

        btn.setAttribute( 'disabled', true );

        try {
            const response  = await fetch( smliser_var.ajaxURL, {
                method      : 'POST',
                body        : payLoad,
                credentials : 'same-origin',
            });

            const data = await this._parseResponse( response );

            if ( ! data.success ) {
                throw new Error( data.data?.message ?? `Unable to delete ${ config.asset_name }.` );
            }

            SmliserToast.show( data.data?.message, 3000 );

            const card = btn.closest( '.app-uploader-image-preview' );
            jQuery( card ).fadeOut( 'slow', () => card.remove() );

        } catch ( error ) {
            SmliserToast.show( error.message, 10000 );
        } finally {
            btn.removeAttribute( 'disabled' );
        }

        // Refresh add-button visibility after deletion
        const addButton = document.querySelector( `.app-uploader-asset-container.${ config.asset_type } .smliser-uploader-add-image` );

        addButton?.classList.remove( 'smliser-hide' );
    }

    /*
    |-----------
    |Utilities
    |-----------
    */

    /**
     * Parse a fetch Response, handling both JSON and plain-text error bodies.
     *
     * The server may wrap error detail under `data.message` (WP ajax standard)
     * or under `result.message` (this API's batch envelope) — we check both.
     *
     * @param {Response} response
     * @returns {Promise<object>}
     */
    async _parseResponse( response ) {
        const contentType = response.headers.get( 'Content-Type' ) ?? '';

        if ( ! response.ok ) {
            let errorMessage = 'Something went wrong!';

            if ( contentType.includes( 'application/json' ) ) {
                const body      = await response.json();
                errorMessage    = body?.result?.message ?? body?.data?.message ?? errorMessage;
            } else {
                errorMessage = await response.text();
            }

            throw new Error( errorMessage );
        }

        return response.json();
    }

    /**
     * Convert any image Blob/File to PNG using an offscreen Canvas.
     *
     * @param {Blob|File}   blob
     * @param {string}      outputName
     * @returns {Promise<File>}
     */
    _convertToPng( blob, outputName = 'converted.png' ) {
        return new Promise( ( resolve, reject ) => {
            const img   = new Image();
            const url   = URL.createObjectURL( blob );

            img.onload = () => {
                const canvas    = document.createElement( 'canvas' );
                canvas.width    = img.width;
                canvas.height   = img.height;

                canvas.getContext( '2d' ).drawImage( img, 0, 0 );

                canvas.toBlob( ( pngBlob ) => {
                    URL.revokeObjectURL( url );

                    if ( ! pngBlob ) {
                        reject( new Error( 'Canvas PNG export failed.' ) );
                        return;
                    }

                    resolve( new File( [pngBlob], outputName, { type: 'image/png' } ) );
                }, 'image/png' );
            };

            img.onerror = () => {
                URL.revokeObjectURL( url );
                reject( new Error( 'Invalid image file.' ) );
            };

            img.src = url;
        });
    }

    /**
     * Toggle the `is-active` class on a URL input based on whether it has a value.
     */
    _manageInputFocus( e ) {
        const input = e.target;
        input.classList.toggle( 'is-active', input.value.trim().length > 0 );
    }

    /**
     * Observe `src` attribute mutations on an <img> element and emit a custom
     * `srcChanged` event when the value changes.
     *
     * @param {HTMLImageElement} img
     * @returns {MutationObserver}
     */
    _observeImageSrcChange( img ) {
        const observer = new MutationObserver( ( mutations ) => {
            for ( const mutation of mutations ) {
                if ( mutation.type === 'attributes' && mutation.attributeName === 'src' ) {
                    img.dispatchEvent( new CustomEvent( 'srcChanged', {
                        detail: { oldSrc: mutation.oldValue, newSrc: img.src },
                    }));
                }
            }
        });

        observer.observe( img, {
            attributes         : true,
            attributeFilter    : ['src'],
            attributeOldValue  : true,
        });

        return observer;
    }

    /*
    |-------------
    |JSON Editor
    |-------------
    */

    /**
     * Mount the SmliserJSONEditor over the raw textarea.
     */
    #mountJsonEditor() {
        const textarea          = this.appJsonTextarea;
        const textareaParent    = textarea.parentElement;
        const editorFrame       = document.createElement( 'div' );
        editorFrame.id          = 'smliser-appjson-editor';

        textarea.setAttribute( 'disabled', true );
        textareaParent.classList.add( 'json-mounted' );

        let jsonData = {};
        try {
            jsonData = StringUtils.JSONparse( textarea.value );
        } catch {
            jsonData = {};
        }

        textareaParent.parentElement.appendChild( editorFrame );        

        this.editor = new SmliserJsonEditor( editorFrame, {
            title: 'APP JSON Editor',
            description: textarea.dataset.editorDescription ?? "Edit your application's JSON file (app.json). Values in this file will be served in the REST API response.",
            data: jsonData,
            autoFocus: false
        });

        this.editor.expandAll();
    }

    /*
    |----------------------
    | Artifact Uploader
    |----------------------
    */

    /**
     * Initialize the artifact uploader section.
     */
    _initArtifactUploader() {
        this.artifactUploader.addEventListener( 'click', this._handleArtifactPageClick.bind(this) );
    }

    /**
     * Handle click events on the artifact uploads page.
     * 
     * @param {MouseEvent} e
     */
    async _handleArtifactPageClick( e ) {
        const clickedBtn    = e.target;
        const configEl      = clickedBtn?.closest( '[data-config]' );
        const config        = StringUtils.JSONparse( StringUtils.rawUrlDecode( configEl?.getAttribute( 'data-config' ) ?? null ) );        

        if ( clickedBtn.closest( AppUploader.SELECTORS.ARTIFACT_ADD_NEW_BTN ) ) {
            // Open modal for add new artifact.
            this.openArtifactModal( config );
            return;
        }

        if ( clickedBtn.closest( AppUploader.SELECTORS.ARTIFACT_EDIT_BTN ) ) {
            // Open modal to edit artifact.
            this.openArtifactModal( config );
            return;
        }

        const deleteBtn = clickedBtn.closest( AppUploader.SELECTORS.ARTIFACT_DELETE_BTN );
        const filename  = config?.filename ?? 'artifact';        

        if ( deleteBtn && await SmliserModal.confirm( `Are you sure to delete  ${filename}?` ) ) {
            config.artifact_filename    = config?.filename ?? null;
            this._deleteArtifact( config );
        }

        if ( clickedBtn.closest( AppUploader.SELECTORS.ARTIFACT_DOWNLOAD_BTN ) ) {
            this._downloadArtifact( config.url, config.filename );
        }
    }

    /**
     * Open the artifact modal for adding or editing an artifact.
     */
    openArtifactModal( config = {isNew: true, filename: '' } ) {             

        if ( ! this.artifactModal ) {
            const modalBody = document.createElement( 'form' );
            modalBody.className = 'smliser-artifact-form';
            modalBody.id = Date.now().toString();
            modalBody.innerHTML = `
                <label for="artifact-name" class="smliser-form-label-row">
                    <span class="smliser-form-label">Artifact File Name: <i class="smliser-form-description ti ti-question-mark" title="This artifact file name will be sanitized and the uploaded file extension will be appended to it. e.g. &quot;my artifact.zip&quot; will be sanitized to &quot;my-artifact.zip&quot;"></i></span>
                    <input type="text" class="smliser-form-input" style="font-size: 15px;" id="artifact-name" name="canonical_filename" required>
                    
                </label>
                <span class="smliser-spinner modal"></span>
                <textarea id="artifact-config" class="smliser-hide" style="width: 100%; height: 200px;"></textarea>
                <div class="smliser-form-file-row">
                    <input type="file" class="smliser-hide artifact-file-input" id="artifact-file" name="artifact_file">
                    <div class="smliser-file-info">
                        <span>No file selected.</span>
                    </div>
                    <button type="button" class="smliser-upload-btn button">Drag over or click to upload file</button>
                    <button type="button" class="smliser-file-remove button smliser-hide"><span class="ti ti-x" title="remove file"></span> Clear</button>
                </div>
                <em>Max Upload Size: ${smliser_var.uploads.max_upload_size_readable}</em>`;

            const modalFooter       = document.createElement( 'div' );
            modalFooter.className   = 'smliser-button-container';
            const saveButton        = document.createElement( 'button' );
            saveButton.type         = 'submit';
            saveButton.className    = 'smliser-save-btn button';
            saveButton.textContent  = 'Save';
            saveButton.setAttribute( 'form', modalBody.id );

            modalFooter.appendChild( saveButton );

            this.artifactModal = new SmliserModal({
                title: '',
                body: modalBody,
                width: '90%',
                footer: modalFooter,
                autoFocus: false,
            });

            this.initArtifactFileUploader();
        }

        this.artifactModal.setHeaderTitle( config?.isNew ? 'Add New Artifact' : 'Edit Artifact' );
        this.artifactModal.getBody( '#artifact-name' ).value    = config?.filename ?? '';
        this.artifactModal.getBody( 'textarea' ).value          = StringUtils.JSONstringify( config, 2 );
        this.artifactModal.open();
    }

    /**
     * Initialize artifact file upload feature.
     */
    initArtifactFileUploader() {
        this.artifactFileUploader   = this.artifactModal.getBody( '.smliser-form-file-row' );
        
        this.artifactFileInput      = this.artifactModal.getBody( '#artifact-file' );
        const fileUploadBtn         = this.artifactModal.getBody( '.smliser-upload-btn' );
        const clearBtn              = this.artifactModal.getBody( '.smliser-file-remove' );
        const fileInfo              = this.artifactModal.getBody( '.smliser-file-info' );

        fileUploadBtn.addEventListener( 'click', () => this.artifactFileInput.click() );
        
        this.artifactFileInput.addEventListener( 'change', e => {
            this._handleZipFileChange( e, fileInfo, clearBtn, fileUploadBtn );
        });

        clearBtn.addEventListener( 'click', () => {
            this.artifactFileInput.value    = '';
            fileInfo.innerHTML              = '<span>No file selected.</span>';

            clearBtn.classList.add( 'smliser-hide' );
            fileUploadBtn.classList.remove( 'smliser-hide' );
        });

        const originalText  = fileInfo.textContent;
        const dropZone      = this.artifactFileUploader;
        this._initDragAndDrop( dropZone, this.artifactFileInput, fileInfo, originalText );

        this.artifactModal.on( 'beforeClose', () => clearBtn.click() );
        this.artifactModal.on( 'afterClose', async ( modal ) => {
            const inputs    = modal.queryAll( 'body', 'input' );
            inputs.forEach( ( input ) => {
                input.value = '';
            });
        });

        this.artifactModal.on( 'onSubmit', async ( modal ) => {
            const canonicalFileName     = modal.getBody( '#artifact-name' ).value.trim();
            const file                  = this.artifactFileInput.files[0];

            if ( ! canonicalFileName ) {
                SmliserToast.show( 'Please enter a valid artifact filename.', 5000 );
                return;
            }

            const config    = StringUtils.JSONparse( modal.getBody( '#artifact-config' ).value );
            
            if ( ! config ) {
                SmliserToast.show( 'Invalid artifact configuration.', 5000 );
                return;
            }

            if ( ! file ) {
                let errorMessage = config.isNew ? 
                    'Please select a file to upload.' :
                    ( canonicalFileName === config.filename ? 'No changes detected' : null ); 
                let proceed = false;

                if ( errorMessage ) {
                    await SmliserModal.error( errorMessage, 'Error' );
                } else {
                    const message = `No file selected. Do you want to proceed with changing the artifact filename from "${config.filename}" to "${canonicalFileName}"?`;
                    proceed = await SmliserModal.confirm({
                        title: 'File Name Change',
                        message: message,
                        confirmText: 'Yes, Proceed',
                        cancelText: 'No, Cancel'
                    });                    
                }

                if ( ! proceed ) return;
            }

            const payLoad = new FormData();
            payLoad.set( 'action',   'smliser_app_artifact_upload' );
            payLoad.set( 'security', smliser_var.csrf_token );
            payLoad.set( 'app_slug', config.app_slug );
            payLoad.set( 'app_type', config.app_type );
            payLoad.set( 'artifact_filename', config.filename );
            payLoad.set( 'canonical_filename', canonicalFileName );

            if ( file ) {
                payLoad.set( 'artifact_file', file );
            }

            const result = await this._uploadArtifactFile( payLoad, config.isNew );

            if ( ! result ) return;

            const newConfig  = {
                oldSlug: config.slug,
                ...config, 
                ...result
            }

            this.artifactModal.close();

            this._updateArtifactList( newConfig );
            
        });
    }

    /**
     * Upload the artifact file to the server.
     * 
     * @param {FormData} payLoad
     * @param {boolean} isNew
     * @returns {Promise<{filename: string, slug: string, size: number, url: string, mimeType: string, mTime: string}|null>}
     */
    async _uploadArtifactFile( payLoad, isNew ) {
        const spinner = showSpinner( '.smliser-spinner.modal', true );
        
        try {    
            const url = new URL( smliser_var.ajaxURL );

            const response  = await smliserFetchJSON( url, {
                method: isNew ? 'POST' : 'PATCH',
                body: payLoad,
                credentials: 'same-origin',
            });

            if ( ! response.success ) {
                throw new Error( response?.data?.message ?? 'Artifact upload request failed with an unknown error.' );
            }

            return {
                filename: response.data.filename,
                slug: response.data.slug,
                size: response.data.size,
                url: response.data.download_url,
                mimeType: response.data.mime_type,
                mTime: response.data.mtime,
            };
        } catch( error ) {
            await SmliserModal.error( error.message );
            return null;
        } finally {
            removeSpinner( spinner );
        }
        
    }

    /**
     * Delete an artifact
     */
    async _deleteArtifact( config ) {        
        try {
            const defaultParams = {
                app_slug: '',
                app_type: '',
                artifact_filename: '',
            };

            const requestBody   = {...defaultParams, ...config }
            const url           = new URL( smliser_var.ajaxURL );

            requestBody.security    = smliser_var.csrf_token;

            url.searchParams.set( 'action', 'smliser_delete_artifact' );

            const response  = await smliserFetchJSON( url, {
                method: 'DELETE',
                credentials: 'same-origin',
                headers: {
                    'accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body: StringUtils.JSONstringify( requestBody )
            });

            if ( ! response.success ) {
                throw new Error( response?.data?.message ?? 'Artifact upload request failed with an unknown error.' );
            }

            await SmliserModal.success( response.message || 'Sucessfully deleted.' );

            const slug      = StringUtils.replace( '.', '-', config.slug )
            const targetEl  = this.artifactUploader.querySelector( `.smliser-app-artifacts_item.${slug}` );

            if ( targetEl ) {
                jQuery( targetEl ).fadeOut( 1000, () => {
                    targetEl.remove();
                });
            }

        } catch (error) {
            await SmliserModal.error( error.message, 'Delete Error' );
        }
        
    }

    async _downloadArtifact( url, fileName ) {
        try {
            SmliserToast.show( `Downloading ${fileName ?? 'artifact'}...` );
            const response  = await smliserDownloadUrl( url );
            const fileSize  = StringUtils.formatBytes( response.size );
            const duration  = ( response.duration / 1000 ).toFixed( 2 );

            await SmliserModal.success(
                `Successfully downloaded <strong>${ response.filename }</strong> (${ fileSize }) in ${ duration } seconds.`,
                'File Downloaded'
            );

        } catch ( error ) {
            await SmliserModal.error( error.message );
        }
    }

    /**
     * Update the artifact list in the UI after a successful upload or edit.
     * 
     * @param {{filename: string, oldSlug: string, slug: string, size: number, url: string, mimeType: string, mTime: string, isNew: boolean}} artifactData
     */
    async _updateArtifactList( artifactData ) {

        if ( ! artifactData ) return;
        
        const listBase  = this.artifactUploader.querySelector( '.smliser-app-artifacts' );
        const liEl      = artifactData.isNew 
            ? document.createElement( 'li' ) : 
            this.artifactUploader.querySelector( `li.smliser-app-artifacts_item.${StringUtils.replace( '.', '-', artifactData.oldSlug )}` );

        if ( ! liEl ) {
            await SmliserModal.error( 'Target list was not found in the DOM.');
            return;
        }

        jQuery( liEl ).fadeOut( 100 );
        
        const originalConfig    = structuredClone( artifactData );
        artifactData.isNew      = false;
        delete artifactData.oldSlug;
        const data              = StringUtils.rawUrlEncode( StringUtils.JSONstringify( artifactData ) );
        const newSlug           = StringUtils.replace( '.', '-', artifactData.slug );
        liEl.className = `smliser-app-artifacts_item ${newSlug}`;

        liEl.innerHTML  = `
            <div class="smliser-app-artifacts_item-heading">
                <span>
                    <strong>Name:</strong> ${artifactData.filename}
                </span>
                    
                <div class="smliser-app-artifacts_item-buttons">
                    <button title="Edit artifact" class="smliser-edit-artifact" data-config="${data}"> <i class="ti ti-edit"></i></button>
                    <button title="Delete artifact" class="smliser-delete-artifact" data-config="${data}"> <i class="ti ti-trash"></i></button>
                    <button title="Download artifact" class="smliser-download-artifact" data-config="${data}"> <i class="ti ti-download"></i></button>
                </div>

            </div>
                
            <div class="smliser-app-artifacts_item-info">
                <table>
                    <tbody>
                        <tr>
                            <th>Size:</th>
                            <td>${StringUtils.formatBytes( artifactData.size )}</td>
                        </tr>
                        <tr>
                            <th>Mime Type:</th>
                            <td>${artifactData.mimeType}</td>
                        </tr>
                        <tr>
                            <th>Download URL:</th>
                            <td>
                                <span title="${artifactData.url}">
                                    ${StringUtils.truncate( artifactData.url, 40 )}   
                                </span>
                                <i class="ti ti-copy smliser-click-to-copy" title="Click to copy" data-copy-value="${artifactData.url}"></i>
                            </td>
                        </tr>
                        <tr>
                            <th>Last Updated:</th>
                            <td>Now</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        `;

        if ( originalConfig.isNew ) {
            listBase.appendChild( liEl );
        }

        jQuery( liEl ).fadeIn( 1000 );
    }
}

/*
|-----------
|Bootstrap
|-----------
*/

document.addEventListener( 'DOMContentLoaded', () => new AppUploader() );