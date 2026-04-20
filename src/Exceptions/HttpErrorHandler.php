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

    /**
     * HTML head content.
     *
     * @var array
     */
    private array $head_content = [];

    /**
     * CSS styles.
     *
     * @var array
     */
    private array $styles = [];

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

    /**
     * Render HTML head section.
     *
     * @return string
     */
    private function renderHead() : string {
        $html = '';

        // Meta tags.
        $html .= "\n\t<meta charset=\"" . htmlspecialchars( $this->config['charset'], ENT_QUOTES ) . '">' . "\n";
        $html .= "\t<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";

        // Additional meta tags.
        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'meta' ) {
                $html .= "\t<meta";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
                }
                $html .= ">\n";
            }
        }

        $html .= "\t<title>" . htmlspecialchars( $this->getTitle(), ENT_QUOTES, 'UTF-8' ) . "</title>\n";

        // Styles.
        $html .= $this->renderStyles();

        // Additional links.
        foreach ( $this->head_content as $item ) {
            if ( $item['type'] === 'link' ) {
                $html .= "\t<link";
                foreach ( $item['data'] as $key => $value ) {
                    $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
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
                'padding' => '50px',
                'margin' => '0',
            ],
            '.error-container' => [
                'max-width' => '80%',
                'margin' => 'auto',
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
                'scrollbar-width' => 'thin',
                'border-radius' => '4px',
            ],
        ];

        // Merge with custom styles.
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
            $html .= ' ' . htmlspecialchars( $key, ENT_QUOTES ) . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
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
            $html .= '<p><a href="' . htmlspecialchars( $this->config['link_url'], ENT_QUOTES ) . '">';
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
        $html .= "\t\t\t<pre>" . $content . "</pre>\n";
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
     * Render complete HTML - DELEGATES TO EXCEPTION CLASS FOR RENDERING.
     *
     * If rendering an Exception, uses its render() method.
     * This respects the Exception class's formatting and data structure.
     *
     * @return string
     */
    public function render() : string {
        // If rendering an Exception, use its render() method.
        if ( $this->error_object instanceof Exception ) {
            // For HTTP, use trace based on debug configuration.
            $include_trace = static::$global_debug_mode;
            $rendered = $this->error_object->render( $include_trace );
            
            // Wrap Exception's output in HTML template.
            return $this->wrapInHtml( $rendered );
        }

        // Fallback for string messages.
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
        $html .= "\t\t\t" . $this->getMessage() . "\n";
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

    /**
     * Static factory method.
     *
     * @param string|Exception $message Error message or exception.
     * @param string|int $title Error title or HTTP code.
     * @param array|int $args Additional arguments or HTTP code.
     * @return static
     */
    public static function create( $message = '', $title = '', $args = [] ) : static {
        return new static( $message, $title, $args );
    }
}