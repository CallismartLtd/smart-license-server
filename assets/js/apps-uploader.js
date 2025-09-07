let newAppUploaderForm      = document.getElementById( 'newAppUploaderForm' );
const queryParam            = new URLSearchParams( window.location.search );
let appAssetUploadModal     = document.querySelector( '.smliser-admin-modal.app-asset-uploader' );
let assetsContainer         = newAppUploaderForm.querySelector( '.app-uploader-below-section_assets' );
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
    const clickedBtn    = e.target.closest( '.remove-modal, .clear-uploaded, #upload-image, #upload-from-device, #upload-from-wp, #upload-from-url, .smliser-uploader-add-image' );
    const action        = clickedBtn ? clickedBtn.getAttribute( 'data-action' ) : null;

    if ( ! action ) return;
    const config = JSON.parse( clickedBtn.getAttribute( 'data-config' ) );
    modalActions[action]?.( config );
    
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

        if ( json ) {
            smliserCurrentConfig.set( 'app_slug', json.app_slug );
            smliserCurrentConfig.set( 'app_type', json.app_type );
            smliserCurrentConfig.set( 'asset_prefix', json.asset_prefix );            
        }
    },
    'closeModal': () => {
        modalActions.resetModal();
        appAssetUploadModal.classList.add( 'hidden' );
    },

    'resetModal': () => {
        assetImageUploaderContainer.classList.remove( 'has-image' );
        smliserCurrentImage = null;
        imageFileInput.value = '';
        smliserCurrentConfig.clear();
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

    'uploadFromUrl': async () => {
        
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
            if ( ! url.pathname.includes( '.' ) ) {

                let error   = new Error( "Url is not pointing to a file" );
                error.type  = 'INPUT_NOT_FILE';
                throw error;
            }
        } catch (error) {
            console.log(error.type);
            if ( 'INPUT_NOT_FILE' === error.type ) {
                imageUrlInput.setCustomValidity(error.message);
            }
            
            url = null;
        }        

        if ( ! imageUrlInput.checkValidity() ) {
            imageUrlInput.reportValidity();
            return;
        }

        const image = await modalActions.processFromUrl( imageUrlInput.value.trim() );
        if ( ! image ) {
            console.warn( 'Image not fetched' );
            return;
        }

        smliserCurrentImage = image;
        modalActions.showImagePreview( smliserCurrentImage );        
    },
    'uploadToRepository': async () => {
        if ( ! smliserCurrentImage ) {
            smliserNotify( 'Please upload an image.', 3000 );
            return;
        }

        const url = new URL( smliser_var.smliser_ajax_url );
        url.searchParams.set( 'action', 'smliser_app_asset_upload' );
        url.searchParams.set( 'security', smliser_var.nonce );

        const payLoad = new FormData();
        payLoad.set( 'app_slug', smliserCurrentConfig?.get( 'app_slug' ) );
        payLoad.set( 'app_type', smliserCurrentConfig?.get( 'app_type' ) );
        payLoad.set( 'asset_prefix', smliserCurrentConfig?.get( 'asset_prefix' ) );
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
                const error = new Error( `Upload error: ${response.statusText}` );
                throw error;
            }

            const data = await response.json();

            const new_image_url = data?.data?.image_url;
            console.log(new_image_url);
            
        } catch (error) {
            smliserNotify( error.message );
        } finally {
            removeSpinner( spinner );
        }



    },

    'processUploadedImage': async ( e ) => {
        let image = e.target.files[0];
        if ( ! image ) {
            modalActions.resetModal();
            return;
        }

        if ( ! image.type.includes( 'image/png' ) ) {
            image = await modalActions.convertToPng( image );
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
            const response = await fetch( ajaxEndpoint.href );

            if ( ! response.ok ) {
                throw new Error( `Image fetch failed: ${response.statusText}` );
            }

            const contentType = response.headers.get( 'Content-Type' );        
            const blob        = await response.blob();
            const fileName = imageUrl.split('/').pop().split('?')[0] || 'image.png';
            if ( ! contentType.includes( 'image/png' ) ) {
                return await modalActions.convertToPng( blob, fileName );
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
