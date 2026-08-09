<?php
/**
 * DispatchResult class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Result of Router::dispatch(). Exactly one of the three named constructors
 * produced any given instance — check ->status to know which fields are
 * meaningful:
 *
 *   Found            -> ->route, ->handler, ->params are set.
 *   NotFound         -> nothing else is set.
 *   MethodNotAllowed -> ->allowedMethods is set (for an HTTP 405 Allow header).
 */
final class DispatchResult {

	/**
	 * @param array<string,string> $params
	 * @param string[]              $allowedMethods
	 */
	private function __construct(
		public readonly DispatchStatus $status,
		public readonly ?Route $route = null,
		public readonly mixed $handler = null,
		public readonly array $params = array(),
		public readonly array $allowedMethods = array()
	) {
	}

	/**
	 * @param array<string,string> $params
	 */
	public static function found( Route $route, array $params ): self {
		return new self( DispatchStatus::Found, $route, $route->handler, $params );
	}

	public static function notFound(): self {
		return new self( DispatchStatus::NotFound );
	}

	/**
	 * @param string[] $allowedMethods
	 */
	public static function methodNotAllowed( array $allowedMethods ): self {
		return new self( DispatchStatus::MethodNotAllowed, allowedMethods: $allowedMethods );
	}
}