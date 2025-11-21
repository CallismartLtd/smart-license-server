<?php
/**
 * Smliser Markdown parser (CommonMark wrapper + fallback).
 *
 * Pure Markdown → HTML conversion.
 * WP-specific syntax should be handled by a dedicated parser.
 *
 * @package SmartLicenseServer\MDParser
 * @since 1.0.0
 */

namespace SmartLicenseServer\Utils;

defined( 'ABSPATH' ) || exit;

use Exception;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use SmartLicenseServer\MDParser\WPReadmeParser;

/**
 * Class MDParser
 *
 * Converts Markdown to HTML, using CommonMark when available.
 */
class MDParser {

	/**
	 * CommonMark converter instance or null.
	 *
	 * @var CommonMarkConverter|null
	 */
	protected $converter;

	/**
	 * Constructor.
	 *
	 * @param array $config Optional CommonMark environment configuration.
	 */
	public function __construct( $config = array() ) {
		$this->converter = null;

		$default = array(
			'html_input'         => 'strip',
			'allow_unsafe_links' => false,
		);

		$config = array_merge( $default, (array) $config );

		if ( class_exists( CommonMarkConverter::class ) ) {
			try {
				$env = new Environment( $config );
				$env->addExtension( new CommonMarkCoreExtension() );
				if ( class_exists( GithubFlavoredMarkdownExtension::class ) ) {
					$env->addExtension( new GithubFlavoredMarkdownExtension() );
				}
				if ( class_exists( TableExtension::class ) ) {
					$env->addExtension( new TableExtension() );
				}
				$this->converter = new CommonMarkConverter( array(), $env );
			} catch ( Exception $e ) {
				$this->converter = null;
			}
		}
	}

	/**
	 * Main entry point: parse Markdown into HTML.
	 *
	 * @param string $text Markdown content.
	 * @return string HTML output.
	 */
	public function parse( $text ) {
		$text	= (string) $text;
		$text	= WPReadmeParser::parse( $text );

		if ( $this->converter instanceof CommonMarkConverter ) {
			return $this->parse_with_commonmark( $text );
		}

		return $this->fallback_parse( $text );
	}

	/**
	 * Convert using CommonMark.
	 *
	 * @param string $text Preprocessed Markdown.
	 * @return string HTML
	 */
	protected function parse_with_commonmark( $text ) {
		try {
			$result = $this->converter->convert( $text );

			if ( is_string( $result ) ) {
				return $result;
			}
			if ( method_exists( $result, 'getContent' ) ) {
				return $result->getContent();
			}

			return (string) $result;
		} catch ( Exception $e ) {
			return $this->fallback_parse( $text );
		}
	}

	/**
	 * Lightweight fallback Markdown parser.
	 *
	 * @param string $text Markdown text.
	 * @return string HTML
	 */
	protected function fallback_parse( $text ) {
		$text = $this->parse_headings( $text );
		$text = $this->parse_lists( $text );
		$text = $this->parse_paragraphs( $text );
		$text = $this->parse_strongs( $text );
		$text = $this->parse_anchors( $text );

		return $text;
	}

	/**
	 * Parse Markdown headings (# style).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_headings( $text ) {
		for ( $i = 6; $i >= 1; $i-- ) {
			$pattern = '/^' . str_repeat( '#', $i ) . '\s*(.+?)\s*$/m';
			$text    = preg_replace( $pattern, '<h' . $i . '>$1</h' . $i . '>', $text );
		}
		return $text;
	}

	/**
	 * Parse unordered and ordered lists.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_lists( $text ) {
		// Unordered
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*[-*]\s+.+\n?)+)/m',
			function( $matches ) {
				$items = preg_split( '/\n+/', trim( $matches[1] ) );
				$list  = '<ul>';
				foreach ( $items as $item ) {
					$item = preg_replace( '/^[ \t]*[-*]\s+/', '', $item );
					$list .= '<li>' . htmlspecialchars( trim( $item ), ENT_QUOTES, 'UTF-8' ) . '</li>';
				}
				$list .= '</ul>';
				return $list;
			},
			$text
		);

		// Ordered
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*\d+\.\s+.+\n?)+)/m',
			function( $matches ) {
				$items = preg_split( '/\n+/', trim( $matches[1] ) );
				$list  = '<ol>';
				foreach ( $items as $item ) {
					$item = preg_replace( '/^[ \t]*\d+\.\s+/', '', $item );
					$list .= '<li>' . htmlspecialchars( trim( $item ), ENT_QUOTES, 'UTF-8' ) . '</li>';
				}
				$list .= '</ol>';
				return $list;
			},
			$text
		);

		return $text;
	}

	/**
	 * Parse bold **strong** syntax.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_strongs( $text ) {
		return preg_replace_callback(
			'/\*\*(.+?)\*\*/s',
			function( $matches ) {
				$content = $matches[1];
				if ( preg_match( '/^\s*<[^>]+>.*<\/[^>]+>\s*$/s', $content ) ) {
					return $matches[0];
				}
				return '<strong>' . htmlspecialchars( $content, ENT_QUOTES, 'UTF-8' ) . '</strong>';
			},
			$text
		);
	}

	/**
	 * Parse anchor links [text](url).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_anchors( $text ) {
		return preg_replace_callback(
			'/\[(.*?)\]\((.*?)\)/',
			function( $matches ) {
				$link_text = $matches[1] ?? '';
				$href      = $matches[2] ?? '';
				return '<a href="' . htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' ) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars( $link_text, ENT_QUOTES, 'UTF-8' ) . '</a>';
			},
			$text
		);
	}

	/**
	 * Wrap paragraphs.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_paragraphs( $text ) {
		$paragraphs = preg_split( '/\n\s*\n/', $text );
		$html       = '';
		$block_tags = array( 'p', 'ol', 'ul', 'li', 'h[1-6]' );
		$block_tag_pattern = '/<(' . implode( '|', $block_tags ) . ')[^>]*>.*<\/\1>/is';

		foreach ( $paragraphs as $p ) {
			$trimmed = trim( $p );
			if ( $trimmed === '' ) {
				continue;
			}
			if ( preg_match( $block_tag_pattern, $trimmed ) ) {
				$html .= $trimmed;
			} else {
				$html .= '<p>' . htmlspecialchars( $trimmed, ENT_QUOTES, 'UTF-8' ) . '</p>';
			}
		}

		return $html;
	}
}

/**
 * Returns the singleton instance of the parser.
 *
 * @return MDParser
 */
function md_parser() {
	static $instance = null;
	if ( null === $instance ) {
		$instance = new MDParser();
	}
	return $instance;
}
