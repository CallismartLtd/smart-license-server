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
    var tooltips = document.querySelectorAll('.smliser-form-description');

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
