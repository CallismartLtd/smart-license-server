/**
 * Add a loading spinner to a given element
 * 
 * @param {String|HTMLElement} selector - The element selector.
 * @param {Boolean} larger - Whether the spinner should be bigger?
 * @return {HTMLImageElement}
 */
function showSpinner( selector, larger = false ) {
    const element = ( selector instanceof HTMLElement ) ? selector : document.querySelector( selector );

    if ( ! element ) { console.warn( 'Spinner element not found' ); return };

    const image     = document.createElement( 'img' );
    const gifUrl    = larger ? smliser_var.wp_spinner_gif_2x : smliser_var.wp_spinner_gif;

    image.src       = gifUrl;
    image.id        = 'smliser-spinner-image';

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

/**
 * Utility: Fetch wrapper (consistent error parsing)
 * 
 * @param {string} url - The URL to fetch
 * @param {Object} options - Fetch options
 * @param {string} options.responseType - Expected response type: 'json' (default), 'text', 'html', 'blob'
 * @returns {Promise<Object|string|Blob>} Parsed response based on type
 * @throws {Object} Error object with message and optional field
 */
async function smliserFetch( url, options = { responseType: 'json' } ) {
    // Extract custom option
    const responseType = options.responseType || 'json';
    delete options.responseType; // Remove before passing to fetch
    
    try {
        const response = await fetch( url, options );
        const contentType = response.headers.get( 'content-type' ) || '';
        
        // Handle successful responses
        if ( response.ok ) {
            // Return based on requested response type
            switch ( responseType ) {
                case 'json':
                    if ( contentType.includes( 'application/json' ) ) {
                        return await response.json();
                    }
                    // Fallback: try to parse as JSON anyway
                    try {
                        return await response.json();
                    } catch {
                        return { success: true };
                    }
                
                case 'text':
                case 'html':
                    return await response.text();
                
                case 'blob':
                    return await response.blob();
                
                case 'arraybuffer':
                    return await response.arrayBuffer();
                
                case 'formdata':
                    return await response.formData();
                
                default:
                    // Auto-detect based on content-type
                    if ( contentType.includes( 'application/json' ) ) {
                        return await response.json();
                    } else if ( contentType.includes( 'text/html' ) ) {
                        return await response.text();
                    } else if ( contentType.includes( 'text/' ) ) {
                        return await response.text();
                    } else {
                        return await response.blob();
                    }
            }
        }
        
        // Handle error responses
        let errorMessage = 'An error occurred';
        let errorField = null;
        let errorCode = null;
        
        if ( contentType.includes( 'application/json' ) ) {
            try {
                const errorData = await response.json();
                errorMessage = errorData.data?.message 
                            || errorData.message 
                            || errorData.error 
                            || errorMessage;
                errorField = errorData.data?.field_id || errorData.field || null;
                errorCode = errorData.code || errorData.data?.code || null;
            } catch ( parseError ) {
                console.error( 'Failed to parse error JSON:', parseError );
            }
        } else if ( contentType.includes( 'text/html' ) ) {
            // Handle HTML error pages (e.g., 404, 500 pages)
            try {
                const htmlText = await response.text();
                
                // Try to extract meaningful error from HTML
                const parser = new DOMParser();
                const doc = parser.parseFromString( htmlText, 'text/html' );
                
                // Look for common error message containers
                const errorElement = doc.querySelector( '.error-message, .wp-die-message, h1, title' );
                if ( errorElement ) {
                    errorMessage = errorElement.textContent.trim() || errorMessage;
                }
                
                // Fallback to status text
                if ( errorMessage === 'An error occurred' ) {
                    errorMessage = `${response.status}: ${response.statusText}`;
                }
            } catch ( htmlError ) {
                errorMessage = `HTTP Error ${response.status}: ${response.statusText}`;
            }
        } else {
            // Non-JSON, non-HTML error response
            try {
                const text = await response.text();
                errorMessage = text || `HTTP Error ${response.status}: ${response.statusText}`;
            } catch ( textError ) {
                errorMessage = `HTTP Error ${response.status}: ${response.statusText}`;
            }
        }
        
        // Throw structured error object.
        throw {
            message: errorMessage,
            field: errorField,
            code: errorCode,
            status: response.status,
            statusText: response.statusText,
            contentType: contentType
        };
        
    } catch ( error ) {
        if ( error.message && typeof error.status !== 'undefined' ) {
            // Already structured error from above.
            throw error;
        }        
        
        let category        = 'request_failed';
        let customMessage   = error.message || 'An unexpected error occurred';

        if ( error instanceof TypeError ) {
            // The request never reached the server.
            if ( ! navigator.onLine ) {
                category = 'offline';
                customMessage = 'You appear to be offline. Please check your connection.';
            } else {
                // If online but TypeError occurs, it's almost always DNS or CORS.
                category = 'network_error';
                customMessage = 'Server unreachable (DNS or Connection Refused).';
            }
        } else if ( error.name === 'AbortError' ) {
            category = 'timeout';
            customMessage = 'The request timed out.';
        }

        throw {
            message: customMessage,
            field: null,
            code: category,
            status: 0,
            statusText: 'Network Error',
            originalError: error // Useful for debugging
        };
    }
}

/**
 * Helper: Fetch and expect JSON
 */
async function smliserFetchJSON( url, options = {} ) {
    return await smliserFetch( url, { ...options, responseType: 'json' } );
}

/**
 * Helper: Fetch and expect HTML
 */
async function smliserFetchHTML( url, options = {} ) {
    return await smliserFetch( url, { ...options, responseType: 'html' } );
}

/**
 * Helper: Fetch and expect text
 */
async function smliserFetchText( url, options = {} ) {
    return await smliserFetch( url, { ...options, responseType: 'text' } );
}

/**
 * Helper: Fetch and expect blob (for files/images)
 */
async function smliserFetchBlob( url, options = {} ) {
    return await smliserFetch( url, { ...options, responseType: 'blob' } );
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
        const copiedText    = text.length > 0 && text.length < 50 ? `: ${text}` : '';
        smliserNotify( `Copied to clipboard ${copiedText}`, 3000);
    }).catch( (err) => {
        console.error('Could not copy text', err);
    });
}

/**
 * StringUtils - Comprehensive string manipulation utilities
 * @version 2.0.0
 */
const StringUtils = {
    /**
     * Capitalize the first character of a string.
     * @param {string} str - Input string
     * @returns {string} String with first character capitalized
     */
    ucfirst: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str.charAt( 0 ).toUpperCase() + str.slice( 1 );
    },

    /**
     * Capitalize the first character of each word in a string.
     * @param {string} str - Input string
     * @returns {string} String with each word capitalized
     */
    ucwords: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str
            .toLowerCase()
            .split( ' ' )
            .map( word => word.charAt( 0 ).toUpperCase() + word.slice( 1 ) )
            .join( ' ' );
    },

    /**
     * Convert string to camelCase
     * @param {string} str - Input string
     * @returns {string} camelCase string
     */
    camelCase: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str
            .toLowerCase()
            .replace( /[^a-zA-Z0-9]+(.)/g, ( match, chr ) => chr.toUpperCase() );
    },

    /**
     * Convert string to snake_case
     * @param {string} str - Input string
     * @returns {string} snake_case string
     */
    snakeCase: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str
            .replace( /([A-Z])/g, '_$1' )
            .toLowerCase()
            .replace( /[^a-z0-9]+/g, '_' )
            .replace( /^_|_$/g, '' );
    },

    /**
     * Convert string to kebab-case
     * @param {string} str - Input string
     * @returns {string} kebab-case string
     */
    kebabCase: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str
            .replace( /([A-Z])/g, '-$1' )
            .toLowerCase()
            .replace( /[^a-z0-9]+/g, '-' )
            .replace( /^-|-$/g, '' );
    },

    /**
     * Decodes HTML entities
     * @param {string} html - The HTML string to decode 
     * @returns {string} Decoded HTML
     */
    decodeEntity: function ( html ) {
        if ( ! html || typeof html !== 'string' ) return '';
        const textarea = document.createElement( 'textarea' );
        textarea.innerHTML = html;
        return textarea.value;
    },

    /**
     * Escapes HTML special characters
     * @param {string} string - The string to escape
     * @returns {string} Safe HTML for output
     */
    escHtml: function ( string ) {
        if ( ! string || typeof string !== 'string' ) return '';
        const div = document.createElement( 'div' );
        div.textContent = string;
        return div.innerHTML.replace( /"/g, '&quot;' );
    },

    /**
     * Strip HTML tags from string
     * @param {string} html - HTML string
     * @returns {string} Plain text
     */
    stripTags: function ( html ) {
        if ( ! html || typeof html !== 'string' ) return '';
        const div = document.createElement( 'div' );
        div.innerHTML = html;
        return div.textContent || div.innerText || '';
    },

    /**
     * Truncate string to specified length
     * @param {string} str - Input string
     * @param {number} length - Max length
     * @param {string} suffix - Suffix to add (default: '...')
     * @returns {string} Truncated string
     */
    truncate: function ( str, length, suffix = '...' ) {
        if ( ! str || typeof str !== 'string' ) return '';
        if ( str.length <= length ) return str;
        return str.substring( 0, length - suffix.length ) + suffix;
    },

    /**
     * Truncate string at word boundary
     * @param {string} str - Input string
     * @param {number} length - Max length
     * @param {string} suffix - Suffix to add (default: '...')
     * @returns {string} Truncated string
     */
    truncateWords: function ( str, length, suffix = '...' ) {
        if ( ! str || typeof str !== 'string' ) return '';
        if ( str.length <= length ) return str;
        
        const truncated = str.substring( 0, length - suffix.length );
        const lastSpace = truncated.lastIndexOf( ' ' );
        
        if ( lastSpace > 0 ) {
            return truncated.substring( 0, lastSpace ) + suffix;
        }
        
        return truncated + suffix;
    },

    /**
     * Format a number as currency.
     * @param {string} amount - Amount to format
     * @param {Object} config - Currency configuration
     * @returns {string} Formatted currency string
     */
    formatCurrency: function ( amount, config = {} ) {
        const {
            symbol = '$',
            symbol_position = 'left',
            decimals = 2,
            decimal_separator = '.',
            thousand_separator = ','
        } = config;

        // Handle non-numeric values
        const numAmount = parseFloat( amount );
        if ( isNaN( numAmount ) ) return symbol + '0.00';

        const isNegative = numAmount < 0;
        const absAmount = Math.abs( numAmount );

        // Format the number
        let formatted = absAmount
            .toFixed( decimals )
            .replace( /\B(?=(\d{3})+(?!\d))/g, thousand_separator )
            .replace( '.', decimal_separator );

        // Decode currency symbol (in case it's encoded)
        const decodedSymbol = this.decodeEntity( symbol );

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
    },

    /**
     * Format bytes to human-readable size
     * @param {number} bytes - Size in bytes
     * @param {number} decimals - Decimal places (default: 2)
     * @returns {string} Formatted size string
     */
    formatBytes: function ( bytes, decimals = 2 ) {
        if ( bytes === 0 ) return '0 Bytes';
        
        const k = 1024;
        const dm = decimals < 0 ? 0 : decimals;
        const sizes = [ 'Bytes', 'KB', 'MB', 'GB', 'TB', 'PB' ];
        const i = Math.floor( Math.log( bytes ) / Math.log( k ) );
        
        return parseFloat( ( bytes / Math.pow( k, i ) ).toFixed( dm ) ) + ' ' + sizes[ i ];
    },

    /**
     * Format number with separators
     * @param {string} num - Number to format
     * @param {string} separator - Thousand separator (default: ',')
     * @returns {string} Formatted number
     */
    formatNumber: function ( num, separator = ',' ) {
        const numValue = parseFloat( num );
        if ( isNaN( numValue ) ) return '0';
        return numValue.toString().replace( /\B(?=(\d{3})+(?!\d))/g, separator );
    },

    /**
     * Generates a cryptographically strong random password.
     * @param {number} length - Length of the password (default: 16)
     * @param {Object} options - Character set options
     * @returns {string} Generated password
     */
    generatePassword: function ( length = 16, options = {} ) {
        const {
            includeUpper = true,
            includeNumbers = true,
            includeSymbols = true,
            excludeAmbiguous = false // Exclude similar-looking characters
        } = options;

        let lowercase = 'abcdefghijklmnopqrstuvwxyz';
        let uppercase = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        let numbers = '0123456789';
        let symbols = '!@#$%^&*()_+~`|}{[]:;?><,./-=';

        // Exclude ambiguous characters if requested
        if ( excludeAmbiguous ) {
            lowercase = lowercase.replace( /[ilo]/g, '' );
            uppercase = uppercase.replace( /[IO]/g, '' );
            numbers = numbers.replace( /[01]/g, '' );
            symbols = symbols.replace( /[|`]/g, '' );
        }

        let charset = lowercase;
        if ( includeUpper ) charset += uppercase;
        if ( includeNumbers ) charset += numbers;
        if ( includeSymbols ) charset += symbols;

        if ( ! charset ) charset = lowercase; // Fallback

        let password = '';
        const array = new Uint32Array( length );
        
        // Use crypto API if available, fallback to Math.random
        if ( window.crypto && window.crypto.getRandomValues ) {
            window.crypto.getRandomValues( array );
            for ( let i = 0; i < length; i++ ) {
                password += charset.charAt( array[ i ] % charset.length );
            }
        } else {
            // Fallback for older browsers
            for ( let i = 0; i < length; i++ ) {
                password += charset.charAt( Math.floor( Math.random() * charset.length ) );
            }
        }

        return password;
    },

    /**
     * Generate a random slug-safe string
     * @param {number} length - Length of slug (default: 8)
     * @returns {string} Random slug
     */
    generateSlug: function ( length = 8 ) {
        const charset = 'abcdefghijklmnopqrstuvwxyz0123456789';
        let slug = '';
        
        for ( let i = 0; i < length; i++ ) {
            slug += charset.charAt( Math.floor( Math.random() * charset.length ) );
        }
        
        return slug;
    },

    /**
     * Convert string to URL-friendly slug
     * @param {string} str - Input string
     * @returns {string} URL slug
     */
    slugify: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        
        return str
            .toLowerCase()
            .trim()
            .replace( /[^\w\s-]/g, '' ) // Remove non-word chars
            .replace( /[\s_-]+/g, '-' ) // Replace spaces/underscores with dash
            .replace( /^-+|-+$/g, '' ); // Remove leading/trailing dashes
    },

    /**
     * Parse JSON safely
     * @param {string} string - JSON string
     * @param {*} defaultValue - Default value if parsing fails (default: {})
     * @returns {*} Parsed JSON or default value
     */
    JSONparse: function ( string, defaultValue = {} ) {
        if ( ! this.isJSON(string) ) return defaultValue;
        
        try {
            return JSON.parse( string );
        } catch ( error ) {
            console.warn( 'JSON parse error:', error.message );
            return defaultValue;
        }
    },

    /**
     * Stringify JSON safely
     * @param {*} value - Value to stringify
     * @param {number} space - Indentation spaces (default: 0)
     * @returns {string} JSON string or empty string on error
     */
    JSONstringify: function ( value, space = 0 ) {
        try {
            return JSON.stringify( value, null, space );
        } catch ( error ) {
            console.warn( 'JSON stringify error:', error.message );
            return '';
        }
    },

    /**
     * Check if string is valid JSON
     * @param {string} string - String to test
     * @returns {boolean} True if valid JSON
     */
    isJSON: function ( string ) {
        if ( ! string || typeof string !== 'string' ) return false;
        
        try {
            JSON.parse( string );
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Check if string is valid email
     * @param {string} email - Email to validate
     * @returns {boolean} True if valid email
     */
    isEmail: function ( email ) {
        if ( ! email || typeof email !== 'string' ) return false;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test( email );
    },

    /**
     * Check if string is valid URL
     * @param {string} url - URL to validate
     * @returns {boolean} True if valid URL
     */
    isURL: function ( url ) {
        if ( ! url || typeof url !== 'string' ) return false;
        
        try {
            new URL( url );
            return true;
        } catch {
            return false;
        }
    },

    /**
     * Extract all URLs from text
     * @param {string} text - Text to search
     * @returns {Array<string>} Array of URLs
     */
    extractURLs: function ( text ) {
        if ( ! text || typeof text !== 'string' ) return [];
        const urlRegex = /(https?:\/\/[^\s]+)/g;
        return text.match( urlRegex ) || [];
    },

    /**
     * Replace placeholders in template string
     * @param {string} template - Template string with {{placeholders}}
     * @param {Object} data - Data object
     * @returns {string} Processed string
     */
    template: function ( template, data = {} ) {
        if ( ! template || typeof template !== 'string' ) return '';
        
        return template.replace( /\{\{(\w+)\}\}/g, ( match, key ) => {
            return data.hasOwnProperty( key ) ? data[ key ] : match;
        } );
    },

    /**
     * Count words in text
     * @param {string} text - Text to count
     * @returns {number} Word count
     */
    wordCount: function ( text ) {
        if ( ! text || typeof text !== 'string' ) return 0;
        return text.trim().split( /\s+/ ).filter( word => word.length > 0 ).length;
    },

    /**
     * Calculate reading time
     * @param {string} text - Text to analyze
     * @param {number} wordsPerMinute - Average reading speed (default: 200)
     * @returns {number} Reading time in minutes
     */
    readingTime: function ( text, wordsPerMinute = 200 ) {
        const words = this.wordCount( text );
        return Math.ceil( words / wordsPerMinute );
    },

    /**
     * Reverse a string
     * @param {string} str - String to reverse
     * @returns {string} Reversed string
     */
    reverse: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str.split( '' ).reverse().join( '' );
    },

    /**
     * Check if string is palindrome
     * @param {string} str - String to check
     * @returns {boolean} True if palindrome
     */
    isPalindrome: function ( str ) {
        if ( ! str || typeof str !== 'string' ) return false;
        const cleaned = str.toLowerCase().replace( /[^a-z0-9]/g, '' );
        return cleaned === cleaned.split( '' ).reverse().join( '' );
    },

    /**
     * Repeat string n times
     * @param {string} str - String to repeat
     * @param {number} times - Number of repetitions
     * @returns {string} Repeated string
     */
    repeat: function ( str, times ) {
        if ( ! str || typeof str !== 'string' ) return '';
        return str.repeat( times );
    },

    /**
     * Pad string to specified length
     * @param {string} str - String to pad
     * @param {number} length - Target length
     * @param {string} char - Padding character (default: ' ')
     * @param {string} position - 'left', 'right', or 'both' (default: 'right')
     * @returns {string} Padded string
     */
    pad: function ( str, length, char = ' ', position = 'right' ) {
        if ( ! str || typeof str !== 'string' ) str = '';
        
        const padLength = length - str.length;
        if ( padLength <= 0 ) return str;
        
        const padding = char.repeat( padLength );
        
        switch ( position ) {
            case 'left':
                return padding + str;
            case 'both':
                const leftPad = Math.floor( padLength / 2 );
                const rightPad = padLength - leftPad;
                return char.repeat( leftPad ) + str + char.repeat( rightPad );
            case 'right':
            default:
                return str + padding;
        }
    }
};

/**
 * App section via select2
 * 
 * @param {Element} selectEl 
 */
function smliserSelect2AppSelect( selectEl ) {
    if ( ! ( selectEl instanceof HTMLElement ) ) {
        console.warn( 'Could not instantiate app selection, invalid html element' );
        return;     
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
        minimumInputLength: 1,
    });    
}

/**
 * Search security entities using select2.
 * 
 * @param {Element} selectEl
 * @param {Object} options
 */
function smliserSearchSecurityEntities( selectEl, options = {} ) {
    if ( ! ( selectEl instanceof HTMLElement ) ) {
        console.warn( 'Could not instantiate app selection, invalid html element' );
        return;     
    }

    const defaults = {
        placeholder: 'Search users or organizations',
        entityType: 'resource_owners', // Only `resource_owners` and `owner_subjects` supported.
        types: []
    };

    options = { ...defaults, ...options };

    const prepareArgs = ( params ) => {
        return {
            search: params.term,
            types: options.types
        };
    };

    const processResults = ( data ) => {        
        // Group entities by type (individuals and organizations)
        const grouped = {};

        data?.items?.forEach( entity => {
            if ( ! grouped[ entity.type ] ) {
                grouped[ entity.type ] = [];
            }

            grouped[ entity.type ].push({
                id: `${entity.type}:${entity.id}`,
                text: entity?.name ?? entity?.display_name ?? 'No name',
                type: entity.type,
                avatar: entity?.avatar
            });
        });        

        // Convert to Select2’s optgroup structure
        const results = Object.keys( grouped ).map( type => ({
            text: StringUtils.ucwords(type),
            children: grouped[ type ]
        }));
        
        return { results };
    };

    const $select2  = jQuery( selectEl );
    const url       = new URL( smliser_var.ajaxURL );

    url.searchParams.set( 'action', 'smliser_admin_security_entity_search' );
    url.searchParams.set( 'security', smliser_var.nonce );
    url.searchParams.set( 'entity_type', options.entityType );

    $select2.select2({
        placeholder: options.placeholder,
        ajax: {
            url: url,
            dataType: 'json',
            delay: 500,
            data: prepareArgs,
            processResults: processResults,
            cache: true,
        },
        allowClear: true,
        minimumInputLength: 2,
        width: '100%',
        
    });

    const ownerTypeInput    = $select2.closest( 'form' ).find( '#owner_type' );
    const nameInput         = $select2.closest( 'form' ).find( '#name' );
    const avatarOnly        = $select2.closest( 'form' ).find( '.smliser-avatar-upload_image-preview.avatar-only' );
    const defaultValue      = ownerTypeInput.val();
    
    $select2.on( 'select2:select select2:unselect', e => {
        const param         = e.params;
        const data          = param?.data;
        const entityType    = data?.type;

        if ( ownerTypeInput.length ) {
            ownerTypeInput.val( param.data.selected ? entityType : defaultValue );

            if ( ( param.data.selected && nameInput ) && ! nameInput.val() ) {
                nameInput.val( param.data.text );
            }
        }

        if ( avatarOnly && data.avatar ) {
            avatarOnly.attr( 'src', data.avatar );
            avatarOnly.attr( 'title', data.text );
        }        
                    
    }); 

}

document.addEventListener( 'DOMContentLoaded', function() {
    let tokenBtn                = document.getElementById( 'smliserDownloadTokenBtn' );
    let licenseKeyContainers    = document.querySelectorAll( '.smliser-license-obfuscation' );
    let searchInput             = document.getElementById('smliser-search');
    let tooltips                = document.querySelectorAll( '.smliser-form-description, .smliser-tooltip' );
    let deleteBtn               = document.getElementById( 'smliser-license-delete-button' );
    let updateBtn               = document.querySelector('#smliser-update-btn');
    let appActionsBtn           = document.querySelectorAll( '.smliser-app-delete-button, .smliser-app-restore-button' );

    /**@type {HTMLInputElement} Select all checkbox */
    let selectAllCheckbox       = document.querySelector('#smliser-select-all');
    let dashboardPage           = document.querySelector( '.smliser-admin-dashboard-template.overview' );
    let apiKeyForm              = document.getElementById('smliser-api-key-generation-form');
    let revokeBtns              = document.querySelectorAll( '.smliser-revoke-btn' );
    let monetizationUI          = document.querySelector( '.smliser-monetization-ui' );
    let optionForms             = document.querySelectorAll( 'form.smliser-options-form' );
    const bulkMessageForm       = document.querySelector( 'form.smliser-compose-message-container' );
    const licenseAppSelect      = document.querySelector( '.license-app-select' );
    const allCopyEl             = document.querySelectorAll( '.smliser-click-to-copy' );
    const adminNav              = document.querySelector( '.smliser-top-nav' );
    const allLicenseDomain      = document.querySelector( '.smliser-all-license-domains' );
    const queryParam            = new URLSearchParams( window.location.search );
    const roleBuilderEl         = document.querySelector( '#smliser-role-builder' );
    const avatarUploadFields    = document.querySelectorAll( '.smliser-avatar-upload' );
    const generatePasswordBtn   = document.querySelector( '#smliser-generate-password' );

    /** @type {HTMLFormElement} */
    const accessControlForm     = document.querySelector( '.smliser-access-control-form' );
    const ownerSubjectSearch    = document.querySelector( '#subject_id' );
    /** @type {HTMLSelectElement} */
    const usersSearch           = document.querySelector( '#user_id' );
    /** @type {HTMLSelectElement} */
    const ownersSearch          = document.querySelector( '#owner_id' );
    const deleteEntities        = document.querySelectorAll( '.smliser-delete-entity' );

    licenseAppSelect && smliserSelect2AppSelect( licenseAppSelect );
    
    if ( usersSearch ) {
        const options = {
            entityType: 'owner_subjects',
            placeholder: 'Search users...',
            types: ['individual']
        };

        const selectedUser  = usersSearch.value.trim();
        if ( selectedUser.length ) {
            const shadowInput       = document.createElement( 'input' );
            shadowInput.name        = usersSearch.name;
            shadowInput.type        = 'hidden';
            shadowInput.value       = selectedUser;
            const form              = usersSearch.closest( 'form' );
            
            usersSearch.removeAttribute('name');
            usersSearch.disabled    = true;

            form.appendChild( shadowInput );
        }
        smliserSearchSecurityEntities( usersSearch, options );
    }

    if ( ownerSubjectSearch ) {
        const options = {
            entityType: 'owner_subjects',
            placeholder: 'Search for users or organizations...'
        };

        smliserSearchSecurityEntities( ownerSubjectSearch, options );
    }

    if ( ownersSearch ) {
        const options = {
            entityType: 'resource_owners',
            placeholder: 'Search for resource owners...'
        };
        smliserSearchSecurityEntities( ownersSearch, options );
    }

    if ( generatePasswordBtn ) {

        const jsonField = generatePasswordBtn.getAttribute( 'data-fields' );
        let pwdvalues   = StringUtils.JSONparse( jsonField, null );

        if ( ! pwdvalues ) return;

        const [pwd1Selector, pwd2Selector] = pwdvalues;
        /** @type {HTMLInputElement} */
        const pwd1Field = document.querySelector( `#${pwd1Selector}` );
         /** @type {HTMLInputElement} */
        const pwd2Field = document.querySelector( `#${pwd2Selector}` );

        generatePasswordBtn.addEventListener( 'click', e => {
            const btn   = e.target?.closest( '.button' );

            if ( ! btn ) return;
            const password  = StringUtils.generatePassword();

            if ( pwd1Field ) {
                pwd1Field.value = password;
            }

            if ( pwd2Field ) {
                pwd2Field.value = password;
            }
            
        });

        [pwd1Field, pwd2Field].forEach( pwdInput => {
            pwdInput.parentElement.addEventListener( 'click', e => {
                const btn = e.target.closest( '.smliser-password-toggle' );
                
                if ( ! btn ) return;
                
                const targetId      = btn.getAttribute( 'data-target' );
                const passwordField = document.querySelector( `#${targetId}` );
                const showIcon      = btn.querySelector( '.smliser-eye-show' );
                const hideIcon      = btn.querySelector( '.smliser-eye-hide' );
                
                if ( ! passwordField ) return;
                
                if ( passwordField.type === 'password' ) {
                    passwordField.type = 'text';
                    showIcon.style.display = 'none';
                    hideIcon.style.display = 'block';
                    btn.setAttribute( 'aria-label', 'Hide password' );
                } else {
                    passwordField.type = 'password';
                    showIcon.style.display = 'block';
                    hideIcon.style.display = 'none';
                    btn.setAttribute( 'aria-label', 'Show password' );
                }
            });

            const openField = () => {
                pwdInput.disabled   = false
                pwdInput.type       = 'text';
                
                if ( queryParam.has( 'section', 'edit' ) ) {
                    pwdInput.required = false;
                }
                
            }
            setTimeout( openField, 1500);
        })
    }

    if ( adminNav ) {
        document.addEventListener( 'scroll', (e) => {
            if ( window.scrollY > 0 ) {
                adminNav.classList.add( 'is-scrolled' );
            } else {
                adminNav.classList.remove( 'is-scrolled' );
            }
            
        })
    }

    if ( optionForms ) {
        optionForms.forEach( form => {
            form.addEventListener( 'submit', ( e ) => {
                e.preventDefault();
                const submittedForm = e.target;
                if ( ! ( submittedForm instanceof HTMLFormElement ) ) return;
                const payLoad   = new FormData( submittedForm );
                const url       = smliser_var.ajaxURL;
                payLoad.set( 'security', smliser_var.nonce );
                const submitBtn = submittedForm.querySelector( 'button[type="submit"]' );
                
                submitBtn?.setAttribute( 'disabled', 'disabled' );
                const spinner = showSpinner( '.smliser-spinner', true );

                fetch( url, {
                    method: 'POST',
                    body: payLoad,
                    credentials: 'same-origin'
                }).then( async response => {
                    const contentType = response.headers.get( 'content-type' );
                    if ( ! response.ok ) {
                        let errorMessage = 'Something went wrong!';
                        if ( contentType.includes( 'application/json' ) ) {
                            const body      = await response.json();
                            errorMessage    = body?.data?.message ?? errorMessage;
                        } else {
                            errorMessage = await response.text();
                        }

                        throw new Error( errorMessage );
                    }

                    const responseJson = await response.json();

                    if ( ! responseJson.success ) {
                        let errorMessage = responseJson?.data?.message ?? 'An error occurred';

                        throw new Error( errorMessage );
                    }

                    const message = responseJson?.data?.message ?? 'Success';
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
        deleteBtn.addEventListener( 'click', async ( event ) => {
            event.preventDefault();
            const userConfirmed = await SmliserModal.confirm( 'You are about to delete this license, be careful action cannot be reversed' );
            if ( userConfirmed && deleteBtn.href ) {
                window.location.href    = deleteBtn.href;
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
            let url = new URL( smliser_var.ajaxURL );
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
            let updateUrl = new URL( smliser_var.ajaxURL );
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

    if ( appActionsBtn.length ) {
        appActionsBtn.forEach( actionBtn => {
            actionBtn.addEventListener('click', async (e)=>{
                e.preventDefault();
                let requestArgs    = StringUtils.JSONparse( actionBtn.getAttribute( 'data-action-args' ) );
                
                if ( ! requestArgs ) {
                    smliserNotify( 'App data not found', 5000 );
                    return;
                }

                if ( 'trash' === requestArgs.status ) {
                    let message     = `You are about to trash this ${requestArgs.type}, it will be automatically deleted after 60 days. Are you sure you want to proceed?`;
                    let confirmed   = await SmliserModal.confirm( message );
                    
                    if ( ! confirmed ) {
                        return;
                    }                    
                }

                let url = new URL( smliser_var.ajaxURL );
                url.searchParams.set( 'action', 'smliser_app_status_action' );
                url.searchParams.set( 'slug', requestArgs.slug );
                url.searchParams.set( 'type', requestArgs.type );
                url.searchParams.set( 'security', smliser_var.nonce );
                url.searchParams.set( 'status', requestArgs.status );
                
                fetch(url)
                    .then( response=>{
                        if ( ! response.ok ) {
                            smliserNotify(`Error: [${response.status}] ${response.statusText}`, 5000);
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
                            smliserNotify(`Error: ${responseData.data.message}`, 6000 );

                        }
                    });
                
            });
        });
    }

    if ( selectAllCheckbox ) {
        /** @type {NodeListOf<HTMLInputElement>} */
        let checkboxes = document.querySelectorAll('.smliser-license-checkbox, .smliser-checkbox');
        let lastChecked = null; // Track the last checkbox clicked

        selectAllCheckbox.addEventListener('change', function () {
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAllCheckbox.checked;
            });
        });

        // Individual and Shift-Select Logic.
        checkboxes.forEach((checkbox, index) => {
            checkbox.addEventListener('click', function (e) {
                // Check if Shift key is held and there's a previous click
                if (e.shiftKey && lastChecked !== null) {
                    let start = Math.min(index, lastChecked);
                    let end = Math.max(index, lastChecked);

                    // Apply the state of the clicked checkbox to the whole range
                    for (let i = start; i <= end; i++) {
                        checkboxes[i].checked = checkbox.checked;
                    }
                }

                // Update the lastChecked reference
                lastChecked = index;

                // Update the "Select All" master checkbox state
                selectAllCheckbox.checked = Array.from(checkboxes).every(cb => cb.checked);
            });
        });
    }

    if ( dashboardPage ) {
        const colors = {
            blue:   { border: '#3b82f6', bg: 'rgba(59, 130, 246, 0.1)' },
            purple: { border: '#8b5cf6', bg: 'rgba(139, 92, 246, 0.1)' },
            emerald:{ border: '#10b981', bg: 'rgba(16, 185, 129, 0.1)' },
            rose:   { border: '#f43f5e', bg: 'rgba(244, 63, 94, 0.1)' },
            amber:  { border: '#f59e0b', bg: 'rgba(245, 158, 11, 0.1)' }
        };

        Chart.defaults.font.family  = "'Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', Roboto, sans-serif";
        Chart.defaults.font.size    = 12;
        Chart.defaults.color        = '#64748b'; // Slate 500
        Chart.defaults.plugins.tooltip.padding      = 12;
        Chart.defaults.plugins.tooltip.borderRadius = 8;
        Chart.defaults.elements.bar.borderRadius    = 4; // Rounded bars
        Chart.defaults.elements.line.borderWidth    = 3;
        Chart.defaults.elements.point.radius        = 0; // Hide points until hover
        Chart.defaults.elements.point.hoverRadius   = 5;

        const canvases                              = document.querySelectorAll('canvas[data-chart-json]');
        
        canvases.forEach(function(canvas) {
            const chartConfig = StringUtils.JSONparse( canvas.getAttribute( 'data-chart-json' ) );
            
            // Inject smooth line tension if not defined
            if (chartConfig.type === 'line') {
                chartConfig.data.datasets.forEach(ds => {
                    ds.tension = 0.4; // Smooth curves
                    ds.fill = true;  // Modern Area chart look
                });
            }

            new Chart(canvas.getContext('2d'), chartConfig);
        });
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

            smliserFetch( smliser_var.ajaxURL, { method: 'POST', body: payLoad } )
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
                let tier = StringUtils.JSONparse( json );
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

            deleteTier: async ( json ) => {
                let tier = StringUtils.JSONparse( json );
                const confirmed = SmliserModal.confirm( `Are you sure you want to delete tier "${tier.name}"?` );
                if ( confirmed ) {
                    const payLoad = new FormData();
                    payLoad.set( 'action', 'smliser_delete_monetization_tier' );
                    payLoad.set( 'security', smliser_var.nonce );
                    payLoad.set( 'monetization_id', tier.monetization_id );
                    payLoad.set( 'tier_id', tier.id );

                    smliserFetch( smliser_var.ajaxURL, { method: 'POST', body: payLoad } )
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
                let tier = StringUtils.JSONparse( json );

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

                smliserFetch( `${smliser_var.ajaxURL}?${params.toString()}`, { method: 'GET' } )
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

            toggleMonetization: ( monetizationId, enabled ) => {
                const payLoad = new FormData();
                payLoad.set( 'action', 'smliser_toggle_monetization' );
                payLoad.set( 'security', smliser_var.nonce );
                payLoad.set( 'monetization_id', monetizationId );
                payLoad.set( 'enabled', enabled );

                fetch( smliser_var.ajaxURL, {
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
                const response = await fetch( smliser_var.ajaxURL, {
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
                    throw error;
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

                console.log(error);
                

            } finally {
                submitBtn && ( submitBtn.disabled = false );
                removeSpinner( spinner );
            }

        })

    }

    if ( allCopyEl.length ) {
        allCopyEl.forEach( el => {
            el.addEventListener( 'click', e => {
                smliserCopyToClipboard( e.target.getAttribute( 'data-copy-value' ) );
            });
        })
    }

    if ( allLicenseDomain ) {
        allLicenseDomain.addEventListener( 'click', async e => {
            const deleteBtn = e.target.closest( '.remove' );
            const confirmed = await SmliserModal.confirm( 'Are you sure you want to remove this domain?' );

            if ( ! deleteBtn || ! confirmed ) return;

            const domain    = e.target.closest( '[data-domain-value]' )?.getAttribute( 'data-domain-value' );

            if ( ! domain ) {
                smliserNotify( 'Domain value was not found', 5000 );
                return;
            }

            const url   = new URL( smliser_var.ajaxURL );

            url.searchParams.set( 'action', 'smliser_remove_licensed_domain' );
            url.searchParams.set( 'security', smliser_var.nonce );
            url.searchParams.set( 'license_id', queryParam.get( 'license_id' ) );
            url.searchParams.set( 'domain', domain );
            
            try {
                const response = await fetch( url, {credentials: 'same-origin'} );

                const contentType = response.headers.get( 'content-type' );
                if ( ! response.ok ) {
                    let errorMessage = 'Something went wrong!';
                    if ( contentType.includes( 'application/json' ) ) {
                        const body      = await response.json();
                        errorMessage    = body?.data?.message ?? errorMessage;
                    } else {
                        errorMessage = await response.text();
                    }

                    throw new Error( errorMessage );
                }

                const responseJson = await response.json();

                if ( ! responseJson.success ) {
                    let errorMessage = responseJson?.data?.message ?? 'An error occurred';

                    throw new Error( errorMessage );
                }

                const message = responseJson?.data?.message ?? 'Success';
                smliserNotify( message, 5000 );
                const el    = e.target.closest( '[data-domain-value]' );

                jQuery( el ).fadeOut( 'slow', () => {
                    el?.remove();
                });
                
            } catch (error) {
                smliserNotify( error.message, 5000 );
            }

        });
    }

    if ( roleBuilderEl ) {
        const defaultRoles  = smliser_var.default_roles;

        let existingRoles   = StringUtils.JSONparse( roleBuilderEl.getAttribute( 'data-roles' ), null );
        const builder       = new RoleBuilder( roleBuilderEl, defaultRoles, existingRoles );
    
        window.SmliserRoleBuilder = builder;
    }

    if ( accessControlForm ) {
        const orgMembersContainer   = document.querySelector( '.smliser-organization-members-list' );
        const qv                    = new URLSearchParams( queryParam );
        
        accessControlForm.addEventListener( 'submit', async e => {
            e.preventDefault();
            const payLoad   = new FormData( accessControlForm );
            const url       = new URL( smliser_var.ajaxURL );

            if ( typeof window.SmliserRoleBuilder !== 'undefined' ) {
                const roleValues = SmliserRoleBuilder.getValue();

                payLoad.set( 'role_slug', roleValues.roleSlug ?? '' );
                payLoad.set( 'role_label', roleValues.roleLabel );

                roleValues.capabilities.forEach( cap => {
                    payLoad.append( 'capabilities[]', cap );
                });
            }

            payLoad.set( 'security', smliser_var.nonce );
            let spinner = showSpinner( '.smliser-spinner', true );

            try {
                const response    = await smliserFetchJSON( url.href, {
                    method: "POST",
                    body: payLoad,
                    credentials: "same-origin"
                });

                let message   = response?.data?.message ?? response;

                if ( ! response.success ) {
                    const errorMessage  = message;
                    throw new Error( errorMessage );
                }

                message   = message ?? 'Request was successfull, but no response message.';

                smliserNotify( message, 3000 );
                setTimeout( processAfterEntitySave, 3000, response );
            } catch ( error ) {
                smliserNotify( error.message, 10000 );
                
            } finally {
                removeSpinner( spinner );
            }

        });

        if ( orgMembersContainer ) {
            orgMembersContainer.addEventListener( 'click', async e => {
                e.preventDefault();
                const addnewMemberBtn   = e.target.closest( '.smliser-add-member-to-org-btn' );
                const editMemberBtn     = e.target.closest( '.button.edit-member' );
                const deleteMemberBtn   = e.target.closest( '.button.delete-member' );
                const clickedBtn        = addnewMemberBtn ?? editMemberBtn ?? deleteMemberBtn;
                
                qv.set( 'org_id', qv.get( 'id' ) );

                if ( editMemberBtn ) {
                    qv.set( 'section', 'edit-member' );
                    qv.set( 'member_id', editMemberBtn.dataset.memberId );
                }

                if ( addnewMemberBtn ) {
                    qv.set( 'section', 'add-new-member' );
                }

                if ( clickedBtn ) {
                    clickedBtn.disabled = true;

                    if ( clickedBtn === deleteMemberBtn ) {
                        const confirmed = await SmliserModal.confirm(
                            {
                                confirmText: 'Yes',
                                cancelText: 'No',
                                message: 'Are you sure you want to remove the selected member from this organization?',
                                title: 'Confirm Member Removal'
                            }
                        );
                        
                        if ( ! confirmed ) {
                            clickedBtn.disabled = false;
                            return;
                        }
                        
                        const url   = new URL( smliser_var.ajaxURL );

                        url.searchParams.set( 'action', 'smliser_delete_org_member' );
                        url.searchParams.set( 'security', smliser_var.nonce );
                        url.searchParams.set( 'organization_id', qv.get( 'org_id' ) );
                        url.searchParams.set( 'member_id', deleteMemberBtn.dataset.memberId );
                        url.searchParams.set( 'action', 'smliser_delete_org_member' );
                        let spinner = showSpinner( '.smliser-spinner', true );

                        try {
                            const response  = await smliserFetchJSON( url.href );
                            let message     = response?.data.message;

                            if ( response?.success ) {
                                const memberContainer   = clickedBtn.closest( 'li.smliser-org-member' );
                                jQuery( memberContainer ).fadeOut( 'slow', () => {
                                    memberContainer.remove();
                                });

                                smliserNotify( message, 5000 );
                            } else {
                                throw new Error( message );
                            }                         

                        } catch (error) {
                            clickedBtn.disabled = false;
                            smliserNotify( error.message, 10000 );
                        } finally {
                            removeSpinner( spinner );
                        }
                        return;
                    }

                    showSpinner( '.smliser-spinner', true );
                    qv.delete( 'id' );
                    const url   = new URL( window.location );
                    url.search  = qv.toString();
                    window.location.href = url.href;
                }
                
            });
        }

        /**
         * Process the response body ofter a successful submission of the
         * access control form.
         * 
         * @param {Object} responseBody - The HTTP response body.
         */
        const processAfterEntitySave    = ( responseBody ) => {
            const isUsersTab                = qv.has( 'tab', 'users' );
            const isAPITab                  = qv.has( 'tab', 'service-account' );
            const isOwnersTab               = qv.has( 'tab', 'owners' );
            const isOrgTab                  = qv.has( 'tab', 'organizations' );
            const currentUrl                = window.location.href;
            const doRedirectOnAddNewPage    = isUsersTab || isOrgTab || isOwnersTab;
            const responseData              = responseBody.data;
            const redirectUrl               = new URL( currentUrl );
            const entityID                  = responseData?.entity_id ?? 0;

            redirectUrl.searchParams.set( 'section', 'edit' );
            redirectUrl.searchParams.set( 'id', entityID );

            if ( 'add-new' === qv.get( 'section' ) ) {
                
                if ( doRedirectOnAddNewPage ) {
                    window.location.href = redirectUrl.href;
                    return;                 
                }

                if ( isAPITab ) {
                    const apiKeyData = responseData?.api_keys;

                    if ( ! apiKeyData?.api_key ) {
                        console.warn( 'Unable to get API key Data' );
                        return;                        
                    }

                    const modalBody     = document.createElement( 'div' );
                    modalBody.className = 'smliser-api-key-delivery';

                    const warning       = document.createElement( 'div' );
                    warning.className   = 'smliser-api-key-warning';
                    const strong        = document.createElement( 'strong' );
                    strong.textContent  = 'Important: ';
                    warning.append( strong, document.createTextNode( 'Save this key now. For security, we cannot show it to you again.' ) );

                    const label         = document.createElement( 'span' );
                    label.className     = 'smliser-api-key-label';
                    label.textContent   = 'Identifier: ';
                    const code          = document.createElement( 'code' );
                    code.textContent    = apiKeyData.identifier;
                    label.appendChild( code );

                    const keyDisplay        = document.createElement( 'div' );
                    keyDisplay.className    = 'smliser-api-key-display';
                    keyDisplay.textContent  = apiKeyData.api_key;

                    modalBody.append( warning, label, keyDisplay );

                    const footerContainer                   = document.createElement( 'div' );
                    footerContainer.className               = 'smliser-modal-footer-api-actions';
                    footerContainer.style.display           = 'flex';
                    footerContainer.style.justifyContent    = 'space-between';
                    footerContainer.style.alignItems        = 'center';
                    footerContainer.style.width             = '100%';

                    const creationInfo          = document.createElement( 'span' );
                    creationInfo.style.fontSize = '12px';
                    creationInfo.style.color    = '#666';
                    creationInfo.textContent    = `For: ${apiKeyData.display_name}`;

                    const btnGroup              = document.createElement( 'div' );
                    btnGroup.style.display      = 'flex';
                    btnGroup.style.gap          = '10px';

                    const downloadBtn           = document.createElement( 'button' );
                    downloadBtn.className       = 'button';
                    downloadBtn.textContent     = 'Download Key';

                    const copyBtn               = document.createElement( 'button' );
                    copyBtn.className           = 'smliser-copy-btn';
                    copyBtn.textContent         = 'Copy Key';

                    btnGroup.append( downloadBtn, copyBtn );
                    footerContainer.append( creationInfo, btnGroup );

                    const modal = new SmliserModal({
                        title: 'API Key Generated',
                        body: modalBody,
                        footer: footerContainer,
                    });

                    copyBtn.addEventListener( 'click', () => {
                        navigator.clipboard.writeText( apiKeyData.api_key ).then( () => {
                            const originalText = copyBtn.textContent;
                            copyBtn.textContent = 'Copied!';
                            setTimeout( () => { copyBtn.textContent = originalText; }, 2000 );
                        });
                    });

                    downloadBtn.addEventListener( 'click', () => {
                        const timestamp = new Date().toLocaleString();
                        const fileContent = [
                            `Smart License Server - API Key Export`,
                            `------------------------------------`,
                            `Display Name : ${apiKeyData.display_name}`,
                            `Identifier   : ${apiKeyData.identifier}`,
                            `Description  : ${apiKeyData.description || 'N/A'}`,
                            `Created At   : ${timestamp}`,
                            `------------------------------------`,
                            `SECRET API KEY (Keep this safe):`,
                            `${apiKeyData.api_key}`,
                            `------------------------------------`,
                            `Note: This key provides access to your service account. Do not share it.`
                        ].join('\n');

                        const blob = new Blob([fileContent], { type: 'text/plain' });
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        
                        a.href = url;
                        a.download = `${apiKeyData.identifier}_key.txt`;
                        document.body.appendChild(a);
                        a.click();
                        
                        window.URL.revokeObjectURL(url);
                        document.body.removeChild(a);
                    });

                    modal.open().then( () => downloadBtn.focus() );
                    
                    modal.on( 'afterClose', () => {
                        window.location.href = redirectUrl.href;
                    });

                    return;
                }


            }
            
            if ( isOrgTab && 'add-new-member' === qv.get( 'section' ) ) {
                const orgID = qv.get( 'org_id' );

                redirectUrl.searchParams.set( 'section', 'edit' );
                redirectUrl.searchParams.set( 'id', orgID );

                redirectUrl.searchParams.delete( 'org_id', orgID );
                window.location.href = redirectUrl.href;
                return;
            }

            if ( 'edit' === qv.get( 'section' ) ) {
                window.location.reload();
            }
        }
    }

    if ( avatarUploadFields.length ) {
        avatarUploadFields.forEach( avatarUpload => {
            /**
             * @type {HTMLInputElement}
             */
            const fileInput = avatarUpload.querySelector( 'input[type="file"]' );

            avatarUpload.addEventListener( 'dragover', e => {
                e.preventDefault();
                if ( e.dataTransfer.types.includes( 'Files' ) ) {
                    e.dataTransfer.dropEffect = 'copy';
                } else {
                    e.dataTransfer.dropEffect = 'none';
                }
                avatarUpload.style.border = "dashed 3px #dcdcde";
            });

            avatarUpload.addEventListener( 'dragleave', e => {
                e.preventDefault();
                avatarUpload.style.removeProperty( 'border' );
            });

            avatarUpload.addEventListener( 'drop', e => {
                e.preventDefault();
                avatarUpload.style.removeProperty( 'border' );
                if ( e.dataTransfer.types.includes( 'Files' ) ) {
                    const files = e.dataTransfer.files;
                    const dataTransfer = new DataTransfer();
                    dataTransfer.items.add( files[0] );
                    fileInput.files = dataTransfer.files;
                    fileInput.dispatchEvent( new Event( 'change' ) );
                }

            });

            /**
             * @type {HTMLImageElement}
             */
            const imagePreview          = avatarUpload.querySelector( '.smliser-avatar-upload_image-preview' );
            const imageHolder           = avatarUpload.querySelector( '.smliser-avatar-upload_image-holder' );
            const originalSrc           = imagePreview.src;
            const originalImageTitle    = imagePreview.title;
            const imageNamePreview      = avatarUpload.querySelector( '.smliser-avatar-upload_data-filename' );
            const defaultFilename       = imageNamePreview?.textContent;

            const buttonsRow            = avatarUpload.querySelector( '.smliser-avatar-upload_buttons-row' );

            imagePreview?.setAttribute( 'draggable', false );
            const imageFullScreenMode = () => {
                if ( ! imageHolder.requestFullscreen ) {
                    smliserNotify( 'Fullscreen not supported by your browser.', 3000 );
                    return;
                }

                imageHolder.requestFullscreen().catch( err => {
                    smliserNotify( `Error attempting to enable fullscreen: ${err.message}`, 3000 );
                });
            }

            const clearImagePreview = () => {
                fileInput.value                 = '';
                imagePreview.src                = originalSrc;
                imageNamePreview.textContent    = defaultFilename;
                imagePreview.title              = originalImageTitle;

                buttonsRow.querySelector( '.clear' )?.classList.add( 'hidden' );
                buttonsRow.querySelector( '.add-file' )?.classList.remove( 'hidden' );
            }

            imageNamePreview?.addEventListener( 'click', imageFullScreenMode );
            imagePreview?.addEventListener( 'dblclick', imageFullScreenMode );

            buttonsRow?.addEventListener( 'click', e => {
                const btn = e.target.closest( '.button' );

                if ( ! btn ) return;
                
                if ( btn.classList.contains( 'clear' ) ) {
                    clearImagePreview();
                    return;
                }

                if ( btn.classList.contains( 'add-file' ) ) {
                    fileInput.click();
                }                
            });

            fileInput?.addEventListener( 'change', e => {
                const target    = e.target;
                if ( target.type !== 'file' ) return;

                /**
                 * @type {File}
                 */
                const image         = target.files[0];
                
                if ( ! image || ! image.type.includes( 'image/' ) ) {
                    clearImagePreview();
                    smliserNotify( 'Please upload an image.', 3000 );
                    return;
                }

                const maxSize = 2 * 1024 * 1024;

                if ( image.size > maxSize ) {
                    smliserNotify( 'File is too large. Maximum size is 2MB.', 3000 );
                    fileInput.value = ''; // Reset the input
                    return;
                }
                
                if ( imagePreview.src.startsWith('blob:') ) {
                    URL.revokeObjectURL(imagePreview.src);
                }

                const objectUrl = URL.createObjectURL( image );
                imagePreview.src = objectUrl;
                imagePreview.title = image.name;
                imageNamePreview.textContent = image.name;
                buttonsRow.querySelector( '.clear' )?.classList.remove( 'hidden' );
                
            });
        });
    }

    if ( deleteEntities.length ) {
        /**
         * Render empty table state.
         * @param {HTMLTableElement} table HTML table
         */
        const renderEmptyTableState = ( table ) => {
            if ( ! table ) return;
            
            // Find how many columns the table has to span the message across all of them
            const colCount          = table.querySelector( 'thead tr' )?.cells.length || 1;
            const notFoundMessage   = `No ${queryParam.get( 'tab' ).replace( '-', ' ' )} found`;
            
            const emptyRow = `
                <tr class="no-results">
                    <td colspan="${colCount}" style="text-align: center;
                        padding: 20px; background-color: #ffffff">
                        ${notFoundMessage}
                    </td>
                </tr>`;
            table.querySelector( 'thead' )?.classList.add( 'hidden' );
            table.tBodies[0].innerHTML = emptyRow;
        }
        deleteEntities.forEach( deleteBtn => {
            deleteBtn.addEventListener( 'click', async e => {
                e.preventDefault();
                
                const args  = StringUtils.JSONparse( deleteBtn.dataset.args, null );
                if ( ! args ) return;

                deleteBtn.blur();
                deleteBtn.style.pointerEvents   = 'none'; 
                const confirmed                 = await SmliserModal.confirm( 'Are you sure to delete' );
                
                if ( ! confirmed ) {
                    deleteBtn.style.pointerEvents = 'auto';
                    deleteBtn.style.opacity = '1';
                    return;
                };

                const url   = new URL( smliser_var.ajaxURL );
                url.searchParams.set( 'action', 'smliser_access_control_delete' );
                url.searchParams.set( 'security', smliser_var.nonce );

                Object.entries( args ).forEach( ( [key, value] ) => {
                    url.searchParams.set( key, value );
                });
                
                try {
                    const result    = await smliserFetchJSON( url.href,{
                        method: 'DELETE',
                        credentials: 'same-origin',
                        headers: { 'X-HTTP-Method-Override': 'DELETE' }
                    });

                    if ( result.success ) {
                        await SmliserModal.success( result.data.message || 'Deleted' );

                        const tableRow  = deleteBtn.closest( 'tr' );
                        const table     = tableRow?.closest( 'table' );

                        jQuery( tableRow ).fadeOut( 'slow', () => {
                            tableRow.remove();

                            // Check the row count AFTER removal
                            const bodyRowsCount = table?.tBodies[0]?.rows.length || 0;

                            if ( bodyRowsCount === 0 ) {
                                renderEmptyTableState( table );
                            }

                            console.log(bodyRowsCount);
                            
                        });
                    }

                } catch ( error ) {
                    await SmliserModal.error( error.message, error.statusText );
                } finally {
                    deleteBtn?.style.removeProperty( 'pointer-events' )
                }

            });
        });
    }

});