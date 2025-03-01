document.addEventListener('DOMContentLoaded', function () {
    const licenseKeyContainers = document.querySelectorAll('.smliser-key-div');

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
});

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


document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('smliser-search');
    const tableRows = document.querySelectorAll('.smliser-table tbody tr');
    const tableBody = document.querySelector('.smliser-table tbody');

    if (searchInput) {
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

});


document.addEventListener('DOMContentLoaded', function() {
    var tooltips = document.querySelectorAll('.smliser-form-description, .smliser-tooltip');

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
});


document.addEventListener('DOMContentLoaded', function() {
    const fileInput         = document.getElementById('smliser-plugin-file');
    const fileInfo          = document.querySelector('.smliser-file-info');
    let   uploadBtn         = document.querySelector('.smliser-upload-btn');
    const deleteBtn         = document.getElementById( 'smliser-license-delete-button' );
    let   submitBtn         = document.querySelector('#smliser-repo-submit-btn');
    let   deletePluginBtn   = document.querySelector('#smliser-plugin-delete-button');
    let   updateBtn         = document.querySelector('#smliser-update-btn');
    let   updateClickedNotice = document.querySelector('#smliser-click-notice');
    if ( deleteBtn ) {
        deleteBtn.addEventListener( 'click', function( event ) {
            const userConfirmed = confirm( 'You are about to delete this license, be careful action cannot be reversed' );
            if ( ! userConfirmed ) {
                event.preventDefault();
            }
        });
    }

    if ( fileInput && uploadBtn  ) {
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

    // Handle "select all" checkbox
    const selectAllCheckbox = document.getElementById('smliser-select-all');
    const checkboxes = document.querySelectorAll('.smliser-license-checkbox');

    if (selectAllCheckbox ) {
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

    if ( updateBtn ) {
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
    
});



document.addEventListener('DOMContentLoaded', function () {
    var dashboardPage = document.getElementById( 'smliser-admin-dasboard-wrapper' );

    if( dashboardPage ) {
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

});


document.addEventListener('DOMContentLoaded', function () {
    var apiKeyForm = document.getElementById('smliser-api-key-generation-form');
    var spinnerOverlay = document.querySelector('.spinner-overlay');

    if (apiKeyForm) {
        apiKeyForm.addEventListener('submit', function (event) {
            event.preventDefault();

            // Show full-page spinner overlay
            spinnerOverlay.classList.add('show');

            // Serialize form data
            var formData = new FormData(apiKeyForm);

            // Append additional data
            formData.append('action', 'smliser_key_generate');
            formData.append('security', smliser_var.nonce );

            // Send AJAX request
            jQuery.ajax({
                type: 'POST',
                url: smliser_var.smliser_ajax_url,
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var consumer_public = response.data ? response.data.consumer_public : '';
                        var consumer_secret = response.data ? response.data.consumer_secret : '';
                        var description = response.data ? response.data.description : '';

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

                        // Append the credentials div after the form
                        apiKeyForm.parentNode.insertBefore(credentialsDiv, apiKeyForm.nextSibling);
                    } else {
                        var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                        console.log(errorMessage);
                    }
                },
                error: function(xhr, status, error) {
                    console.error(error); // Log detailed error information
                },
                complete: function () {
                    spinnerOverlay.classList.remove('show');
                    apiKeyForm.style.display = 'none';
                }
            });
        });
    }
});

// Function to copy text to clipboard using Clipboard API
function smliserCopyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        smliserNotify('Copied to clipboard: ', 3000);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
}

document.addEventListener( 'DOMContentLoaded', function() {
    var revokeBtns = document.querySelectorAll( '.smliser-revoke-btn' );
    var spinnerOverlay = document.querySelector('.spinner-overlay');
    if ( revokeBtns ) {
        revokeBtns.forEach( function( revokeBtn ){
            var apiKeyId       = revokeBtn.dataset.keyId;

            revokeBtn.addEventListener( 'click', function( event ) {
             
                const userConfirmed = confirm( 'You are about to revoke this license, connected app will not be able to access resource on this server, be careful action cannot be reversed' );
                if ( userConfirmed ) {
                    spinnerOverlay.classList.add('show');
    
                    jQuery.ajax( {
                        type: 'GET',
                        url: smliser_var.smliser_ajax_url,
                        data: { 
                            action: 'smliser_revoke_key',
                            security: smliser_var.nonce,
                            api_key_id: apiKeyId,
    
                        },
                        success: function( response ) {
                            if ( response.success ) {
                                var feedback = response.data ? response.data.message : '';
                                smliserNotify(feedback, 3000);
                                location.reload();
                            } else {
                                var errorMessage = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                                console.log(errorMessage);
                                smliserNotify(errorMessage, 3000);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error(error); // Log detailed error information
                        },
                        complete: function () {
                            spinnerOverlay.classList.remove('show');
                        }
                    })
                }
            });
        } );
    }
});

document.addEventListener( 'DOMContentLoaded', function() {
    var tokenBtn    = document.getElementById( 'smliserDownloadTokenBtn' );
    
    
    if ( tokenBtn ) {
        let spinnerDiv          = document.querySelector( '.smliser-loader-container' );
        let licenseKey          = tokenBtn.dataset.licenseKey;
        let itemId              = tokenBtn.dataset.itemId;
        let contentContainner   = document.getElementById( 'ajaxContentContainer' );
        
        tokenBtn.addEventListener( 'click', function(event) {
            spinnerDiv.style.display = "block";

            jQuery.ajax( {
                type : 'GET',
                url: smliser_var.smliser_ajax_url,
                data: {
                    action: 'smliser_token_gen_form',
                    security: smliser_var.nonce,
                    license_key: licenseKey,
                    item_id: itemId,
                },
                success: function( response ) {
                    
                    jQuery( contentContainner ).html( response );
                    var removeBtn  = document.getElementById( 'remove' );
                    var theForm = document.getElementById('smliser-token-body');
                    var expiry  = document.getElementById( 'expiryDate' );
                    var itemID   = document.getElementById( 'popup-item-id' );
                    var licenseK = document.getElementById( 'pop-up-license-key' );
                    var submitBtn = document.getElementById( 'createToken' );
                    submitBtn.addEventListener( 'click', function() {
                        
                        smilerClientToken(itemID.value, licenseK.value, expiry.value)
                            .then(function(downloadToken) {
                                if ( downloadToken ) {
                                    // Create a div to display the credentials
                                    var credentialsDiv = document.createElement('div');
                                    credentialsDiv.classList.add( 'smliser-token-body' );
                                    credentialsDiv.classList.add( 'added' );
                                    let htmlContent = '<p><strong>New token:</strong> ' + smliserWrapText( downloadToken, 16 ) + ' <span onclick="smliserCopyToClipboard(\'' + downloadToken + '\')" class="dashicons dashicons-admin-page"></span></p>';
                                    htmlContent += '<p><strong>Note:</strong> This is the last time this token will be revealed. Please copy and save it securely.</p>';
                                    credentialsDiv.innerHTML = htmlContent;

                                    // Append the credentials div after the form
                                    jQuery( '#smliserNewToken' ).html( credentialsDiv );
                                    document.getElementById( 'formBody' ).remove();
                                    submitBtn.remove();
                                }
                                
                            })
                            
                            .catch(function(error) {
                                console.error('Error generating token:', error);
                                smliserNotify( error );
                            });
                            
                    });

                    removeBtn.addEventListener( 'click', function(){
                        theForm.remove();
                        contentContainner.style.display = "none";
                    });

                    //startCountdown( 30, timerId );

                },
                error: function(xhr, status, error) {
                    console.error(error);
                },
                complete: function() {
                    spinnerDiv.style.display = "none";
                    contentContainner.style.display = "";

                }
            });
            
        });

    }
});

function smilerClientToken(itemid, licensekey, expiry) {
    return new Promise((resolve, reject) => {
        let spinner = document.getElementById('spinner');

        // Force the display of the spinner with !important
        spinner.style.setProperty('display', 'block', 'important');
        spinner.style.setProperty('border', '5px dotted #000', 'important');

        jQuery.ajax({
            type: "POST",
            url: smliser_var.smliser_ajax_url,
            data: {
                action: 'smliser_generate_item_token',
                security: smliser_var.nonce,
                license_key: licensekey,
                item_id: itemid,
                expiry: expiry,
            },
            success: function(response) {
                if (response.success) {
                    resolve(response.data.token);  // Resolve the promise with the token
                } else {
                    reject('Error! ' + response.data.message);  // Reject the promise with an error message
                }
            },
            error: function(xhr, status, error) {
                reject('Could not generate token, please refresh the current page. Error: ' + error);  // Reject the promise with an error message
                console.log(error);
            },
            complete: function() {
                spinner.style.display = "none";
            }
        });
    });
}


function smliserWrapText(text, maxLength) {
    let wrappedText = '';
    let words = text.split(' ');

    let line = '';
    for (let i = 0; i < words.length; i++) {
        if ((line + words[i]).length > maxLength) {
            wrappedText += line.trim() + '\n';
            line = '';
        }
        line += words[i] + ' ';
    }

    // Add the last line to the wrapped text
    wrappedText += line.trim();
    return wrappedText;
}