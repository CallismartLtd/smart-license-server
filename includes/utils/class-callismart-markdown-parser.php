<?php
/**
 * Markdown-to-HTML parser class file.
 * 
 * @author Callistus Nwachukwu
 * @package Smliser\class
 * @since 1.0.0
 */

defined('ABSPATH') || exit;

/**
 * Markdown-to-HTML parser class.
 */
class Callismart_Markdown_to_HTML {
    /**
     * HTML tag properties.
     */
    protected $headings = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'];
    protected $paragraph = '<p>|</p>';
    protected $unordered_list = '<ul>|</ul>';
    protected $ordered_list = '<ol>|</ol>';
    protected $list_item = '<li>|</li>';
    protected $anchor = '<a>';
    protected $line_break = '<br>';

    /**
     * Parse raw Markdown-style text into HTML.
     *
     * @param string $raw_text The raw text to parse.
     * @return string The parsed HTML content.
     */
    public function parse( $html ) {
        $html = $this->parse_headings( $html );
        $html = $this->parse_lists( $html );
        $html = $this->parse_paragraphs( $html );
        $html = $this->parse_strongs( $html );
        $html = $this->parse_anchors( $html );
    
        return $html;
    }

    /**
     * Parse headings (supports both WordPress and standard Markdown syntax).
     *
     * WordPress:
     * - "=== Heading ===" to <h1>
     * - "== Heading ==" to <h2>
     * - "= Heading =" to <h3>
     *
     * Standard Markdown:
     * - "# Heading 1" to <h1>
     * - "## Heading 2" to <h2>
     * - ... up to "###### Heading 6" to <h6>
     *
     * @param string $text The raw text to parse.
     * @return string The text with headings converted to HTML.
     */
    protected function parse_headings( $text ) {
        // --- WordPress-style headings ---
        $text = preg_replace_callback(
            '/^===\s*(.+?)\s*===$/m',
            function( $matches ) {
                return '<h1>' . trim( $matches[1] ) . '</h1>';
            },
            $text
        );

        $text = preg_replace_callback(
            '/^==\s*(.+?)\s*==$/m',
            function( $matches ) {
                return '<h2>' . trim( $matches[1] ) . '</h2>';
            },
            $text
        );

        $text = preg_replace_callback(
            '/^=\s*(.+?)\s*=$/m',
            function( $matches ) {
                return '<h3>' . trim( $matches[1], ' =' ) . '</h3>';
            },
            $text
        );

        // --- Standard Markdown-style headings (# to ######) ---
        for ( $i = 6; $i >= 1; $i-- ) {
            $pattern = '/^' . str_repeat( '#', $i ) . '\s*(.+?)\s*$/m';
            $replacement = '<h' . $i . '>$1</h' . $i . '>';
            $text = preg_replace_callback(
                $pattern,
                function( $matches ) {
                    return '<h' . strlen( preg_replace( '/^#+/', '', $matches[0] ) ) . '>' . trim( $matches[1] ) . '</h' . strlen( preg_replace( '/^#+/', '', $matches[0] ) ) . '>';
                },
                $text
            );
        }

        return $text;
    }

    /**
     * Parse unordered and ordered lists.
     *
     * @param string $text The raw text to parse.
     * @return string The text with lists converted to HTML.
     */
    protected function parse_lists($text) {
        // Parse unordered lists
        $text = preg_replace_callback(
            '/(?:^|\n)([-*])\s+(.+?)(?=\n|$)/m',
            function ($matches) {
                // Matches unordered list items
                $items = explode("\n", trim($matches[0]));
                $list = '<ul>';
                foreach ($items as $item) {
                    $list .= '<li>' . esc_html(preg_replace('/^[-*]\s+/', '', $item)) . '</li>';
                }
                $list .= '</ul>';
                return $list;
            },
            $text
        );

        // Parse ordered lists
        $text = preg_replace_callback(
            '/(?:^|\n)(\d+\.)\s+(.+?)(?=\n|$)/m',
            function ($matches) {
                // Matches ordered list items
                $items = explode("\n", trim($matches[0]));
                $list = '<ol>';
                foreach ($items as $item) {
                    $list .= '<li>' . esc_html(preg_replace('/^\d+\.\s+/', '', $item)) . '</li>';
                }
                $list .= '</ol>';
                return $list;
            },
            $text
        );

        return $text;
    }


    /**
     * Parse anchor tags ([Link Text](url)).
     *
     * @param string $text The raw text to parse.
     * @return string The text with links converted to HTML.
     */
    protected function parse_anchors($text) {
        return preg_replace_callback(
            '/\[(.*?)\]\((.*?)\)/',
            function ($matches) {
                $text = esc_html($matches[1]);
                $url = esc_url($matches[2]);
                return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
            },
            $text
        );
    }

    /**
     * Parse paragraphs (add <p> tags around blocks of text).
     *
     * @param string $text The raw text to parse.
     * @return string The text with paragraphs converted to HTML.
     */
    protected function parse_paragraphs( $text ) {
        $ptags = explode( '|', $this->paragraph );
        $paragraphs = preg_split( '/\n\s*\n/', $text );
        $html = '';
    
        // Define the list of tags to check for (p, ol, ul, li, h1-h6).
        $block_tags = [
            'p', 'ol', 'ul', 'li', 'h[1-6]'
        ];
    
        // Pattern to detect any block-level tag inside the paragraph (including nested tags)
        $block_tag_pattern = '/<(' . implode( '|', $block_tags ) . ')[^>]*>.*<\/\1>/is';
    
        foreach ( $paragraphs as $paragraph ) {
            $trimmed_paragraph = trim( $paragraph );
    
            // Check if the paragraph already contains any block-level tags (even nested tags)
            if ( preg_match( $block_tag_pattern, $trimmed_paragraph ) ) {
                // If a block-level tag is found inside the paragraph, don't wrap it in additional tags
                $html .= $trimmed_paragraph;
            } else {
                // If no block-level tags are found, wrap it in the defined paragraph tags
                $html .= $ptags[0] . esc_html( $trimmed_paragraph ) . $ptags[1];
            }
        }
    
        return $html;
    }

    /**
     * Parse the markdown bold **string** syntax and replace with <strong> tag.
     */
    protected function parse_strongs( $text ) {
        // Use preg_replace with a callback to replace **bold text** with <strong>bold text</strong>
        $text = preg_replace_callback(
            '/\*\*(.+?)\*\*/', // Match text between ** **
            function( $matches ) {
                // Check if the content inside is already an HTML tag (e.g., <strong>bold</strong>)
                if ( str_starts_with( $matches[1], '<' ) && str_ends_with( $matches[1], '>' ) ) {
                    // Return the matched content as is (don't replace)
                    return $matches[0];
                }
                // Otherwise, wrap the content inside <strong> tags
                return '<strong>' . $matches[1] . '</strong>';
            },
            $text // The input text
        );

        return $text;
    }
}

$GLOBALS['smliser_md_html'] = new Callismart_Markdown_to_HTML();
