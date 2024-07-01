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
                    visibleLicenseKeyDiv.style.display          = 'block';
                    partiallyHiddenLicenseKeyDiv.style.display  = 'none';
                } else {
                    visibleLicenseKeyDiv.style.display          = 'none';
                    partiallyHiddenLicenseKeyDiv.style.display  = 'block';
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



function smliserNotify(message, duration) {
    // Create a div element for the notification
    const notification = document.createElement('div');
    notification.classList.add('notification');
    
    // Set the notification message
    notification.innerHTML = `
        <div class="notification-content">
            <span class="close-btn" onclick="this.parentElement.parentElement.remove()">&times;</span>
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
    const fileInput = document.getElementById('smliser-plugin-file');
    const fileSizeDisplay = document.querySelector('.smliser-file-size');
    const deleteBtn = document.getElementById( 'smliser-license-delete-button' );
    
    if ( deleteBtn ) {
        deleteBtn.addEventListener( 'click', function( event ) {
            const userConfirmed = confirm( 'You are about to delete this license, be careful action cannot be reversed' );
            if ( ! userConfirmed ) {
                event.preventDefault();
            }
        });
    }

    if ( fileInput ) {
        fileInput.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const fileSizeMB = (file.size / 1024 / 1024).toFixed(2); // Convert size to MB and round to 2 decimal places
                fileSizeDisplay.textContent = `File size: ${fileSizeMB} MB`;
            } else {
                fileSizeDisplay.textContent = '';
            }
        });
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
    var revokeBtn = document.getElementById( 'smliser-revoke-btn' );
    if ( revokeBtn ) {
        var spinnerOverlay = document.querySelector('.spinner-overlay');
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
    }
});