<?php
/**
 * RouteSegment class file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * Immutable compiled representation of a single "/"-delimited path segment.
 * Segments are the unit RoutePattern reasons about when nesting optional
 * groups, since optionality in a URL path only ever makes sense per segment.
 */
final class RouteSegment {

	/**
	 * @param string   $regex      Regex fragment for this segment (no surrounding slashes).
	 * @param bool     $optional   Whether this segment (and, per the trailing-optional
	 *                             invariant, everything after it) may be omitted from the URL.
	 * @param string[] $paramNames Ordered names of the capture groups this segment introduces.
	 *                             Empty for pure-literal segments; two entries (`name`, `name_ext`)
	 *                             for a `{name.ext}` placeholder.
	 */
	public function __construct(
		public readonly string $regex,
		public readonly bool $optional,
		public readonly array $paramNames
	) {
	}
}