<?php
/**
 * RoutePattern class file.
 *
 * @package SmartLicenseServer\Environments\WordPress\Routing
 */

declare(strict_types=1);

namespace SmartLicenseServer\Environments\WordPress\Routing;

/**
 * Compiles a placeholder pattern string into a CompiledPattern.
 *
 * This replaces the previous implementation's two sequential
 * preg_replace_callback() passes over the whole pattern (one for `{name.ext}`,
 * one for generic `{name}`) with a single-pass, per-segment compiler. Two
 * problems with the old approach motivated the change:
 *
 *   1. Pass ordering was load-bearing but undocumented at the call site —
 *      swap the two preg_replace_callback() calls and extension placeholders
 *      silently mis-parse.
 *   2. There was no natural place to reason about optionality, since the old
 *      code operated on the whole pattern string at once. Optional segments
 *      need to nest correctly (`a/{b?}/{c?}` must become
 *      `a(?:/b(?:/c)?)?`, not three independently-optional groups, which
 *      would also match the nonsensical `a//c`), and that nesting is only
 *      well-defined at the segment level.
 *
 * Placeholder syntax (all combinable except as noted):
 *
 *   {name}                 Segment capture, defaults to `[^/]+`.
 *   {name:regex}           Segment capture using a literal regex fragment.
 *   {name:alias}           Segment capture using a registered constraint alias
 *                          (built in: int, slug, uuid, path — see DEFAULT_CONSTRAINTS).
 *   {name?}                Optional segment. Must be the last segment, or
 *                          followed only by other optional segments.
 *   {name.ext}              Filename + extension, captured as two query vars:
 *                          `name` and `name_ext`. Extension defaults to `[a-zA-Z0-9]+`.
 *   {name.ext:png|jpg|zip}  As above, with the extension restricted to a whitelist
 *                          (or a registered alias, e.g. `{name.ext:slug}`).
 *   {name.ext?}             Optional filename+extension segment.
 *
 * An optional placeholder must be the sole content of its path segment
 * (`{slug?}` is fine; `prefix-{slug?}` is rejected) — this keeps the optional
 * nesting logic in buildRegex() a per-segment concern instead of a
 * sub-segment one.
 */
final class RoutePattern {

	/**
	 * Matches one placeholder token: name, optional `.ext` marker, optional
	 * `?`, optional `:constraint`.
	 */
	public const PLACEHOLDER_REGEX = '/\{([a-zA-Z_][a-zA-Z0-9_]*)(\.ext)?(\?)?(?::([^}]+))?\}/';

	/**
	 * Built-in constraint aliases usable as `{name:alias}`.
	 *
	 * @var array<string,string>
	 */
	private const DEFAULT_CONSTRAINTS = array(
		'int'  => '[0-9]+',
		'slug' => '[A-Za-z0-9_-]+',
		'uuid' => '[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}',
		'path' => '.+',
	);

	/**
	 * WordPress core / router-reserved query variables that a route parameter
	 * must not shadow.
	 *
	 * @var string[]
	 */
	private const RESERVED_QUERY_VARS = array(
		'page',
		'paged',
		'p',
		'name',
		'author',
		'category_name',
		'tag',
		'feed',
		'attachment_id',
		'preview',
		's',
		'pagename',
	);

	/**
	 * Compiles a full pattern (group prefix already applied) into a CompiledPattern.
	 *
	 * @param string               $pattern           Raw pattern, e.g. 'repository/{app_type}/{app_slug?}'.
	 * @param array<string,string> $customConstraints Alias => regex pairs registered via Router::constraint(),
	 *                                                 merged over (and able to override) the built-in aliases.
	 * @throws InvalidRouteException On any malformed pattern or invariant violation.
	 */
	public static function compile( string $pattern, array $customConstraints = array() ): CompiledPattern {
		$pattern     = trim( $pattern, '/' );
		$constraints = $customConstraints + self::DEFAULT_CONSTRAINTS;
		$rawSegments = '' === $pattern ? array() : explode( '/', $pattern );

		$segments    = array();
		$seenParams  = array();
		$sawOptional = false;

		foreach ( $rawSegments as $rawSegment ) {
			$segment = self::compileSegment( $rawSegment, $constraints, $seenParams );

			if ( $sawOptional && ! $segment->optional ) {
				throw new InvalidRouteException(
					sprintf(
						'Required segment "%s" cannot follow an optional segment in pattern "%s". ' .
						'Optional placeholders must be the last segment(s) of the route.',
						$rawSegment,
						$pattern
					)
				);
			}

			$sawOptional = $sawOptional || $segment->optional;
			$segments[]  = $segment;
		}

		$paramNames = array();
		foreach ( $segments as $segment ) {
			array_push( $paramNames, ...$segment->paramNames );
		}

		return new CompiledPattern( self::buildRegex( $segments, 0 ), $paramNames, $pattern );
	}

	/**
	 * Compiles a single "/"-delimited segment into its regex fragment.
	 *
	 * @param array<string,string> $constraints
	 * @param string[]              $seenParams Accumulator (by reference) of every param name
	 *                                          seen so far in this pattern, for duplicate detection.
	 * @throws InvalidRouteException
	 */
	private static function compileSegment( string $rawSegment, array $constraints, array &$seenParams ): RouteSegment {
		if ( '' === $rawSegment ) {
			throw new InvalidRouteException(
				'Route pattern contains an empty segment (check for "//" or a stray slash).'
			);
		}

		$matchCount = preg_match_all( self::PLACEHOLDER_REGEX, $rawSegment, $matches, PREG_SET_ORDER );

		if ( 0 === $matchCount ) {
			// Pure literal segment — no placeholders, nothing to capture.
			return new RouteSegment( preg_quote( $rawSegment, '#' ), false, array() );
		}

		$isSoleToken = 1 === $matchCount && $matches[0][0] === $rawSegment;
		$optional    = false;
		$paramNames  = array();

		$regex = preg_replace_callback(
			self::PLACEHOLDER_REGEX,
			function ( array $match ) use ( $constraints, $rawSegment, $isSoleToken, &$optional, &$paramNames, &$seenParams ) {
				$name       = $match[1];
				$isExt      = '' !== ( $match[2] ?? '' );
				$isOptional = '' !== ( $match[3] ?? '' );
				$constraint = ( $match[4] ?? '' );
				$constraint = '' === $constraint ? null : $constraint;

				self::assertValidParamName( $name, $seenParams );

				if ( $isOptional && ! $isSoleToken ) {
					throw new InvalidRouteException(
						sprintf(
							'Optional parameter "%s" must occupy its entire path segment (found alongside other content in "%s").',
							$name,
							$rawSegment
						)
					);
				}

				$optional = $optional || $isOptional;

				if ( $isExt ) {
					$extRegex = null !== $constraint ? self::resolveConstraint( $constraint, $constraints, $name ) : '[a-zA-Z0-9]+';

					$paramNames[] = $name;
					$paramNames[] = $name . '_ext';
					$seenParams[] = $name;
					$seenParams[] = $name . '_ext';

					// Non-greedy filename so the final dot before the extension binds correctly
					// even when the filename itself legitimately contains dots.
					return '([^/]+?)\.(' . $extRegex . ')';
				}

				$captureRegex = null !== $constraint ? self::resolveConstraint( $constraint, $constraints, $name ) : '[^/]+';

				$paramNames[] = $name;
				$seenParams[] = $name;

				return '(' . $captureRegex . ')';
			},
			$rawSegment
		);

		return new RouteSegment( $regex, $optional, $paramNames );
	}

	/**
	 * @param string[] $seenParams
	 * @throws InvalidRouteException
	 */
	private static function assertValidParamName( string $name, array $seenParams ): void {
		if ( in_array( $name, $seenParams, true ) ) {
			throw new InvalidRouteException( sprintf( 'Duplicate route parameter "%s".', $name ) );
		}

		if ( in_array( $name, self::RESERVED_QUERY_VARS, true ) ) {
			throw new InvalidRouteException(
				sprintf( 'Route parameter "%s" collides with a reserved WordPress query variable.', $name )
			);
		}
	}

	/**
	 * Resolves a `:constraint` token to a regex fragment — either a registered
	 * alias or a literal regex, validated for well-formedness either way.
	 *
	 * @param array<string,string> $constraints
	 * @throws InvalidRouteException If a literal regex fragment is invalid.
	 */
	private static function resolveConstraint( string $constraint, array $constraints, string $paramName ): string {
		if ( isset( $constraints[ $constraint ] ) ) {
			return $constraints[ $constraint ];
		}

		if ( false === @preg_match( '#' . $constraint . '#', '' ) ) {
			throw new InvalidRouteException(
				sprintf( 'Invalid constraint regex for parameter "%s": "%s".', $paramName, $constraint )
			);
		}

		return $constraint;
	}

	/**
	 * Recursively nests optional segments from right to left so that, e.g.,
	 * "a/{b?}/{c?}" compiles to "a(?:/b(?:/c)?)?" — one segment's optionality
	 * correctly carries everything after it inside the same optional group —
	 * rather than three independently-optional groups, which would also
	 * accept the nonsensical "a//c".
	 *
	 * @param RouteSegment[] $segments
	 */
	private static function buildRegex( array $segments, int $index ): string {
		if ( $index >= count( $segments ) ) {
			return '';
		}

		$segment = $segments[ $index ];
		$rest    = self::buildRegex( $segments, $index + 1 );
		$piece   = $segment->regex . $rest;
		$isFirst = 0 === $index;

		if ( $segment->optional ) {
			return '(?:' . ( $isFirst ? '' : '/' ) . $piece . ')?';
		}

		return ( $isFirst ? '' : '/' ) . $piece;
	}

	/**
	 * Exposed so Router can validate `$extraVars` keys against the same list.
	 *
	 * @return string[]
	 */
	public static function reservedQueryVars(): array {
		return self::RESERVED_QUERY_VARS;
	}
}