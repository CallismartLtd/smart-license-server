<?php
/**
 * WP Readme.txt parser
 *
 * Converts WordPress.org readme.txt content into standard Markdown,
 * so it can be processed by a Markdown parser like MDParser.
 *
 * - Namespace: SmartLicenseServer\MDParser
 * - Converts WP headings and bullet/numbered lists into Markdown format.
 *
 * @package SmartLicenseServer\MDParser
 * @since 1.0.0
 */

namespace SmartLicenseServer\MDParser;

defined( 'ABSPATH' ) || exit;

/**
 * Class WPReadmeParser
 *
 * Converts WP-style headings and lists into standard Markdown.
 */
class WPReadmeParser {

    /**
     * Convert WP readme.txt content to standard Markdown.
     *
     * @param string $text Raw WP readme.txt content.
     * @return string Markdown-ready content.
     */
    public static function parse( $text ) {
        $text = (string) $text;

        // Normalize line endings
        $text = str_replace( ["\r\n", "\r"], "\n", $text );

        // 1) Convert WP-style headings
        // === Heading === -> # Heading
        $text = preg_replace_callback(
            '/^===\s*(.+?)\s*===$/m',
            fn( $matches ) => '# ' . trim( $matches[1] ),
            $text
        );

        // == Heading == -> ## Heading
        $text = preg_replace_callback(
            '/^==\s*(.+?)\s*==$/m',
            fn( $matches ) => '## ' . trim( $matches[1] ),
            $text
        );

        // = Heading = -> ### Heading
        $text = preg_replace_callback(
            '/^=\s*(.+?)\s*=$/m',
            fn( $matches ) => '### ' . trim( $matches[1] ),
            $text
        );

        // 2) Convert unordered lists: normalize lines starting with * or -
        $text = preg_replace('/^[ \t]*[\*\-]\s+/m', '- ', $text);

        // 3) Convert numbered lists: normalize lines like 1. 2. etc.
        $text = preg_replace('/^[ \t]*(\d+)\.\s+/m', '$1. ', $text);

        // Trim excess whitespace
        return trim( $text );
    }
}
