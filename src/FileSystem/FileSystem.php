<?php
/**
 * FileSystem
 *
 * Provides a unified API for filesystem operations across different environments
 * (WordPress, Flysystem, native PHP) by proxying every call to the adapter it
 * was built with.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

use RuntimeException;
use SmartLicenseServer\Exceptions\ProxyMethodException;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;

/**
 * FileSystem singleton.
 *
 * Holds one FileSystemAdapterInterface implementation, supplied on the first
 * call to instance() (the DI container does this at boot). The methods below
 * are exactly the adapter contract; each is forwarded to the adapter as is.
 *
 * @method bool is_dir( string $path ) Determine if a given path is a directory.
 * @method bool is_file( string $path ) Determine if a given path is a file.
 * @method bool exists( string $path ) Check if a file or directory exists.
 * @method bool is_readable( string $path ) Check if a file is readable.
 * @method bool is_writable( string $path ) Check if a file is writable.
 * @method bool is_stream( mixed $thing ) Determine if the given input is a stream wrapper.
 * @method string|false get_contents( string $file ) Retrieve the contents of a file.
 * @method bool put_contents( string $path, string $contents, int|false|null $mode = null ) Write contents to a file.
 * @method resource|false read_stream( string $path ) Open a file for reading as a stream.
 * @method bool write_stream( string $path, mixed $stream, int|false|null $mode = null ) Write a stream's contents to a file.
 * @method bool delete( string $file, bool $recursive = false, string|false $type = false ) Delete a file or directory.
 * @method bool mkdir( string $path, int|false $chmod = false, bool $recursive = true ) Create a directory.
 * @method bool rmdir( string $path, bool $recursive = false ) Remove a directory.
 * @method bool copy( string $source, string $dest, bool $overwrite = false, int|false $mode = false ) Copy a file or directory.
 * @method bool move( string $source, string $dest, bool $overwrite = false ) Move or rename a file or directory.
 * @method bool rename( string $source, string $dest ) Rename a file or directory.
 * @method bool chmod( string $file, int|false $mode = false, bool $recursive = false ) Change file permissions.
 * @method bool chown( string $file, string|int $owner, bool $recursive = false ) Change file owner.
 * @method int|false filesize( string $path ) Get file size.
 * @method int|false filemtime( string $path ) Get file modification time.
 * @method array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}|false stat( string $path ) Get file stat information.
 * @method list<array{path: string, exists: bool, is_dir: bool, is_file: bool, size: int, mtime: int, perms: string}>|false list_dir( string $path ) List the entries of a directory.
 */
class FileSystem {
	/**
	 * Private constructor to enforce singleton.
	 *
	 * @param FileSystemAdapterInterface $adapter The adapter instance.
	 */
	private function __construct(
		protected FileSystemAdapterInterface $adapter
	) {}

	/**
	 * Singleton instance.
	 * 
	 * @param FileSystemAdapterInterface|null $adapter
	 * @return static
	 */
	public static function instance( ?FileSystemAdapterInterface $adapter = null ) : static {
		static $static;

		if ( ! isset( $static ) ) {
			if ( ! $adapter ) {
				throw new RuntimeException( 'Filesystem API must be called with an adapter early.' );
			}

			$static = new static( $adapter );
		}

		return $static;
	}

	/**
	 * Proxy method calls to the underlying adapter.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Method arguments.
	 * @return mixed
	 *
	 * @throws ProxyMethodException If the method does not exist on the adapter.
	 */
	public function __call( string $method, array $args ) {
		if ( method_exists( $this->adapter, $method ) ) {
			return call_user_func_array( [ $this->adapter, $method ], $args );
		}

		throw new ProxyMethodException( static::class, $method );
	}

	/**
	 * Get the underlying adapter instance
	 * 
	 * @return FileSystemAdapterInterface
	 */
	public function get_adaper() : FileSystemAdapterInterface {
		return $this->adapter;
	}
}