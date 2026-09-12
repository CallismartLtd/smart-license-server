<?php
/**
 * Convenience wrapper around symfony/html-sanitizer, exposing purpose-built
 * sanitize methods instead of requiring callers to build HtmlSanitizerConfig
 * objects themselves.
 *
 * Requires: composer require symfony/html-sanitizer
 *
 * Adjust the namespace below to match your project's autoloading. This class
 * supersedes the earlier hand-rolled HtmlSanitizer.php - remove that file or
 * this one will collide with it if both declare the same class name in the
 * same namespace.
 *
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Utils;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class HtmlSanitizer {

	/**
	 * Sanitizer for general untrusted HTML fragments (descriptions, comments,
	 * anything not produced by your own rich text editor).
	 *
	 * @var HtmlSanitizerInterface
	 */
	private HtmlSanitizerInterface $html_sanitizer;

	/**
	 * Sanitizer tuned for rich text editor output (TinyMCE, etc.).
	 *
	 * @var HtmlSanitizerInterface
	 */
	private HtmlSanitizerInterface $editor_sanitizer;

	/**
	 * @param HtmlSanitizerConfig|null $html_config   Config for sanitize_html(). Defaults to self::default_html_config().
	 * @param HtmlSanitizerConfig|null $editor_config Config for sanitize_editor(). Defaults to self::default_editor_config().
	 */
	public function __construct( ?HtmlSanitizerConfig $html_config = null, ?HtmlSanitizerConfig $editor_config = null ) {
		$this->html_sanitizer   = new SymfonyHtmlSanitizer( $html_config ?? self::default_html_config() );
		$this->editor_sanitizer = new SymfonyHtmlSanitizer( $editor_config ?? self::default_editor_config() );
	}

	/*
	|--------------------
	|Convenience methods
	|--------------------
	*/

	/**
	 * Safe-by-default sanitizer for general untrusted HTML.
	 *
	 * Built on allowSafeElements(): the W3C "safe" baseline, which excludes
	 * "style" and other attributes capable of CSS injection/click-jacking,
	 * on top of the usual script/event-handler removal.
	 */
	public function sanitize_html( string $html ) : string {
		return $this->html_sanitizer->sanitize( $html );
	}

	/**
	 * Sanitizer for rich text editor output. Matches the tag/attribute set
	 * from the original hand-rolled sanitizer, including inline "style".
	 *
	 * NOTE: allowing "style" reopens the CSS-injection/click-jacking surface
	 * that allowSafeElements() exists to close in sanitize_html(). This is a
	 * deliberate trade-off to preserve editor formatting (colors, alignment,
	 * etc.) - confirm it's acceptable for where this content gets rendered
	 * before relying on it, rather than assuming it's equally safe.
	 */
	public function sanitize_editor( string $html ) : string {
		return $this->editor_sanitizer->sanitize( $html );
	}

	/**
	 * For fields that should never contain live HTML (plain text inputs).
	 * Any HTML the user typed is entity-encoded, so it displays literally
	 * instead of being interpreted or silently stripped.
	 */
	public function sanitize_textarea( string $text ) : string {
		return $this->html_sanitizer->sanitizeFor( 'textarea', $text );
	}

	/**
	 * Sanitizes a bare attribute value (class, id, data-*) meant for markup
	 * YOU build, e.g. sprintf( '<div class="%s">', $sanitizer->sanitize_attribute_value( $value ) ).
	 *
	 * Not a symfony/html-sanitizer feature - that package only sanitizes full
	 * HTML fragments, not standalone attribute strings - so this is a small
	 * hand-written allowlist rather than a wrapper around a nonexistent API.
	 *
	 * @param string $value     Raw attribute value.
	 * @param string $attribute 'id', 'class', 'data', or any other name (falls back to stripping quotes/angle brackets).
	 */
	public function sanitize_attribute_value( string $value, string $attribute = 'class' ) : string {
		switch ( $attribute ) {
			case 'id':
				$value = preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
				return preg_match( '/^[0-9]/', $value ) ? '' : $value;

			case 'class':
				$tokens = preg_split( '/\s+/', trim( $value ) );
				$tokens = array_filter( array_map( function ( $token ) {
					$token = preg_replace( '/[^A-Za-z0-9_-]/', '', $token );
					return preg_match( '/^[0-9]/', $token ) ? '' : $token;
				}, $tokens ) );
				return implode( ' ', $tokens );

			case 'data':
				return preg_replace( '/[<>"\']/', '', $value );

			default:
				return preg_replace( '/[<>"\']/', '', $value );
		}
	}

	/*
	|-----------------------
	| Underlying sanitizers
	|-----------------------
	*/

	// For callers who need sanitizeFor() with a different context (e.g. 'title', 'head').

	public function get_html_sanitizer() : SymfonyHtmlSanitizer {
		return $this->html_sanitizer;
	}

	public function get_editor_sanitizer() : SymfonyHtmlSanitizer {
		return $this->editor_sanitizer;
	}

	/*
	|-----------------
	| Default configs
	|-----------------
	*/

	public static function default_html_config() : HtmlSanitizerConfig {
		return ( new HtmlSanitizerConfig() )
			->allowSafeElements()
			->allowLinkSchemes( [ 'http', 'https', 'mailto', 'tel' ] )
			->allowRelativeLinks()
			->allowMediaSchemes( [ 'http', 'https' ] )
			->allowRelativeMedias()
			->forceAttribute( 'a', 'rel', 'noopener noreferrer nofollow' );
	}

	public static function default_editor_config() : HtmlSanitizerConfig {
		return ( new HtmlSanitizerConfig() )
			->allowElement( 'p', [ 'style', 'class' ] )
			->allowElement( 'br' )
			->allowElement( 'strong' )
			->allowElement( 'b' )
			->allowElement( 'em' )
			->allowElement( 'i', [ 'class', 'id', 'style' ] )
			->allowElement( 'u' )
			->allowElement( 'ol', [ 'style', 'class' ] )
			->allowElement( 'ul', [ 'style', 'class' ] )
			->allowElement( 'li', [ 'style', 'class' ] )
			->allowElement( 'a', [ 'href', 'title', 'target', 'rel' ] )
			->allowElement( 'img', [ 'src', 'alt', 'width', 'height', 'style', 'class' ] )
			->allowElement( 'h1', [ 'style', 'class' ] )
			->allowElement( 'h2', [ 'style', 'class' ] )
			->allowElement( 'h3', [ 'style', 'class' ] )
			->allowElement( 'h4', [ 'style', 'class' ] )
			->allowElement( 'h5', [ 'style', 'class' ] )
			->allowElement( 'h6', [ 'style', 'class' ] )
			->allowElement( 'div', [ 'style', 'class' ] )
			->allowElement( 'span', [ 'style', 'class' ] )
			->allowElement( 'blockquote', [ 'style', 'class' ] )
			->allowElement( 'code', [ 'style', 'class' ] )
			->allowElement( 'pre', [ 'style', 'class' ] )
			->allowElement( 'table', [ 'border', 'cellpadding', 'cellspacing', 'width', 'style', 'class' ] )
			->allowElement( 'thead', [ 'style', 'class' ] )
			->allowElement( 'tbody', [ 'style', 'class' ] )
			->allowElement( 'tfoot', [ 'style', 'class' ] )
			->allowElement( 'tr', [ 'style', 'class' ] )
			->allowElement( 'th', [ 'style', 'scope', 'class' ] )
			->allowElement( 'td', [ 'style', 'colspan', 'rowspan', 'class' ] )
			->allowLinkSchemes( [ 'http', 'https', 'mailto' ] )
			->allowRelativeLinks()
			->allowMediaSchemes( [ 'http', 'https' ] )
			->allowRelativeMedias()
			->forceAttribute( 'a', 'rel', 'noopener noreferrer nofollow' );
	}
}