<?php
/**
 * App file reference class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\HostedApps\Storage
 */

namespace SmartLicenseServer\HostedApps\Storage;

use SmartLicenseServer\Exceptions\StorageException;

/**
 * Names one file of a hosted app without saying where it is stored.
 *
 * A file is identified by the app type, the app slug, what kind of file it
 * is, and (for the kinds that have several files) its name. Every adapter
 * maps this identity to its own storage; nothing else in the app ever sees a
 * storage path.
 *
 * Kinds:
 *  - package:  the main distributable, "{slug}.zip". No name.
 *  - manifest: the app.json served as "{type}.manifest". No name.
 *  - artifact: another distributable served under "artifacts". Named.
 *  - asset:    a screenshot, banner, icon or similar. Named.
 *  - document: an app-type file such as readme.txt or style.css. Named.
 *
 * Construction validates everything, so an AppFile that exists is always
 * well formed. The rules:
 *  - type: lowercase letters, digits and underscores in runs joined by
 *    single hyphens, at most 255 bytes; ^[a-z0-9_]+(-[a-z0-9_]+)*$
 *  - slug and name: one path segment of 1 to 255 bytes; no "/", "\" or
 *    control character; does not start with "." (so never "." or "..",
 *    and never a dot file such as .htaccess). Slugs are not restricted
 *    further because existing apps already have slugs with uppercase and
 *    other letters; how new slugs are made is the repository's business.
 *  - name: required for named kinds, empty for the others.
 */
final class AppFile {

	public const PACKAGE  = 'package';
	public const MANIFEST = 'manifest';
	public const ARTIFACT = 'artifact';
	public const ASSET    = 'asset';
	public const DOCUMENT = 'document';

	/**
	 * Kinds that need a name.
	 *
	 * @var string[]
	 */
	public const NAMED_KINDS = [ self::ARTIFACT, self::ASSET, self::DOCUMENT ];

	/**
	 * All kinds.
	 *
	 * @var string[]
	 */
	public const KINDS = [ self::PACKAGE, self::MANIFEST, self::ARTIFACT, self::ASSET, self::DOCUMENT ];

	/**
	 * Pattern for app types.
	 *
	 * @var string
	 */
	public const IDENTIFIER_PATTERN = '/^[a-z0-9_]+(?:-[a-z0-9_]+)*$/';

	/**
	 * Longest type, slug or name, in bytes.
	 *
	 * @var int
	 */
	public const MAX_BYTES = 255;

	/**
	 * Construct a reference. Prefer the named constructors.
	 *
	 * @param string $type App type, e.g. "plugin".
	 * @param string $slug App slug.
	 * @param string $kind One of the kind constants.
	 * @param string $name File name for named kinds; "" for the others.
	 * @throws StorageException INVALID_PATH when any part breaks the rules.
	 */
	public function __construct(
		public readonly string $type,
		public readonly string $slug,
		public readonly string $kind,
		public readonly string $name = '',
	) {
		static::check_identity( $type, $slug );

		if ( ! in_array( $kind, self::KINDS, true ) ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( '"%s" is not an app file kind.', $kind ), $type, $slug, $name );
		}

		$named = in_array( $kind, self::NAMED_KINDS, true );

		if ( $named ) {
			static::check_name( $type, $slug, $name );
		} elseif ( '' !== $name ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( 'A %s file has no name.', $kind ), $type, $slug, $name );
		}
	}

	/**
	 * The main package.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return self
	 */
	public static function package( string $type, string $slug ) : self {
		return new self( $type, $slug, self::PACKAGE );
	}

	/**
	 * The manifest.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return self
	 */
	public static function manifest( string $type, string $slug ) : self {
		return new self( $type, $slug, self::MANIFEST );
	}

	/**
	 * An artifact.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Artifact file name.
	 * @return self
	 */
	public static function artifact( string $type, string $slug, string $name ) : self {
		return new self( $type, $slug, self::ARTIFACT, $name );
	}

	/**
	 * An asset.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Asset file name.
	 * @return self
	 */
	public static function asset( string $type, string $slug, string $name ) : self {
		return new self( $type, $slug, self::ASSET, $name );
	}

	/**
	 * A document.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @param string $name Document file name.
	 * @return self
	 */
	public static function document( string $type, string $slug, string $name ) : self {
		return new self( $type, $slug, self::DOCUMENT, $name );
	}

	/**
	 * Throw INVALID_PATH unless a type and slug follow the rules.
	 *
	 * @param string $type App type.
	 * @param string $slug App slug.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	public static function check_identity( string $type, string $slug ) : void {
		if ( ! static::is_identifier( $type ) ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid app type.', $type ), $type, $slug );
		}

		if ( ! static::is_segment( $slug ) ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid app slug.', $slug ), $type, $slug );
		}
	}

	/**
	 * Throw INVALID_PATH unless a type follows the rules.
	 *
	 * @param string $type App type.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	public static function check_type( string $type ) : void {
		if ( ! static::is_identifier( $type ) ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid app type.', $type ), $type );
		}
	}

	/**
	 * Throw INVALID_PATH unless a file name follows the rules.
	 *
	 * @param string $type App type, for the error data.
	 * @param string $slug App slug, for the error data.
	 * @param string $name File name.
	 * @return void
	 * @throws StorageException INVALID_PATH.
	 */
	public static function check_name( string $type, string $slug, string $name ) : void {
		if ( ! static::is_segment( $name ) ) {
			throw new StorageException( StorageException::INVALID_PATH, sprintf( '"%s" is not a valid file name.', $name ), $type, $slug, $name );
		}
	}

	/**
	 * Tell whether a string is a valid app type.
	 *
	 * @param string $value The string.
	 * @return bool True when valid.
	 */
	public static function is_identifier( string $value ) : bool {
		return strlen( $value ) <= self::MAX_BYTES && 1 === preg_match( self::IDENTIFIER_PATTERN, $value );
	}

	/**
	 * Tell whether a string is a valid slug or file name: one path segment
	 * that is not a dot name.
	 *
	 * @param string $value The string.
	 * @return bool True when valid.
	 */
	public static function is_segment( string $value ) : bool {
		return '' !== $value
			&& strlen( $value ) <= self::MAX_BYTES
			&& ! str_starts_with( $value, '.' )
			&& 1 !== preg_match( '/[\\\\\/\x00-\x1F\x7F]/', $value );
	}
}