<?php
/**
 * Sanitization class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 * @since 0.2.0
 */
namespace SmartLicenseServer\Utils;

use DOMDocument;
use DOMNode;

/**
 * Sanitization Utility Class
 * 
 * Provides comprehensive input sanitization and output escaping methods
 * to prevent XSS, SQL injection, and other security vulnerabilities.
 */
class Sanitizer {

    /*
    |--------------------------------------------------------------------------
    | INPUT SANITIZATION METHODS
    |--------------------------------------------------------------------------
    | These methods clean user input before processing or storage.
    */

    /**
     * Remove invalid UTF-8 characters.
     * 
     * @param string $str The input string.
     * @return string The cleaned string or empty string if invalid.
     */
    public static function check_invalid_utf8( string $str ) : string {
        return mb_check_encoding( $str, 'UTF-8' ) ? $str : '';
    }

    /**
     * Strips HTML tags, trims spaces, and ensures clean text input.
     * 
     * @param mixed $str The input value.
     * @return string The sanitized string.
     */
    public static function sanitize_text_field( $str ) : string {
        if ( empty( $str ) || ! is_scalar( $str ) ) {
            return '';
        }

        $str = (string) $str;
        $str = self::check_invalid_utf8( $str );
        $str = strip_tags( $str );
        $str = trim( $str );
        $str = preg_replace( '/[\r\n\t ]+/', ' ', $str );
        
        return $str;
    }

    /**
     * Sanitizes textarea input (preserves line breaks).
     * 
     * @param mixed $str The input value.
     * @return string The sanitized string.
     */
    public static function sanitize_textarea_field( $str ) : string {
        if ( empty( $str ) || ! is_scalar( $str ) ) {
            return '';
        }

        $str = (string) $str;
        $str = self::check_invalid_utf8( $str );
        $str = strip_tags( $str );
        $str = trim( $str );
        
        return $str;
    }

    /**
     * Sanitizes an email address.
     * 
     * @param string $email The input email.
     * @return string The sanitized email or empty string if invalid.
     */
    public static function sanitize_email( string $email ) : string {
        $email = trim( $email );

        // Test for minimum length
        if ( strlen( $email ) < 6 ) {
            return '';
        }

        // Test for @ character after the first position
        if ( strpos( $email, '@', 1 ) === false ) {
            return '';
        }

        // Split out the local and domain parts
        list( $local, $domain ) = explode( '@', $email, 2 );

        // LOCAL PART: Remove invalid characters
        $local = preg_replace( '/[^a-zA-Z0-9!#$%&\'*+\/=?^_`{|}~\.-]/', '', $local );
        if ( '' === $local ) {
            return '';
        }

        // DOMAIN PART: Remove sequences of periods
        $domain = preg_replace( '/\.{2,}/', '', $domain );
        if ( '' === $domain ) {
            return '';
        }

        // Trim leading/trailing periods and whitespace
        $domain = trim( $domain, " \t\n\r\0\x0B." );
        if ( '' === $domain ) {
            return '';
        }

        // Split domain into subdomains
        $subs = explode( '.', $domain );

        // Domain must have at least two parts
        if ( count( $subs ) < 2 ) {
            return '';
        }

        // Validate each subdomain
        $new_subs = [];
        foreach ( $subs as $sub ) {
            $sub = trim( $sub, " \t\n\r\0\x0B-" );
            $sub = preg_replace( '/[^a-z0-9-]+/i', '', $sub );

            if ( '' !== $sub ) {
                $new_subs[] = $sub;
            }
        }

        // Ensure we still have at least 2 valid parts
        if ( count( $new_subs ) < 2 ) {
            return '';
        }

        // Rebuild email
        $domain = implode( '.', $new_subs );
        $sanitized_email = $local . '@' . $domain;

        // Final validation using filter_var
        return filter_var( $sanitized_email, FILTER_VALIDATE_EMAIL ) ?: '';
    }

    /**
     * Sanitizes a URL (basic sanitization for storage).
     * 
     * For output escaping, use esc_url() instead.
     *
     * @param string $url The URL to sanitize.
     * @return string The sanitized URL or empty string if invalid.
     */
    public static function sanitize_url( string $url ) : string {
        if ( empty( $url ) ) {
            return '';
        }

        $url = self::check_invalid_utf8( $url );
        $url = trim( $url );

        // Use esc_url for comprehensive sanitization
        return self::esc_url( $url );
    }

    /**
     * Sanitizes a number (removes non-numeric characters).
     * 
     * @param mixed $number The input value.
     * @return string The sanitized number string containing only digits.
     */
    public static function sanitize_number( $number ) : string {
        if ( ! is_scalar( $number ) ) {
            return '';
        }

        return preg_replace( '/[^0-9]/', '', (string) $number );
    }

    /**
     * Sanitizes an integer value.
     * 
     * @param mixed $number The input value.
     * @return int|false Integer value or false if invalid.
     */
    public static function sanitize_int( $number ) : int|false {
        return filter_var( $number, FILTER_VALIDATE_INT );
    }

    /**
     * Sanitizes a float value.
     * 
     * @param mixed $number The input value.
     * @return float|false Float value or false if invalid.
     */
    public static function sanitize_float( $number ) : float|false {
        return filter_var( $number, FILTER_VALIDATE_FLOAT );
    }
    
    /**
     * Sanitize HTML content to prevent XSS and unsafe HTML.
     * 
     * Uses DOM parsing to safely filter HTML from rich text editors like TinyMCE.
     *
     * @param string $html The raw HTML string.
     * @return string The cleaned HTML string.
     */
    public static function sanitize_html( string $html ) : string {
        if ( empty( $html ) ) {
            return '';
        }

        // Allowed tags and their attributes
        $allowed_tags = [
            'p' => ['style', 'class'], 
            'br' => [],
            'strong' => [], 
            'b' => [],
            'em' => [], 
            'i' => [], 
            'u' => [],
            'ol' => ['style', 'class'], 
            'ul' => ['style', 'class'], 
            'li' => ['style', 'class'],
            'a' => ['href', 'title', 'target', 'rel'],
            'img' => ['src', 'alt', 'width', 'height', 'style', 'class'],
            'h1' => ['style', 'class'], 
            'h2' => ['style', 'class'], 
            'h3' => ['style', 'class'],
            'h4' => ['style', 'class'], 
            'h5' => ['style', 'class'], 
            'h6' => ['style', 'class'],
            'div' => ['style', 'class'], 
            'span' => ['style', 'class'],
            'blockquote' => ['style', 'class'], 
            'code' => ['style', 'class'], 
            'pre' => ['style', 'class'],
            'table' => ['border', 'cellpadding', 'cellspacing', 'width', 'style', 'class'],
            'thead' => ['style', 'class'], 
            'tbody' => ['style', 'class'], 
            'tfoot' => ['style', 'class'],
            'tr' => ['style', 'class'], 
            'th' => ['style', 'scope', 'class'],
            'td' => ['style', 'colspan', 'rowspan', 'class'],
        ];

        // Dangerous event handler attributes
        $dangerous_attrs = [
            'onabort', 'onblur', 'onchange', 'onclick', 'ondblclick', 'onerror',
            'onfocus', 'onkeydown', 'onkeypress', 'onkeyup', 'onload', 'onmousedown',
            'onmousemove', 'onmouseout', 'onmouseover', 'onmouseup', 'onreset',
            'onselect', 'onsubmit', 'onunload', 'onresize', 'onscroll', 'oncontextmenu',
            'oninput', 'oninvalid', 'onsearch', 'ontoggle', 'onwheel',
            'formaction', 'formmethod', 'formtarget', 'formnovalidate', 'formenctype'
        ];

        // Load HTML safely in DOMDocument
        libxml_use_internal_errors( true );
        $dom = new DOMDocument( '1.0', 'UTF-8' );
        $dom->loadHTML( 
            '<?xml encoding="UTF-8"><div>' . $html . '</div>', 
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD 
        );
        libxml_clear_errors();

        $root = $dom->getElementsByTagName( 'div' )->item( 0 );
        if ( ! $root ) {
            return '';
        }

        // Recursive node sanitization
        $sanitize_node = function ( DOMNode $node ) use ( &$sanitize_node, $allowed_tags, $dangerous_attrs ) {
            if ( $node->nodeType === XML_ELEMENT_NODE ) {
                $tag = strtolower( $node->nodeName );

                // Remove disallowed tags
                if ( ! isset( $allowed_tags[ $tag ] ) ) {
                    $node->parentNode->removeChild( $node );
                    return;
                }

                // Filter attributes
                if ( $node->hasAttributes() ) {
                    $remove_attrs = [];
                    
                    foreach ( $node->attributes as $attr ) {
                        $attr_name  = strtolower( $attr->name );
                        $attr_value = trim( $attr->value );

                        // Remove dangerous or disallowed attributes
                        if ( in_array( $attr_name, $dangerous_attrs, true ) ||
                             ! in_array( $attr_name, $allowed_tags[ $tag ], true ) ) {
                            $remove_attrs[] = $attr_name;
                            continue;
                        }

                        // Block javascript:/data: URIs in href/src
                        if ( in_array( $attr_name, ['href', 'src'], true ) &&
                             preg_match( '/^(javascript|data|vbscript):/i', $attr_value ) ) {
                            $remove_attrs[] = $attr_name;
                            continue;
                        }

                        // Sanitize inline styles
                        if ( $attr_name === 'style' ) {
                            $clean_style = preg_replace( [
                                '/expression\s*\(.*?\)/i',
                                '/url\s*\(.*?(javascript|data).*?\)/i',
                                '/javascript\s*:/i',
                                '/behavior\s*:/i',
                                '/@import/i',
                                '/binding\s*:/i',
                            ], '', $attr_value );

                            // Remove control characters
                            $clean_style = preg_replace( '/[\x00-\x1F\x7F]+/', '', $clean_style );
                            if ( $node instanceof \DOMElement ) {
                                $node->setAttribute( 'style', $clean_style );
                            }
                        }

                        // Sanitize rel attribute on links
                        if ( $attr_name === 'rel' ) {
                            $allowed_rels = ['nofollow', 'noopener', 'noreferrer', 'external'];
                            $rels = array_filter( 
                                explode( ' ', strtolower( $attr_value ) ), 
                                fn($rel) => in_array( $rel, $allowed_rels, true ) 
                            );
                            if ( $node instanceof \DOMElement ) {
                                $node->setAttribute( 'rel', implode( ' ', $rels ) );
                            }
                        }
                    }

                    foreach ( $remove_attrs as $attr ) {
                        if ( $node instanceof \DOMElement ) {
                            $node->removeAttribute( $attr );
                        }
                    }
                }
            } elseif ( $node->nodeType === XML_COMMENT_NODE ) {
                // Remove HTML comments
                $node->parentNode->removeChild( $node );
                return;
            }

            // Recurse into children
            $children = [];
            foreach ( $node->childNodes as $child ) {
                $children[] = $child;
            }
            foreach ( $children as $child ) {
                $sanitize_node( $child );
            }
        };

        $sanitize_node( $root );

        // Extract sanitized HTML
        $output = '';
        foreach ( $root->childNodes as $child ) {
            $output .= $dom->saveHTML( $child );
        }

        return $output;
    }

    /**
     * Stronger XSS filter - rejects input containing script tags.
     * 
     * @param string $str The input string.
     * @return string|false Sanitized string or false if malicious content detected.
     */
    public static function strict_sanitize( string $str ) : string|false {
        if ( preg_match( '/<script[^>]*>.*?<\/script>/is', $str ) ) {
            return false;
        }
        
        return self::sanitize_text_field( $str );
    }

    /**
     * Recursively removes backslashes from strings or arrays.
     * 
     * Useful for cleaning magic_quotes_gpc data.
     *
     * @param mixed $value The input value (string, array, or object).
     * @return mixed The cleaned value with slashes removed.
     */
    public static function unslash( $value ) : mixed {
        return self::stripslashes_deep( $value );
    }

    /**
     * Recursively strip slashes from value.
     * 
     * @param mixed $value The input value.
     * @return mixed The value with slashes removed.
     */
    private static function stripslashes_deep( $value ) : mixed {
        if ( is_array( $value ) ) {
            return array_map( [ __CLASS__, 'stripslashes_deep' ], $value );
        }
        
        if ( is_object( $value ) ) {
            $vars = get_object_vars( $value );
            foreach ( $vars as $key => $data ) {
                $value->{$key} = self::stripslashes_deep( $data );
            }
            return $value;
        }
        
        return is_string( $value ) ? stripslashes( $value ) : $value;
    }

    /*
    |--------------------------------------------------------------------------
    | OUTPUT ESCAPING METHODS
    |--------------------------------------------------------------------------
    | These methods escape data for safe output in various contexts.
    */

    /**
     * Escape a string for safe HTML output.
     *
     * @param mixed $text The input value.
     * @return string Escaped string safe for HTML context.
     */
    public static function esc_html( $text ) : string {
        if ( ! is_scalar( $text ) ) {
            return '';
        }

        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
    }

    /**
     * Escape a string for use in HTML attributes.
     *
     * @param mixed $text The input value.
     * @return string Escaped string safe for HTML attributes.
     */
    public static function esc_attr( $text ) : string {
        if ( ! is_scalar( $text ) ) {
            return '';
        }
        
        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
    }

    /**
     * Escape and sanitize a URL for safe output.
     * 
     * Validates protocol, removes injection attempts, and normalizes the URL structure.
     *
     * @param string $url The input URL.
     * @param array $protocols Optional. Allowed protocols. Default: common web protocols.
     * @return string Sanitized URL safe for output or empty string if invalid.
     */
    public static function esc_url( string $url, array $protocols = [] ) : string {
        if ( empty( $url ) ) {
            return '';
        }

        // Default allowed protocols
        if ( empty( $protocols ) ) {
            $protocols = ['http', 'https', 'ftp', 'ftps', 'mailto', 'news', 'irc', 
                         'gopher', 'nntp', 'feed', 'telnet'];
        }

        // Normalize whitespace
        $url = str_replace( ' ', '%20', trim( $url ) );
        
        // Strip dangerous characters
        $url = preg_replace( '|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\[\]\\x80-\\xff]|i', '', $url );

        if ( '' === $url ) {
            return '';
        }

        // Handle mailto: links - prevent email injection
        if ( str_starts_with( $url, 'mailto:' ) ) {
            $remove_chars = [
                '%0d', '%0a', '%0D', '%0A',  // CRLF injection
                'content-type:', 'bcc:', 'to:', 'cc:', 'from:' // Header injection
            ];
            $url = str_ireplace( $remove_chars, '', $url );
        }

        // Remove HTML entities
        $url = preg_replace( '|&#([0-9]+);?|', '', $url );
        $url = preg_replace( '|&#[xX]([0-9a-fA-F]+);?|', '', $url );
        
        // Remove NULL bytes
        $url = str_replace( ['%00', "\0"], '', $url );

        // Parse URL
        $parsed = @parse_url( $url );
        
        if ( false === $parsed ) {
            return '';
        }

        // Handle relative URLs
        if ( ! isset( $parsed['scheme'] ) ) {
            return $url;
        }

        // Validate protocol
        $scheme = strtolower( $parsed['scheme'] );
        if ( ! in_array( $scheme, $protocols, true ) ) {
            return '';
        }

        // Special handling for mailto (doesn't need full URL rebuild)
        if ( $scheme === 'mailto' ) {
            return $url;
        }

        // Rebuild URL for full URLs
        $rebuilt = $scheme . '://';

        // Add authentication (discouraged but supported)
        if ( isset( $parsed['user'] ) ) {
            $rebuilt .= rawurlencode( $parsed['user'] );
            if ( isset( $parsed['pass'] ) ) {
                $rebuilt .= ':' . rawurlencode( $parsed['pass'] );
            }
            $rebuilt .= '@';
        }

        // Add host
        if ( isset( $parsed['host'] ) ) {
            $host = $parsed['host'];
            
            // Convert IDN to ASCII (punycode)
            if ( function_exists( 'idn_to_ascii' ) ) {
                $ascii_host = @idn_to_ascii( $host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46 );
                $host = $ascii_host ?: $host;
            }
            
            $rebuilt .= strtolower( $host );
        } else {
            // Host is required for non-relative URLs
            return '';
        }

        // Add non-standard ports
        if ( isset( $parsed['port'] ) ) {
            $default_ports = [
                'http'  => 80,
                'https' => 443,
                'ftp'   => 21,
                'ftps'  => 990,
            ];
            
            $port = (int) $parsed['port'];
            if ( ! isset( $default_ports[ $scheme ] ) || $default_ports[ $scheme ] !== $port ) {
                $rebuilt .= ':' . $port;
            }
        }

        // Add path with trailing slash logic
        if ( isset( $parsed['path'] ) ) {
            $path = $parsed['path'];
            
            // Files (with extensions) - no trailing slash
            if ( preg_match( '/\.[a-z0-9]{2,6}$/i', $path ) ) {
                $rebuilt .= $path;
            } else {
                // Directories - ensure trailing slash
                $path = rtrim( $path, '/' );
                $rebuilt .= $path;
                if ( $path !== '' ) {
                    $rebuilt .= '/';
                }
            }
        } else {
            $rebuilt .= '/';
        }

        // Add query string
        if ( isset( $parsed['query'] ) ) {
            $rebuilt .= '?' . $parsed['query'];
        }

        // Add fragment
        if ( isset( $parsed['fragment'] ) ) {
            $rebuilt .= '#' . $parsed['fragment'];
        }

        return $rebuilt;
    }

    /**
     * Escape a URL for use in href attributes.
     * 
     * Alias for esc_url() with http/https only.
     *
     * @param string $url The input URL.
     * @param array $protocols Optional. Allowed protocols.
     * @return string Escaped URL.
     */
    public static function esc_href( string $url, array $protocols = [] ) : string {
        // Default to web protocols only for href
        if ( empty( $protocols ) ) {
            $protocols = ['http', 'https'];
        }
        
        return self::esc_url( $url, $protocols );
    }

    /**
     * Escape a string for safe JavaScript output.
     *
     * @param mixed $text The input value.
     * @return string Escaped JavaScript-safe string.
     */
    public static function esc_js( $text ) : string {
        if ( ! is_scalar( $text ) ) {
            return '';
        }

        $text = (string) $text;
        
        // Escape special JavaScript characters
        $safe = [
            '\\' => '\\\\',
            '"'  => '\\"',
            "'"  => "\\'",
            "\n" => '\\n',
            "\r" => '\\r',
            "\t" => '\\t',
            '/'  => '\\/',
            '<'  => '\\x3C',
            '>'  => '\\x3E',
        ];
        
        return str_replace( array_keys( $safe ), array_values( $safe ), $text );
    }

    /**
     * Escape text for use inside a textarea element.
     *
     * @param mixed $text The input value.
     * @return string Escaped text safe for textarea.
     */
    public static function esc_textarea( $text ) : string {
        if ( ! is_scalar( $text ) ) {
            return '';
        }

        return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_HTML5, 'UTF-8', false );
    }

    /**
     * Escape an email address for safe output.
     *
     * @param string $email The input email.
     * @return string Sanitized and escaped email.
     */
    public static function esc_email( string $email ) : string {
        $email = self::sanitize_email( $email );
        return $email ? self::esc_attr( $email ) : '';
    }

    /**
     * Escape a CSS class name.
     *
     * @param string $class The input class name.
     * @return string Escaped class name containing only safe characters.
     */
    public static function esc_class( string $class ) : string {
        // Only allow alphanumeric, hyphens, and underscores
        return preg_replace( '/[^a-zA-Z0-9\-_]/', '', $class );
    }

    /**
     * Escape a CSS style value.
     * 
     * Note: This is basic escaping. For complex CSS, use a dedicated CSS parser.
     *
     * @param string $css The input CSS value.
     * @return string Escaped CSS value.
     */
    public static function esc_css( string $css ) : string {
        // Remove dangerous CSS constructs
        $css = preg_replace( [
            '/expression\s*\(.*?\)/i',
            '/javascript\s*:/i',
            '/behavior\s*:/i',
            '/@import/i',
            '/binding\s*:/i',
        ], '', $css );

        // Allow common CSS characters
        return preg_replace( '/[^a-zA-Z0-9\-_#%;:,.()\s\/]/', '', $css );
    }

    /**
     * Escape and cast to integer.
     *
     * @param mixed $int The input value.
     * @return int Sanitized integer value.
     */
    public static function esc_int( $int ) : int {
        return (int) $int;
    }

    /**
     * Escape and cast to float.
     *
     * @param mixed $float The input value.
     * @return float Sanitized float value.
     */
    public static function esc_float( $float ) : float {
        return (float) $float;
    }

    /**
     * Escape and cast to boolean.
     *
     * @param mixed $bool The input value.
     * @return bool Sanitized boolean value.
     */
    public static function esc_bool( $bool ) : bool {
        return (bool) $bool;
    }

    /**
     * Escape a SQL LIKE pattern.
     * 
     * Escapes wildcards to prevent LIKE injection attacks.
     *
     * @param string $pattern The LIKE pattern.
     * @return string Escaped pattern.
     */
    public static function esc_like( string $pattern ) : string {
        return addcslashes( $pattern, '_%\\' );
    }

    /**
     * Sanitize URL for form action attributes (HTTP/HTTPS only).
     *
     * @param string $url The input URL.
     * @return string Sanitized URL safe for form actions.
     */
    public static function esc_form_action( string $url ) : string {
        return self::esc_url( $url, ['http', 'https'] );
    }
}