<?php
/**
 * FileSystem
 *
 * Provides a unified API for filesystem operations across different environments
 * (WordPress, Flysystem, native PHP). Automatically selects the correct adapter.
 *
 * Methods are proxied to the underlying adapter.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

use SmartLicenseServer\FileSystem\Adapters\DirectFileSystem;
use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * FileSystem singleton.
 *
 * Automatically detects the environment and uses the correct FileSystem adapter.
 *
 * @method bool is_dir(string $path) Tells whether the filename is a directory.
 * @method bool is_file(string $path) Tells whether the filename is a regular file.
 * @method bool exists(string $path) Tells whether the directory or file exists
 * @method bool is_readable(string $path) Tells whether a file exists and is readable.
 * @method bool is_writable(string $path) Tells whether the filename is writable.
 * @method bool is_stream(mixed $thing) Tells whether the given `thing` is a stream.
 * @method string|false get_contents(string $file) Reads the content of a file as string.
 * @method bool put_contents(string $path, string $contents, int $mode = FS_CHMOD_FILE) Write string content to the specified filename
 * @method bool delete(string $file, bool $recursive = false, string|false $type = false) Deletes a file or directory.
 * @method bool mkdir(string $path, int|false $chmod = false, bool $recursive = true) Makes directory.
 * @method bool rmdir(string $path, bool $recursive = false) Removes directory.
 * @method bool copy(string $source, string $dest, bool $overwrite = false) Copy file
 * @method bool move(string $source, string $dest, bool $overwrite = false) Moves file
 * @method bool rename(string $source, string $dest) Renames a file or directory.
 * @method bool chmod(string $file, int|false $mode = false, bool $recursive = false) Changes file mode.
 * @method bool chown(string $file, string|int $owner, bool $recursive = false) Changes file owner.
 * @method array|false list(?string $path ) List files and directories at a path.
 * @method int|false filesize(string $path) Gets file size
 * @method int|false filemtime(string $path) Gets file modification time
 * @method array|false stat(string $path) Gives information about a file
 * @method bool readfile(string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576) Efficiently outputs the contents of a file.
 */
class FileSystem {

    /**
     * Singleton instance.
     *
     * @var FileSystem|null
     */
    protected static ?FileSystem $instance = null;

    /**
     * The active filesystem adapter.
     *
     * @var FileSystemAdapterInterface
     */
    protected FileSystemAdapterInterface $adapter;

    /**
     * Private constructor to enforce singleton.
     *
     * @param FileSystemAdapterInterface $adapter The adapter instance.
     */
    private function __construct( FileSystemAdapterInterface $adapter ) {
        $this->adapter = $adapter;
    }

    /**
     * Get the singleton instance.
     *
     * @return FileSystem
     */
    public static function instance( ?FileSystemAdapterInterface $fs = null ): FileSystem {
        if ( is_null( self::$instance ) ) {
            $fs = $fs ?? static::detect_adapter();
            self::$instance = new self( $fs );
        }

        return self::$instance;
    }

    /**
     * Detect the environment and return the appropriate filesystem adapter.
     *
     * Adapter priority:
     * 1. WordPress (WP_Filesystem)
     * 2. Laravel (Illuminate Filesystem)
     * 3. Flysystem (explicitly configured fallback)
     *
     * @return FileSystemAdapterInterface
     *
     * @throws \RuntimeException If no suitable filesystem adapter can be resolved.
     */
    protected static function detect_adapter(): FileSystemAdapterInterface {
        if ( class_exists( DirectFileSystem::class ) ) {
            return new DirectFileSystem;
        }
        /**
         * ------------------------------------------------------------
         * No viable filesystem available
         * ------------------------------------------------------------
         */
        throw new \RuntimeException(
            'No supported filesystem adapter could be resolved. ' .
            'Ensure WordPress, Laravel, or a configured Flysystem instance is available.'
        );
    }

    /**
     * Proxy method calls to the underlying adapter.
     *
     * @param string $method Method name.
     * @param array  $args   Method arguments.
     * @return mixed
     *
     * @throws \BadMethodCallException If method does not exist on the adapter.
     */
    public function __call( string $method, array $args ) {
        if ( method_exists( $this->adapter, $method ) ) {
            return call_user_func_array( [ $this->adapter, $method ], $args );
        }

        throw new \BadMethodCallException(
            sprintf( 'Method %s::%s does not exist.', get_class( $this->adapter ), $method )
        );
    }
}
