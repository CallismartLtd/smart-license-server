<?php
/**
 * CompiledPattern class file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

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
}