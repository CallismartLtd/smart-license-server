<?php
/**
 * Conditional function file
 */
use SmartLicenseServer\Exceptions\Exception;

if ( ! function_exists( 'is_json' ) ) {
    /**
     * Check if a given string is a valid JSON.
     *
     * @param  mixed  $value  The value to test.
     * @return bool
     */
    function is_json( mixed $value ) : bool {
        
        if ( ! is_string( $value ) ) {
            return false;
        }

        if ( function_exists( 'json_validate' ) ) {
            return json_validate( $value );
        }

        json_decode( $value );

        return ( json_last_error() === JSON_ERROR_NONE );
    }    
}

if ( ! function_exists( 'is_wp' ) ) {
	/**
	 * Tells whether this app is running on WordPress.
	 * 
	 * @return bool
	 */
	function is_wp() : bool {
		return function_exists( 'wp_die' );
	}
}

if ( ! function_exists( 'is_base64_encoded' ) ) {
    /**
     * Check if a string is base64 encoded.
     *
     * @param  mixed  $value
     * @return bool
     */
    function is_base64_encoded( $value ) {
        if ( ! is_string( $value ) ) {
            return false;
        }

        // Basic pattern: only A-Z, a-z, 0-9, +, /, and =
        if ( ! preg_match( '/^[A-Za-z0-9\/\r\n+]*={0,2}$/', $value ) ) {
            return false;
        }

        // Validate by decoding and re-encoding
        $decoded = base64_decode( $value, true );

        return $decoded !== false && base64_encode( $decoded ) === $value;
    }
}

/**
 * Check whether the given value is an error instance.
 * @param mixed $value The value to check.
 * @return bool True if the value is an instance of a known error class, false otherwise.
 */
function is_smliser_error( $value ) {
    if ( function_exists( 'is_wp_error' ) && is_wp_error( $value ) ) {
        return true;
        
    } elseif ( $value instanceof WP_Error ) {
        return true;
    }

    return ( $value instanceof Exception );
}

if ( ! function_exists( '__' ) ) {
    /**
     * Retrieves the translation of $text.
     */
    function __( string $text, string $domain = 'default' ): string {
        return smliser__( $text, $domain );
    }
}

if ( ! function_exists( 'is_interactive_shell' ) ) {
    /**
     * Tells whether the current request is an intercative shell session
     * 
     * @return bool True when in interactive shell, false otherwise.
     */
    function is_interactive_shell() : bool {
        return defined( 'SMLISER_INTERACTIVE_SHELL' ) && SMLISER_INTERACTIVE_SHELL;
    }
}

if ( ! function_exists( 'is_utf8' ) ) {
	/**
	 * Determines whether a string is valid UTF-8.
	 *
	 * This function prefers PHP's native UTF-8 validators when available and
	 * falls back to a manual RFC 3629-compliant validator when necessary.
	 *
	 * @param string $value The string to validate.
	 * @return bool True if the string is valid UTF-8, otherwise false.
	 */
	function is_utf8( string $value ): bool {
		// Empty strings are valid UTF-8.
		if ( '' === $value ) {
			return true;
		}

		// Prefer mbstring when available.
		if ( function_exists( 'mb_check_encoding' ) ) {
			return mb_check_encoding( $value, 'UTF-8' );
		}

		// Fall back to PCRE's UTF-8 validator.
		if ( function_exists( 'preg_match' ) ) {
			return 1 === preg_match( '//u', $value );
		}

		// Manual validator.
		$length = strlen( $value );

		for ( $i = 0; $i < $length; $i++ ) {
			$byte = ord( $value[ $i ] );

			// ASCII.
			if ( $byte <= 0x7F ) {
				continue;
			}

			// Two-byte sequence.
			if ( $byte >= 0xC2 && $byte <= 0xDF ) {
				if (
					++$i >= $length ||
					( ord( $value[ $i ] ) & 0xC0 ) !== 0x80
				) {
					return false;
				}

				continue;
			}

			// Three-byte sequence.
			if ( $byte >= 0xE0 && $byte <= 0xEF ) {
				if ( $i + 2 >= $length ) {
					return false;
				}

				$b1 = ord( $value[ ++$i ] );
				$b2 = ord( $value[ ++$i ] );

				if (
					( $b1 & 0xC0 ) !== 0x80 ||
					( $b2 & 0xC0 ) !== 0x80
				) {
					return false;
				}

				// Reject overlong sequences.
				if ( 0xE0 === $byte && $b1 < 0xA0 ) {
					return false;
				}

				// Reject UTF-16 surrogate pairs.
				if ( 0xED === $byte && $b1 >= 0xA0 ) {
					return false;
				}

				continue;
			}

			// Four-byte sequence.
			if ( $byte >= 0xF0 && $byte <= 0xF4 ) {
				if ( $i + 3 >= $length ) {
					return false;
				}

				$b1 = ord( $value[ ++$i ] );
				$b2 = ord( $value[ ++$i ] );
				$b3 = ord( $value[ ++$i ] );

				if (
					( $b1 & 0xC0 ) !== 0x80 ||
					( $b2 & 0xC0 ) !== 0x80 ||
					( $b3 & 0xC0 ) !== 0x80
				) {
					return false;
				}

				// Reject overlong sequences.
				if ( 0xF0 === $byte && $b1 < 0x90 ) {
					return false;
				}

				// Reject code points above U+10FFFF.
				if ( 0xF4 === $byte && $b1 > 0x8F ) {
					return false;
				}

				continue;
			}

			// Invalid leading byte.
			return false;
		}

		return true;
	}
}

if ( ! function_exists( 'is_email' ) ) {
	/**
	 * Validate an email address for syntactic validity, structural constraints, 
	 * security risks, and optional MX record deliverability.
	 *
	 * @param string $email        The email address to validate.
	 * @param bool   $check_dns    Whether to perform a DNS MX/A record check on the domain.
	 * @return bool True if the email is valid, false otherwise.
	 */
	function is_email( string $email, bool $check_dns = false ) : bool {
		// Sanitize and check total length boundaries (RFC 5321 max length is 254 octets).
		$email = trim( $email );
		$length = strlen( $email );

		if ( $length < 6 || $length > 254 ) {
			return false;
		}

		// Guard against header injection attacks (CR / LF characters).
		if ( preg_match( '/[\r\n]/', $email ) ) {
			return false;
		}

		// Must contain exactly one '@' symbol.
		$parts = explode( '@', $email );
		if ( count( $parts ) !== 2 ) {
			return false;
		}

		[ $local, $domain ] = $parts;

		// Validate Local-Part length (RFC 5321 max length is 64 octets) and Domain length.
		$local_length  = strlen( $local );
		$domain_length = strlen( $domain );

		if ( $local_length < 1 || $local_length > 64 || $domain_length < 3 || $domain_length > 255 ) {
			return false;
		}

		// Prevent consecutive dots or leading/trailing dots in either part.
		if ( str_contains( $local, '..' ) || str_contains( $domain, '..' ) ) {
			return false;
		}

		if ( str_starts_with( $local, '.' ) || str_ends_with( $local, '.' ) ||
			str_starts_with( $domain, '.' ) || str_ends_with( $domain, '.' ) ) {
			return false;
		}

		// Canonical PHP filter check using native engine (RFC-compliant parser).
		if ( ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			return false;
		}

		// Ensure domain contains a valid TLD dot structure (excluding IP literal domains unless intentional).
		if ( ! str_contains( $domain, '.' ) && ! str_starts_with( $domain, '[' ) ) {
			return false;
		}

		// Optional DNS check for active mail exchanger (MX or fallback A/AAAA records).
		if ( $check_dns ) {
			// Normalize internationalized domain names (IDN) if intl extension is present.
			if ( function_exists( 'idn_to_ascii' ) ) {
				$domain = idn_to_ascii( $domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 ) ?: $domain;
			}

			if ( ! checkdnsrr( $domain, 'MX' ) && ! checkdnsrr( $domain, 'A' ) && ! checkdnsrr( $domain, 'AAAA' ) ) {
				return false;
			}
		}

		return true;
	}
}