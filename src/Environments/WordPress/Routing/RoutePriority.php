<?php
/**
 * RoutePriority enum file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * Priority for add_rewrite_rule(). Mirrors WordPress' own 'top' | 'bottom' values,
 * as an enum instead of an unvalidated string.
 */
enum RoutePriority: string {

	case Top    = 'top';
	case Bottom = 'bottom';

	/**
	 * @throws InvalidRouteException If $value is not 'top' or 'bottom'.
	 */
	public static function fromString( string $value ): self {
		try {
			return self::from( $value );
		} catch ( \ValueError $e ) {
			throw new InvalidRouteException(
				sprintf( 'Invalid rewrite priority "%s"; expected "top" or "bottom".', $value ),
				0,
				$e
			);
		}
	}
}