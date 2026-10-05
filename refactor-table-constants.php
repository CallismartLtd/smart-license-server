<?php
/**
 * One-off refactor: replace the SMLISER_*_TABLE constants with TableName.
 *
 * Usage:
 *   php tools/refactor-table-names.php src            # dry run: lists every change
 *   php tools/refactor-table-names.php src --write    # applies the changes
 *
 * What it does, per PHP file:
 *  - SMLISER_USERS_TABLE (also \SMLISER_USERS_TABLE) becomes
 *    TableName::USERS->table() — the prefixed name, resolved when used.
 *  - In src/Schema/Definitions/ it becomes TableName::USERS->value instead,
 *    because schemas are registered by logical (unprefixed) name.
 *  - The constants are removed from `use const …;` statements (the statement
 *    is dropped when nothing is left).
 *  - `use SmartLicenseServer\Schema\TableName;` is added after the namespace
 *    line when the file uses TableName and is not in that namespace.
 *
 * Only real code is changed: occurrences inside strings and comments are left
 * alone and listed as warnings to check by hand. Review the diff before
 * committing; delete this script afterwards.
 */

declare( strict_types=1 );

const MAP = array(
	'SMLISER_LICENSE_TABLE'             => 'LICENSES',
	'SMLISER_LICENSE_META_TABLE'        => 'LICENSE_META',
	'SMLISER_PLUGINS_TABLE'             => 'PLUGINS',
	'SMLISER_PLUGINS_META_TABLE'        => 'PLUGIN_META',
	'SMLISER_THEMES_TABLE'              => 'THEMES',
	'SMLISER_THEMES_META_TABLE'         => 'THEME_META',
	'SMLISER_SOFTWARE_TABLE'            => 'SOFTWARE',
	'SMLISER_SOFTWARE_META_TABLE'       => 'SOFTWARE_META',
	'SMLISER_DOWNLOAD_TOKEN_TABLE'      => 'ITEM_DOWNLOAD_TOKEN',
	'SMLISER_APP_DOWNLOAD_TOKEN_TABLE'  => 'APP_DOWNLOAD_TOKENS',
	'SMLISER_MONETIZATION_TABLE'        => 'MONETIZATION',
	'SMLISER_PRICING_TIER_TABLE'        => 'PRICING_TIERS',
	'SMLISER_BULK_MESSAGES_TABLE'       => 'BULK_MESSAGES',
	'SMLISER_BULK_MESSAGES_APPS_TABLE'  => 'BULK_MESSAGES_APPS',
	'SMLISER_OPTIONS_TABLE'             => 'OPTIONS',
	'SMLISER_ANALYTICS_LOGS_TABLE'      => 'ANALYTICS_LOG',
	'SMLISER_ANALYTICS_DAILY_TABLE'     => 'ANALYTICS_DAILY',
	'SMLISER_OWNERS_TABLE'              => 'RESOURCE_OWNERS',
	'SMLISER_USERS_TABLE'               => 'USERS',
	'SMLISER_USER_OPTIONS_TABLE'        => 'USER_OPTIONS',
	'SMLISER_SERVICE_ACCOUNTS_TABLE'    => 'SERVICE_ACCOUNTS',
	'SMLISER_ROLES_TABLE'               => 'ROLES',
	'SMLISER_ROLE_CAPABILITIES_TABLE'   => 'ROLE_CAPS',
	'SMLISER_ROLE_ASSIGNMENT_TABLE'     => 'PRINCIPAL_ROLES',
	'SMLISER_ORGANIZATIONS_TABLE'       => 'ORGANIZATIONS',
	'SMLISER_ORGANIZATION_MEMBERS_TABLE' => 'ORGANIZATION_MEMBERS',
	'SMLISER_IDENTITY_FEDERATION_TABLE' => 'IDENTITY_PROVIDER_LOOKUP',
	'SMLISER_BACKGROUND_JOBS_TABLE'     => 'BACKGROUND_JOBS',
	'SMLISER_FAILED_JOBS_TABLE'         => 'FAILED_JOBS',
);

const ENUM_FQCN      = 'SmartLicenseServer\\Schema\\TableName';
const ENUM_NAMESPACE = 'SmartLicenseServer\\Schema';

$dir   = $argv[1] ?? '';
$write = in_array( '--write', $argv, true );

if ( '' === $dir || ! is_dir( $dir ) ) {
	fwrite( STDERR, "Usage: php refactor-table-names.php <src-dir> [--write]\n" );
	exit( 1 );
}

$files    = 0;
$changes  = 0;
$warnings = 0;

$iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );

foreach ( $iterator as $file ) {
	/** @var SplFileInfo $file */
	if ( 'php' !== $file->getExtension() ) {
		continue;
	}

	$path   = str_replace( '\\', '/', $file->getPathname() );
	$source = (string) file_get_contents( $path );

	if ( ! preg_match( '/SMLISER_\w+_TABLE\b/', $source ) ) {
		continue;
	}

	$is_schema = str_contains( $path, '/Schema/Definitions/' );
	$replaced  = 0;
	$notes     = array();

	// 1. Drop the constants from `use const …;` statements.
	$source = preg_replace_callback(
		'/^([ \t]*)use\s+const\s+([^;]+);[ \t]*\R?/m',
		static function ( array $m ) use ( &$replaced ) : string {
			$names = array_map( 'trim', explode( ',', $m[2] ) );
			$keep  = array_filter( $names, static fn ( string $n ) : bool => ! isset( MAP[ ltrim( $n, '\\' ) ] ) );

			if ( count( $keep ) === count( $names ) ) {
				return $m[0];
			}

			$replaced += count( $names ) - count( $keep );

			return array() === $keep ? '' : $m[1] . 'use const ' . implode( ', ', $keep ) . ";\n";
		},
		$source
	);

	// 2. Replace code references, token by token (strings and comments untouched).
	$out          = '';
	$statement    = array(); // Significant tokens since the last ;, { or }.
	$parens       = 0;
	$await_params = false;   // A function or fn keyword was seen; its "(" opens the parameter list.
	$param_depth  = null;    // Parenthesis depth of the open parameter list, if any.

	foreach ( token_get_all( $source ) as $token ) {
		if ( ! is_array( $token ) ) {
			$out .= $token;

			if ( in_array( $token, array( ';', '{', '}' ), true ) ) {
				$statement    = array();
				$parens       = 0;
				$await_params = false;
				$param_depth  = null;
			} else {
				if ( '(' === $token ) {
					$parens++;

					if ( $await_params ) {
						$param_depth  = $parens;
						$await_params = false;
					}
				} elseif ( ')' === $token ) {
					if ( null !== $param_depth && $parens === $param_depth ) {
						$param_depth = null; // Parameter list closed; an arrow function body may follow.
					}

					$parens--;
				}

				$statement[] = $token;
			}

			continue;
		}

		[ $id, $text, $line ] = $token;
		$name = ltrim( $text, '\\' );

		if ( ( T_STRING === $id || T_NAME_FULLY_QUALIFIED === $id ) && isset( MAP[ $name ] ) ) {
			// ->table() is a method call, which PHP does not allow in constant
			// expressions; ->value (schema files) is allowed there.
			if ( ! $is_schema && in_constant_expression( $statement, null !== $param_depth ) ) {
				$notes[]     = sprintf( 'line %d: %s is a const, property or parameter default; a method call is not allowed there. Resolve the name at runtime instead.', $line, $name );
				$out        .= $text;
				$statement[] = $id;
				continue;
			}

			$out .= 'TableName::' . MAP[ $name ] . ( $is_schema ? '->value' : '->table()' );
			$replaced++;
			$statement[] = $id;
			continue;
		}

		if ( T_FUNCTION === $id || T_FN === $id ) {
			$await_params = true;
		}

		if ( ! in_array( $id, array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
			$statement[] = $id;
		}

		if ( in_array( $id, array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true )
			&& preg_match_all( '/SMLISER_\w+_TABLE\b/', $text, $found )
		) {
			foreach ( array_unique( $found[0] ) as $constant ) {
				if ( isset( MAP[ $constant ] ) ) {
					$notes[] = sprintf( 'line %d: %s in a %s', $line, $constant, token_name( $id ) );
				}
			}
		}

		$out .= $text;
	}

	// 3. Import the enum where it is now used.
	if ( $replaced > 0 && str_contains( $out, 'TableName::' ) ) {
		$namespace = preg_match( '/^namespace\s+([^;\s{]+)/m', $out, $ns ) ? $ns[1] : '';
		$imported  = preg_match( '/^use\s+\\\\?' . preg_quote( ENUM_FQCN, '/' ) . '\s*;/m', $out );

		if ( ENUM_NAMESPACE !== $namespace && ! $imported ) {
			if ( '' === $namespace ) {
				$out    = preg_replace( '/TableName::/', '\\' . ENUM_FQCN . '::', $out );
				$notes[] = 'no namespace: used the fully qualified enum name';
			} elseif ( preg_match( '/^use\s/m', $out ) ) {
				// Next to the existing imports.
				$out = preg_replace( '/^use\s/m', 'use ' . ENUM_FQCN . ";\nuse ", $out, 1 );
			} else {
				$out = preg_replace( '/^(namespace\s+[^;]+;)\R+/m', "$1\n\nuse " . ENUM_FQCN . ";\n\n", $out, 1 );
			}
		}
	}

	if ( 0 === $replaced && array() === $notes ) {
		continue;
	}

	$files++;
	$changes  += $replaced;
	$warnings += count( $notes );

	printf( "%s  (%d replaced)\n", $path, $replaced );

	foreach ( $notes as $note ) {
		printf( "    check by hand: %s\n", $note );
	}

	if ( $write && $replaced > 0 ) {
		file_put_contents( $path, $out );
	}
}

printf(
	"\n%d file(s), %d reference(s) %s, %d to check by hand.%s\n",
	$files,
	$changes,
	$write ? 'replaced' : 'to replace',
	$warnings,
	$write ? '' : ' Dry run: nothing was written; add --write to apply.'
);

/**
 * Whether the current position is a constant expression: a const, a property
 * or static variable default, or a parameter default.
 *
 * @param array<int, int|string> $statement     Token IDs (or single-character tokens) since the last ;, { or }.
 * @param bool                   $in_parameters Whether a function's parameter list is open.
 * @return bool
 */
function in_constant_expression( array $statement, bool $in_parameters ) : bool {
	if ( $in_parameters || in_array( T_CONST, $statement, true ) ) {
		return true;
	}

	// Property or static variable default: modifiers, optional type, $name, =.
	$modifiers = array( T_PUBLIC, T_PROTECTED, T_PRIVATE, T_STATIC, T_VAR, T_READONLY );

	if ( array() === $statement || ! in_array( $statement[0], $modifiers, true ) ) {
		return false;
	}

	foreach ( $statement as $token ) {
		if ( T_DOUBLE_COLON === $token || T_OBJECT_OPERATOR === $token || T_FUNCTION === $token || T_FN === $token ) {
			return false; // static::$x, static::method(), static function …
		}

		if ( '=' === $token ) {
			return in_array( T_VARIABLE, $statement, true );
		}
	}

	return false;
}