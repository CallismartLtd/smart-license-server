let newAppUploaderForm      = document.getElementById( 'newAppUploaderForm' );
const queryParam            = new URLSearchParams( window.location.search );
let appAssetUploadModal     = document.querySelector( '.smliser-admin-modal.app-asset-uploader' );
let assetsContainer         = newAppUploaderForm.querySelector( '.app-uploader-below-section_assets' );
let uploadToRepoButton      = appAssetUploadModal?.querySelector( '#upload-image' );

if ( newAppUploaderForm  ) {
    let uploadBtn       = document.querySelector('.smliser-upload-btn');
    let fileInfo        = document.querySelector('.smliser-file-info');
    let submitBtn       = newAppUploaderForm.querySelector( 'button[type="submit"]' );
    let fileInput       = document.querySelector( '#smliser-file-input' );
    let originalText    = fileInfo.textContent;

    let clearFileInputBtn   = document.querySelector( '.smliser-file-remove' );
    
    uploadBtn.addEventListener( 'click', ()=> fileInput.click() );
    clearFileInputBtn.addEventListener( 'click', ()=>{
        fileInput.value = '';
        fileInfo.innerHTML = '<span>No file selected.</span>';
        clearFileInputBtn.classList.add( 'smliser-hide' );
        uploadBtn.classList.remove( 'smliser-hide' );
    });

    fileInput.setAttribute( 'accept', '.zip' );

    fileInput.addEventListener('change', function(event) {
        const file = event.target.files[0];
        if (file) {
            let maxUploadSize   = parseFloat( fileInfo.getAttribute('wp-max-upload-size') );
            const fileSizeMB = (file.size / 1024 / 1024).toFixed(2); // Convert size to MB and round to 2 decimal places
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
                            ${ maxUploadSize < fileSizeMB 
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
                smliserNotify('The uploaded file is higher than the max_upload_size, this server cannot process this request', 6000);
            }

            
        } else {
            fileInfo.innerHTML = originalText;
        }
    });

    submitBtn.addEventListener( 'click', (e)=>{
        if ( 'add-new' === queryParam.get( 'tab' ) && fileInput.files.length === 0 ) {
            e.preventDefault();
            const appType = StringUtils.ucfirst( queryParam.get( 'type' ) );
            smliserNotify( `A ${appType} file is required.` );
        }
    });

    newAppUploaderForm.addEventListener( 'submit', async (e) => {
        e.preventDefault();

        const payLoad = new FormData( e.currentTarget );
        payLoad.set( 'security', smliser_var.nonce  );
        const spinner = showSpinner( '.smliser-spinner', true );
        try {
            const response = await fetch( smliser_var.smliser_ajax_url,
                {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: payLoad
                }
            );

            if ( ! response.ok ) {
                const contentType = response.headers.get( 'Content-Type' );
                let errorMessage    = 'Something went wrong!';

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

            smliserNotify( responseJson?.data?.message || 'Saved', 6000 );
            setTimeout( () => {
                window.location.href = responseJson.data.redirect_url;
            });
        } catch ( error ) {
            smliserNotify( error.message, 10000 );
        } finally {
            removeSpinner( spinner );
        }
    });
    
}


const handleClickAction = ( e ) => {
    if ( e.target === appAssetUploadModal ) {
        modalActions.closeModal();
        return;
    }
    const clickedBtn    = e.target.closest( '.delete-image, .remove-modal, .clear-uploaded, #upload-image, #upload-from-device, #upload-from-wp, #upload-from-url, .smliser-uploader-add-image, .edit-image' );
    const action        = clickedBtn ? clickedBtn.getAttribute( 'data-action' ) : null;

    if ( ! action ) return;
    const config = JSON.parse( decodeURIComponent( clickedBtn.getAttribute( 'data-config' ) ) );
    modalActions[action]?.( config, clickedBtn );
    
}

let imageFileInput              = document.querySelector( '#app-uploader-asset-file-input' );
let imageUrlInput               = document.querySelector( '#app-uploader-asset-url-input' );
let assetImageUploaderContainer = document.querySelector( '.app-asset-uploader-body_uploaded-asset' );
let imagePreview                = document.querySelector( '#currentImage' );

let smliserCurrentImage;
let smliserCurrentConfig = new Map;
const modalActions = {
    'openModal': ( json ) => {
        appAssetUploadModal.classList.remove( 'hidden' );

        const totalImages   = document.querySelectorAll( `.app-uploader-asset-container.${json?.asset_type} img`).length;
        const addButton     = document.querySelector( `.app-uploader-asset-container.${json?.asset_type} .smliser-uploader-add-image`);

        smliserCurrentConfig.set( 'total_images', totalImages );
        smliserCurrentConfig.set( 'add_button', addButton );

        if ( json ) {
            smliserCurrentConfig.set( 'app_slug', json.app_slug );
            smliserCurrentConfig.set( 'app_type', json.app_type );
            smliserCurrentConfig.set( 'asset_name', json.asset_name ?? '' );            
            smliserCurrentConfig.set( 'asset_type', json.asset_type ?? '' );
            
            smliserCurrentConfig.set( 'limit', json.limit );

            if ( json.asset_url ) {
                assetImageUploaderContainer.classList.add( 'has-image' );
                imagePreview.src = json.asset_url;
                uploadToRepoButton.setAttribute( 'disabled', true );
                smliserCurrentConfig.set( 'observer', observeImageSrcChange( imagePreview ) );
            } 
            
            if ( totalImages >= json.limit ) {
                addButton.classList.add( 'smliser-hide' );
                modalActions.closeModal();
                smliserNotify( `limit for ${StringUtils.ucfirst( json.asset_type )} has been reached.` );
            } else {
                addButton.classList.remove( 'smliser-hide' );
            }            
        }
        
        
    },
    'closeModal': () => {
        modalActions.resetModal( true );
        appAssetUploadModal.classList.add( 'hidden' );
    },

    'resetModal': ( all = false ) => {
        assetImageUploaderContainer.classList.remove( 'has-image' );
        smliserCurrentImage     = null;
        imageFileInput.value    = '';
        imageUrlInput.value     = '';
        
        if ( all ) {
            smliserCurrentConfig.get( 'observer' )?.disconnect();
            smliserCurrentConfig.clear();
        }

    },

    'uploadFromDevice': () => {
        imageFileInput.click();

    },

    'uploadFromWpGallery': () => {
        // Create the media frame
        const frame = wp.media({
            title: 'Select an Image',
            button: { text: 'Use this image' },
            library: { type: 'image' },
            multiple: false
        });

        // When an image is selected
        frame.on( 'select', async () => {
            const attachment = frame.state().get('selection').first().toJSON();

            if ( ! attachment || ! attachment.url ) {
                smliserNotify( 'No image selected', 5000 );
                return;
            }

            // Pass the image URL to your existing processFromUrl()
            const image = await modalActions.processFromUrl( attachment.url );

            if ( ! image ) {
                console.warn( 'Image not processed' );
                return;
            }

            smliserCurrentImage = image;
            modalActions.showImagePreview( smliserCurrentImage );
        });

        // Open the frame
        frame.open();
    },

    'uploadFromUrl': async ( json, clickedBtn) => {
        
        if ( ! imageUrlInput.classList.contains( 'is-active' ) ) {
            imageUrlInput.focus();
            return;
        }
        
        if ( ! imageUrlInput.value.trim().length ) {            
            return;
        }

        let url;
        try {
            url = new URL( imageUrlInput.value );
        } catch (error) {}        

        if ( ! imageUrlInput.checkValidity() ) {
            imageUrlInput.reportValidity();
            return;
        }

        clickedBtn.setAttribute( 'disabled', true );

        try {
            const image = await modalActions.processFromUrl( imageUrlInput.value.trim() );
            if ( ! image ) {
                console.warn( 'Image not fetched' );
                return;
                
            }

            smliserCurrentImage = image;
            modalActions.showImagePreview( smliserCurrentImage );  
        } finally {
            clickedBtn.removeAttribute( 'disabled' );
        }
    },

    /**
     * Submits the uploaded image to the repository.
     * 
     * @param {Object} json The configuration object.
     * @param {HTMLElement} clickedBtn The button clicked.
     * @returns void
     */
    'uploadToRepository': async ( json, clickedBtn ) => {
        if ( ! smliserCurrentImage ) {
            smliserNotify( 'Please upload an image.', 3000 );
            return;
        }        

        clickedBtn.setAttribute( 'disabled', true );
        const container = document.querySelector( `.app-uploader-asset-container.${smliserCurrentConfig.get( 'asset_type' )}` );

        const url = new URL( smliser_var.smliser_ajax_url );
        url.searchParams.set( 'action', 'smliser_app_asset_upload' );
        url.searchParams.set( 'security', smliser_var.nonce );

        const payLoad = new FormData();
        payLoad.set( 'app_slug', smliserCurrentConfig?.get( 'app_slug' ) );
        payLoad.set( 'app_type', smliserCurrentConfig?.get( 'app_type' ) );
        payLoad.set( 'asset_name', smliserCurrentConfig?.get( 'asset_name' ) );
        payLoad.set( 'asset_type', smliserCurrentConfig?.get( 'asset_type' ) );
        payLoad.set( 'asset_file', smliserCurrentImage );

        const spinner = showSpinner( '.smliser-spinner.modal', true );
        try {
            const response = await fetch( url,
                {
                    method: 'POST',
                    body: payLoad,
                    credentials: 'same-origin'
                }
            );

            if ( ! response.ok ) {
                const contentType = response.headers.get( 'Content-Type' );
                let errorMessage    = 'Something went wrong!';

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
                const error = new Error( data?.data?.message || 'Unable to upload image' );
                throw error;
            }

            const configJson    = data.data.config;
            const new_image_url = configJson.asset_url;
            const existingImage = smliserCurrentConfig.get( 'asset_name' );
            if ( existingImage ) {
                const imageEl = document.querySelector( `#${existingImage.split( '.' )[0]}` );                
                imageEl.setAttribute( 'src', new_image_url );
            } else {
                const imageContainer        = document.createElement( 'div' );
                imageContainer.className    = `app-uploader-image-preview`;
                const imageName             = new_image_url.split( '/' ).pop();
                imageContainer.innerHTML    = `
                    <img src="${new_image_url}" alt="${imageName}" title="${imageName}">
                    <div class="app-uploader-image-preview_edit">
                        <span class="dashicons dashicons-edit edit-image" data-config="${encodeURIComponent( JSON.stringify( configJson ) )}" data-action="openModal" title="Edit"></span>
                        <span class="dashicons dashicons-trash delete-image" data-config="${encodeURIComponent( JSON.stringify( configJson ) )}" data-action="deleteImage" title="Delete"></span>
                    </div>
                `;
                
                let addButtonMore   = smliserCurrentConfig.get( 'add_button' );
                let title           = smliserCurrentConfig.get( 'asset_type' );
                let limit           = smliserCurrentConfig.get( 'limit' );
                setTimeout( () => {
                    const target    = container.querySelector( '.app-uploader-asset-container_images' );
                    let addButton = target.querySelector( '.smliser-uploader-add-image' );
                    
                    target.insertBefore(imageContainer, addButton)
                    const totalImages   = document.querySelectorAll( `.app-uploader-asset-container.${title} img`).length;

                    if ( totalImages >= limit ) {
                        addButtonMore.classList.add( 'smliser-hide' );
                        modalActions.closeModal();
                        smliserNotify( `limit for ${StringUtils.ucfirst( title )} has been reached.` );
                    } else {
                        addButtonMore.classList.remove( 'smliser-hide' );
                    }                    
                
                }, 400 );           
            }

            modalActions.resetModal();
            modalActions.closeModal();
        } catch (error) {
            smliserNotify( error.message, 20000 );
            console.error(error);
            
        } finally {
            removeSpinner( spinner );
            clickedBtn.removeAttribute( 'disabled' );
        }
    },

    /**
     * Process uploaded image and show it in the modal.
     * 
     * @param {Event} e - The event object.
     * 
     */
    'processUploadedImage': async ( e ) => {
        let image = e.target.files[0];
        if ( ! image ) {
            modalActions.resetModal();
            return;
        }

        let shouldConvert = () => {
            const assetType = smliserCurrentConfig.get( 'asset_type' );
            const appType   = smliserCurrentConfig.get( 'app_type' );

            return ( 'theme' === appType ) && ( 'screenshot' === assetType );
        }
        
        if ( ! image.type.includes( 'image/png' ) && shouldConvert() ) {            
            image = await modalActions.convertToPng( image );

            smliserNotify( 'Image has been converted to PNG', 5000 );
        }

        smliserCurrentImage = image;
        modalActions.showImagePreview( smliserCurrentImage );
    },

    'processFromUrl': async ( imageUrl ) => {

        const ajaxEndpoint = new URL( `${smliser_var.admin_url}admin-post.php` );
        ajaxEndpoint.searchParams.set( 'action', 'smliser_download_image' );
        ajaxEndpoint.searchParams.set( 'image_url', imageUrl );
        ajaxEndpoint.searchParams.set( 'security', smliser_var.nonce );

        const spinner = showSpinner( '.smliser-spinner.modal' );
        try {
            const response      = await fetch( ajaxEndpoint.href );
            const contentType   = response.headers.get( 'Content-Type' ); 
            if ( ! response.ok ) {
                let errorMessage = response.statusText;

                let text = await response.text();

                if ( text.length < 5000 ) {
                    errorMessage = text;
                }

                throw new Error( `Image fetch failed: ${errorMessage}` );
            }

                   
            const blob          = await response.blob();
            const fileName      = imageUrl.split( '/' ).pop() ?? 'image.png';            
            let shouldConvert = () => {
                const assetType = smliserCurrentConfig.get( 'asset_type' );
                const appType   = smliserCurrentConfig.get( 'app_type' );

                return ( 'theme' === appType ) && ( 'screenshot' === assetType );
            }
        
            if ( ! contentType.includes( 'image/png' ) && shouldConvert() ) {            
                image = await modalActions.convertToPng( blob, fileName );
                smliserNotify( 'Image has been converted to PNG', 5000 );
                return image;
            }
         

            return new File( [ blob ], fileName, { type: 'image/png' } );

        } catch (error) {
            smliserNotify( error.message, 10000 );
        } finally {
            removeSpinner( spinner );
        }

        
        return null;
    },

    'showImagePreview': ( file ) => {
        const fileReader    = new FileReader();
        fileReader.onload   = ( e ) => {
            imagePreview.src = e.target.result ;
            assetImageUploaderContainer.classList.add( 'has-image' );
            uploadToRepoButton.removeAttribute( 'disabled' );
        }

        fileReader.readAsDataURL( file );  
    },

    'convertToPng' : ( blob, outputName = 'converted.png' ) => {
        return new Promise( ( resolve, reject ) => {
            const img = new Image();
            const url = URL.createObjectURL( blob );

            img.onload = () => {
                const canvas = document.createElement( 'canvas' );
                canvas.width  = img.width;
                canvas.height = img.height;

                const ctx = canvas.getContext( '2d' );
                ctx.drawImage( img, 0, 0 );

                canvas.toBlob( ( pngBlob ) => {
                    if ( ! pngBlob ) {
                        reject( new Error( 'Canvas export failed' ) );
                        return;
                    }

                    resolve( new File( [ pngBlob ], outputName, { type: 'image/png' } ) );
                }, 'image/png' );

                URL.revokeObjectURL( url );
            };

            img.onerror = () => reject( new Error( 'Invalid image file' ) );
            img.src = url;
        });
    },

    'deleteImage': async (json, clickedBtn) => {
        const confirmed = confirm( `Are you sure to delete ${json.asset_name ?? `this image`}? This action cannot be reversed`);
        if ( ! confirmed ) {
            return;
        }
        let payLoad = new FormData();

        for( const key in json ) {
            const value = json[key];
            payLoad.set( key, value );
        }

        payLoad.set( 'action', 'smliser_app_asset_delete' )
        payLoad.set( 'security', smliser_var.nonce )
        
        clickedBtn.setAttribute( 'disabled', true );
        try {
            const response = await fetch( smliser_var.smliser_ajax_url, 
                {
                    method: 'POST',
                    body: payLoad,
                    credentials: 'same-origin'
                }
            );

            const contentType = response.headers.get( 'Content-Type' );
            
            if ( ! response.ok ) {
                let isJson  = contentType.includes( 'application/json' );
                let data    = isJson ? await response.json() : await response.text();

                let errorMessage = data?.data?.message ?? data;
                
                throw new Error( errorMessage );
            }

            const responseJson = await response.json();

            if ( ! responseJson.success ) {
                throw Error( responseJson.data?.message ?? `unable to delete ${json.asset_name}` );
            }

            smliserNotify( responseJson.data?.message, 300 );

            const target = clickedBtn.closest( '.app-uploader-image-preview' );

            jQuery( target ).fadeOut( 'slow', () => {
                target.remove();
            });



        } catch (error) {
            smliserNotify( error.message, 10000 );
        }

        const totalImages   = document.querySelectorAll( `.app-uploader-asset-container.${json?.asset_type} img`).length;
        const addButton     = document.querySelector( `.app-uploader-asset-container.${json?.asset_type} .smliser-uploader-add-image`);

        if ( totalImages >= json.limit ) {
            addButton.classList.add( 'smliser-hide' );
            modalActions.closeModal();
            smliserNotify( `limit for ${StringUtils.ucfirst( json.asset_type )} has been reached.` );
        } else {
            addButton.classList.remove( 'smliser-hide' );
        }
    }
}

const manageInputFocus = ( e ) => {
    const input = e.target;

    if ( input.value.trim().length ) {
        input.classList.add( 'is-active' );
    } else {
        input.classList.remove( 'is-active' );
    }
}


appAssetUploadModal?.addEventListener( 'click', handleClickAction );
assetsContainer?.addEventListener( 'click', handleClickAction );
imageFileInput?.addEventListener( 'change', modalActions.processUploadedImage );
imageUrlInput?.addEventListener( 'input', ( e )=> e.target.setCustomValidity( '' ) );
imageUrlInput?.addEventListener( 'blur', manageInputFocus );
imageUrlInput?.addEventListener( 'focus', manageInputFocus );
imagePreview?.addEventListener("srcChanged", (e) => {
  const { oldSrc, newSrc } = e.detail;
  if (oldSrc !== newSrc) {
    uploadToRepoButton?.removeAttribute("disabled");
  }
});

function observeImageSrcChange(img) {
    const observer = new MutationObserver(mutations => {
        for (const mutation of mutations) {
        if (mutation.type === "attributes" && mutation.attributeName === "src") {
            const event = new CustomEvent("srcChanged", {
            detail: {
                oldSrc: mutation.oldValue,
                newSrc: img.src
            }
            });
            img.dispatchEvent(event);
        }
        }
    });

    observer.observe(img, { 
        attributes: true, 
        attributeFilter: ["src"], 
        attributeOldValue: true
    });

    return observer;
}



