<?php
/**
 * Route class file.
 *
 * @package SmartLicenseServer\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * A single registered route definition.
 *
 * Every property but the name is fixed at construction time; naming is a
 * one-time, one-way operation (name() throws if called twice) so a Route
 * can otherwise be treated as immutable once built.
 *
 * Two construction paths exist:
 *
 *   - Route::compiled() — the normal path, used by Router::add(). The regex
 *     and query string are derived from a CompiledPattern.
 *   - Route::raw()       — an escape hatch, used by Router::raw(), for the
 *     rare rule the placeholder DSL genuinely cannot express (see Router::raw()
 *     docblock for when that's the case). The regex and query string are
 *     taken verbatim, exactly as if add_rewrite_rule() had been called directly.
 */
final class Route {

	private ?string $name = null;

	/**
	 * Private: use Route::compiled() or Route::raw().
	 *
	 * @param array<string,string> $extraVars
	 * @param string[]              $rawQueryVarNames
	 */
	private function __construct(
		public readonly ?string $pattern,
		public readonly string $pagename,
		public readonly array $extraVars,
		public readonly RoutePriority $priority,
		public readonly bool $optionalTrailingSlash,
		public readonly ?CompiledPattern $compiled,
		private readonly ?string $rawRegex = null,
		private readonly ?string $rawQuery = null,
		private readonly array $rawQueryVarNames = array()
	) {}

	/**
	 * @param array<string,string> $extraVars
	 */
	public static function compiled(
		string $pattern,
		string $pagename,
		array $extraVars,
		RoutePriority $priority,
		bool $optionalTrailingSlash,
		CompiledPattern $compiled
	): self {
		return new self( $pattern, $pagename, $extraVars, $priority, $optionalTrailingSlash, $compiled );
	}

	/**
	 * @param string[] $queryVarNames Query var names this raw rule populates, so
	 *                                query_vars() still knows to register them.
	 */
	public static function raw( string $regex, string $query, RoutePriority $priority, array $queryVarNames = array() ): self {
		return new self( null, '', array(), $priority, false, null, $regex, $query, $queryVarNames );
	}

	/**
	 * Attaches a name to this route for later lookup via Router::url().
	 * Not supported on raw() routes, since there's no pattern template to render from.
	 *
	 * @throws InvalidRouteException If the route is already named, or is a raw route.
	 */
	public function name( string $name ): self {
		if ( null === $this->compiled ) {
			throw new InvalidRouteException(
				sprintf( 'Route "%s" was registered via Router::raw() and has no pattern template, so it cannot be named for URL generation.', $name )
			);
		}

		if ( null !== $this->name ) {
			throw new InvalidRouteException(
				sprintf( 'Route already named "%s"; cannot rename to "%s".', $this->name, $name )
			);
		}

		$this->name = $name;

		return $this;
	}

	public function getName(): ?string {
		return $this->name;
	}

	/**
	 * Builds the anchored regex and target query string for add_rewrite_rule().
	 *
	 * @return array{regex: string, query: string, priority: string}
	 */
	public function toRewriteRule(): array {
		if ( null !== $this->rawRegex ) {
			return array(
				'regex'    => $this->rawRegex,
				'query'    => (string) $this->rawQuery,
				'priority' => $this->priority->value,
			);
		}

		$regex = $this->compiled->regex . ( $this->optionalTrailingSlash ? '/?' : '' );

		$queryParts = array();

		if ( '' !== $this->pagename ) {
			$queryParts[] = 'pagename=' . rawurlencode( $this->pagename );
		}

		foreach ( $this->compiled->paramNames as $index => $paramName ) {
			$queryParts[] = $paramName . '=$matches[' . ( $index + 1 ) . ']';
		}

		foreach ( $this->extraVars as $varName => $value ) {
			$queryParts[] = $varName . '=' . $value;
		}

		return array(
			'regex'    => '^' . $regex . '$',
			'query'    => 'index.php?' . implode( '&', $queryParts ),
			'priority' => $this->priority->value,
		);
	}

	/**
	 * Every query variable this route contributes: derived params plus extra vars,
	 * or the explicit list passed to Route::raw().
	 *
	 * @return string[]
	 */
	public function queryVarNames(): array {
		if ( null !== $this->rawRegex ) {
			return $this->rawQueryVarNames;
		}

		return array_merge( $this->compiled->paramNames, array_keys( $this->extraVars ) );
	}
}