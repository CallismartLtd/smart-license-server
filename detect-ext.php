#!/usr/bin/env php
<?php
/**
 * PHP Extension Requirement Scanner.
 *
 * Recursively scans a PHP codebase and determines PHP extensions required by
 * referenced internal PHP classes and functions.
 *
 * Extension detection uses a curated static symbol map first (so it can
 * flag extensions that are NOT loaded in the PHP environment running the
 * scanner), then falls back to runtime reflection over whatever extensions
 * happen to be loaded locally, to catch anything the static map doesn't
 * cover.
 *
 * Usage:
 *
 *     php detect-php-extensions.php /path/to/project
 *     php detect-php-extensions.php .
 *     php detect-php-extensions.php . --json
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Tools
 */

declare( strict_types=1 );

/**
 * PHP extensions that are part of the normal PHP runtime and should not be
 * emitted as Composer extension requirements.
 *
 * @var array<string, true>
 */
const BUILTIN_EXTENSIONS = [
	'Core'         => true,
	'date'         => true,
	'filter'       => true,
	'hash'         => true,
	'json'         => true,
	'libxml'       => true,
	'openssl'      => true,
	'pcre'         => true,
	'Reflection'   => true,
	'SPL'          => true,
	'standard'     => true,
	'zlib'         => true,
];

/**
 * PHP language constructs which look like function calls but are not
 * extension-provided functions.
 *
 * @var array<string, true>
 */
const LANGUAGE_CONSTRUCTS = [
	'isset'         => true,
	'empty'         => true,
	'eval'          => true,
	'exit'          => true,
	'die'           => true,
	'unset'         => true,
	'print'         => true,
	'include'       => true,
	'include_once'  => true,
	'require'       => true,
	'require_once'  => true,
];

/**
 * Token types that mean a following name-token is NOT a bare function call
 * (it's a method call, static call, or a `new`/attribute target already
 * handled elsewhere).
 *
 * @var array<int, true>
 */
function non_function_call_predecessors(): array {
	static $tokens = null;

	if ( null === $tokens ) {
		$tokens = [
			T_OBJECT_OPERATOR => true,
			T_NEW              => true,
			T_DOUBLE_COLON     => true,
			T_FUNCTION         => true,
		];

		if ( defined( 'T_NULLSAFE_OBJECT_OPERATOR' ) ) {
			$tokens[ constant( 'T_NULLSAFE_OBJECT_OPERATOR' ) ] = true;
		}

		if ( defined( 'T_ATTRIBUTE' ) ) {
			$tokens[ constant( 'T_ATTRIBUTE' ) ] = true;
		}
	}

	return $tokens;
}

/**
 * Exit with an error message.
 *
 * @param string $message Error message.
 * @param int    $code    Exit code.
 *
 * @return never
 */
function fail( string $message, int $code = 1 ): never {
	fwrite( STDERR, "Error: {$message}\n" );
	exit( $code );
}

/**
 * Determine whether a token represents a PHP name.
 *
 * @param int $token Token ID.
 *
 * @return bool
 */
function is_name_token( int $token ): bool {
	static $name_tokens = null;

	if ( null === $name_tokens ) {
		$name_tokens = [ T_STRING ];

		foreach (
			[
				'T_NAME_QUALIFIED',
				'T_NAME_FULLY_QUALIFIED',
				'T_NAME_RELATIVE',
			] as $constant
		) {
			if ( defined( $constant ) ) {
				$name_tokens[] = constant( $constant );
			}
		}
	}

	return in_array( $token, $name_tokens, true );
}

/**
 * Normalize a PHP symbol name.
 *
 * @param string $name Symbol name.
 *
 * @return string
 */
function normalize_name( string $name ): string {
	return ltrim( trim( $name ), '\\' );
}

/**
 * Get the next non-whitespace/comment token.
 *
 * @param array<int, mixed> $tokens Tokens.
 * @param int               $index  Current index.
 *
 * @return array{index:int,token:mixed}|null
 */
function next_meaningful_token( array $tokens, int $index ): ?array {
	$count = count( $tokens );

	for ( $index++; $index < $count; $index++ ) {
		$token = $tokens[ $index ];

		if ( is_array( $token ) ) {
			if (
				in_array(
					$token[0],
					[
						T_WHITESPACE,
						T_COMMENT,
						T_DOC_COMMENT,
					],
					true
				)
			) {
				continue;
			}

			return [
				'index' => $index,
				'token' => $token,
			];
		}

		if ( '' === trim( $token ) ) {
			continue;
		}

		return [
			'index' => $index,
			'token' => $token,
		];
	}

	return null;
}

/**
 * Get the previous non-whitespace/comment token.
 *
 * @param array<int, mixed> $tokens Tokens.
 * @param int               $index  Current index.
 *
 * @return array{index:int,token:mixed}|null
 */
function previous_meaningful_token( array $tokens, int $index ): ?array {
	for ( $index--; $index >= 0; $index-- ) {
		$token = $tokens[ $index ];

		if ( is_array( $token ) ) {
			if (
				in_array(
					$token[0],
					[
						T_WHITESPACE,
						T_COMMENT,
						T_DOC_COMMENT,
					],
					true
				)
			) {
				continue;
			}

			return [
				'index' => $index,
				'token' => $token,
			];
		}

		if ( '' === trim( $token ) ) {
			continue;
		}

		return [
			'index' => $index,
			'token' => $token,
		];
	}

	return null;
}

/**
 * Read a PHP qualified name starting at the supplied token.
 *
 * @param array<int, mixed> $tokens Tokens.
 * @param int               $index  Starting token index.
 *
 * @return array{index:int,name:string}|null
 */
function read_name( array $tokens, int $index ): ?array {
	$next = next_meaningful_token( $tokens, $index );

	if ( ! $next ) {
		return null;
	}

	$token = $next['token'];

	/*
	 * PHP 8 name tokens already contain the complete qualified name.
	 */
	if ( is_array( $token ) && is_name_token( $token[0] ) ) {
		return [
			'index' => $next['index'],
			'name'  => $token[1],
		];
	}

	/*
	 * Fully-qualified names can be tokenized as:
	 *
	 *     \
	 *     Foo
	 *     \
	 *     Bar
	 */
	if ( '\\' !== $token ) {
		return null;
	}

	$name    = '\\';
	$current = $next['index'];

	while ( true ) {
		$part = next_meaningful_token( $tokens, $current );

		if ( ! $part ) {
			break;
		}

		$part_token = $part['token'];

		if ( is_array( $part_token ) && is_name_token( $part_token[0] ) ) {
			$name   .= $part_token[1];
			$current = $part['index'];
			continue;
		}

		if ( '\\' === $part_token ) {
			$name   .= '\\';
			$current = $part['index'];
			continue;
		}

		break;
	}

	return [
		'index' => $current,
		'name'  => $name,
	];
}

/**
 * Read one or more qualified names separated by the given separator tokens
 * (e.g. '|' for union/catch types, '&' for intersection types, ',' for
 * `implements`/`extends` lists).
 *
 * @param array<int, mixed>  $tokens     Tokens.
 * @param int                $index      Starting token index.
 * @param array<int, string> $separators Separator token strings.
 *
 * @return array{index:int,names:array<int,string>}
 */
function read_name_list( array $tokens, int $index, array $separators ): array {
	$names   = [];
	$current = $index;

	while ( true ) {
		$name = read_name( $tokens, $current );

		if ( ! $name ) {
			break;
		}

		$names[] = $name['name'];
		$current = $name['index'];

		$next = next_meaningful_token( $tokens, $current );

		if ( ! $next || ! in_array( $next['token'], $separators, true ) ) {
			break;
		}

		$current = $next['index'];
	}

	return [
		'index' => $current,
		'names' => $names,
	];
}

/**
 * Resolve a class name against namespace imports.
 *
 * Unlike functions/constants, PHP resolves an unqualified class name
 * strictly against the current namespace — there is no fallback to the
 * global namespace.
 *
 * @param string               $name      Referenced name.
 * @param string               $namespace Current namespace.
 * @param array<string,string> $uses      Imported class names (lowercased alias => FQCN).
 *
 * @return string
 */
function resolve_class_name(
	string $name,
	string $namespace,
	array $uses
): string {
	$name = trim( $name );

	if ( '' === $name ) {
		return '';
	}

	if ( str_starts_with( $name, '\\' ) ) {
		return normalize_name( $name );
	}

	$name = str_replace( ' ', '', $name );

	$separator = strpos( $name, '\\' );

	if ( false === $separator ) {
		if ( isset( $uses[ strtolower( $name ) ] ) ) {
			return $uses[ strtolower( $name ) ];
		}

		if ( '' !== $namespace ) {
			return $namespace . '\\' . $name;
		}

		return $name;
	}

	$alias = substr( $name, 0, $separator );

	if ( isset( $uses[ strtolower( $alias ) ] ) ) {
		return $uses[ strtolower( $alias ) ] . substr( $name, $separator );
	}

	if ( '' !== $namespace ) {
		return $namespace . '\\' . $name;
	}

	return $name;
}

/**
 * Resolve a function name reference for extension-symbol lookup purposes.
 *
 * PHP resolves an unqualified function call against the current namespace
 * first, then falls back to the global namespace if no such function is
 * declared there. This scanner does not track user-defined function
 * declarations, so — mirroring the shortcut taken by tools like
 * composer-require-checker — an unqualified call (not imported via
 * `use function`) is resolved directly to the global/bare name, which is
 * the form under which built-in and extension functions are indexed.
 *
 * @param string               $name           Referenced name, as written.
 * @param string               $namespace      Current namespace.
 * @param array<string,string> $function_uses  Imported function names (lowercased alias => FQFN).
 *
 * @return string
 */
function resolve_function_name(
	string $name,
	string $namespace,
	array $function_uses
): string {
	$name = trim( $name );

	if ( '' === $name ) {
		return '';
	}

	if ( str_starts_with( $name, '\\' ) ) {
		return normalize_name( $name );
	}

	$name = str_replace( ' ', '', $name );

	if ( false === strpos( $name, '\\' ) ) {
		$key = strtolower( $name );

		if ( isset( $function_uses[ $key ] ) ) {
			return $function_uses[ $key ];
		}

		/*
		 * Unqualified and not imported: resolve to the bare/global name
		 * rather than prefixing the current namespace, so this matches
		 * against the known internal/extension function indexes.
		 */
		return $name;
	}

	if ( '' !== $namespace ) {
		return $namespace . '\\' . $name;
	}

	return $name;
}

/**
 * Add a reference to the result set.
 *
 * @param array<string,array> $references Reference map.
 * @param string              $name       Symbol name.
 * @param string              $file       File.
 * @param int                 $line       Line.
 *
 * @return void
 */
function add_reference(
	array &$references,
	string $name,
	string $file,
	int $line
): void {
	$name = normalize_name( $name );

	if ( '' === $name ) {
		return;
	}

	$key = strtolower( $name );

	if ( ! isset( $references[ $key ] ) ) {
		$references[ $key ] = [
			'name'  => $name,
			'files' => [],
		];
	}

	$references[ $key ]['files'][ $file ][] = $line;
}

/**
 * Load the curated static extension symbol map.
 *
 * @return array<string,array{functions:array<int,string>,classes:array<int,string>}>
 */
function load_static_extension_map(): array {
	$path = __DIR__ . '/extension-symbols.php';

	if ( ! is_file( $path ) ) {
		fail( "Missing extension symbol map: {$path}" );
	}

	$map = require $path;

	if ( ! is_array( $map ) ) {
		fail( "Extension symbol map did not return an array: {$path}" );
	}

	return $map;
}

/**
 * Build lookup indexes (by lowercase symbol name) from the static map.
 *
 * @param array<string,array> $static_map Static extension map.
 *
 * @return array{classes:array<string,array{name:string,extension:string}>,functions:array<string,array{name:string,extension:string}>}
 */
function build_static_indexes( array $static_map ): array {
	$classes   = [];
	$functions = [];

	foreach ( $static_map as $extension => $symbols ) {
		foreach ( $symbols['classes'] ?? [] as $class ) {
			$classes[ strtolower( normalize_name( $class ) ) ] = [
				'name'      => $class,
				'extension' => $extension,
			];
		}

		foreach ( $symbols['functions'] ?? [] as $function ) {
			$functions[ strtolower( $function ) ] = [
				'name'      => $function,
				'extension' => $extension,
			];
		}
	}

	return [
		'classes'   => $classes,
		'functions' => $functions,
	];
}

/**
 * Build the internal PHP class/interface/trait/enum index from whatever is
 * loaded in the PHP environment running the scanner. Used only as a
 * fallback for symbols the static map does not cover.
 *
 * @return array<string,array{name:string,extension:string}>
 */
function build_runtime_class_index(): array {
	$result = [];

	$symbols = array_merge(
		get_declared_classes(),
		get_declared_interfaces(),
		get_declared_traits()
	);

	if ( function_exists( 'get_declared_enums' ) ) {
		$symbols = array_merge(
			$symbols,
			get_declared_enums()
		);
	}

	foreach ( $symbols as $symbol ) {
		try {
			$reflection = new ReflectionClass( $symbol );
		} catch ( ReflectionException ) {
			continue;
		}

		if ( ! $reflection->isInternal() ) {
			continue;
		}

		$extension = $reflection->getExtension();

		if ( ! $extension ) {
			continue;
		}

		$name = $reflection->getName();

		$result[ strtolower( normalize_name( $name ) ) ] = [
			'name'      => normalize_name( $name ),
			'extension' => $extension->getName(),
		];
	}

	return $result;
}

/**
 * Build the internal PHP function index from whatever is loaded in the PHP
 * environment running the scanner. Used only as a fallback for symbols the
 * static map does not cover.
 *
 * @return array<string,array{name:string,extension:string}>
 */
function build_runtime_function_index(): array {
	$result = [];

	foreach ( get_defined_functions()['internal'] as $function ) {
		try {
			$reflection = new ReflectionFunction( $function );
		} catch ( ReflectionException ) {
			continue;
		}

		$extension = $reflection->getExtension();

		if ( ! $extension ) {
			continue;
		}

		$result[ strtolower( $function ) ] = [
			'name'      => $function,
			'extension' => $extension->getName(),
		];
	}

	return $result;
}

/**
 * Build the index of classes defined by the scanned codebase, so those are
 * never mistaken for internal/extension symbols.
 *
 * @param array<int,string> $files PHP files.
 *
 * @return array<string,true>
 */
function build_user_class_index( array $files ): array {
	$result = [];

	foreach ( $files as $file ) {
		$contents = file_get_contents( $file );

		if ( false === $contents ) {
			continue;
		}

		$tokens    = token_get_all( $contents );
		$namespace = '';
		$count     = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$token = $tokens[ $i ];

			if ( ! is_array( $token ) ) {
				continue;
			}

			if ( T_NAMESPACE === $token[0] ) {
				$name = read_name( $tokens, $i );
				$namespace = $name ? normalize_name( $name['name'] ) : '';
				continue;
			}

			if (
				! in_array(
					$token[0],
					[
						T_CLASS,
						T_INTERFACE,
						T_TRAIT,
						T_ENUM,
					],
					true
				)
			) {
				continue;
			}

			/*
			 * Anonymous classes have no class name.
			 */
			$name_token = next_meaningful_token( $tokens, $i );

			if (
				! $name_token ||
				! is_array( $name_token['token'] ) ||
				! is_name_token( $name_token['token'][0] )
			) {
				continue;
			}

			$name = $name_token['token'][1];

			if ( '' !== $namespace ) {
				$name = $namespace . '\\' . $name;
			}

			$result[ strtolower( normalize_name( $name ) ) ] = true;
		}
	}

	return $result;
}

/**
 * Collect PHP files recursively.
 *
 * @param string $root Root directory.
 *
 * @return array<int,string>
 */
function collect_php_files( string $root ): array {
	$files = [];

	$excluded = [
		'.git',
		'.svn',
		'.hg',
		'vendor',
		'node_modules',
		'build',
		'dist',
		'coverage',
		'cache',
	];

	$directory = new RecursiveDirectoryIterator(
		$root,
		FilesystemIterator::SKIP_DOTS
	);

	$iterator = new RecursiveIteratorIterator( $directory );

	foreach ( $iterator as $file ) {
		if ( ! $file->isFile() ) {
			continue;
		}

		if ( 'php' !== strtolower( $file->getExtension() ) ) {
			continue;
		}

		$relative = substr(
			$file->getPathname(),
			strlen( rtrim( $root, DIRECTORY_SEPARATOR ) ) + 1
		);

		$parts = explode( DIRECTORY_SEPARATOR, $relative );

		if ( array_intersect( $parts, $excluded ) ) {
			continue;
		}

		$files[] = $file->getPathname();
	}

	sort( $files, SORT_STRING );

	return $files;
}

/**
 * Parse a `use` import statement (class, function, or const; plain or
 * grouped) starting at the `use` keyword.
 *
 * Populates $uses (classes) and $function_uses (functions) by reference.
 * Const imports are recognized (so they don't fall through incorrectly)
 * but not recorded, since this scanner does not track bare constant
 * references.
 *
 * @param array<int, mixed>    $tokens        Tokens.
 * @param int                  $index         Index of the `use` token.
 * @param array<string,string> $uses          Class import map (by reference).
 * @param array<string,string> $function_uses Function import map (by reference).
 *
 * @return int Index to resume the main scan loop from.
 */
function parse_use_statement(
	array $tokens,
	int $index,
	array &$uses,
	array &$function_uses
): int {
	$count = count( $tokens );
	$peek  = next_meaningful_token( $tokens, $index );

	/*
	 * Closure `use (...)` — not an import statement at all.
	 */
	if ( $peek && '(' === $peek['token'] ) {
		return $index;
	}

	$statement_kind = 'class';
	$cursor         = $index;

	if ( $peek && is_array( $peek['token'] ) ) {
		if ( T_FUNCTION === $peek['token'][0] ) {
			$statement_kind = 'function';
			$cursor         = $peek['index'];
		} elseif ( T_CONST === $peek['token'][0] ) {
			$statement_kind = 'const';
			$cursor         = $peek['index'];
		}
	}

	/**
	 * Record a single import (plain or from within a group).
	 *
	 * @param string $kind Item kind: 'class', 'function', or 'const'.
	 * @param string $fqn  Fully-qualified name.
	 * @param string $alias Alias (bare identifier).
	 */
	$record = static function ( string $kind, string $fqn, string $alias ) use ( &$uses, &$function_uses ): void {
		$fqn = normalize_name( $fqn );

		if ( '' === $fqn || '' === $alias ) {
			return;
		}

		if ( 'function' === $kind ) {
			$function_uses[ strtolower( $alias ) ] = $fqn;
		} elseif ( 'class' === $kind ) {
			$uses[ strtolower( $alias ) ] = $fqn;
		}
		/*
		 * 'const' imports are intentionally not recorded.
		 */
	};

	/*
	 * One top-level `use` statement may contain several comma-separated
	 * items, and (at most) one of those items may be a `{...}` group.
	 */
	while ( true ) {
		$item_kind = $statement_kind;
		$peek      = next_meaningful_token( $tokens, $cursor );

		if ( $peek && is_array( $peek['token'] ) ) {
			if ( T_FUNCTION === $peek['token'][0] ) {
				$item_kind = 'function';
				$cursor    = $peek['index'];
				$peek      = next_meaningful_token( $tokens, $cursor );
			} elseif ( T_CONST === $peek['token'][0] ) {
				$item_kind = 'const';
				$cursor    = $peek['index'];
				$peek      = next_meaningful_token( $tokens, $cursor );
			}
		}

		$name = read_name( $tokens, $cursor );
		$base = $name ? $name['name'] : '';

		if ( $name ) {
			$cursor = $name['index'];
		}

		$next = next_meaningful_token( $tokens, $cursor );

		/*
		 * On PHP 8, the `\` immediately before a group's `{` tokenizes as
		 * its own T_NS_SEPARATOR token rather than being folded into the
		 * preceding name token — skip over it so the group check below
		 * still fires.
		 */
		if ( $next && is_array( $next['token'] ) && T_NS_SEPARATOR === $next['token'][0] ) {
			$after_separator = next_meaningful_token( $tokens, $next['index'] );

			if ( $after_separator && '{' === $after_separator['token'] ) {
				$cursor = $next['index'];
				$next   = $after_separator;
			}
		}

		/*
		 * Group: `Foo\Bar\{Baz, Qux as Q, function fn_a}`.
		 */
		if ( $next && '{' === $next['token'] ) {
			$cursor = $next['index'];
			$prefix = rtrim( $base, '\\' ) . '\\';

			while ( true ) {
				$sub_kind  = $item_kind;
				$sub_peek  = next_meaningful_token( $tokens, $cursor );

				if ( $sub_peek && '}' === $sub_peek['token'] ) {
					$cursor = $sub_peek['index'];
					break;
				}

				if ( $sub_peek && is_array( $sub_peek['token'] ) ) {
					if ( T_FUNCTION === $sub_peek['token'][0] ) {
						$sub_kind = 'function';
						$cursor   = $sub_peek['index'];
					} elseif ( T_CONST === $sub_peek['token'][0] ) {
						$sub_kind = 'const';
						$cursor   = $sub_peek['index'];
					}
				}

				$sub_name = read_name( $tokens, $cursor );

				if ( ! $sub_name ) {
					break;
				}

				$cursor    = $sub_name['index'];
				$sub_fqn   = $prefix . ltrim( $sub_name['name'], '\\' );
				$sub_parts = explode( '\\', rtrim( $sub_fqn, '\\' ) );
				$sub_alias = end( $sub_parts );

				$sub_next = next_meaningful_token( $tokens, $cursor );

				if ( $sub_next && is_array( $sub_next['token'] ) && T_AS === $sub_next['token'][0] ) {
					$alias_token = next_meaningful_token( $tokens, $sub_next['index'] );

					if ( $alias_token && is_array( $alias_token['token'] ) && T_STRING === $alias_token['token'][0] ) {
						$sub_alias = $alias_token['token'][1];
						$cursor    = $alias_token['index'];
					}
				}

				$record( $sub_kind, $sub_fqn, $sub_alias );

				$after = next_meaningful_token( $tokens, $cursor );

				if ( $after && ',' === $after['token'] ) {
					$cursor = $after['index'];
					continue;
				}

				if ( $after && '}' === $after['token'] ) {
					$cursor = $after['index'];
				}

				break;
			}

			$next = next_meaningful_token( $tokens, $cursor );
		} elseif ( '' !== $base ) {
			/*
			 * Plain (non-grouped) item.
			 */
			$parts = explode( '\\', rtrim( $base, '\\' ) );
			$alias = end( $parts );

			if ( $next && is_array( $next['token'] ) && T_AS === $next['token'][0] ) {
				$alias_token = next_meaningful_token( $tokens, $next['index'] );

				if ( $alias_token && is_array( $alias_token['token'] ) && T_STRING === $alias_token['token'][0] ) {
					$alias  = $alias_token['token'][1];
					$cursor = $alias_token['index'];
					$next   = next_meaningful_token( $tokens, $cursor );
				}
			}

			$record( $item_kind, $base, $alias );
		}

		if ( $next && ',' === $next['token'] ) {
			$cursor = $next['index'];
			continue;
		}

		if ( $next ) {
			$cursor = $next['index'];
		}

		break;
	}

	return $cursor;
}

/**
 * Scan one PHP file.
 *
 * @param string $file File path.
 *
 * @return array{
 *     classes:array<string,array>,
 *     functions:array<string,array>
 * }
 */
function scan_file( string $file ): array {
	$contents = file_get_contents( $file );

	if ( false === $contents ) {
		return [
			'classes'   => [],
			'functions' => [],
		];
	}

	$tokens = token_get_all( $contents );

	$classes   = [];
	$functions = [];

	$namespace     = '';
	$uses          = [];
	$function_uses = [];

	$non_function_predecessors = non_function_call_predecessors();

	$count = count( $tokens );

	for ( $i = 0; $i < $count; $i++ ) {
		$token = $tokens[ $i ];

		if ( ! is_array( $token ) ) {
			continue;
		}

		$id   = $token[0];
		$text = $token[1];
		$line = $token[2];

		/*
		 * Namespace.
		 */
		if ( T_NAMESPACE === $id ) {
			$name      = read_name( $tokens, $i );
			$namespace = $name ? normalize_name( $name['name'] ) : '';
			$uses          = [];
			$function_uses = [];

			continue;
		}

		/*
		 * Imports: class, function, and const `use` statements, plain or
		 * grouped.
		 */
		if ( T_USE === $id ) {
			$resume = parse_use_statement( $tokens, $i, $uses, $function_uses );

			if ( $resume > $i ) {
				$i = $resume;
			}

			continue;
		}

		/*
		 * Explicit class contexts. Supports union (`|`) and intersection
		 * (`&`) types for catch blocks, and comma-separated lists for
		 * `implements`/`extends`.
		 */
		if (
			in_array(
				$id,
				[
					T_NEW,
					T_EXTENDS,
					T_IMPLEMENTS,
					T_INSTANCEOF,
					T_CATCH,
				],
				true
			)
		) {
			$list_start = $i;

			/*
			 * `catch (Type $var)` — unlike new/extends/implements/
			 * instanceof, the type name is preceded by a "(", which must
			 * be skipped before reading the name(s).
			 */
			if ( T_CATCH === $id ) {
				$paren = next_meaningful_token( $tokens, $i );

				if ( $paren && '(' === $paren['token'] ) {
					$list_start = $paren['index'];
				}
			}

			$list = read_name_list( $tokens, $list_start, [ '|', '&', ',' ] );

			foreach ( $list['names'] as $raw_name ) {
				$class = resolve_class_name( $raw_name, $namespace, $uses );

				if (
					! in_array(
						strtolower( $class ),
						[ 'self', 'parent', 'static' ],
						true
					)
				) {
					add_reference( $classes, $class, $file, $line );
				}
			}

			continue;
		}

		/*
		 * Attribute target, e.g. `#[Foo(...)]` — treat as a class
		 * reference rather than a function call.
		 */
		if ( defined( 'T_ATTRIBUTE' ) && constant( 'T_ATTRIBUTE' ) === $id ) {
			$name = read_name( $tokens, $i );

			if ( $name ) {
				$class = resolve_class_name( $name['name'], $namespace, $uses );
				add_reference( $classes, $class, $file, $line );
			}

			continue;
		}

		/*
		 * Class static access:
		 *
		 *     mysqli::query()
		 *     ZipArchive::CREATE
		 *     PDO::class
		 */
		if ( is_name_token( $id ) ) {
			$next = next_meaningful_token( $tokens, $i );

			$next_is_double_colon = $next
				&& is_array( $next['token'] )
				&& T_DOUBLE_COLON === $next['token'][0];

			if ( $next_is_double_colon ) {
				$class = resolve_class_name( $text, $namespace, $uses );

				if (
					! in_array(
						strtolower( $class ),
						[ 'self', 'parent', 'static' ],
						true
					)
				) {
					add_reference( $classes, $class, $file, $line );
				}

				continue;
			}

			/*
			 * Function calls: a name token immediately followed by "(",
			 * that is not a method/static call, a declaration, a `new`
			 * target, or an attribute target.
			 */
			if ( $next && '(' === $next['token'] ) {
				$previous = previous_meaningful_token( $tokens, $i );

				if (
					$previous &&
					is_array( $previous['token'] ) &&
					isset( $non_function_predecessors[ $previous['token'][0] ] )
				) {
					continue;
				}

				$function = resolve_function_name( $text, $namespace, $function_uses );

				if ( isset( LANGUAGE_CONSTRUCTS[ strtolower( $function ) ] ) ) {
					continue;
				}

				add_reference( $functions, $function, $file, $line );

				continue;
			}
		}
	}

	return [
		'classes'   => $classes,
		'functions' => $functions,
	];
}

/**
 * Convert an extension name to a Composer platform requirement.
 *
 * @param string $extension Extension name.
 *
 * @return string
 */
function composer_extension_name( string $extension ): string {
	return 'ext-' . strtolower( $extension );
}

/**
 * Sort and de-duplicate a symbol list in place.
 *
 * @param array<int,array{symbol:string,type:string}> $symbols Symbols.
 *
 * @return array<int,array{symbol:string,type:string}>
 */
function dedupe_symbols( array $symbols ): array {
	$unique = [];

	foreach ( $symbols as $symbol ) {
		$key = strtolower( $symbol['type'] . ':' . $symbol['symbol'] );
		$unique[ $key ] = $symbol;
	}

	$symbols = array_values( $unique );

	usort(
		$symbols,
		static fn( array $a, array $b ): int => strcasecmp( $a['symbol'], $b['symbol'] )
	);

	return $symbols;
}

/*
 * =========================================================================
 * CLI
 * =========================================================================
 */

$args = $argv;
array_shift( $args );

$json = false;
$root = null;

foreach ( $args as $arg ) {
	if ( '--json' === $arg ) {
		$json = true;
		continue;
	}

	if ( str_starts_with( $arg, '-' ) ) {
		fail( "Unknown option: {$arg}" );
	}

	if ( null !== $root ) {
		fail( 'Only one codebase path may be supplied.' );
	}

	$root = $arg;
}

$root ??= getcwd();

$root = realpath( $root );

if ( false === $root || ! is_dir( $root ) ) {
	fail( 'Codebase path does not exist or is not a directory.' );
}

$files = collect_php_files( $root );

if ( ! $files ) {
	fail( 'No PHP files were found.' );
}

$static_map        = load_static_extension_map();
$static_indexes    = build_static_indexes( $static_map );
$runtime_classes   = build_runtime_class_index();
$runtime_functions = build_runtime_function_index();
$user_classes      = build_user_class_index( $files );

$used_classes   = [];
$used_functions = [];

foreach ( $files as $file ) {
	$scanned = scan_file( $file );

	foreach ( $scanned['classes'] as $key => $reference ) {
		if ( ! isset( $used_classes[ $key ] ) ) {
			$used_classes[ $key ] = [
				'name'  => $reference['name'],
				'files' => [],
			];
		}

		foreach ( $reference['files'] as $path => $lines ) {
			$used_classes[ $key ]['files'][ $path ] = array_merge(
				$used_classes[ $key ]['files'][ $path ] ?? [],
				$lines
			);
		}
	}

	foreach ( $scanned['functions'] as $key => $reference ) {
		if ( ! isset( $used_functions[ $key ] ) ) {
			$used_functions[ $key ] = [
				'name'  => $reference['name'],
				'files' => [],
			];
		}

		foreach ( $reference['files'] as $path => $lines ) {
			$used_functions[ $key ]['files'][ $path ] = array_merge(
				$used_functions[ $key ]['files'][ $path ] ?? [],
				$lines
			);
		}
	}
}

ksort( $used_classes, SORT_STRING );
ksort( $used_functions, SORT_STRING );

/*
 * =========================================================================
 * Resolve extension requirements.
 *
 * Static map first (works even when the extension isn't loaded here),
 * runtime reflection as a fallback (covers anything the static map
 * doesn't enumerate, as long as it happens to be loaded locally).
 * =========================================================================
 */

$extensions = [];
$bundled    = [];
$unresolved = [
	'classes'   => [],
	'functions' => [],
];

/*
 * Classes.
 */
foreach ( $used_classes as $key => $reference ) {
	if ( isset( $user_classes[ $key ] ) ) {
		continue;
	}

	$symbol = $static_indexes['classes'][ $key ] ?? $runtime_classes[ $key ] ?? null;

	if ( null === $symbol ) {
		$unresolved['classes'][ $key ] = $reference;
		continue;
	}

	$entry = [
		'symbol' => $symbol['name'],
		'type'   => 'class',
	];

	if ( isset( BUILTIN_EXTENSIONS[ $symbol['extension'] ] ) ) {
		$bundled[ $symbol['extension'] ][] = $entry;
	} else {
		$extensions[ $symbol['extension'] ][] = $entry;
	}
}

/*
 * Functions.
 */
foreach ( $used_functions as $key => $reference ) {
	$symbol = $static_indexes['functions'][ $key ] ?? $runtime_functions[ $key ] ?? null;

	if ( null === $symbol ) {
		/*
		 * Most unresolved function references are just the project's own
		 * (or a vendor's) functions — not tracked here since this scanner
		 * only indexes user-declared *classes*, not functions. These are
		 * intentionally not reported as "unresolved" to avoid drowning
		 * real findings in noise; only class references are reported as
		 * unresolved.
		 */
		continue;
	}

	$entry = [
		'symbol' => $symbol['name'],
		'type'   => 'function',
	];

	if ( isset( BUILTIN_EXTENSIONS[ $symbol['extension'] ] ) ) {
		$bundled[ $symbol['extension'] ][] = $entry;
	} else {
		$extensions[ $symbol['extension'] ][] = $entry;
	}
}

ksort( $extensions, SORT_STRING );
ksort( $bundled, SORT_STRING );
ksort( $unresolved['classes'], SORT_STRING );

foreach ( $extensions as &$symbols ) {
	$symbols = dedupe_symbols( $symbols );
}
unset( $symbols );

foreach ( $bundled as &$symbols ) {
	$symbols = dedupe_symbols( $symbols );
}
unset( $symbols );

/*
 * Which of the required extensions are actually loaded in the PHP
 * environment running this scan — the main reason to consult the static
 * map instead of relying only on runtime reflection.
 */
$loaded_status = [];

foreach ( array_keys( $extensions ) as $extension ) {
	$loaded_status[ $extension ] = extension_loaded( $extension );
}

/*
 * =========================================================================
 * JSON output.
 * =========================================================================
 */

if ( $json ) {
	$output = [
		'root'         => $root,
		'files'        => count( $files ),
		'requirements' => [],
		'bundled'      => $bundled,
		'unresolved'   => $unresolved,
	];

	foreach ( $extensions as $extension => $symbols ) {
		$output['requirements'][ composer_extension_name( $extension ) ] = [
			'extension' => $extension,
			'loaded_in_scanner_env' => $loaded_status[ $extension ],
			'symbols'   => $symbols,
		];
	}

	echo json_encode(
		$output,
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . PHP_EOL;

	exit( 0 );
}

/*
 * =========================================================================
 * Human-readable output.
 * =========================================================================
 */

echo "\n";
echo "PHP Extension Requirement Scanner\n";
echo "=================================\n\n";

echo "Codebase: {$root}\n";
echo "PHP files: " . count( $files ) . "\n\n";

echo "PHP extensions required by detected symbols:\n";
echo "---------------------------------------------\n\n";

if ( ! $extensions ) {
	echo "  None detected.\n\n";
} else {
	foreach ( $extensions as $extension => $symbols ) {
		$flag = $loaded_status[ $extension ] ? '' : '  [NOT LOADED in this PHP environment]';
		echo "  " . composer_extension_name( $extension ) . $flag . "\n";

		foreach ( $symbols as $symbol ) {
			echo "      {$symbol['type']}: {$symbol['symbol']}\n";
		}

		echo "\n";
	}
}

echo "Built-in / bundled PHP facilities:\n";
echo "-----------------------------------\n\n";

if ( ! $bundled ) {
	echo "  None detected.\n\n";
} else {
	foreach ( $bundled as $extension => $symbols ) {
		echo "  {$extension}\n";

		foreach ( $symbols as $symbol ) {
			echo "      {$symbol['type']}: {$symbol['symbol']}\n";
		}

		echo "\n";
	}
}

echo "Unresolved class references (not project-defined, not in the static map, not loaded here):\n";
echo "-------------------------------------------------------------------------------------------\n\n";

if ( ! $unresolved['classes'] ) {
	echo "  None.\n\n";
} else {
	foreach ( $unresolved['classes'] as $reference ) {
		echo "  {$reference['name']}\n";

		foreach ( $reference['files'] as $file => $lines ) {
			$lines = array_unique( $lines );
			sort( $lines, SORT_NUMERIC );

			echo "      {$file}: " . implode( ', ', $lines ) . "\n";
		}

		echo "\n";
	}
}

echo "Composer requirements:\n";
echo "----------------------\n\n";

if ( ! $extensions ) {
	echo "  None detected.\n";
} else {
	foreach ( array_keys( $extensions ) as $extension ) {
		echo '    "' .
			composer_extension_name( $extension ) .
			'": "*",' .
			"\n";
	}
}

echo "\n";