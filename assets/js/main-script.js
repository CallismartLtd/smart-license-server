
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

document.addEventListener( 'DOMContentLoaded', function() {
    let tokenBtn                = document.getElementById( 'smliserDownloadTokenBtn' );
    let licenseKeyContainers    = document.querySelectorAll( '.smliser-key-div' );
    let searchInput             = document.getElementById('smliser-search');
    let tooltips                = document.querySelectorAll('.smliser-form-description, .smliser-tooltip');
    let deleteBtn               = document.getElementById( 'smliser-license-delete-button' );
    let updateBtn               = document.querySelector('#smliser-update-btn');
    let fileInput               = document.getElementById( 'smliser-plugin-file' );
    let deletePluginBtn         = document.querySelector( '#smliser-plugin-delete-button' );
    let selectAllCheckbox       = document.getElementById('smliser-select-all');
    let dashboardPage           = document.getElementById( 'smliser-admin-dasboard-wrapper' );
    let apiKeyForm              = document.getElementById('smliser-api-key-generation-form');
    let revokeBtns              = document.querySelectorAll( '.smliser-revoke-btn' );

    if ( deleteBtn ) {
        deleteBtn.addEventListener( 'click', function( event ) {
            const userConfirmed = confirm( 'You are about to delete this license, be careful action cannot be reversed' );
            if ( ! userConfirmed ) {
                event.preventDefault();
            }
        });
    }

    if ( tokenBtn ) {
        let spinnerDiv          = document.querySelector( '.smliser-loader-container' );
        let licenseKey          = tokenBtn.getAttribute( 'data-license-key' );
        let itemId              = tokenBtn.getAttribute( 'data-item-id' );
        let pluginName          = tokenBtn.getAttribute( 'data-plugin-name' );
        let contentContainner   = document.getElementById( 'ajaxContentContainer' );
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
                <input type="datetime-Local" id="expiryDate" />
                <input type="hidden" id="pop-up-license-key" name="license_key" value="${licenseKey}"/>
                <input type="hidden" id="popup-item-id" name="item_id" value="${itemId}"/>
            </div>
            <button id="createToken" class="button action smliser-nav-btn">generate token</button>
        </div>`;
        templateContainer.innerHTML = bodyContent;
        jQuery( contentContainner ).hide();
        contentContainner.appendChild( templateContainer );
        
        tokenBtn.addEventListener( 'click', (event) => {
            event.preventDefault();
            jQuery( contentContainner ).fadeIn();
            
        });

        let removeBtn = document.getElementById( 'remove' );
        let createTokenBtn = document.getElementById( 'createToken' );
        let removeModal = () =>{
            jQuery( contentContainner ).fadeOut();
        }

        removeBtn.addEventListener( 'click', removeModal );
        createTokenBtn.addEventListener( 'click', (event) => {
            event.preventDefault();
            let spinner = document.getElementById('spinner');
            
            let expiryDate = document.getElementById( 'expiryDate' ).value;
            let licenseKey = document.getElementById( 'pop-up-license-key' ).value;
            let itemId     = document.getElementById( 'popup-item-id' ).value;
            
            spinner.setAttribute( 'style', 'display: block; border: 5px dotted #000' );

            if ( ! expiryDate ) {
                expiryDate = '';
            }
            let url = new URL( smliser_var.smliser_ajax_url );
            let payLoad = new FormData();
            payLoad.set( 'action', 'smliser_generate_item_token' );
            payLoad.set( 'security', smliser_var.nonce );
            payLoad.set( 'expiry', expiryDate );
            payLoad.set( 'license_key', licenseKey );
            payLoad.set( 'item_id', itemId );
            fetch(url, {
                method: 'POST',
                body: payLoad,
            }).then(response => {
                if (!response.ok) {
                    throw new Error(response.statusText);
                }
                return response.json();
            }).then(data => {
                if (data.success) {
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
            const licenseKeyField = container.querySelector('.smliser-license-key-field');
            const showLicenseKeyCheckbox = container.querySelector('.smliser-show-license-key');
            const visibleLicenseKeyDiv = container.querySelector('.smliser-visible-license-key');
            const partiallyHiddenLicenseKeyDiv = container.querySelector('.smliser-partially-hidden-license-key-container');
            const copyButton = container.querySelector('.smliser-to-copy');

            if (showLicenseKeyCheckbox) {
                showLicenseKeyCheckbox.addEventListener('change', function () {
                    if (this.checked) {
                        // visibleLicenseKeyDiv.style.display          = 'block';
                        jQuery(visibleLicenseKeyDiv).fadeIn().css('display', 'block')
                        partiallyHiddenLicenseKeyDiv.style.display  = 'none';
                    } else {
                        visibleLicenseKeyDiv.style.display          = 'none';
                        jQuery(partiallyHiddenLicenseKeyDiv).fadeIn().css('display', 'block');

                    }
                });

                copyButton.addEventListener('click', function ( event ) {
                    event.preventDefault();
                    
                    navigator.clipboard.writeText(licenseKeyField.value).then(function() {
                        smliserNotify('copied', 2000)
                    }).catch(function(error) {
                        smliserNotify( error, 2000)
                    });
                });
            }
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

    if ( fileInput  ) {
        let uploadBtn       = document.querySelector('.smliser-upload-btn');
        let fileInfo        = document.querySelector('.smliser-file-info');
        let submitBtn     = document.querySelector('#smliser-repo-submit-btn');

        fileInput.accept = '.zip';
        let clearFileInputBtn = document.querySelector('#smliser-file-remove');
        uploadBtn .addEventListener('click', ()=>{
            fileInput.click();
        });
        const originalText = fileInfo.textContent;

        fileInput.addEventListener('change', function(event) {
            
            const file = event.target.files[0];
            if (file) {
                let maxUploadSize = fileInfo.getAttribute('wp-max-upload-size');
                let fileSizeElement = document.createElement('p');
                let fileNameElement = document.createElement('p');
                const fileSizeMB = (file.size / 1024 / 1024).toFixed(2); // Convert size to MB and round to 2 decimal places
                fileInfo.innerHTML = '';
                fileSizeElement.innerHTML = maxUploadSize > fileSizeMB
                ? `File size: ${fileSizeMB} MB <span class="dashicons dashicons-yes-alt"></span>`
                : `File size: ${fileSizeMB} MB <span class="dashicons dashicons-no" style="color: red;"></span>`;
                fileNameElement.textContent = `File name: ${file.name}`;
                fileInfo.append(fileNameElement, fileSizeElement);
                clearFileInputBtn.style.display = "block";

                if ( maxUploadSize < fileSizeMB ) {
                    smliserNotify('The uploaded file is higher than the max_upload_size, this server cannot process this request', 6000);
                }

                
            } else {
                fileInfo.innerHTML = originalText;
            }
        });
        
        clearFileInputBtn.addEventListener('click', ()=>{
            clearFileInputBtn.style.display = "none";
            fileInput.value = '';
            fileInfo.innerHTML = originalText;

        });

        if ( submitBtn ) {
            submitBtn.addEventListener( 'click', (e)=>{
                if ( fileInput && fileInput.required && fileInput.files.length === 0 ) {
                    e.preventDefault();
                    smliserNotify('File upload is required.' );
                }
            });
        }
    }

    if ( deletePluginBtn ) {
        deletePluginBtn.addEventListener('click', (e)=>{
            let confirmed = confirm('You are about to delete this plugin from the repository, this action cannot be reversed!');
            if (! confirmed ) {
                e.preventDefault();
                return;
            }
            let url = new URL( smliser_var.smliser_ajax_url );
            url.searchParams.set( 'action', 'smliser_plugin_action');
            url.searchParams.set( 'real_action', 'delete' );
            url.searchParams.set( 'item_id', deletePluginBtn.getAttribute( 'item-id' ) );
            url.searchParams.set( 'security', smliser_var.nonce );
            
            fetch(url)
                .then( response=>{
                    if ( ! response.ok ) {
                        smliserNotify(`Error: [${response.status}] ${response.statusText}`);
                    }
                    return response.json();
                })
                .then( responseData=>{
                    if ( responseData.success ) {
                        smliserNotify(`Success: ${responseData.data.message}`, 3000);
                        setTimeout(()=>{
                            window.location.href = responseData.data.redirect_url;
                        }, 3000);
                    } else {
                        smliserNotify(`Error: ${responseData.data.message}`, 6000);

                    }
                });
            
        });
    }

    if ( selectAllCheckbox ) {
        let  checkboxes = document.querySelectorAll( '.smliser-license-checkbox' );
        selectAllCheckbox.addEventListener('change', function () {
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });
    
        // Prevent row click when individual checkbox is clicked
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('click', function (event) {
                event.stopPropagation();
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
});