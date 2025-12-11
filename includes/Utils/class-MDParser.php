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

defined( 'SMLISER_ABSPATH' ) || exit;

use Exception;
use League\CommonMark\CommonMarkConverter;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;
use SmartLicenseServer\Utils\WPReadmeParser;

/**
 * Class MDParser
 *
 * Converts Markdown to HTML, using CommonMark when available.
 */
class MDParser {

	/**
	 * CommonMark converter instance or null.
	 *
	 * @var MarkdownConverter|CommonMarkConverter|null
	 */
	protected $converter;

	/**
	 * Constructor.
	 *
	 * @param array $config Optional CommonMark environment configuration.
	 */
	public function __construct( $config = array() ) {
		$default = array(
			'html_input'         => 'allow',
			'allow_unsafe_links' => false,
		);

		$config = array_merge( $default, (array) $config );

		// Build environment.
		$environment = new Environment( $config );

		// Core + GFM + Tables.
		$environment->addExtension( new CommonMarkCoreExtension() );
		$environment->addExtension( new GithubFlavoredMarkdownExtension() );
		$environment->addExtension( new TableExtension() );

		// Create converter.
		$this->converter = new MarkdownConverter( $environment );
	}



	/**
	 * Main entry point: parse Markdown into HTML.
	 *
	 * @param string $text Markdown content.
	 * @return string HTML output.
	 */
	public function parse( $text ) {
		$text = (string) $text;
		
		// Pre-process WP-specific syntax
		$text = WPReadmeParser::parse( $text );

		if ( $this->converter !== null ) {
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
	public function parse_with_commonmark( $text ) {
		try {
			$result = $this->converter->convert( $text );

			// Handle different return types
			if ( is_string( $result ) ) {
				return $result;
			}
			
			if ( method_exists( $result, 'getContent' ) ) {
				return $result->getContent();
			}
			
			if ( method_exists( $result, '__toString' ) ) {
				return (string) $result;
			}

			return $this->fallback_parse( $text );
		} catch ( Exception $e ) {
			throw $e;
			// return $this->fallback_parse( $text ); debugging
		}
	}

	/**
	 * Lightweight fallback Markdown parser.
	 *
	 * Processes in correct order to avoid conflicts.
	 *
	 * @param string $text Markdown text.
	 * @return string HTML
	 */
	protected function fallback_parse( $text ) {
		// Normalize line endings
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );
		
		// Process in order: code blocks first (to protect content), then inline elements, then block elements
		$text = $this->parse_code_blocks( $text );
		$text = $this->parse_inline_code( $text );
		$text = $this->parse_anchors( $text );
		$text = $this->parse_bold_and_italic( $text );
		$text = $this->parse_headings( $text );
		$text = $this->parse_tables( $text );
		$text = $this->parse_lists( $text );
		$text = $this->parse_paragraphs( $text );

		return trim( $text );
	}

	/**
	 * Parse inline code `code`.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_inline_code( $text ) {
		return preg_replace_callback(
			'/`([^`]+)`/',
			function( $matches ) {
				return '<code>' . htmlspecialchars( $matches[1], ENT_QUOTES, 'UTF-8' ) . '</code>';
			},
			$text
		);
	}

	/**
	 * Parse code blocks (triple backticks or indented).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_code_blocks( $text ) {
		// Fenced code blocks with optional language (```lang...```)
		$text = preg_replace_callback(
			'/```([a-zA-Z0-9_+-]*)\n?(.*?)\n?```/s',
			function( $matches ) {
				$lang = trim( $matches[1] );
				$code = $matches[2];
				
				// Add language class if specified
				$class_attr = $lang ? ' class="language-' . htmlspecialchars( $lang, ENT_QUOTES, 'UTF-8' ) . '"' : '';
				
				// Escape HTML entities in code
				$escaped_code = htmlspecialchars( $code, ENT_QUOTES, 'UTF-8' );
				
				return "\n<pre><code{$class_attr}>" . $escaped_code . "</code></pre>\n";
			},
			$text
		);

		// Indented code blocks (4 spaces or 1 tab)
		$text = preg_replace_callback(
			'/(?:^|\n)((?:(?:    |\t).+\n?)+)/m',
			function( $matches ) {
				$code = preg_replace( '/^(?:    |\t)/m', '', $matches[1] );
				$escaped_code = htmlspecialchars( trim( $code ), ENT_QUOTES, 'UTF-8' );
				return "\n<pre><code>" . $escaped_code . "</code></pre>\n";
			},
			$text
		);

		return $text;
	}

	/**
	 * Parse Markdown headings (# style).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_headings( $text ) {
		// Process from h6 to h1 to avoid conflicts
		for ( $i = 6; $i >= 1; $i-- ) {
			$hashes = str_repeat( '#', $i );
			$text = preg_replace(
				'/^' . preg_quote( $hashes, '/' ) . '\s+(.+?)\s*$/m',
				'<h' . $i . '>$1</h' . $i . '>',
				$text
			);
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
		// Unordered lists (- or *)
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*[-*+]\s+.+(?:\n|$))+)/m',
			function( $matches ) {
				$items = preg_split( '/\n/', trim( $matches[1] ) );
				$list  = '<ul>';
				foreach ( $items as $item ) {
					if ( trim( $item ) === '' ) {
						continue;
					}
					$item = preg_replace( '/^[ \t]*[-*+]\s+/', '', $item );
					$list .= '<li>' . trim( $item ) . '</li>';
				}
				$list .= '</ul>';
				return "\n" . $list . "\n";
			},
			$text
		);

		// Ordered lists (1. 2. etc.)
		$text = preg_replace_callback(
			'/(?:^|\n)((?:[ \t]*\d+\.\s+.+(?:\n|$))+)/m',
			function( $matches ) {
				$items = preg_split( '/\n/', trim( $matches[1] ) );
				$list  = '<ol>';
				foreach ( $items as $item ) {
					if ( trim( $item ) === '' ) {
						continue;
					}
					$item = preg_replace( '/^[ \t]*\d+\.\s+/', '', $item );
					$list .= '<li>' . trim( $item ) . '</li>';
				}
				$list .= '</ol>';
				return "\n" . $list . "\n";
			},
			$text
		);

		return $text;
	}

	/**
	 * Parse bold **text** and italic *text* or _text_.
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_bold_and_italic( $text ) {
		// Bold: **text** or __text__
		$text = preg_replace(
			'/\*\*(.+?)\*\*/',
			'<strong>$1</strong>',
			$text
		);
		$text = preg_replace(
			'/__(.+?)__/',
			'<strong>$1</strong>',
			$text
		);

		// Italic: *text* or _text_
		$text = preg_replace(
			'/\*([^*]+?)\*/',
			'<em>$1</em>',
			$text
		);
		$text = preg_replace(
			'/_([^_]+?)_/',
			'<em>$1</em>',
			$text
		);

		return $text;
	}

	/**
	 * Parse anchor links [text](url).
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_anchors( $text ) {
		return preg_replace_callback(
			'/\[([^\]]+)\]\(([^)]+)\)/',
			function( $matches ) {
				$link_text = $matches[1];
				$href      = $matches[2];
				
				// Don't escape if already HTML
				if ( strpos( $link_text, '<' ) !== false ) {
					$safe_text = $link_text;
				} else {
					$safe_text = htmlspecialchars( $link_text, ENT_QUOTES, 'UTF-8' );
				}
				
				$safe_href = htmlspecialchars( $href, ENT_QUOTES, 'UTF-8' );
				
				return '<a href="' . $safe_href . '" target="_blank" rel="noopener noreferrer">' . $safe_text . '</a>';
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
		// Split on double newlines
		$blocks = preg_split( '/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY );
		$html   = '';
		
		// Block-level HTML tags that shouldn't be wrapped in <p>
		$block_pattern = '/^\s*<(h[1-6]|ul|ol|pre|div|blockquote|table|hr)/i';

		foreach ( $blocks as $block ) {
			$trimmed = trim( $block );
			
			if ( $trimmed === '' ) {
				continue;
			}
			
			// Don't wrap block-level HTML elements
			if ( preg_match( $block_pattern, $trimmed ) ) {
				$html .= $trimmed . "\n";
			} else {
				// Wrap in paragraph
				$html .= '<p>' . $trimmed . '</p>' . "\n";
			}
		}

		return $html;
	}

	/**
	 * Parse Markdown tables.
	 *
	 * Supports basic GitHub-flavored Markdown table syntax:
	 * | Header 1 | Header 2 |
	 * |----------|----------|
	 * | Cell 1   | Cell 2   |
	 *
	 * @param string $text Input.
	 * @return string
	 */
	protected function parse_tables( $text ) {
		return preg_replace_callback(
			'/(?:^|\n)(\|.+\|[ \t]*\n\|[-: |]+\|[ \t]*\n(?:\|.+\|[ \t]*\n?)*)/m',
			function( $matches ) {
				$table_text = trim( $matches[1] );
				$lines = explode( "\n", $table_text );
				
				if ( count( $lines ) < 2 ) {
					return $matches[0];
				}

				$html = "\n<table>\n";
				
				// Parse header row
				$header_row = array_shift( $lines );
				$headers = $this->parse_table_row( $header_row );
				
				if ( ! empty( $headers ) ) {
					$html .= "<thead>\n<tr>\n";
					foreach ( $headers as $header ) {
						$html .= "<th>" . trim( $header ) . "</th>\n";
					}
					$html .= "</tr>\n</thead>\n";
				}
				
				// Skip separator row (|---|---|)
				array_shift( $lines );
				
				// Parse body rows
				if ( ! empty( $lines ) ) {
					$html .= "<tbody>\n";
					foreach ( $lines as $line ) {
						$line = trim( $line );
						if ( empty( $line ) ) {
							continue;
						}
						
						$cells = $this->parse_table_row( $line );
						if ( ! empty( $cells ) ) {
							$html .= "<tr>\n";
							foreach ( $cells as $cell ) {
								$html .= "<td>" . trim( $cell ) . "</td>\n";
							}
							$html .= "</tr>\n";
						}
					}
					$html .= "</tbody>\n";
				}
				
				$html .= "</table>\n";
				
				return $html;
			},
			$text
		);
	}

	/**
	 * Parse a table row into individual cells.
	 *
	 * @param string $row Table row string like "| cell 1 | cell 2 |"
	 * @return array Array of cell contents
	 */
	protected function parse_table_row( $row ) {
		// Remove leading/trailing pipes and split
		$row = trim( $row, " \t|" );
		$cells = explode( '|', $row );
		
		// Process each cell
		return array_map( function( $cell ) {
			return trim( $cell );
		}, $cells );
	}
}