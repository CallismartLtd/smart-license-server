
// Countdown Timer
function startCountdown(duration, display) {
    let timer = duration, minutes, seconds;
    const interval = setInterval(function() {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);

        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;

        display.textContent = minutes + ":" + seconds;

        if (--timer < 0) {
            clearInterval(interval);
            alert("Time's up!");
        }
    }, 1000);
}

/**
 * Add a loading spinner to a given element
 * 
 * @param {String|HTMLElement} selector - The element selector.
 * @param {Boolean} larger - Whether the spinner should be bigger?
 * @return {HTMLImageElement}
 */
function showSpinner( selector, larger = false ) {
    const element = ( selector instanceof HTMLElement ) ? selector : document.querySelector( selector );

    if ( ! element ) return console.warn( 'Spinner element not found' );

    const image     = document.createElement( 'img' );
    const gifUrl    = larger ? smliser_var.wp_spinner_gif_2x : smliser_var.wp_spinner_gif;

    image.src   = gifUrl;
    image.id    = 'smliser-spinner-image';

    element.querySelector( '#smliser-spinner-image' )?.remove();

    element.appendChild( image );
    document.body.style.setProperty( 'cursor', 'progress' );

    return image;
}

/**
 * Remove spinner
 * 
 * @param {HTMLImageElement} spinner
 */
function removeSpinner( spinner ) {
    spinner?.remove();
    document.body.style.removeProperty( 'cursor' );
}


function smliserNotify(message, duration) {
    // Create a div element for the notification
    const notification = document.createElement('div');
    notification.classList.add('notification');
    
    // Set the notification message
    notification.innerHTML = `
        <div class="notification-content">
            <span id="remove" onclick="this.parentElement.parentElement.remove()">&times;</span>
            <p>${message}</p>
        </div>
    `;
    
    // Apply styles to the notification
    notification.style.position = 'fixed';
    notification.style.top = '40px';
    notification.style.left = '50%';
    notification.style.width = '30%';
    notification.style.fontWeight = 'bold';
    notification.style.transform = 'translateX(-50%)';
    notification.style.padding = '15px';
    notification.style.backgroundColor = '#fff'; // White background
    notification.style.color = '#333'; // Black text color
    notification.style.border = '1px solid #ccc';
    notification.style.borderRadius = '9px';
    notification.style.boxShadow = '0 2px 5px rgba(0, 0, 0, 0.5)';
    notification.style.zIndex = '9999';
    notification.style.textAlign = 'center';
    
    // Append the notification to the body
    document.body.appendChild(notification);
    
    // Automatically remove the notification after a specified duration (in milliseconds)
    if (duration) {
        setTimeout(() => {
            notification.remove();
        }, duration);
    }
}

// Function to copy text to clipboard using Clipboard API
function smliserCopyToClipboard(text) {
    navigator.clipboard.writeText(text).then( () => {
        smliserNotify('Copied to clipboard: ', 3000);
    }).catch( (err) => {
        console.error('Could not copy text: ', err);
    });
}

const StringUtils = {
    /**
     * Capitalize the first character of a string.
     * @param {string} str
     * @returns {string}
     */
    ucfirst: function ( str ) {
        if ( ! str ) return '';
        return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
    },

    /**
     * Capitalize the first character of each word in a string.
     * @param {string} str
     * @returns {string}
     */
    ucwords: function ( str ) {
        if ( ! str ) return '';
        return str
            .toLowerCase()
            .split( ' ' )
            .map( word => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
            .join( ' ' );
    },

    /**
     * Decodes HTML entities
     * @param {String} html - The html string to decode 
     * @returns {String} Decoded html
     */
    decodeEntity: function ( html ) {
        const textarea = document.createElement( 'textarea' );
        textarea.innerHTML = html;
        return textarea.value;
    },

    /**
     *  Escapes html
     * 
     * @param {String} string - The string to escape
     * @return {String} Safe html for outputs
     */
    escHtml: ( string ) => {
        const div = document.createElement('div' );
        div.textContent = string;

        return div.innerHTML.replace(/"/g, '&quot;');
    },
    
    /**
     * Format a number as currency.
     * @param {number} amount
     * @param {Object} config
     * @returns {string}
     */
    formatCurrency: function ( amount, config ) {
        const {
            symbol = '$',
            symbol_position = 'left',
            decimals = 2,
            decimal_separator = '.',
            thousand_separator = ','
        } = config || {};
    
        const isNegative = amount < 0;
        const absAmount = Math.abs( amount );
    
        // Format the number
        let formatted = absAmount
            .toFixed( decimals )
            .replace( /\B(?=(\d{3})+(?!\d))/g, thousand_separator )
            .replace( '.', decimal_separator );
    
        // Decode currency symbol (in case it's encoded)
        const decodedSymbol = StringUtils.decodeEntity( symbol );
    
        switch ( symbol_position ) {
            case 'left':
                formatted = decodedSymbol + formatted;
                break;
            case 'left_space':
                formatted = decodedSymbol + ' ' + formatted;
                break;
            case 'right':
                formatted = formatted + decodedSymbol;
                break;
            case 'right_space':
                formatted = formatted + ' ' + decodedSymbol;
                break;
        }
    
        return isNegative ? '-' + formatted : formatted;
    }

};

/**
 * App section via select2
 * 
 * @param {HTMLElement} selectEl 
 */
function smliserSelect2AppSelect( selectEl ) {
    if ( ! selectEl instanceof HTMLElement ) {
        console.warn( 'Could not instantiate app selection, invalid html element' );        
    }

    const prepareArgs = ( params ) => {
        return {
            search: params.term
        };
    };

    const processResults = ( data ) => {

        // Group apps by type (plugin, theme, etc.)
        const grouped = {};

        data.apps.forEach( app => {
            if ( ! grouped[ app.type ] ) {
                grouped[ app.type ] = [];
            }

            grouped[ app.type ].push({
                id: `${app.type}:${app.slug}`, // unique id combining both
                text: app.name,
                type: app.type,
            });
        });

        // Convert to Select2’s optgroup structure
        const results = Object.keys( grouped ).map( type => ({
            text: type.charAt(0).toUpperCase() + type.slice(1),
            children: grouped[ type ]
        }));

        return { results };
    };

    jQuery( selectEl ).select2({
        placeholder: 'Search apps',
        ajax: {
            url: smliser_var.app_search_api,
            dataType: 'json',
            delay: 1000,
            data: prepareArgs,
            processResults: processResults,
            cache: true,
        },
        allowClear: true,
        minimumInputLength: 2
    });    
}

document.addEventListener( 'DOMContentLoaded', function() {
    let tokenBtn                = document.getElementById( 'smliserDownloadTokenBtn' );
    let licenseKeyContainers    = document.querySelectorAll( '.smliser-license-obfuscation' );
    let searchInput             = document.getElementById('smliser-search');
    let tooltips                = document.querySelectorAll('.smliser-form-description, .smliser-tooltip');
    let deleteBtn               = document.getElementById( 'smliser-license-delete-button' );
    let updateBtn               = document.querySelector('#smliser-update-btn');
    let appDeleteBtns           = document.querySelectorAll( '.smliser-app-delete-button' );
    let selectAllCheckbox       = document.querySelector('#smliser-select-all');
    let dashboardPage           = document.getElementById( 'smliser-admin-dasboard-wrapper' );
    let apiKeyForm              = document.getElementById('smliser-api-key-generation-form');
    let revokeBtns              = document.querySelectorAll( '.smliser-revoke-btn' );
    let monetizationUI          = document.querySelector( '.smliser-monetization-ui' );
    let optionForms             = document.querySelectorAll( 'form.smliser-options-form' );
    const bulkMessageForm       = document.querySelector( 'form.smliser-compose-message-container' );
    const licenseAppSelect      = document.querySelector( '.license-app-select' );
    const repoGaleryPreview     = document.querySelector( '.smliser-screenshot-gallery' );

    licenseAppSelect && smliserSelect2AppSelect( licenseAppSelect );

    if ( repoGaleryPreview ) {
        repoGaleryPreview.addEventListener( 'click', e => {
            const clickedImage = e.target.closest( 'img.repo-image-preview' );

            if ( ! clickedImage ) {
                return;
            }

            repoGaleryPreview.querySelectorAll( 'img.repo-image-preview' ).forEach( img => img.classList.remove( 'active' ) );
            const currentImage  = document.querySelector( '.smliser-gallery-preview_image img' );
            const currentTitle  = document.querySelector( '.smliser-gallery-preview_title' );
            
            if ( ! currentImage ) return;

            currentImage.src            = clickedImage.src;
            currentTitle.textContent    = clickedImage.getAttribute( 'data-repo-image-title' );
            clickedImage.classList.add( 'active' );
            
        })
    }
    if ( optionForms ) {
        optionForms.forEach( form => {
            form.addEventListener( 'submit', ( e ) => {
                e.preventDefault();

                const payLoad   = new FormData( e.target );
                const url       = smliser_var.smliser_ajax_url;
                payLoad.set( 'security', smliser_var.nonce );
                const submitBtn = e.target.querySelector( 'button[type="submit"]' );
                
                submitBtn?.setAttribute( 'disabled', true );
                const spinner = showSpinner( '.smliser-spinner', true );

                fetch( url, {
                    method: 'POST',
                    body: payLoad,
                    credentials: 'same-origin'
                }).then( async response => {
                    const contentType = response.headers.get( 'content-type' );
                    if ( ! response.ok ) {
                        let errorMessage = await response.text();
                        if ( contentType.includes( 'application/json' ) ) {
                            const body      = await response.json();
                            errorMessage    = body?.data?.message ?? errorMessage;
                        }

                        throw new Error( errorMessage );
                    }

                    const responseJson = await response.json();

                    if ( ! responseJson.success ) {
                        let errorMessage = responseJson?.data?.message ?? 'An error occurred';

                        throw new Error( errorMessage );
                    }

                    const message = response?.data?.message ?? 'Success';
                    smliserNotify( message, 5000 );
                }).catch( error => {
                    if ( error.message?.toLowerCase().includes( "network" ) || error.message?.toLowerCase().includes( "failed to fetch" ) ){
                        smliserNotify( 'Check network connection', 5000 );
                    } else {
                        smliserNotify( error.message, 5000 );
                    }
                    
                }).finally( () => {
                    removeSpinner( spinner );
                    submitBtn?.removeAttribute( 'disabled' );
                });
                
            })
        })
    }

    if ( deleteBtn ) {
        deleteBtn.addEventListener( 'click', function( event ) {
            const userConfirmed = confirm( 'You are about to delete this license, be careful action cannot be reversed' );
            if ( ! userConfirmed ) {
                event.preventDefault();
            }
        });
    }

    if ( tokenBtn ) {
        let licenseId           = tokenBtn.getAttribute( 'data-license-id' );
        let pluginName          = tokenBtn.getAttribute( 'data-plugin-name' );
        let contentContainer    = document.getElementById( 'ajaxContentContainer' );
        let templateContainer   = document.createElement( 'div' );
        templateContainer.classList.add( 'smliser-gen-token-form-container' )

        let bodyContent = `
        <div class="smliser-token-body" id="smliser-token-body">
            <span id="remove">&times;</span>
            <h2>Generate Download Token</h2>
            <hr>

            <span class="smliser-loader" style="display: none; color:#000000;" id="spinner"></span>
        
            <p>The token will be valid to download "<strong>${pluginName}</strong>", token will expire in 10 days if no expiry date is selected</p>
            <div id="smliserNewToken"></div>
            <div class="smliser-token-expiry-field" id="formBody">
                <label for="expiryDate">Choose Expiry Date</label>
                <input type="date" id="expiryDate" />
            </div>
            <button id="createToken" class="button action smliser-nav-btn">generate token</button>
        </div>`;
        templateContainer.innerHTML = bodyContent;
        jQuery( contentContainer ).hide();
        contentContainer.appendChild( templateContainer );
        
        tokenBtn.addEventListener( 'click', (event) => {
            event.preventDefault();
            jQuery( contentContainer ).fadeIn();
            
        });

        let removeBtn       = document.getElementById( 'remove' );
        let createTokenBtn  = document.getElementById( 'createToken' );
        
        let removeModal = () =>{
            jQuery( contentContainer ).fadeOut();
        }

        removeBtn.addEventListener( 'click', removeModal );

        createTokenBtn.addEventListener( 'click', (event) => {
            event.preventDefault();
            let spinner = document.getElementById('spinner');
            
            let expiryDate = document.getElementById( 'expiryDate' ).value;
            
            spinner.setAttribute( 'style', 'display: block; border: 5px dotted #000' );

            if ( ! expiryDate ) {
                expiryDate = '';
            }
            let url = new URL( smliser_var.smliser_ajax_url );
            let payLoad = new FormData();
            payLoad.set( 'action', 'smliser_generate_download_token' );
            payLoad.set( 'security', smliser_var.nonce );
            payLoad.set( 'expiry', expiryDate );
            payLoad.set( 'license_id', licenseId );

            fetch( url, {
                method: 'POST',
                body: payLoad,
            }).then( response => {
                if ( ! response.ok ) {
                    throw new Error( response.statusText );
                }
                return response.json();
            }).then( data => {
                if ( data.success ) {
                    downloadToken = data.data.token;
                    var credentialsDiv = document.createElement('div');
                    credentialsDiv.classList.add( 'smliser-token-body' );
                    credentialsDiv.classList.add( 'added' );
                    let htmlContent = `<p><strong>New token:</strong> ${downloadToken} <span class="dashicons dashicons-admin-page"></span></p>`;
                    htmlContent += '<p><strong>Note:</strong> This is the last time this token will be revealed. Please copy and save it securely.</p>';
                    credentialsDiv.innerHTML = htmlContent;

                    jQuery( '#smliserNewToken' ).html( credentialsDiv );
                    document.getElementById( 'formBody' ).remove();
                    createTokenBtn.remove();
                    
                    let copyButton = credentialsDiv.querySelector( 'span.dashicons' );
                    copyButton.addEventListener( 'click', () =>{
                        smliserCopyToClipboard( downloadToken );
                    });
                    
                } else {
                    let errorMessage = data.data && data.data.message ? data.data.message : 'An error occurred';
                    smliserNotify(errorMessage, 5000);
                }
            }).catch(error => {
                smliserNotify(error.message || 'An unexpected error occurred', 5000);
            }).finally(() => {
                spinner.setAttribute( 'style', 'display: none;' );
            });
            
        });
    }

    if ( licenseKeyContainers.length ) {
        licenseKeyContainers.forEach(container => {
            const inputField    = container.querySelector( '.smliser-license-input' );

            container.addEventListener( 'click', (e) => {
                const checkToggle = e.target.closest( '.smliser-licence-key-visibility-toggle' );
                const copyButton = e.target.closest( '.copy-key' );
                if ( checkToggle ) {
                    inputField.classList.toggle( 'active' );
                    return;
                }
                if ( copyButton ) {
                    const licenseKeyField = container.querySelector( '.smliser-license-text' );
                    navigator.clipboard.writeText(licenseKeyField.value).then(function() {
                        smliserNotify('copied', 2000)
                    }).catch(function(error) {
                        smliserNotify( error, 2000)
                    });
                }
            });
        });
    }

    if (searchInput) {
        let tableRows = document.querySelectorAll('.smliser-table tbody tr');
        let tableBody = document.querySelector('.smliser-table tbody');
        searchInput.addEventListener('input', function () {
            const searchTerm = searchInput.value.toLowerCase();
            let noMatchFound = true;
    
            tableRows.forEach(function (row) {
                const licenseId = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const clientName = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const licenseKey = row.querySelector('td:nth-child(4)').textContent.toLowerCase();
                const serviceId = row.querySelector('td:nth-child(5)').textContent.toLowerCase();
                const itemId = row.querySelector('td:nth-child(6)').textContent.toLowerCase();
                const status = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
    
                if (
                    licenseId.includes(searchTerm) ||
                    clientName.includes(searchTerm) ||
                    licenseKey.includes(searchTerm) ||
                    serviceId.includes(searchTerm) ||
                    itemId.includes(searchTerm) ||
                    status.includes(searchTerm)
                ) {
                    row.style.display = '';
                    noMatchFound = false;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show or hide the "not found" message
            if (noMatchFound) {
                if (!document.querySelector('.smliser-not-found')) {
                    const notFoundMessage = document.createElement('tr');
                    notFoundMessage.className = 'smliser-not-found';
                    notFoundMessage.innerHTML = '<td colspan="7">No results found</td>';
                    tableBody.appendChild(notFoundMessage);
                }
            } else {
                const notFoundMessage = document.querySelector('.smliser-not-found');
                if (notFoundMessage) {
                    notFoundMessage.remove();
                }
            }
        });
    }

    if ( tooltips.length ) {
        tooltips.forEach(function(tooltip) {
            tooltip.addEventListener('mouseenter', function() {
                var title = this.getAttribute('title');
                if (title) {
                    this.setAttribute('data-title', title);
                    this.removeAttribute('title');

                    var tooltipElement = document.createElement('div');
                    tooltipElement.className = 'custom-tooltip';
                    tooltipElement.innerText = title;
                    document.body.appendChild(tooltipElement);

                    var rect = this.getBoundingClientRect();
                    tooltipElement.style.top = rect.top + window.scrollY - tooltipElement.offsetHeight - 5 + 'px';
                    tooltipElement.style.left = rect.left + window.scrollX + (rect.width / 2) - (tooltipElement.offsetWidth / 2) + 'px';
                    
                    tooltipElement.classList.add('show');

                    this._tooltipElement = tooltipElement;
                }
            });

            tooltip.addEventListener('mouseleave', function() {
                var tooltipElement = this._tooltipElement;
                if (tooltipElement) {
                    tooltipElement.classList.remove('show');
                    setTimeout(function() {
                        document.body.removeChild(tooltipElement);
                    }, 300);
                    this.setAttribute('title', this.getAttribute('data-title'));
                    this.removeAttribute('data-title');
                    this._tooltipElement = null;
                }
            });
        });
    }

    if ( updateBtn ) {
        let   updateClickedNotice = document.querySelector('#smliser-click-notice');
        updateBtn.addEventListener('click', (e) => {
            e.preventDefault();
            updateBtn.parentElement.style.display = 'none';
            updateClickedNotice.style.display ='block';
            let dismissBtn = document.createElement( 'span' );
            dismissBtn.classList.add( 'dashicons', 'dashicons-dismiss' );
            dismissBtn.style.color = 'red';
            dismissBtn.style.float = 'right';
            dismissBtn.addEventListener( 'click', ()=>{
                dismissBtn.parentElement.parentElement.remove();
            });
            
            updateClickedNotice.appendChild(dismissBtn);
            
            // Construct the URL
            let updateUrl = new URL( smliser_var.smliser_ajax_url );
            updateUrl.searchParams.set( 'action', 'smliser_upgrade' );
            updateUrl.searchParams.set( 'security', smliser_var.nonce );
        
            // Fetch request
            fetch( updateUrl )
                .then(response => {
                    // Check if the response is ok
                    if (!response.ok) {
                        throw new Error(response.statusText);
                    }
                    return response.json(); // Parse the JSON body
                })
                .then(data => {
                    if (data.success) {
                        smliserNotify(data.data.message || 'Upgrade successful!', 5000);
                        updateBtn.parentElement.style.display = 'none'; // Update UI only if successful
                        updateClickedNotice.style.display = 'block';
                    } else {
                        smliserNotify(data.data?.message || 'An error occurred', 5000);
                    }
                })
                .catch(error => {
                    // Handle fetch or JSON parsing errors
                    smliserNotify(error.message || 'An unexpected error occurred', 5000);
                });
        });
    }

    if ( appDeleteBtns.length ) {
        appDeleteBtns.forEach( appDeleteBtn => {
            appDeleteBtn.addEventListener('click', (e)=>{
                let confirmed = confirm('You are about to delete this plugin from the repository, this action cannot be reversed!');
                if ( ! confirmed ) {
                    e.preventDefault();
                    return;
                }
                let appInfo    = '';

                try {
                    appInfo = JSON.parse( appDeleteBtn.getAttribute( 'data-app-info' ) );
                } catch (error) {
                    smliserNotify( 'App data not found' );
                    return;
                }
                let url = new URL( smliser_var.smliser_ajax_url );
                url.searchParams.set( 'action', 'smliser_delete_app' );
                url.searchParams.set( 'slug', appInfo.slug );
                url.searchParams.set( 'type', appInfo.type );
                url.searchParams.set( 'security', smliser_var.nonce );
                
                fetch(url)
                    .then( response=>{
                        if ( ! response.ok ) {
                            smliserNotify(`Error: [${response.status}] ${response.statusText}`);
                        }
                        return response.json();
                    })
                    .then( responseData => {
                        
                        if ( responseData.success ) {
                            smliserNotify(`Success: ${responseData.data.message}`, 3000);
                            setTimeout( () => {
                                window.location.href = responseData.data.redirect_url;
                            }, 3000);
                        } else {
                            smliserNotify(`Error: ${responseData.data.message}`, 6000);

                        }
                    });
                
            });
        });
    }

    if ( selectAllCheckbox ) {
        let  checkboxes = document.querySelectorAll( '.smliser-license-checkbox, .smliser-checkbox' );

        selectAllCheckbox.addEventListener('change', function () {
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                selectAllCheckbox.checked = Array.from(checkboxes).every(cb => cb.checked);
                
            });
        });
    }

    if ( dashboardPage ) {
        var ctx1 = document.getElementById('pluginUpdateChart').getContext('2d');
        var ctx2 = document.getElementById('licenseActivationChart').getContext('2d');
        var ctx3 = document.getElementById('licenseDeactivationChart').getContext('2d');

        new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: ['Hits', 'Visits Today', 'Unique Visitors', 'Plugin Downloads Served'],
                datasets: [{
                    label: 'Plugin Update Route',
                    data: [smliserStats.pluginUpdateHits, smliserStats.pluginUpdateVisits, smliserStats.pluginUniqueVisitors, smliserStats.pluginDownloads],
                    backgroundColor: ['#36a2eb', '#ff6384', '#ff0000', '#16f2b0']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Hits', 'Visits Today', 'Unique Visitors'],
                datasets: [{
                    label: 'License Activation Route',
                    data: [smliserStats.licenseActivationHits, smliserStats.licenseActivationVisits, smliserStats.licenseActivationUniqueVisitors],
                    backgroundColor: ['#36a2eb', '#ff6384', '#ff0000']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
        new Chart(ctx3, {
            type: 'bar',
            data: {
                labels: ['Hits', 'Visits Today', 'Unique Visitors'],
                datasets: [{
                    label: 'License Deactivation Route',
                    data: [smliserStats.licenseDeactivationHits, smliserStats.licenseDeactivationVisits, smliserStats.licenseDeactivationUniqueVisitors],
                    backgroundColor: ['#36a2eb', '#ff6384', '#ff0000']
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
    
    if ( apiKeyForm ) {
        let spinnerOverlay = document.querySelector( '.spinner-overlay' );
        apiKeyForm.addEventListener('submit', ( e ) => {
            e.preventDefault();
            spinnerOverlay.classList.add('show');
            
            let formData = new FormData( apiKeyForm );
            formData.append( 'action', 'smliser_key_generate' );
            formData.append( 'security', smliser_var.nonce );
            fetch( smliser_var.smliser_ajax_url, {
                method: 'POST',
                body: formData,
            }).then( response => {
                if ( ! response.ok ) {
                    throw new Error( response.statusText );
                }
                return response.json();
            }).then( response => {
                if (response.success) {
                    let consumer_public = response.data ? response.data.consumer_public : '';
                    let consumer_secret = response.data ? response.data.consumer_secret : '';
                    let description = response.data ? response.data.description : '';

                    // Create a div to display the credentials
                    var credentialsDiv = document.createElement('div');
                    credentialsDiv.style.border = '1px solid #ccc';
                    credentialsDiv.style.borderRadius = '9px';
                    credentialsDiv.style.padding = '10px';
                    credentialsDiv.style.margin = '20px auto';
                    credentialsDiv.style.backgroundColor = '#fff';
                    credentialsDiv.style.width = '50%';

                    var htmlContent = '<h2 style="text-align:center;">API Credentials</h2>';
                    htmlContent += '<p><strong>Description:</strong> ' + description + '</p>';
                    htmlContent += '<p><strong>Consumer Public:</strong> ' + consumer_public + '  <span onclick="smliserCopyToClipboard(\'' + consumer_public + '\')" class="dashicons dashicons-admin-page"></span></p>';
                    htmlContent += '<p><strong>Consumer Secret:</strong> ' + consumer_secret + '  <span onclick="smliserCopyToClipboard(\'' + consumer_secret + '\')" class="dashicons dashicons-admin-page"></span></p>';
                    htmlContent += '<p><strong>Note:</strong> This is the last time these credentials will be revealed. Please copy and save them securely.</p>';

                    credentialsDiv.innerHTML = htmlContent;
                    jQuery( credentialsDiv ).hide();
                    jQuery ( apiKeyForm ).fadeOut( 'slow', () =>{
                        apiKeyForm.parentNode.insertBefore( credentialsDiv, apiKeyForm.nextSibling );
                        jQuery( credentialsDiv ).fadeIn( 'slow' );
                    })
                    
                } else {
                    var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                    console.error(errorMessage);
                    smliserNotify( errorMessage, 3000 );
                }
            }).catch( error => {
                console.error(error);
                
            }).finally( () => {
                spinnerOverlay.classList.remove('show');
                apiKeyForm.reset();
            });
        });
    }

    if ( revokeBtns ) {
        let spinnerOverlay = document.querySelector( '.spinner-overlay' );
        revokeBtns.forEach( function( revokeBtn ){
            let apiKeyId = revokeBtn.getAttribute( 'data-key-id' );

            revokeBtn.addEventListener( 'click', () => {
                const userConfirmed = confirm( 'You are about to revoke this license, connected app will not be able to access resource on this server, be careful action cannot be reversed' );
                if ( ! userConfirmed ) {
                    return;
                }
                spinnerOverlay.classList.add('show');
                let url = new URL( smliser_var.smliser_ajax_url );
                url.searchParams.set( 'action', 'smliser_revoke_key' );
                url.searchParams.set( 'security', smliser_var.nonce );
                url.searchParams.set( 'api_key_id', apiKeyId );

                fetch( url, {
                    method: 'GET',
                }).then( response =>{
                    if ( ! response.ok ) {
                        throw new Error( response.statusText );
                    }
                    return response.json();
                }).then( responseJson =>{
                    if ( responseJson.success ) {
                        let feedback = responseJson.data ? responseJson.data.message : '';
                        smliserNotify(feedback, 3000);
                        location.reload();
                    } else {
                        let errorMessage = responseJson.data && responseJson.data.message ? responseJson.data.message : 'An unknown error occurred.';
                        console.log( errorMessage );
                        smliserNotify(errorMessage, 3000);
                    }
                }).catch( error =>{
                    console.error(error); // Log detailed error information
                    
                }).finally( () =>{
                    spinnerOverlay.classList.remove('show');
                });
            });
        } );
    }
    
    if ( monetizationUI ) {
        let tierModal   = document.querySelector( '.smliser-admin-modal.pricing-tier' );
        let tierForm    = document.querySelector( '#tier-form' );

        /**
         * Utility: Validate and reset field errors
         */
        const checkValidity = ( field ) => {
            if ( ! field.value.trim() ) {
                let fieldName = field.getAttribute( 'field-name' ) || 'This field';
                field.setCustomValidity( `${fieldName} is required.` );
                field.addEventListener( 'input', resetValidity );
            }
        };
        const resetValidity = ( e ) => {
            e.target.setCustomValidity( '' );
            e.target.removeEventListener( 'input', resetValidity );
        };
        const highlightErrorField = ( fieldId, message ) => {
            const field = tierForm.querySelector( `#${fieldId}` );
            if ( field ) {
                field.setCustomValidity( message );
                field.reportValidity();
                field.addEventListener( 'input', resetValidity );
            }
        };

        /**
         * Utility: Fetch wrapper (consistent error parsing)
         */
        const smliserFetch = async ( url, options = {} ) => {
            const response = await fetch( url, options );
            if ( ! response.ok ) {
                const contentType = response.headers.get( 'content-type' );
                let errorMessage = 'An error occurred';
                let errorField   = null;

                if ( contentType && contentType.includes( 'application/json' ) ) {
                    const errorData = await response.json();
                    errorMessage = errorData.data?.message || errorMessage;
                    errorField   = errorData.data?.field_id || null;
                    throw { message: errorMessage, field: errorField };
                } else {
                    const text = await response.text();
                    throw { message: text || errorMessage };
                }
            }
            return response.json();
        };

        /**
         * Form Submit Handler
         */
        const submitForm = ( e ) => {
            e.preventDefault();

            tierForm.querySelectorAll( '#tier_name, #product_id, #billing_cycle, #provider_id, #features' )
                .forEach( checkValidity );

            if ( ! tierForm.checkValidity() ) {
                tierForm.reportValidity();
                return;
            }

            const payLoad = new FormData( tierForm );
            payLoad.set( 'security', smliser_var.nonce );

            smliserFetch( smliser_var.smliser_ajax_url, { method: 'POST', body: payLoad } )
                .then( responseJson => {
                    if ( responseJson.success ) {
                        smliserNotify( responseJson.data?.message || 'Operation successful', 3000 );
                        setTimeout( () => window.location.reload(), 3000 );
                    } else {
                        let errorMessage = responseJson.data?.message || 'An unknown error occurred.';
                        let errorField   = responseJson.data?.field_id || null;
                        if ( errorField ) highlightErrorField( errorField, errorMessage );
                        smliserNotify( errorMessage, 6000 );
                    }
                })
                .catch( error => {
                    if ( error.field ) highlightErrorField( error.field, error.message );
                    smliserNotify( error.message || 'An unexpected error occurred', 6000 );
                });
        };

        /**
         * Modal Actions
         */
        const smliserModalActions = {
            addNewTier: () => {
                tierModal.classList.remove( 'hidden' );
                tierForm.querySelectorAll( 'input, select, textarea' ).forEach( input => {
                    if ( 'action' === input.name ) {
                        input.value = 'smliser_save_monetization_tier';
                    }
                    if ( 'hidden' !== input.type ) {
                        input.value = '';
                    }
                });
            },

            editTier: ( json ) => {
                let tier = JSON.parse( json );
                tierModal.classList.remove( 'hidden' );

                // Switch action to update
                tierForm.querySelector( 'input[name="action"]' ).value = 'smliser_save_monetization_tier';
                tierForm.querySelector( 'input[name="tier_id"]' ).value = tier.id || '';

                tierForm.querySelector( '#tier_name' ).value     = tier.name || '';
                tierForm.querySelector( '#product_id' ).value   = tier.product_id || '';
                tierForm.querySelector( '#billing_cycle' ).value = tier.billing_cycle || '';
                tierForm.querySelector( '#provider_id' ).value  = tier.provider_id || '';
                tierForm.querySelector( '#max_sites' ).value    = tier.max_sites || '';
                tierForm.querySelector( '#features' ).value     = Array.isArray( tier.features ) ? tier.features.join(', ') : ( tier.features || '' );
            },

            deleteTier: ( json ) => {
                let tier = JSON.parse( json );
                if ( confirm( `Are you sure you want to delete tier "${tier.name}"?` ) ) {
                    const payLoad = new FormData();
                    payLoad.set( 'action', 'smliser_delete_monetization_tier' );
                    payLoad.set( 'security', smliser_var.nonce );
                    payLoad.set( 'monetization_id', tier.monetization_id );
                    payLoad.set( 'tier_id', tier.id );

                    smliserFetch( smliser_var.smliser_ajax_url, { method: 'POST', body: payLoad } )
                        .then( responseJson => {
                            if ( responseJson.success ) {
                                smliserNotify( responseJson.data?.message || 'Tier deleted', 3000 );
                                setTimeout( () => window.location.reload(), 2000 );
                            } else {
                                smliserNotify( responseJson.data?.message || 'Delete failed', 6000 );
                            }
                        })
                        .catch( error => smliserNotify( error.message || 'Delete failed', 6000 ) );
                }
            },

            closeModal: () => {
                tierModal.classList.add( 'hidden' );
            },

            viewProductData: ( json ) => {
                let tier = JSON.parse( json );

                // Remove any existing product-data modal
                monetizationUI.querySelector( '.smliser-admin-modal.product-data' )?.remove();

                let modal = document.createElement( 'div' );
                modal.className = 'smliser-admin-modal product-data';

                modal.innerHTML = `
                    <div class="smliser-admin-modal_content">
                        <span class="dashicons dashicons-dismiss remove-button" title="remove" data-command="closeModal"></span>
                        <h2 class="product-data-header">
                            <span class="product-image-slot"></span>
                            <span class="product-title">Product Data for: ${tier.name || ''}</span>
                        </h2>
                        <table class="widefat striped">
                            <tbody>
                                <tr><th scope="row">Product ID</th><td>${tier.product_id}</td></tr>
                                <tr><th scope="row">Provider</th><td>${tier.provider_id}</td></tr>
                                <tr><th scope="row">Billing Cycle</th><td>${tier.billing_cycle}</td></tr>
                                <tr><th scope="row">Price</th><td><span class="price-field">Loading...</span></td></tr>
                                <tr><th scope="row">Description</th><td><span class="desc-field">Loading...</span></td></tr>
                            </tbody>
                        </table>
                        <div class="spinner-overlay show">
                            <img src="${smliser_var.admin_url}images/spinner.gif" alt="Loading..." class="spinner-img">
                        </div>
                    </div>
                `;
                monetizationUI.appendChild( modal );

                // Close handlers
                modal.querySelector( '.remove-button' ).addEventListener( 'click', () => modal.remove() );
                modal.addEventListener( 'click', e => {
                    if ( e.target.classList.contains( 'smliser-admin-modal' ) ) modal.remove();
                });

                // Fetch provider product
                const params = new URLSearchParams({
                    action: 'smliser_get_product_data',
                    security: smliser_var.nonce,
                    provider_id: tier.provider_id,
                    product_id: tier.product_id,
                });

                smliserFetch( `${smliser_var.smliser_ajax_url}?${params.toString()}`, { method: 'GET' } )
                    .then( responseJson => {
                        modal.querySelector( '.spinner-overlay' )?.remove();
                        if ( ! responseJson.success ) {
                            smliserNotify( responseJson.data?.message || 'Could not fetch product data', 6000 );
                            return;
                        }

                        const product = responseJson.data.product || {};
                        const pricing = product.pricing || {};

                        // Insert first image if available
                        if ( product.images && product.images.length > 0 ) {
                            const img = document.createElement( 'img' );
                            img.src = product.images[0].src;
                            img.alt = product.images[0].alt || 'Product Image';
                            img.className = 'product-thumb';
                            modal.querySelector( '.product-image-slot' ).appendChild( img );
                        }

                        // Format price
                        let formattedPrice = 'N/A';
                        if ( pricing.price ) {
                            formattedPrice = StringUtils.formatCurrency( pricing.price, product.currency );
                        }

                        modal.querySelector( '.price-field' ).textContent = formattedPrice;
                        modal.querySelector( '.desc-field' ).innerHTML   = product.description || '';
                    })
                    .catch( error => {
                        modal.querySelector( '.spinner-overlay' )?.remove();
                        smliserNotify( error.message || 'An unexpected error occurred', 6000 );
                    });
            },

            'toggleMonetization': ( monetizationId, enabled ) => {
                const payLoad = new FormData();
                payLoad.set( 'action', 'smliser_toggle_monetization' );
                payLoad.set( 'security', smliser_var.nonce );
                payLoad.set( 'monetization_id', monetizationId );
                payLoad.set( 'enabled', enabled );

                fetch( smliser_var.smliser_ajax_url, {
                    method: 'POST',
                    body: payLoad,
                })
                .then( async response => {
                    if ( ! response.ok ) {
                        const contentType = response.headers.get( 'content-type' );
                        let errorMessage = 'An error occurred';

                        if ( contentType && contentType.includes( 'application/json' ) ) {
                            const errorData = await response.json();
                            errorMessage = errorData.data?.message || errorMessage;
                            throw new Error( errorMessage );
                        } else {
                            const text = await response.text();
                            throw new Error( text || errorMessage );
                        }
                    }
                    return response.json();
                })
                .then( responseJson => {
                    if ( responseJson.success ) {
                        smliserNotify( responseJson.data?.message || 'Monetization updated', 3000 );
                    } else {
                        smliserNotify( responseJson.data?.message || 'Update failed', 6000 );
                    }
                })
                .catch( error => {
                    smliserNotify( error.message || 'An unexpected error occurred', 6000 );
                    const toggle    = monetizationUI.querySelector( '.smliser_toggle-switch-input' );
                    const current   = toggle?.checked;
                    toggle.checked  = ! current;
                });
            }
        };

        /**
         * Delegated Click Handler
         */
        monetizationUI.addEventListener( 'click', ( e ) => {
            // Click outside modal closes it
            if ( e.target.classList.contains( 'smliser-admin-modal' ) ) {
                e.preventDefault();
                smliserModalActions.closeModal();
                return;
            }

            // Static modal open/close
            const modal = e.target.closest( '#add-pricing-tier, .remove-modal' );
            if ( modal ) {
                e.preventDefault();
                const action = modal.getAttribute( 'data-command' );
                smliserModalActions[action]?.();
                return;
            }

            // Tier buttons
            const tierBtn = e.target.closest( '.smliser-tier-edit, .smliser-tier-delete, .smliser-tier-view' );
            if ( tierBtn ) {
                e.preventDefault();
                const action = tierBtn.getAttribute( 'data-action' );
                const tierDiv = tierBtn.closest( '.smliser-pricing-tier-info' );
                smliserModalActions[action]?.( tierDiv.dataset.json );
            }
        });

        monetizationUI.addEventListener('change', e => {
            const input = e.target.closest('.smliser_toggle-switch-input');
            if ( input && input.dataset.action === 'toggleMonetization' ) {
                const monetizationId = input.dataset.monetizationId;
                const enabled = input.checked ? 1 : 0;

                smliserModalActions['toggleMonetization']( monetizationId, enabled );
            }
        });


        tierForm.addEventListener( 'submit', submitForm );
    }

    if ( bulkMessageForm ) {
        let appSelect   = bulkMessageForm.querySelector( '#smliser-app-select' );

        if ( appSelect ) {
            smliserSelect2AppSelect( appSelect );
        }

        //Initialize the editor.
        tinymce.init({
            selector: '#message-body',
            skin: 'oxide',
            branding: false,
            license_key: 'gpl',
            menubar: 'file insert table',
            plugins: 'lists link image media table code preview fullscreen autosave searchreplace visualblocks insertdatetime emoticons',
            toolbar: 'add_media_button | styles | alignleft aligncenter alignjustify alignright bullist numlist outdent indent | forecolor backcolor | code fullscreen preview | undo redo',
            height: 600,
            relative_urls: false,
            remove_script_host: false,
            promotion: false,
            valid_children: '+div[div|span],+span[span|div]',
            font_formats: 'Inter=Inter, sans-serif; Arial=Arial, Helvetica, sans-serif; Verdana=Verdana, Geneva, sans-serif; Tahoma=Tahoma, Geneva, sans-serif; Trebuchet MS=Trebuchet MS, Helvetica, sans-serif; Times New Roman=Times New Roman, Times, serif; Georgia=Georgia, serif; Palatino Linotype=Palatino Linotype, Palatino, serif; Courier New=Courier New, Courier, monospace',
            toolbar_mode: 'sliding',
            content_style: 'body { font-family: "Inter", sans-serif; font-size: 16px; }',
        });

        const clearValidity = ( e ) => {
            e.target.setCustomValidity( '' );
            e.target.removeEventListener( 'input', clearValidity );
        }

        bulkMessageForm.addEventListener( 'submit', async e => {
            e.preventDefault();

            const editor        = tinymce.get( 'message-body' );

            editor?.save();

            const subject       = bulkMessageForm.querySelector( '#subject' );
            const messageBody   = bulkMessageForm.querySelector( '#message-body' );
            

            if ( ! subject.value.trim().length ) {
                subject.setCustomValidity( 'Message subject is required.' );
                subject.addEventListener( 'input', clearValidity );
            }

            if ( ! messageBody.value.trim().length ) {
                editor?.notificationManager.open({
                    text: 'Message body cannot be empty.',
                    type: 'error',
                    timeout: 5000,
                    

                });

                return;
            }

            if ( ! bulkMessageForm.reportValidity() ) {
                return;
            }

            const payLoad = new FormData( bulkMessageForm );
            payLoad.set( 'security', smliser_var.nonce );
            payLoad.set( 'action', 'smliser_publish_bulk_message' );
            
            const submitBtn = bulkMessageForm.querySelector( 'button[type="submit"]' );
            const spinner    = showSpinner( submitBtn );
            submitBtn && ( submitBtn.disabled = true );

            try {
                const response = await fetch( smliser_var.smliser_ajax_url, {
                    method: 'POST',
                    body: payLoad,
                    credentials: 'same-origin',
                });

                const contentType   = response.headers.get( 'content-type' );
                const isJson        = contentType && contentType.includes( 'application/json' );
                const responseData  = isJson  ? await response.json() : await response.text();
                if ( ! response.ok ) {
                    const errorMessage = isJson ? ( responseData.data?.message || 'An error occurred' ) : responseData;
                    const error = new Error( errorMessage );
                    error.type  = 'SMLISER_NOT_OK';
                    throw err;
                }

                if ( responseData.success ) {
                    smliserNotify( responseData.data?.message || 'Message sent successfully', 5000 );
                    bulkMessageForm.reset();
                    jQuery( appSelect ).val( null ).trigger( 'change' );
                    editor?.setContent( '' );

                    responseData.data?.redirect_url && ( window.location.href = responseData.data.redirect_url );
                } else {
                    const errorMessage = responseData.data?.message || 'An unknown error occurred.';
                    const error = new Error( errorMessage );
                    error.type  = 'SMLISER_FAILURE';
                    throw error;
                }
            } catch ( error ) {

                if ( error instanceof TypeError ) {
                    smliserNotify( 'Network error or server is unreachable.', 6000 );
                } else if ( error.type === 'SMLISER_NOT_OK' || error.type === 'SMLISER_FAILURE' ) {
                    smliserNotify( error.message, 6000 );
                } else {
                    smliserNotify( 'An unexpected error occurred.', 10000 );
                }

            } finally {
                submitBtn && ( submitBtn.disabled = false );
                removeSpinner( spinner );
            }

        })

    }

});