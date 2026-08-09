<?php
/**
 * HTTP Error Handler Class for Smart License Server.
 *
 * Specializes in rendering errors for web/HTTP environments.
 * Handles HTML and JSON content negotiation, HTTP headers, CSS styling,
 * and security-focused error output.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class HttpErrorHandler extends AbstractErrorHandler {

    /*
    |-------------------------------------
    | INTERNAL STATE & CONFIGURATION
    |-------------------------------------
    */

    /**
     * Cache for detected response format.
     *
     * @var string|null
     */
    private ?string $detected_format = null;

    /**
     * HTML head content elements.
     *
     * @var array
     */
    private array $head_content = [];

    /**
     * Custom CSS styles.
     *
     * @var array
     */
    private array $styles = [];

    /**
     * Custom attributes for html tag.
     *
     * @var array
     */
    private array $html_attributes = [];

    /**
     * Custom attributes for body tag.
     *
     * @var array
     */
    private array $body_attributes = [];

    /*
    |------------------------------------------
    | CONTENT NEGOTIATION & FORMATTING
    |------------------------------------------
    */

    /**
     * Determine preferred response format (json vs html).
     *
     * Checks explicitly set configuration first, then inspects HTTP request headers.
     *
     * @return string 'json' or 'html'
     */
    public function getPreferredFormat() : string {
        if ( null !== $this->detected_format ) {
            return $this->detected_format;
        }

        // 1. Check explicit override in configuration
        if ( ! empty( $this->config['format'] ) ) {
            return $this->detected_format = strtolower( $this->config['format'] );
        }

        // 2. Check HTTP Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ( str_contains( $accept, 'application/json' ) || str_contains( $accept, '+json' ) ) {
            return $this->detected_format = 'json';
        }

        // 3. Check Request Content-Type header (e.g., API requests sending JSON payloads)
        $content_type = $_SERVER['CONTENT_TYPE'] ?? '';
        if ( str_contains( $content_type, 'application/json' ) ) {
            return $this->detected_format = 'json';
        }

        // 4. Default to HTML for standard web browsers
        return $this->detected_format = 'html';
    }

    /**
     * Explicitly set the output response format ('json' or 'html').
     *
     * @param string $format Target format.
     * @return static
     */
    public function setFormat( string $format ) : static {
        $this->config['format'] = $format;
        $this->detected_format  = strtolower( $format );
        return $this;
    }

    /*
    |------------------------------------------
    | HTML HEAD & STYLING CUSTOMIZATION
    |------------------------------------------
    */

    /**
     * Add meta tag to HTML head.
     *
     * @param string $name Attribute name.
     * @param string $content Attribute content.
     * @param array $attributes Additional attributes.
     * @return static
     */
    public function addMeta( string $name, string $content, array $attributes = [] ) : static {
        $meta = array_merge(
            [ 'name' => $name, 'content' => $content ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'meta', 'data' => $meta ];
        return $this;
    }

    /**
     * Add link tag to HTML head.
     *
     * @param string $rel Relationship type.
     * @param string $href URL.
     * @param array $attributes Additional attributes.
     * @return static
     */
    public function addLink( string $rel, string $href, array $attributes = [] ) : static {
        $link = array_merge(
            [ 'rel' => $rel, 'href' => $href ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'link', 'data' => $link ];
        return $this;
    }

    /**
     * Add custom style rules.
     *
     * @param string $selector CSS selector.
     * @param array $properties CSS properties as key-value pairs.
     * @return static
     */
    public function addStyle( string $selector, array $properties ) : static {
        $this->styles[ $selector ] = $properties;
        return $this;
    }

    /**
     * Set multiple style rules at once.
     *
     * @param array $styles Styles array (selector => properties).
     * @return static
     */
    public function setStyles( array $styles ) : static {
        $this->styles = array_merge( $this->styles, $styles );
        return $this;
    }

    /**
     * Set custom HTML tag attributes.
     *
     * @param array $attributes Attributes for html tag.
     * @return static
     */
    public function setHtmlAttributes( array $attributes ) : static {
        $this->html_attributes = array_merge( $this->html_attributes, $attributes );
        return $this;
    }

    /**
     * Set custom body tag attributes.
     *
     * @param array $attributes Attributes for body tag.
     * @return static
     */
    public function setBodyAttributes( array $attributes ) : static {
        $this->body_attributes = array_merge( $this->body_attributes, $attributes );
        return $this;
    }

    /**
     * Add CSS class to body tag.
     *
     * @param string $class CSS class name.
     * @return static
     */
    public function addBodyClass( string $class ) : static {
        $existing = $this->body_attributes['class'] ?? '';
        $this->body_attributes['class'] = trim( $existing . ' ' . $class );
        return $this;
    }

    /*
    |------------------------------------------
    | JSON RENDERING METHODS
    |------------------------------------------
    */

    /**
     * Build structured error payload array for JSON output.
     *
     * @return array
     */
    private function buildJsonPayload() : array {
        $payload = [
            'success' => false,
            'error'   => [
                'code'    => $this->getCode(),
                'title'   => $this->getTitle(),
                'message' => $this->getMessage(),
                'status'  => $this->getResponseCode(),
            ],
        ];

        if ( $this->isDebug() && $this->error_object instanceof \Throwable ) {
            $payload['error']['debug'] = [
                'type'  => get_class( $this->error_object ),
                'file'  => $this->error_object->getFile(),
                'line'  => $this->error_object->getLine(),
                'trace' => explode( "\n", $this->error_object->getTraceAsString() ),
            ];

            if ( $previous = $this->error_object->getPrevious() ) {
                $payload['error']['debug']['previous'] = [
                    'type'    => get_class( $previous ),
                    'message' => $previous->getMessage(),
                    'file'    => $previous->getFile(),
                    'line'    => $previous->getLine(),
                ];
            }
        }

        return $payload;
    }

    /**
     * Render error payload as a JSON string.
     *
     * @return string
     */
    private function renderJson() : string {
        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

        if ( $this->isDebug() ) {
            $flags |= JSON_PRETTY_PRINT;
        }

        return json_encode( $this->buildJsonPayload(), $flags );
    }

    /*
    |------------------------------------------
    | PRIVATE HTML RENDERING HELPER METHODS
    |------------------------------------------
    */

    /**
     * Render HTML head section.
     *
     * @return string
     */
    private function renderHead() : string {
        $html    = '';
        $charset = $this->getCharset();

        $html .= "\n\t<meta charset=\"" . htmlspecialchars( $charset, ENT_QUOTES, 'UTF-8' ) . '">' . "\n";
        $html .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";

        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'meta' ) {
                $html .= "\t<meta";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
                }
                $html .= ">\n";
            }
        }

        $html .= "\t<title>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</title>\n";
        $html .= $this->renderStyles();

        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'link' ) {
                $html .= "\t<link";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
                }
                $html .= ">\n";
            }
        }

        return $html;
    }

    /**
     * Render default and customized CSS styles.
     *
     * @return string
     */
    private function renderStyles() : string {
        $default_styles = [
            'body' => [
                'font-family' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
                'background'  => '#f8f9fa',
                'color'       => '#212529',
                'margin'      => '0',
                'padding'     => '0',
            ],
            '.error-container' => [
                'width'         => '960px',
                'margin'        => '20px auto',
                'background'    => '#ffffff',
                'padding'       => '20px',
                'border-radius' => '8px',
                'border'        => '1px solid #e9ecef',
                'box-shadow'    => '0 4px 12px rgba(0, 0, 0, 0.05)',
                'overflow-wrap' => 'anywhere',
                'max-width'         => '90%'
            ],
            'h1' => [
                'color'       => '#d9534f',
                'margin-top'  => '0',
                'font-size'   => '22px',
                'font-weight' => '600',
            ],
            'p' => [
                'font-size'   => '15px',
                'line-height' => '1.6',
                'color'       => '#495057',
            ],
            'a' => [
                'color'           => '#0d6efd',
                'text-decoration' => 'none',
            ],
            'a:hover' => [
                'text-decoration' => 'underline',
            ],
            'pre' => [
                'max-width'        => '100%',
                'background-color' => '#212529',
                'color'            => '#f8f9fa',
                'overflow-x'       => 'auto',
                'padding'          => '16px',
                'border-radius'    => '6px',
                'font-size'        => '13px',
                'line-height'      => '1.5',
            ],
        ];

        $all_styles = array_merge( $default_styles, $this->styles );

        $css = "\t<style>\n";
        foreach ( $all_styles as $selector => $properties ) {
            $css .= "\t\t" . $selector . " {\n";
            foreach ( $properties as $property => $value ) {
                $css .= "\t\t\t" . $property . ": " . $value . ";\n";
            }
            $css .= "\t\t}\n";
        }
        $css .= "\t</style>\n";

        return $css;
    }

    /**
     * Render HTML attributes key-value list into string.
     *
     * @param array $attributes Attributes array.
     * @return string
     */
    private function renderAttributes( array $attributes ) : string {
        $html = '';
        foreach ( $attributes as $key => $value ) {
            if ( is_array( $value ) ) {
                $value = implode( ' ', $value );
            }
            $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ) . '="' . htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ) . '"';
        }
        return $html;
    }

    /**
     * Render HTML action links safely without CSP violations.
     *
     * @return string
     */
    private function renderLinks() : string {
        $html = '';

        if ( ! empty( $this->config['link_url'] ) && ! empty( $this->config['link_text'] ) ) {
            $html .= '<p><a href="' . htmlspecialchars( $this->config['link_url'], ENT_QUOTES, 'UTF-8' ) . '">';
            $html .= htmlspecialchars( $this->config['link_text'], ENT_QUOTES, 'UTF-8' );
            $html .= '</a></p>' . "\n";
        }

        if ( $this->config['back_link'] && isset( $_SERVER['HTTP_REFERER'] ) ) {
            $referer = filter_var( $_SERVER['HTTP_REFERER'], FILTER_SANITIZE_URL );
            if ( $referer ) {
                $html .= '<p><a href="' . htmlspecialchars( $referer, ENT_QUOTES, 'UTF-8' ) . '">&larr; Go Back</a></p>' . "\n";
            }
        }

        return $html;
    }

    /**
     * Wrap content string in full HTML layout document.
     *
     * @param string $content Inner HTML snippet.
     * @return string
     */
    private function wrapInHtml( string $content ) : string {
        $html_attrs = $this->renderAttributes( $this->html_attributes );
        $body_attrs = $this->renderAttributes( $this->body_attributes );

        $html  = "<!DOCTYPE html>\n";
        $html .= "<html" . $html_attrs . ">\n";
        $html .= "<head>\n";
        $html .= $this->renderHead();
        $html .= "</head>\n";
        $html .= "<body" . $body_attrs . ">\n";
        $html .= "\t<div class=\"error-container\">\n";
        $html .= "\t\t<h1>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</h1>\n";
        $html .= "\t\t<div class=\"error-message\">\n";
        $html .= "\t\t\t" . $content . "\n";
        $html .= "\t\t</div>\n";

        $links = $this->renderLinks();
        if ( ! empty( $links ) ) {
            $html .= "\t\t<div class=\"error-links\">\n";
            $html .= $links;
            $html .= "\t\t</div>\n";
        }

        $html .= "\t</div>\n";
        $html .= "</body>\n";
        $html .= "</html>";

        return $html;
    }

    /**
     * Render Throwable in HTML format.
     *
     * @return string
     */
    private function renderThrowableInHtml() : string {
        $nl      = \PHP_EOL;
        $message = $this->getMessage();

        if ( ! $this->isDebug() ) {
            return $this->wrapInHtml( '<p>' . htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ) . '</p>' );
        }

        $class = get_class( $this->error_object );
        $file  = $this->error_object->getFile();
        $line  = $this->error_object->getLine();

        $out  = htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' ) . ': ';
        $out .= htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ) . $nl . $nl;
        $out .= 'File: ' . htmlspecialchars( $file, ENT_QUOTES, 'UTF-8' ) . $nl;
        $out .= 'Line: ' . $line . $nl;
        $out .= $nl . 'Stack Trace:' . $nl;
        $out .= htmlspecialchars( $this->error_object->getTraceAsString(), ENT_QUOTES, 'UTF-8' ) . $nl;

        $previous = $this->error_object->getPrevious();
        if ( $previous ) {
            $out .= $nl . 'Caused by: ' . get_class( $previous ) . $nl;
            $out .= htmlspecialchars( $previous->getMessage(), ENT_QUOTES, 'UTF-8' ) . $nl;
            $out .= htmlspecialchars( $previous->getTraceAsString(), ENT_QUOTES, 'UTF-8' ) . $nl;
        }

        return $this->wrapInHtml( '<pre>' . $out . '</pre>' );
    }

    /*
    |------------------------------------------
    | ABSTRACT METHOD IMPLEMENTATIONS
    |------------------------------------------
    */

    /**
     * Render complete error response in appropriate format (JSON or HTML).
     *
     * @return string
     */
    public function render() : string {
        if ( $this->getPreferredFormat() === 'json' ) {
            return $this->renderJson();
        }

        if ( $this->error_object instanceof \Throwable ) {
            return $this->renderThrowableInHtml();
        }

        return $this->wrapInHtml( '<p>' . htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' ) . '</p>' );
    }

    /**
     * Render warning or non-fatal minor error.
     *
     * @return string
     */
    public function renderWarning() : string {
        if ( $this->getPreferredFormat() === 'json' ) {
            if ( ! headers_sent() ) {
                $clean_msg = str_replace( [ "\r", "\n" ], ' ', $this->getMessage() );
                $header_val = sprintf( '%s: %s', $this->getTitle(), $clean_msg );
                header( 'X-Server-Warning: ' . ( $header_val ), false );
            }
            return ''; // Return empty string so echoing it doesn't corrupt stdout/JSON streams.
        }

        $html  = '<div style="background:#fff3cd;border:1px solid #ffc107;color:#664d03;padding:12px 16px;margin:12px 0;border-radius:4px;font-family:sans-serif;font-size:14px;">' . PHP_EOL;
        $html .= '<strong>' . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . ':</strong> ';
        $html .= htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' );

        if ( $this->isDebug() && $this->error_object instanceof \Throwable ) {
            $html .= '<br><small style="opacity:0.8;font-family:monospace;">';
            $html .= htmlspecialchars( $this->error_object->getFile(), ENT_QUOTES, 'UTF-8' );
            $html .= ':' . $this->error_object->getLine();
            $html .= '</small>';
        }

        $html .= PHP_EOL . '</div>' . PHP_EOL;
        return $html;
    }

    /**
     * Send HTTP response headers configured for target response format.
     *
     * @return static
     */
    public function sendHeaders() : static {
        if ( headers_sent() ) {
            return $this;
        }

        http_response_code( $this->getResponseCode() );

        if ( $this->getPreferredFormat() === 'json' ) {
            header( 'Content-Type: application/json; charset=' . $this->getCharset() );
            header( 'X-Content-Type-Options: nosniff' );
            return $this;
        }

        // HTML Response Security Headers
        header( 'Content-Type: text/html; charset=' . $this->getCharset() );
        header( 'X-Content-Type-Options: nosniff' );
        header( 'X-Frame-Options: DENY' );
        header( 'X-XSS-Protection: 1; mode=block' );
        header(
            sprintf(
                "Content-Security-Policy: default-src %1\$s; style-src %1\$s %2\$s; script-src %1\$s; img-src %1\$s data:; font-src %1\$s;",
                "'self'",
                "'unsafe-inline'"
            )
        );

        return $this;
    }
}