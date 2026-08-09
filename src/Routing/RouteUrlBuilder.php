<?php
/**
 * RouteUrlBuilder class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Routing;

/**
 * Renders a route's original pattern template back into a concrete URL path
 * given parameter values. Used by Router::url().
 *
 * Same algorithm as the WordPress adapter's RouteUrlBuilder — duplicated
 * rather than shared because the two operate on different Route types
 * (this one's Route has no pagename/extraVars/priority to speak of), but the
 * template-rendering logic itself doesn't depend on any of that.
 */
final class RouteUrlBuilder {

	/**
	 * @param array<string,scalar> $params
	 * @throws InvalidRouteException If a required parameter is missing.
	 */
	public static function build( Route $route, array $params ): string {
		$template = $route->compiled->template;
		$segments = '' === $template ? array() : explode( '/', $template );
		$built    = array();

		foreach ( $segments as $segment ) {
			if ( ! preg_match( RoutePattern::PLACEHOLDER_REGEX, $segment ) ) {
				$built[] = $segment;
				continue;
			}

			$rendered = self::renderSegment( $segment, $params, $route );

			if ( null === $rendered ) {
				// Omitted optional segment. RoutePattern::compile() guarantees optional
				// segments only ever trail, so nothing meaningful can follow — stop here.
				break;
			}

			$built[] = $rendered;
		}

		return implode( '/', $built );
	}

	/**
	 * @param array<string,scalar> $params
	 * @return string|null Rendered segment, or null if it's an omitted optional segment.
	 * @throws InvalidRouteException If a required parameter is missing.
	 */
	private static function renderSegment( string $segment, array $params, Route $route ): ?string {
		$omitted = false;

		$rendered = preg_replace_callback(
			RoutePattern::PLACEHOLDER_REGEX,
			function ( array $match ) use ( $params, $route, &$omitted ) {
				$name       = $match[1];
				$isExt      = '' !== ( $match[2] ?? '' );
				$isOptional = '' !== ( $match[3] ?? '' );

				if ( $isExt ) {
					$extKey = $name . '_ext';

					if ( ! array_key_exists( $name, $params ) || ! array_key_exists( $extKey, $params ) ) {
						return self::missing( $name, $isOptional, $route, $omitted );
					}

					return $params[ $name ] . '.' . $params[ $extKey ];
				}

				if ( ! array_key_exists( $name, $params ) ) {
					return self::missing( $name, $isOptional, $route, $omitted );
				}

				return (string) $params[ $name ];
			},
			$segment
		);

		return $omitted ? null : $rendered;
	}

	/**
	 * @throws InvalidRouteException
	 */
	private static function missing( string $name, bool $isOptional, Route $route, bool &$omitted ): string {
		if ( $isOptional ) {
			$omitted = true;

			return '';
		}

		throw new InvalidRouteException(
			sprintf( 'Missing required parameter "%s" for route "%s".', $name, (string) $route->getName() )
		);
	}
}