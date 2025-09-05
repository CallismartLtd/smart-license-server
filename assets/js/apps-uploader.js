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

const modalActions = {
    'openModal': ( json ) => {
        appAssetUploadModal.classList.remove( 'hidden' );

        if ( json ) {
            console.log( json );
            
        }
    },
    'closeModal': () => {
        appAssetUploadModal.classList.add( 'hidden' );
    },
}


appAssetUploadModal?.addEventListener( 'click', handleClickAction );
assetsContainer?.addEventListener( 'click', handleClickAction );
