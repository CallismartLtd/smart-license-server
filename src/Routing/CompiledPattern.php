<?php
/**
 * CompiledPattern class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Immutable result of compiling a route pattern string. Kept separate from
 * Route itself so a Route can be constructed from either a fresh compile or,
 * in principle, a cached one without changing Route's shape.
 */
final class CompiledPattern {

	/**
	 * @param string   $regex      Unanchored regex body (no leading `^`, no trailing `$` or `/?`),
	 *                             ready for Route to anchor and optionally suffix.
	 * @param string[] $paramNames Ordered list of parameter names, aligned 1:1 with the
	 *                             regex's capture groups in left-to-right order.
	 * @param string   $template   Original pattern string (group prefix already applied,
	 *                             placeholders intact) — kept for URL generation, since
	 *                             building a URL needs the placeholder names and flags,
	 *                             not the compiled regex.
	 */
	public function __construct(
		public readonly string $regex,
		public readonly array $paramNames,
		public readonly string $template
	) {
	}

	/**
	 * Same regex, with every capturing group turned into a named one —
	 * `(...)` becomes `(?P<paramName>...)` — for consumers that read matches
	 * by name rather than position (e.g. WordPress' register_rest_route(),
	 * which populates request params from named groups in the route regex).
	 *
	 * Walks the regex character by character rather than using a single
	 * substitution regex, so it can tell a real capturing group's `(` apart
	 * from a non-capturing group, lookaround, or an escaped literal
	 * parenthesis (`\(`, produced by preg_quote() on literal text) — a blind
	 * find-and-replace on `(` would mis-tag any of those.
	 */
	public function namedRegex(): string {
		$result     = '';
		$paramIndex = 0;
		$length     = strlen( $this->regex );

		for ( $i = 0; $i < $length; $i++ ) {
			$char = $this->regex[ $i ];

			if ( '\\' === $char && $i + 1 < $length ) {
				// Escaped character (e.g. preg_quote()'d literal "(" or ".") — copy
				// both bytes verbatim, don't interpret the next one as a group start.
				$result .= $char . $this->regex[ $i + 1 ];
				++$i;
				continue;
			}

			if ( '(' === $char ) {
				$isSpecialGroup = $i + 1 < $length && '?' === $this->regex[ $i + 1 ];

				if ( $isSpecialGroup ) {
					// Non-capturing (?:...), lookahead (?=...)/(?!...), etc — leave as-is.
					$result .= $char;
					continue;
				}

				$name = $this->paramNames[ $paramIndex ] ?? null;
				++$paramIndex;

				if ( null === $name ) {
					// Shouldn't happen if paramNames stayed aligned with capture groups,
					// but fail safe to a plain (unnamed) group rather than a broken regex.
					$result .= $char;
					continue;
				}

				$result .= '(?P<' . $name . '>';
				continue;
			}

			$result .= $char;
		}

		return $result;
	}
}