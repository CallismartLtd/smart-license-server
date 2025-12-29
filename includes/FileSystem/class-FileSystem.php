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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * FileSystem singleton.
 *
 * Automatically detects the environment and uses the correct FileSystem adapter.
 *
 * @method bool is_dir(string $path)
 * @method bool is_file(string $path)
 * @method bool exists(string $path)
 * @method bool is_readable(string $path)
 * @method bool is_writable(string $path)
 * @method bool is_stream(mixed $thing)
 * @method string|false get_contents(string $file)
 * @method bool put_contents(string $path, string $contents, int $mode = FS_CHMOD_FILE)
 * @method bool delete(string $file, bool $recursive = false, string|false $type = false)
 * @method bool mkdir(string $path, int|false $chmod = false, bool $recursive = true)
 * @method bool rmdir(string $path, bool $recursive = false)
 * @method bool copy(string $source, string $dest, bool $overwrite = false)
 * @method bool move(string $source, string $dest, bool $overwrite = false)
 * @method bool rename(string $source, string $dest)
 * @method bool chmod(string $file, int|false $mode = false, bool $recursive = false)
 * @method bool chown(string $file, string|int $owner, bool $recursive = false)
 * @method array|false list(?string $path = null)
 * @method int|false filesize(string $path)
 * @method int|false filemtime(string $path)
 * @method array|false stat(string $path)
 * @method bool readfile(string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576)
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
    public static function instance(): FileSystem {
        if ( self::$instance === null ) {
            self::$instance = new self( static::detect_adapter() );
        }
        return self::$instance;
    }

    /**
     * Detect the environment and return the appropriate filesystem adapter.
     *
     * @return FileSystemAdapterInterface
     */
    protected static function detect_adapter(): FileSystemAdapterInterface {
        // WordPress context
        if ( defined( 'ABSPATH' ) && function_exists( 'apply_filters' ) ) {
            return new WPFileSystemAdapter();
        }

        // Flysystem context
        if ( class_exists( \League\Flysystem\Filesystem::class ) ) {
            return new FlysystemAdapter();
        }

        // Fallback: Native PHP adapter
        return new NativePHPFileSystemAdapter();
    }

    /**
     * Get the underlying adapter instance.
     *
     * @return FileSystemAdapterInterface
     */
    public function get_adapter(): FileSystemAdapterInterface {
        return $this->adapter;
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
