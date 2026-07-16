/**
 * StringUtils - Comprehensive string manipulation utilities
 *
 * A dependency-free JS string toolkit, deliberately mirroring the shape and
 * naming of familiar PHP string functions (ucfirst, str_pad, wordwrap, etc.)
 * so PHP developers feel at home reaching for it on the frontend.
 *
 * @version 3.0.0
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		// CommonJS (Node, bundlers).
		module.exports = factory();
	} else if ( typeof define === 'function' && define.amd ) {
		// AMD.
		define( [], factory );
	} else {
		// Browser global.
		root.StringUtils = factory();
	}
}( typeof self !== 'undefined' ? self : this, function () {
'use strict';

const StringUtils = {

	/* ---------------------------------------------------------------------
	 * Case conversion
	 * ------------------------------------------------------------------- */

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
	 * Lowercase the first character of a string. Mirrors PHP's lcfirst().
	 * @param {string} str - Input string
	 * @returns {string} String with first character lowercased
	 */
	lcfirst: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.charAt( 0 ).toLowerCase() + str.slice( 1 );
	},

	/**
	 * Capitalize the first character of each word in a string.
	 * @param {string} str - Input string
	 * @param {string} delimiters - Characters that separate words (default: ' ')
	 * @returns {string} String with each word capitalized
	 */
	ucwords: function ( str, delimiters = ' ' ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const delimSet = new Set( delimiters.split( '' ) );
		let result = '';
		let capitalizeNext = true;

		for ( const char of str ) {
			if ( capitalizeNext && /[a-z]/i.test( char ) ) {
				result += char.toUpperCase();
				capitalizeNext = false;
			} else {
				result += char;
			}

			if ( delimSet.has( char ) ) {
				capitalizeNext = true;
			}
		}

		return result;
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
	 * Convert string to PascalCase / StudlyCase (e.g. Laravel's Str::studly()).
	 * @param {string} str - Input string
	 * @returns {string} PascalCase string
	 */
	pascalCase: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return this.ucfirst( this.camelCase( str ) );
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
	 * Convert string to CONSTANT_CASE (a.k.a. SCREAMING_SNAKE_CASE).
	 * @param {string} str - Input string
	 * @returns {string} CONSTANT_CASE string
	 */
	constantCase: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return this.snakeCase( str ).toUpperCase();
	},

	/**
	 * Convert string to Title Case, keeping common minor words lowercase
	 * unless they are the first or last word (AP-style heuristic).
	 * @param {string} str - Input string
	 * @returns {string} Title Case string
	 */
	titleCase: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const minorWords = new Set( [
			'a', 'an', 'and', 'as', 'at', 'but', 'by', 'for', 'if', 'in',
			'nor', 'of', 'on', 'or', 'so', 'the', 'to', 'up', 'yet',
		] );

		const words = str.toLowerCase().split( ' ' );

		return words
			.map( ( word, index ) => {
				const isEdge = index === 0 || index === words.length - 1;
				if ( ! isEdge && minorWords.has( word ) ) {
					return word;
				}
				return this.ucfirst( word );
			} )
			.join( ' ' );
	},

	/**
	 * Swap the case of every character in a string.
	 * @param {string} str - Input string
	 * @returns {string} Case-swapped string
	 */
	swapCase: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str
			.split( '' )
			.map( char => {
				if ( char === char.toUpperCase() && char !== char.toLowerCase() ) {
					return char.toLowerCase();
				}
				if ( char === char.toLowerCase() && char !== char.toUpperCase() ) {
					return char.toUpperCase();
				}
				return char;
			} )
			.join( '' );
	},

	/* ---------------------------------------------------------------------
	 * HTML / entity handling
	 * ------------------------------------------------------------------- */

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
	 * Convert newlines in a string to <br> tags. Mirrors PHP's nl2br().
	 * @param {string} str - Input string
	 * @returns {string} String with <br> tags inserted before line breaks
	 */
	nl2br: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.replace( /(\r\n|\r|\n)/g, '<br>$1' );
	},

	/* ---------------------------------------------------------------------
	 * Truncation / wrapping
	 * ------------------------------------------------------------------- */

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
	 * Build an excerpt limited to a number of words (like WordPress'
	 * wp_trim_words()), rather than characters.
	 * @param {string} str - Input string
	 * @param {number} numWords - Max number of words (default: 55)
	 * @param {string} suffix - Suffix to add (default: '...')
	 * @returns {string} Excerpt
	 */
	excerpt: function ( str, numWords = 55, suffix = '...' ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const words = str.trim().split( /\s+/ );
		if ( words.length <= numWords ) return str.trim();

		return words.slice( 0, numWords ).join( ' ' ) + suffix;
	},

	/**
	 * Wrap a string to a given line width, breaking on word boundaries.
	 * Mirrors PHP's wordwrap().
	 * @param {string} str - Input string
	 * @param {number} width - Max line width (default: 75)
	 * @param {string} lineBreak - Line break character(s) (default: '\n')
	 * @param {boolean} cut - Force-break words longer than width (default: false)
	 * @returns {string} Wrapped string
	 */
	wordWrap: function ( str, width = 75, lineBreak = '\n', cut = false ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const lines = str.split( '\n' );

		return lines
			.map( line => {
				const words = line.split( ' ' );
				const wrapped = [];
				let current = '';

				words.forEach( word => {
					while ( cut && word.length > width ) {
						if ( current.length ) {
							wrapped.push( current );
							current = '';
						}
						wrapped.push( word.slice( 0, width ) );
						word = word.slice( width );
					}

					if ( ( current + ( current ? ' ' : '' ) + word ).length > width ) {
						wrapped.push( current );
						current = word;
					} else {
						current = current ? current + ' ' + word : word;
					}
				} );

				if ( current ) wrapped.push( current );
				return wrapped.join( lineBreak );
			} )
			.join( '\n' );
	},

	/* ---------------------------------------------------------------------
	 * Formatting
	 * ------------------------------------------------------------------- */

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
			thousand_separator = ',',
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
	 * Format an integer as an ordinal number (1st, 2nd, 3rd, 4th...).
	 * @param {number} num - Number to format
	 * @returns {string} Ordinal string
	 */
	ordinal: function ( num ) {
		const n = parseInt( num, 10 );
		if ( isNaN( n ) ) return String( num );

		const mod100 = Math.abs( n ) % 100;
		const mod10 = Math.abs( n ) % 10;

		if ( mod100 >= 11 && mod100 <= 13 ) return n + 'th';

		switch ( mod10 ) {
			case 1: return n + 'st';
			case 2: return n + 'nd';
			case 3: return n + 'rd';
			default: return n + 'th';
		}
	},

	/* ---------------------------------------------------------------------
	 * Generation
	 * ------------------------------------------------------------------- */

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
			excludeAmbiguous = false, // Exclude similar-looking characters
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
	 * Generate a v4 UUID.
	 * @returns {string} UUID
	 */
	generateUuid: function () {
		if ( window.crypto && typeof window.crypto.randomUUID === 'function' ) {
			return window.crypto.randomUUID();
		}

		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace( /[xy]/g, char => {
			const rand = Math.random() * 16 | 0;
			const value = char === 'x' ? rand : ( rand & 0x3 | 0x8 );
			return value.toString( 16 );
		} );
	},

	/* ---------------------------------------------------------------------
	 * Parsing
	 * ------------------------------------------------------------------- */

	/**
	 * Parse JSON safely
	 * @param {string} string - JSON string
	 * @param {*} defaultValue - Default value if parsing fails (default: {})
	 * @returns {*} Parsed JSON or default value
	 */
	JSONparse: function ( string, defaultValue = {} ) {
		if ( ! this.isJSON( string ) ) return defaultValue;

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

	/* ---------------------------------------------------------------------
	 * Validation
	 * ------------------------------------------------------------------- */

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
	 * Check whether a string is empty (zero length after trimming).
	 * @param {*} str - Value to test
	 * @returns {boolean} True if empty/blank
	 */
	isBlank: function ( str ) {
		return ! str || typeof str !== 'string' || str.trim().length === 0;
	},

	/**
	 * Check whether a value is a non-empty, non-whitespace string.
	 * @param {*} str - Value to test
	 * @returns {boolean} True if non-blank string
	 */
	isNotBlank: function ( str ) {
		return ! this.isBlank( str );
	},

	/**
	 * Check whether a string represents a numeric value. Mirrors PHP's
	 * is_numeric() more closely than isNaN() alone (rejects '', null, etc).
	 * @param {*} value - Value to test
	 * @returns {boolean} True if numeric
	 */
	isNumeric: function ( value ) {
		if ( typeof value === 'number' ) return isFinite( value );
		if ( typeof value !== 'string' || value.trim() === '' ) return false;
		return ! isNaN( value ) && ! isNaN( parseFloat( value ) );
	},

	/**
	 * Check whether a string contains only alphabetic characters.
	 * @param {string} str - Input string
	 * @returns {boolean} True if alphabetic only
	 */
	isAlpha: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return false;
		return /^[a-zA-Z]+$/.test( str );
	},

	/**
	 * Check whether a string contains only alphanumeric characters.
	 * @param {string} str - Input string
	 * @returns {boolean} True if alphanumeric only
	 */
	isAlphanumeric: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return false;
		return /^[a-zA-Z0-9]+$/.test( str );
	},

	/**
	 * Check if a string starts with a given substring.
	 * @param {string} str - Haystack
	 * @param {string} search - Needle
	 * @param {boolean} caseInsensitive - Ignore case (default: false)
	 * @returns {boolean} True if str starts with search
	 */
	startsWith: function ( str, search, caseInsensitive = false ) {
		if ( typeof str !== 'string' || typeof search !== 'string' ) return false;
		if ( caseInsensitive ) {
			return str.toLowerCase().startsWith( search.toLowerCase() );
		}
		return str.startsWith( search );
	},

	/**
	 * Check if a string ends with a given substring.
	 * @param {string} str - Haystack
	 * @param {string} search - Needle
	 * @param {boolean} caseInsensitive - Ignore case (default: false)
	 * @returns {boolean} True if str ends with search
	 */
	endsWith: function ( str, search, caseInsensitive = false ) {
		if ( typeof str !== 'string' || typeof search !== 'string' ) return false;
		if ( caseInsensitive ) {
			return str.toLowerCase().endsWith( search.toLowerCase() );
		}
		return str.endsWith( search );
	},

	/**
	 * Check if a string contains a given substring.
	 * @param {string} str - Haystack
	 * @param {string} search - Needle
	 * @param {boolean} caseInsensitive - Ignore case (default: false)
	 * @returns {boolean} True if str contains search
	 */
	contains: function ( str, search, caseInsensitive = false ) {
		if ( typeof str !== 'string' || typeof search !== 'string' ) return false;
		if ( caseInsensitive ) {
			return str.toLowerCase().includes( search.toLowerCase() );
		}
		return str.includes( search );
	},

	/* ---------------------------------------------------------------------
	 * Extraction
	 * ------------------------------------------------------------------- */

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
	 * Extract all email addresses from text.
	 * @param {string} text - Text to search
	 * @returns {Array<string>} Array of email addresses
	 */
	extractEmails: function ( text ) {
		if ( ! text || typeof text !== 'string' ) return [];
		const emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
		return text.match( emailRegex ) || [];
	},

	/**
	 * Return the portion of a string before the first occurrence of a
	 * substring. Mirrors Laravel's Str::before().
	 * @param {string} str - Input string
	 * @param {string} search - Delimiter substring
	 * @returns {string} Substring before delimiter, or full string if not found
	 */
	before: function ( str, search ) {
		if ( ! str || typeof str !== 'string' || ! search ) return str || '';
		const index = str.indexOf( search );
		return index === -1 ? str : str.substring( 0, index );
	},

	/**
	 * Return the portion of a string after the first occurrence of a
	 * substring. Mirrors Laravel's Str::after().
	 * @param {string} str - Input string
	 * @param {string} search - Delimiter substring
	 * @returns {string} Substring after delimiter, or full string if not found
	 */
	after: function ( str, search ) {
		if ( ! str || typeof str !== 'string' || ! search ) return str || '';
		const index = str.indexOf( search );
		return index === -1 ? str : str.substring( index + search.length );
	},

	/**
	 * Return the portion of a string before the last occurrence of a
	 * substring.
	 * @param {string} str - Input string
	 * @param {string} search - Delimiter substring
	 * @returns {string} Substring before last delimiter, or full string if not found
	 */
	beforeLast: function ( str, search ) {
		if ( ! str || typeof str !== 'string' || ! search ) return str || '';
		const index = str.lastIndexOf( search );
		return index === -1 ? str : str.substring( 0, index );
	},

	/**
	 * Return the portion of a string after the last occurrence of a
	 * substring.
	 * @param {string} str - Input string
	 * @param {string} search - Delimiter substring
	 * @returns {string} Substring after last delimiter, or full string if not found
	 */
	afterLast: function ( str, search ) {
		if ( ! str || typeof str !== 'string' || ! search ) return str || '';
		const index = str.lastIndexOf( search );
		return index === -1 ? str : str.substring( index + search.length );
	},

	/**
	 * Return the substring found between two delimiters.
	 * @param {string} str - Input string
	 * @param {string} start - Start delimiter
	 * @param {string} end - End delimiter
	 * @returns {string} Substring between delimiters, or '' if not found
	 */
	between: function ( str, start, end ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const startIndex = str.indexOf( start );
		if ( startIndex === -1 ) return '';

		const fromStart = str.substring( startIndex + start.length );
		const endIndex = fromStart.indexOf( end );
		if ( endIndex === -1 ) return '';

		return fromStart.substring( 0, endIndex );
	},

	/* ---------------------------------------------------------------------
	 * Templating
	 * ------------------------------------------------------------------- */

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

	/* ---------------------------------------------------------------------
	 * Analysis / comparison
	 * ------------------------------------------------------------------- */

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
	 * Count occurrences of a substring within a string. Mirrors PHP's
	 * substr_count().
	 * @param {string} str - Haystack
	 * @param {string} search - Needle
	 * @returns {number} Number of occurrences
	 */
	substrCount: function ( str, search ) {
		if ( ! str || typeof str !== 'string' || ! search ) return 0;

		let count = 0;
		let position = str.indexOf( search );

		while ( position !== -1 ) {
			count++;
			position = str.indexOf( search, position + search.length );
		}

		return count;
	},

	/**
	 * Calculate the Levenshtein edit distance between two strings.
	 * @param {string} a - First string
	 * @param {string} b - Second string
	 * @returns {number} Edit distance
	 */
	levenshtein: function ( a, b ) {
		a = a || '';
		b = b || '';

		const matrix = [];

		for ( let i = 0; i <= b.length; i++ ) {
			matrix[ i ] = [ i ];
		}
		for ( let j = 0; j <= a.length; j++ ) {
			matrix[ 0 ][ j ] = j;
		}

		for ( let i = 1; i <= b.length; i++ ) {
			for ( let j = 1; j <= a.length; j++ ) {
				if ( b.charAt( i - 1 ) === a.charAt( j - 1 ) ) {
					matrix[ i ][ j ] = matrix[ i - 1 ][ j - 1 ];
				} else {
					matrix[ i ][ j ] = Math.min(
						matrix[ i - 1 ][ j - 1 ] + 1,
						matrix[ i ][ j - 1 ] + 1,
						matrix[ i - 1 ][ j ] + 1
					);
				}
			}
		}

		return matrix[ b.length ][ a.length ];
	},

	/**
	 * Calculate a similarity percentage between two strings, based on
	 * Levenshtein distance (0 = completely different, 100 = identical).
	 * @param {string} a - First string
	 * @param {string} b - Second string
	 * @returns {number} Similarity percentage
	 */
	similarity: function ( a, b ) {
		a = a || '';
		b = b || '';

		const longer = a.length >= b.length ? a : b;
		if ( longer.length === 0 ) return 100;

		const distance = this.levenshtein( a, b );
		return Math.round( ( 1 - distance / longer.length ) * 100 );
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

	/* ---------------------------------------------------------------------
	 * Transformation
	 * ------------------------------------------------------------------- */

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
	},

	/**
	 * Collapse repeated whitespace into a single space and trim the ends.
	 * @param {string} str - Input string
	 * @returns {string} Whitespace-collapsed string
	 */
	collapseWhitespace: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.replace( /\s+/g, ' ' ).trim();
	},

	/**
	 * Remove all whitespace from a string.
	 * @param {string} str - Input string
	 * @returns {string} Whitespace-free string
	 */
	removeWhitespace: function ( str ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.replace( /\s+/g, '' );
	},

	/**
	 * Remove a prefix from a string, if present.
	 * @param {string} str - Input string
	 * @param {string} prefix - Prefix to remove
	 * @returns {string} String with prefix removed
	 */
	removePrefix: function ( str, prefix ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.startsWith( prefix ) ? str.slice( prefix.length ) : str;
	},

	/**
	 * Remove a suffix from a string, if present.
	 * @param {string} str - Input string
	 * @param {string} suffix - Suffix to remove
	 * @returns {string} String with suffix removed
	 */
	removeSuffix: function ( str, suffix ) {
		if ( ! str || typeof str !== 'string' ) return '';
		return str.endsWith( suffix ) && suffix.length > 0
			? str.slice( 0, -suffix.length )
			: str;
	},

	/**
	 * Ensure a string starts with a given prefix, adding it only if missing.
	 * Mirrors Laravel's Str::start().
	 * @param {string} str - Input string
	 * @param {string} prefix - Prefix to enforce
	 * @returns {string} String guaranteed to start with prefix
	 */
	ensureStart: function ( str, prefix ) {
		str = str || '';
		return str.startsWith( prefix ) ? str : prefix + str;
	},

	/**
	 * Ensure a string ends with a given suffix, adding it only if missing.
	 * Mirrors Laravel's Str::finish().
	 * @param {string} str - Input string
	 * @param {string} suffix - Suffix to enforce
	 * @returns {string} String guaranteed to end with suffix
	 */
	ensureEnd: function ( str, suffix ) {
		str = str || '';
		return str.endsWith( suffix ) ? str : str + suffix;
	},

	/**
	 * Mask part of a string, leaving a configurable number of visible
	 * characters at the start and/or end. Useful for displaying sensitive
	 * values (API keys, emails, card numbers) without fully exposing them.
	 * @param {string} str - Input string
	 * @param {Object} options - Masking options
	 * @param {number} options.visibleStart - Visible chars at the start (default: 0)
	 * @param {number} options.visibleEnd - Visible chars at the end (default: 4)
	 * @param {string} options.maskChar - Character to mask with (default: '*')
	 * @returns {string} Masked string
	 */
	mask: function ( str, options = {} ) {
		if ( ! str || typeof str !== 'string' ) return '';

		const {
			visibleStart = 0,
			visibleEnd = 4,
			maskChar = '*',
		} = options;

		const total = str.length;
		const keepStart = Math.min( visibleStart, total );
		const keepEnd = Math.min( visibleEnd, Math.max( total - keepStart, 0 ) );
		const maskLength = Math.max( total - keepStart - keepEnd, 0 );

		return (
			str.slice( 0, keepStart ) +
			maskChar.repeat( maskLength ) +
			( keepEnd > 0 ? str.slice( total - keepEnd ) : '' )
		);
	},

	/**
	 * Mask an email address, keeping the first character of the local part
	 * and the full domain visible (e.g. "j***@example.com").
	 * @param {string} email - Email address
	 * @param {string} maskChar - Character to mask with (default: '*')
	 * @returns {string} Masked email
	 */
	maskEmail: function ( email, maskChar = '*' ) {
		if ( ! this.isEmail( email ) ) return email || '';

		const [ local, domain ] = email.split( '@' );
		const visible = local.charAt( 0 );
		const masked = maskChar.repeat( Math.max( local.length - 1, 1 ) );

		return `${ visible }${ masked }@${ domain }`;
	},

	/* ---------------------------------------------------------------------
	 * URL encoding
	 * ------------------------------------------------------------------- */

	/**
	 * Encode a raw URL component.
	 *
	 * Equivalent to PHP's rawurlencode().
	 *
	 * @param {string} string - String to encode.
	 * @returns {string} Encoded string.
	 */
	rawUrlEncode: function ( string ) {
		if ( ! string || typeof string !== 'string' ) {
			return '';
		}

		return encodeURIComponent( string );
	},

	/**
	 * Encode an application/x-www-form-urlencoded string.
	 *
	 * Equivalent to PHP's urlencode().
	 *
	 * @param {string} string - String to encode.
	 * @returns {string} Encoded string.
	 */
	urlEncode: function ( string ) {
		if ( ! string || typeof string !== 'string' ) {
			return '';
		}

		return encodeURIComponent( string ).replace( /%20/g, '+' );
	},

	/**
	 * Decode a raw URL component.
	 *
	 * Equivalent to PHP's rawurldecode().
	 *
	 * @param {string} string - Encoded string.
	 * @returns {string} Decoded string.
	 */
	rawUrlDecode: function ( string ) {
		if ( ! string || typeof string !== 'string' ) {
			return '';
		}

		try {
			return decodeURIComponent( string );
		} catch {
			return string;
		}
	},

	/**
	 * Decode an application/x-www-form-urlencoded string.
	 *
	 * Equivalent to PHP's urldecode().
	 *
	 * @param {string} string - Encoded string.
	 * @returns {string} Decoded string.
	 */
	urlDecode: function ( string ) {
		if ( ! string || typeof string !== 'string' ) {
			return '';
		}

		try {
			return decodeURIComponent( string.replace( /\+/g, ' ' ) );
		} catch {
			return string;
		}
	},

};

return StringUtils;

} ) );