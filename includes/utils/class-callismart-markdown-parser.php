<?php
/**
 * Smliser Markdown parser (CommonMark wrapper + fallback).
 *
 * - Namespace: SmartLicenseServer\MDParser
 * - Uses league/commonmark when available.
 * - Keeps WP-style headings via preprocess_wp_headings().
 *
 * @package SmartLicenseServer\MDParser
 * @since 1.0.0
 */

namespace SmartLicenseServer\Utils;

defined( 'ABSPATH' ) || exit;

use Exception;

// CommonMark classes (conditionally used).
// We import them so PHPStan/IDE can find them when installed.
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;

/**
 * Class Smliser_Markdown_Parser
 *
 */
class MDParser {

	/**
	 * Converter instance (CommonMark) or null when using fallback.
	 *
	 * @var CommonMarkConverter|null
	 */
	protected $converter;

	/**
	 * Constructor.
	 *
	 * If league/commonmark is available it will be initialized.
	 *
	 * @param array $config Optional config for CommonMark environment.
	 */
	public function __construct( $config = array() ) {
		$this->converter = null;

		// Default safe config.
		$default = array(
			'html_input'         => 'strip',
			'allow_unsafe_links' => false,
		);

		$config = array_merge( $default, (array) $config );

		// Initialize CommonMark if available.
		if ( class_exists( '\League\CommonMark\CommonMarkConverter' ) ) {
			try {
				$environment = new Environment( $config );
				$environment->addExtension( new CommonMarkCoreExtension() );

				// Add common extensions: GFM features + tables.
				// You can remove extensions you don't want to enable.
				if ( class_exists( '\League\CommonMark\Extension\GithubFlavoredMarkdown\GithubFlavoredMarkdownExtension' ) ) {
					$environment->addExtension( new GithubFlavoredMarkdownExtension() );
				}

				if ( class_exists( '\League\CommonMark\Extension\Table\TableExtension' ) ) {
					$environment->addExtension( new TableExtension() );
				}

				$this->converter = new CommonMarkConverter( array(), $environment );
			} catch ( Exception $e ) {
				// If CommonMark fails to initialize, fall back gracefully.
				$this->converter = null;
			}
		}
	}

	/**
	 * Parse Markdown (or WP-style markup) to HTML.
	 *
	 * This is the single method used by SMLISER code. Keep signature stable.
	 *
	 * @param string $text Raw markdown / content.
	 * @return string HTML output (safe-ish).
	 */
	public function parse( $text ) {
		$text = (string) $text;

		// 1) Preprocess WP-style headings (dedicated method per your request).
		$text = $this->preprocess_wp_headings( $text );

		// 2) Use CommonMark when available, otherwise fallback.
		if ( $this->converter instanceof CommonMarkConverter ) {
			return $this->parse_with_commonmark( $text );
		}

		return $this->fallback_parse( $text );
	}

	/**
	 * Preprocess WordPress-style headings and convert them into standard Markdown headings.
	 *
	 * Keeps processing isolated in this dedicated method.
	 *
	 * Examples:
	 *   === Heading ===  -> # Heading
	 *   == Heading ==    -> ## Heading
	 *   = Heading =      -> ### Heading
	 *
	 * @param string $text Raw content.
	 * @return string Converted content where WP headings become Markdown headings.
	 */
	protected function preprocess_wp_headings( $text ) {
		// h1: === Heading ===  -> # Heading
		$text = preg_replace_callback(
			'/^===\s*(.+?)\s*===$/m',
			function( $matches ) {
				return '# ' . trim( $matches[1] );
			},
			$text
		);

		// h2: == Heading == -> ## Heading
		$text = preg_replace_callback(
			'/^==\s*(.+?)\s*==$/m',
			function( $matches ) {
				return '## ' . trim( $matches[1] );
			},
			$text
		);

		// h3: = Heading = -> ### Heading
		$text = preg_replace_callback(
			'/^=\s*(.+?)\s*=$/m',
			function( $matches ) {
				return '### ' . trim( $matches[1] );
			},
			$text
		);

		return $text;
	}

	/**
	 * Convert using league/commonmark.
	 *
	 * @param string $text Preprocessed markdown.
	 * @return string HTML produced by CommonMark.
	 */
	protected function parse_with_commonmark( $text ) {
		try {
			// Convert returns an object that may implement __toString() or have getContent().
			$result = $this->converter->convert( $text );

			// Support both CommonMark v2 (stringable) and v1 (Converter->convertToHtml).
			if ( is_string( $result ) ) {
				return $result;
			}

			if ( method_exists( $result, 'getContent' ) ) {
				return $result->getContent();
			}

			// Last resort cast to string.
			return (string) $result;
		} catch ( Exception $e ) {
			// If conversion fails for any reason, fall back to simple parser.
			return $this->fallback_parse( $text );
		}
	}

	/**
	 * Lightweight fallback parser (based on your original logic).
	 *
	 * This keeps basic Markdown features working even if CommonMark isn't installed.
	 *
	 * @param string $text Input text.
	 * @return string HTML output.
	 */
	protected function fallback_parse( $text ) {
		$text = $this->parse_headings( $text ); // keep supporting any leftover heading forms
		$text = $this->parse_lists( $text );
		$text = $this->parse_paragraphs( $text );
		$text = $this->parse_strongs( $text );
		$text = $this->parse_anchors( $text );

		return $text;
	}

	/* -------------------------------------------------------------------------
	 * The fallback helper methods are adapted from your original parser, with
	 * minor hardening. They are only used when CommonMark is unavailable.
	 * ---------------------------------------------------------------------- */

	/**
	 * Parse (leftover) headings (# style) - used by fallback only.
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	protected function parse_headings( $text ) {
		// Standard Markdown-style headings (# to ######)
		for ( $i = 6; $i >= 1; $i-- ) {
			$pattern = '/^' . str_repeat( '#', $i ) . '\s*(.+?)\s*$/m';
			$text    = preg_replace(
				$pattern,
				'<h' . $i . '>$1</h' . $i . '>',
				$text
			);
		}

		return $text;
	}

	/**
	 * Parse unordered and ordered lists (fallback).
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	protected function parse_lists( $text ) {
		// Unordered lists: group contiguous lines starting with - or *
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*[-*]\s+.+\n?)+)/m',
			function( $matches ) {
				$items = preg_split( '/\n+/', trim( $matches[1] ) );
				$list  = '<ul>';
				foreach ( $items as $item ) {
					$item = preg_replace( '/^[ \t]*[-*]\s+/', '', $item );
					$list .= '<li>' . esc_html( trim( $item ) ) . '</li>';
				}
				$list .= '</ul>';
				return $list;
			},
			$text
		);

		// Ordered lists: group contiguous lines starting with digits + dot
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*\d+\.\s+.+\n?)+)/m',
			function( $matches ) {
				$items = preg_split( '/\n+/', trim( $matches[1] ) );
				$list  = '<ol>';
				foreach ( $items as $item ) {
					$item = preg_replace( '/^[ \t]*\d+\.\s+/', '', $item );
					$list .= '<li>' . esc_html( trim( $item ) ) . '</li>';
				}
				$list .= '</ol>';
				return $list;
			},
			$text
		);

		return $text;
	}

	/**
	 * Parse anchor tags ([text](url)) - fallback.
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	protected function parse_anchors( $text ) {
		return preg_replace_callback(
			'/\[(.*?)\]\((.*?)\)/',
			function( $matches ) {
				$link_text = isset( $matches[1] ) ? $matches[1] : '';
				$href      = isset( $matches[2] ) ? $matches[2] : '';

				// Use WP esc helpers if available, otherwise basic escaping.
				if ( function_exists( 'esc_url' ) ) {
					$href = esc_url( $href );
				} else {
					$href = htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' );
				}

				if ( function_exists( 'esc_html' ) ) {
					$link_text = esc_html( $link_text );
				} else {
					$link_text = htmlspecialchars( $link_text, ENT_QUOTES, 'UTF-8' );
				}

				return '<a href="' . $href . '" target="_blank" rel="noopener noreferrer">' . $link_text . '</a>';
			},
			$text
		);
	}

	/**
	 * Wrap paragraph blocks (fallback).
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	protected function parse_paragraphs( $text ) {
		$paragraphs     = preg_split( '/\n\s*\n/', $text );
		$html           = '';
		$block_tags     = array( 'p', 'ol', 'ul', 'li', 'h[1-6]' );
		$block_tag_pattern = '/<(' . implode( '|', $block_tags ) . ')[^>]*>.*<\/\1>/is';

		foreach ( $paragraphs as $paragraph ) {
			$trimmed = trim( $paragraph );

			if ( $trimmed === '' ) {
				continue;
			}

			// If paragraph already contains block-level tags, preserve it.
			if ( preg_match( $block_tag_pattern, $trimmed ) ) {
				$html .= $trimmed;
			} else {
				if ( function_exists( 'esc_html' ) ) {
					$html .= '<p>' . esc_html( $trimmed ) . '</p>';
				} else {
					$html .= '<p>' . htmlspecialchars( $trimmed, ENT_QUOTES, 'UTF-8' ) . '</p>';
				}
			}
		}

		return $html;
	}

	/**
	 * Parse bold **strong** (fallback).
	 *
	 * @param string $text Input.
	 * @return string Output.
	 */
	protected function parse_strongs( $text ) {
		$text = preg_replace_callback(
			'/\*\*(.+?)\*\*/s',
			function( $matches ) {
				$content = $matches[1];

				// If content looks like an HTML tag, don't double-wrap.
				if ( preg_match( '/^\s*<[^>]+>.*<\/[^>]+>\s*$/s', $content ) ) {
					return $matches[0];
				}

				if ( function_exists( 'esc_html' ) ) {
					$content = esc_html( $content );
				} else {
					$content = htmlspecialchars( $content, ENT_QUOTES, 'UTF-8' );
				}

				return '<strong>' . $content . '</strong>';
			},
			$text
		);

		return $text;
	}
}

// Example helper to get a single instance (optional).
/**
 * Returns the Smliser Markdown parser instance.
 *
 * @return Smliser_Markdown_Parser
 */
function smliser_markdown_parser() {
	static $instance = null;

	if ( null === $instance ) {
		$instance = new MDParser();
	}

	return $instance;
}
