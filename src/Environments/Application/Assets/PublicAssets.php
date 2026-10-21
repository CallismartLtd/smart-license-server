<?php
/**
 * Public assets class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Assets
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Environments\Application\Assets;

use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Publishes static asset folders in the public directory.
 *
 * Philosophy:
 *
 *  - Static files are served from public/, by the web server. PHP never
 *    runs for them. Whatever ships them (the core, the default theme, an
 *    installed theme or extension) publishes one folder of assets at one
 *    name inside public/, and only that folder becomes reachable.
 *  - A link where the server allows it, a copy where it does not. A
 *    relative symlink (public/assets -> ../system/assets) keeps working
 *    when the installation folder is moved, and shows a replaced source at
 *    once. Where symlink() is disabled or not permitted, the folder is
 *    copied, with a marker file that identifies the copy as ours.
 *  - Never remove what we did not make. A file or folder at the target that
 *    is neither a link nor a marked copy is left alone and reported (KEPT),
 *    even when forced. Removing a link removes the link only, never the
 *    files it points to.
 *  - Targets are names inside public/, never paths. A name is one or more
 *    lowercase segments ("assets", "themes/default"), so a caller cannot
 *    publish outside public/ or over the front controller.
 *
 * Symlinks are handled natively: the FileSystem API has no link operations,
 * and its delete() and rmdir() follow a link to a directory, so they would
 * empty the source instead of removing the link. PHP's stat and realpath
 * caches are cleared after every change, so later checks in the same
 * request see the current state.
 */
final class PublicAssets {

	/**
	 * publish() and refresh() results.
	 */
	public const LINKED    = 'linked';    // A symlink was created.
	public const COPIED    = 'copied';    // Symlinks are unavailable; the folder was copied.
	public const UNCHANGED = 'unchanged'; // The correct symlink already existed.
	public const KEPT      = 'kept';      // Something we did not make is at the target; left alone.

	/**
	 * unpublish() results (also KEPT).
	 */
	public const REMOVED = 'removed'; // The link or the copy was removed.
	public const ABSENT  = 'absent';  // Nothing was published at the target.

	/**
	 * Marker file identifying a copy made by publish().
	 */
	public const COPY_MARKER = '.smliser-assets-copy';

	/**
	 * A target name: lowercase segments separated by slashes.
	 */
	private const NAME_PATTERN = '#^[a-z0-9][a-z0-9_-]*(?:/[a-z0-9][a-z0-9_-]*)*$#';

	/**
	 * Absolute path of the public directory, without a trailing slash.
	 *
	 * @var string
	 */
	private string $public_dir;

	/**
	 * Constructor.
	 *
	 * @param FileSystem $fs         Filesystem API.
	 * @param string     $public_dir Absolute path of the public directory.
	 */
	public function __construct( private FileSystem $fs, string $public_dir ) {
		$this->public_dir = rtrim( $public_dir, '/\\' );
	}

	/**
	 * The public assets of this installation (SMLISER_ROOT/public).
	 *
	 * @param FileSystem $fs
	 * @return self
	 */
	public static function from_runtime( FileSystem $fs ) : self {
		return new self( $fs, rtrim( \SMLISER_ROOT, '/\\' ) . '/public' );
	}

	/*
	|------------
	| PUBLISHING
	|------------
	*/

	/**
	 * Publish a folder at a name inside public/.
	 *
	 * Safe to run repeatedly:
	 *  - a correct link is left alone (UNCHANGED), unless $force is set;
	 *  - a link to anything else, or a broken one, is replaced;
	 *  - a marked copy is rebuilt;
	 *  - anything else at the target is left alone (KEPT), even with $force.
	 *
	 * $force rebuilds whatever this class manages: a correct link is made
	 * again, and the choice between link and copy is made again from what
	 * the server allows now.
	 *
	 * @param string $source Absolute path of the folder to publish.
	 * @param string $name   Target name inside public/, e.g. "themes/default".
	 * @param bool   $force  Rebuild even when the correct link already exists.
	 * @return string LINKED, COPIED, UNCHANGED or KEPT.
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 * @throws \RuntimeException         When the source is missing or the folder cannot be published.
	 */
	public function publish( string $source, string $name, bool $force = false ) : string {
		$target = $this->path( $name );
		$source = rtrim( $source, '/\\' );

		if ( ! $this->fs->is_dir( $source ) ) {
			throw new \RuntimeException( "The assets folder \"{$source}\" does not exist." );
		}

		$parent = dirname( $target );

		if ( ! $this->fs->mkdir( $parent ) ) {
			throw new \RuntimeException( "Could not create \"{$parent}\". Make sure the public folder is writable by the web server." );
		}

		$this->flush_stat_cache();

		if ( is_link( $target ) ) {
			if ( ! $force && $this->points_to( $target, $source ) ) {
				return self::UNCHANGED;
			}

			$this->remove_link( $target );
		} elseif ( $this->fs->exists( $target ) ) {
			if ( ! $this->is_marked_copy( $target ) ) {
				return self::KEPT;
			}

			$this->remove_copy( $target );
		}

		if ( function_exists( 'symlink' ) && @symlink( $this->relative_path( $parent, $source ), $target ) ) {
			$this->flush_stat_cache();

			if ( $this->fs->is_dir( $target ) ) {
				return self::LINKED;
			}

			// A link that was created but does not resolve is useless; remove it before copying.
			@unlink( $target );
			$this->flush_stat_cache();
		}

		if ( ! $this->fs->copy( $source, $target, true ) ) {
			throw new \RuntimeException( "Could not copy \"{$source}\" to \"{$target}\". Make sure the public folder is writable by the web server." );
		}

		if ( ! $this->fs->put_contents( $target . '/' . self::COPY_MARKER, "Copied from {$source} because this server does not allow symlinks. Rebuilt when republished; do not edit.\n" ) ) {
			throw new \RuntimeException( "Could not write \"{$target}/" . self::COPY_MARKER . '".' );
		}

		$this->flush_stat_cache();

		return self::COPIED;
	}

	/**
	 * Republish after the source folder was replaced.
	 *
	 * A link already shows the new files and is left alone; a copy is
	 * rebuilt; a missing target is published.
	 *
	 * @param string $source Absolute path of the folder to publish.
	 * @param string $name   Target name inside public/.
	 * @return string LINKED, COPIED, UNCHANGED or KEPT.
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 * @throws \RuntimeException         When the source is missing or the folder cannot be published.
	 */
	public function refresh( string $source, string $name ) : string {
		return $this->publish( $source, $name, $this->is_copy( $name ) );
	}

	/**
	 * Remove what publish() made at a name.
	 *
	 * A link is removed, never the files it points to; a marked copy is
	 * deleted; anything else is left alone (KEPT).
	 *
	 * @param string $name Target name inside public/.
	 * @return string REMOVED, ABSENT or KEPT.
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 * @throws \RuntimeException         When the link or the copy cannot be removed.
	 */
	public function unpublish( string $name ) : string {
		$target = $this->path( $name );

		$this->flush_stat_cache();

		if ( is_link( $target ) ) {
			$this->remove_link( $target );
			return self::REMOVED;
		}

		if ( ! $this->fs->exists( $target ) ) {
			return self::ABSENT;
		}

		if ( ! $this->is_marked_copy( $target ) ) {
			return self::KEPT;
		}

		$this->remove_copy( $target );

		return self::REMOVED;
	}

	/*
	|-------
	| STATE
	|-------
	*/

	/**
	 * Whether a folder is reachable at a name, by link, copy or otherwise.
	 *
	 * @param string $name Target name inside public/.
	 * @return bool
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 */
	public function is_published( string $name ) : bool {
		$target = $this->path( $name );

		$this->flush_stat_cache();

		return $this->fs->is_dir( $target );
	}

	/**
	 * Whether a name holds a symlink (resolving or not).
	 *
	 * @param string $name Target name inside public/.
	 * @return bool
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 */
	public function is_link( string $name ) : bool {
		$target = $this->path( $name );

		$this->flush_stat_cache();

		return is_link( $target );
	}

	/**
	 * Whether a name holds a copy made by publish().
	 *
	 * @param string $name Target name inside public/.
	 * @return bool
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 */
	public function is_copy( string $name ) : bool {
		$target = $this->path( $name );

		$this->flush_stat_cache();

		return ! is_link( $target ) && $this->is_marked_copy( $target );
	}

	/**
	 * Absolute path of a name inside public/.
	 *
	 * @param string $name Target name, e.g. "assets" or "themes/default".
	 * @return string
	 * @throws \InvalidArgumentException When the name is not a valid target name.
	 */
	public function path( string $name ) : string {
		if ( ! self::is_valid_name( $name ) ) {
			throw new \InvalidArgumentException( "\"{$name}\" is not a valid public assets name: use lowercase letters, digits, \"-\" and \"_\", in segments separated by \"/\"." );
		}

		return $this->public_dir . '/' . $name;
	}

	/**
	 * Absolute path of the public directory.
	 *
	 * @return string
	 */
	public function public_dir() : string {
		return $this->public_dir;
	}

	/**
	 * Whether a string is a valid target name.
	 *
	 * @param string $name
	 * @return bool
	 */
	public static function is_valid_name( string $name ) : bool {
		return 1 === preg_match( self::NAME_PATTERN, $name );
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Whether a real folder carries the copy marker.
	 *
	 * @param string $target
	 * @return bool
	 */
	private function is_marked_copy( string $target ) : bool {
		return $this->fs->is_dir( $target ) && $this->fs->is_file( $target . '/' . self::COPY_MARKER );
	}

	/**
	 * Remove a symlink, never what it points to.
	 *
	 * rmdir() is the fallback for directory links on Windows.
	 *
	 * @param string $target
	 * @return void
	 * @throws \RuntimeException
	 */
	private function remove_link( string $target ) : void {
		if ( ! @unlink( $target ) && ! @rmdir( $target ) ) {
			throw new \RuntimeException( "Could not remove the link \"{$target}\"." );
		}

		$this->flush_stat_cache();
	}

	/**
	 * Delete a marked copy.
	 *
	 * @param string $target
	 * @return void
	 * @throws \RuntimeException
	 */
	private function remove_copy( string $target ) : void {
		if ( ! $this->fs->rmdir( $target, true ) ) {
			throw new \RuntimeException( "Could not remove the previous copy of the assets at \"{$target}\"." );
		}

		$this->flush_stat_cache();
	}

	/**
	 * Clear PHP's stat and realpath caches.
	 *
	 * is_dir(), is_link(), file_exists() and realpath() results are cached
	 * per request; after creating or removing links and folders they can
	 * describe a state that no longer exists.
	 *
	 * @return void
	 */
	private function flush_stat_cache() : void {
		clearstatcache( true );
	}

	/**
	 * Whether a symlink resolves to the given directory.
	 *
	 * @param string $link   Symlink path.
	 * @param string $target Expected target directory.
	 * @return bool
	 */
	private function points_to( string $link, string $target ) : bool {
		$resolved = realpath( $link );

		return false !== $resolved && realpath( $target ) === $resolved;
	}

	/**
	 * Express a path relative to a directory, for portable symlinks.
	 *
	 * Falls back to the absolute path when no relative path exists (e.g.
	 * different drives on Windows).
	 *
	 * @param string $from_dir Directory the link lives in.
	 * @param string $to       Path the link points to.
	 * @return string
	 */
	private function relative_path( string $from_dir, string $to ) : string {
		$from = realpath( $from_dir );
		$to   = realpath( $to ) ?: $to;

		if ( false === $from ) {
			return $to;
		}

		$from_parts = explode( '/', trim( str_replace( '\\', '/', $from ), '/' ) );
		$to_parts   = explode( '/', trim( str_replace( '\\', '/', $to ), '/' ) );

		// Different roots (e.g. C: and D:) cannot be related.
		if ( ( $from_parts[0] ?? '' ) !== ( $to_parts[0] ?? '' ) ) {
			return $to;
		}

		while ( [] !== $from_parts && [] !== $to_parts && $from_parts[0] === $to_parts[0] ) {
			array_shift( $from_parts );
			array_shift( $to_parts );
		}

		$relative = str_repeat( '../', count( $from_parts ) ) . implode( '/', $to_parts );

		return '' === $relative ? '.' : rtrim( $relative, '/' );
	}
}
