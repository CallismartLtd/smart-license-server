<?php
/**
 * HTTP Error Handler Class for Smart License Server.
 *
 * Specializes in rendering errors for web/HTTP environments.
 * Handles HTML output, HTTP headers, CSS styling, and web-specific features.
 *
 * @package SmartLicenseServer\Exceptions
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Exceptions;

class HttpErrorHandler extends AbstractErrorHandler {

    /*
    |------------------------------------------
    | HTML HEAD CUSTOMIZATION
    |------------------------------------------
    */

    /**
     * HTML head content.
     *
     * @var array
     */
    private array $head_content = [];

    /**
     * Add meta tag to HTML head.
     *
     * @param string $name Attribute name.
     * @param string $content Attribute content.
     * @param array $attributes Additional attributes.
     * @return self
     */
    public function addMeta( string $name, string $content, array $attributes = [] ) : self {
        $meta = array_merge(
            [ 'name' => $name, 'content' => $content ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'meta', 'data' => $meta ];
        return $this;
    }

    /**
     * Add link to HTML head.
     *
     * @param string $rel Relationship type.
     * @param string $href URL.
     * @param array $attributes Additional attributes.
     * @return self
     */
    public function addLink( string $rel, string $href, array $attributes = [] ) : self {
        $link = array_merge(
            [ 'rel' => $rel, 'href' => $href ],
            $attributes
        );
        $this->head_content[] = [ 'type' => 'link', 'data' => $link ];
        return $this;
    }

    /*
    |------------------------------------------
    | CSS STYLING
    |------------------------------------------
    */

    /**
     * CSS styles.
     *
     * @var array
     */
    private array $styles = [];

    /**
     * Add custom style.
     *
     * @param string $selector CSS selector.
     * @param array $properties CSS properties as key-value pairs.
     * @return self
     */
    public function addStyle( string $selector, array $properties ) : self {
        $this->styles[ $selector ] = $properties;
        return $this;
    }

    /**
     * Set multiple styles at once.
     *
     * @param array $styles Styles array (selector => properties).
     * @return self
     */
    public function setStyles( array $styles ) : self {
        $this->styles = array_merge( $this->styles, $styles );
        return $this;
    }

    /*
    |------------------------------------------
    | HTML ATTRIBUTES
    |------------------------------------------
    */

    /**
     * Custom attributes for HTML tag.
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

    /**
     * Set custom HTML attributes.
     *
     * @param array $attributes Attributes for html tag.
     * @return self
     */
    public function setHtmlAttributes( array $attributes ) : self {
        $this->html_attributes = array_merge( $this->html_attributes, $attributes );
        return $this;
    }

    /**
     * Set custom body attributes.
     *
     * @param array $attributes Attributes for body tag.
     * @return self
     */
    public function setBodyAttributes( array $attributes ) : self {
        $this->body_attributes = array_merge( $this->body_attributes, $attributes );
        return $this;
    }

    /**
     * Add CSS class to body tag.
     *
     * @param string $class CSS class name.
     * @return self
     */
    public function addBodyClass( string $class ) : self {
        $existing = $this->body_attributes['class'] ?? '';
        $this->body_attributes['class'] = trim( $existing . ' ' . $class );
        return $this;
    }

    /*
    |------------------------------------------
    | PRIVATE RENDERING METHODS
    |------------------------------------------
    */

    /**
     * Render HTML head section.
     *
     * @return string
     */
    private function renderHead() : string {
        $html = '';
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
     * Render CSS styles.
     *
     * @return string
     */
    private function renderStyles() : string {
        $default_styles = [
            'body' => [
                'font-family' => 'Arial, sans-serif',
                'background' => '#f4f4f4',
                'margin' => '0',
            ],
            '.error-container' => [
                'max-width' => '80%',
                'margin' => '50px auto',
                'background' => '#ffffff',
                'padding' => '30px',
                'border-radius' => '8px',
                'box-shadow' => '0px 4px 15px rgba(0, 0, 0, 0.1)',
                'overflow-wrap' => 'anywhere',
            ],
            'h1' => [
                'color' => '#e74c3c',
                'margin-top' => '0',
                'font-size' => '24px',
            ],
            'p' => [
                'font-size' => '16px',
                'color' => '#333',
            ],
            'a' => [
                'color' => '#3498db',
                'text-decoration' => 'none',
            ],
            'a:hover' => [
                'text-decoration' => 'underline',
            ],
            'pre' => [
                'max-width' => '100%',
                'background-color' => '#f1f1f1',
                'overflow-x' => 'auto',
                'padding' => '10px',
                'border-radius' => '4px',
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
     * Render HTML attributes.
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
     * Render link HTML.
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

        if ( $this->config['back_link'] ) {
            $html .= '<p><a href="javascript:history.back()">Go Back</a></p>' . "\n";
        }

        return $html;
    }

    /**
     * Wrap text content in HTML template.
     *
     * @param string $content The content to wrap.
     * @return string
     */
    private function wrapInHtml( string $content ) : string {
        $html_attrs = $this->renderAttributes( $this->html_attributes );
        $body_attrs = $this->renderAttributes( $this->body_attributes );

        $html = "<!DOCTYPE html>\n";
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
        $nl = "\n";
        $class = get_class( $this->error_object );
        $file = $this->error_object->getFile();
        $line = $this->error_object->getLine();
        $message = $this->getMessage();

        $out = '';
        $out .= htmlspecialchars( $class, ENT_QUOTES, 'UTF-8' ) . $nl;
        $out .= htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' ) . $nl . $nl;
        $out .= 'File: ' . htmlspecialchars( $file, ENT_QUOTES, 'UTF-8' ) . $nl;
        $out .= 'Line: ' . $line . $nl;

        if ( $this->isDebug() ) {
            $out .= $nl . 'Stack Trace:' . $nl;
            $out .= htmlspecialchars( $this->error_object->getTraceAsString(), ENT_QUOTES, 'UTF-8' ) . $nl;

            $previous = $this->error_object->getPrevious();
            if ( $previous ) {
                $out .= $nl . 'Caused by: ' . get_class( $previous ) . $nl;
                $out .= htmlspecialchars( $previous->getMessage(), ENT_QUOTES, 'UTF-8' ) . $nl;
                $out .= htmlspecialchars( $previous->getTraceAsString(), ENT_QUOTES, 'UTF-8' ) . $nl;
            }
        }

        $out = '<pre>' . $out . '</pre>';
        return $this->wrapInHtml( $out );
    }

    /*
    |------------------------------------------
    | ABSTRACT METHODS
    |------------------------------------------
    */

    /**
     * Render error HTML.
     *
     * @return string
     */
    public function render() : string {
        if ( $this->error_object instanceof \Throwable ) {
            return $this->renderThrowableInHtml();
        }

        return $this->wrapInHtml( htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' ) );
    }

    /**
     * Render warning/minor error as inline notice.
     *
     * Lightweight output without full page wrapper.
     * Used for non-fatal errors in debug mode.
     *
     * @return string
     */
    public function renderWarning() : string {
        $html = '<div style="background:#fff3cd;border:1px solid #ffc107;color:#664d03;padding:12px;margin:10px 0;border-radius:4px;">' . PHP_EOL;
        $html .= '<strong>' . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . ':</strong> ';
        $html .= htmlspecialchars( $this->getMessage(), ENT_QUOTES, 'UTF-8' );
        
        if ( $this->error_object instanceof \Throwable ) {
            $html .= '<br><small style="opacity:0.7;">';
            $html .= htmlspecialchars( $this->error_object->getFile(), ENT_QUOTES, 'UTF-8' );
            $html .= ':' . $this->error_object->getLine();
            $html .= '</small>';
        }
        
        $html .= PHP_EOL . '</div>' . PHP_EOL;
        return $html;
    }

    /**
     * Send HTTP headers.
     *
     * @return self
     */
    public function sendHeaders() : self {
        if ( ! headers_sent() ) {
            http_response_code( $this->getResponseCode() );
            header( 'Content-Type: text/html; charset=' . $this->getCharset() );
            header( 'X-Content-Type-Options: nosniff' );
            header( 'X-Frame-Options: DENY' );
            header( 'X-XSS-Protection: 1; mode=block' );

            header(
                "Content-Security-Policy: " .
                "default-src 'self'; " .
                "style-src 'self' 'unsafe-inline'; " .
                "script-src 'self'; " .
                "img-src 'self' data:; " .
                "font-src 'self';"
            );
        }
        
        return $this;
    }
}