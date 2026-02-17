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

use SmartLicenseServer\FileSystem\Adapters\FileSystemAdapterInterface;
use SmartLicenseServer\FileSystem\Adapters\FlysystemAdapter;
use SmartLicenseServer\FileSystem\Adapters\LaravelFileSystemAdapter;
use SmartLicenseServer\FileSystem\Adapters\WPFileSystemAdapter;

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
 * @method object get_fs() Get the underlying filesytem object
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

        /**
         * ------------------------------------------------------------
         * WordPress Environment
         * ------------------------------------------------------------
         */
        if ( defined( 'ABSPATH' ) && function_exists( 'apply_filters' ) ) {
            return new WPFileSystemAdapter();
        }

        /**
         * ------------------------------------------------------------
         * Laravel Environment
         * ------------------------------------------------------------
         */
        if (
            class_exists( \Illuminate\Foundation\Application::class ) &&
            function_exists( 'app' ) &&
            app()->bound( 'filesystem' )
        ) {
            return new LaravelFileSystemAdapter( app( 'filesystem' ) );
        }

        /**
         * ------------------------------------------------------------
         * Flysystem Fallback (Explicit Configuration Required)
         * ------------------------------------------------------------
         */
        if ( static::has_flysystem_config() ) {
            return new FlysystemAdapter(
                static::build_flysystem_instance()
            );
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
     * Get the underlying adapter instance.
     *
     * @return FileSystemAdapterInterface
     */
    public function get_adapter(): FileSystemAdapterInterface {
        return $this->adapter;
    }

    /**
     * Determine whether Flysystem has been configured for use.
     *
     * @return bool
     */
    protected static function has_flysystem_config(): bool {
        return class_exists( \League\Flysystem\Filesystem::class );
    }

    /**
     * Build and return a Flysystem instance.
     *
     * Flysystem is treated as an explicit fallback filesystem and must be
     * configured ahead of time. No automatic driver guessing is performed.
     *
     * Supported drivers:
     * - local
     * - sftp
     * - s3
     *
     * @return \League\Flysystem\Filesystem
     *
     * @throws \RuntimeException If Flysystem is misconfigured or unsupported.
     */
    protected static function build_flysystem_instance(): \League\Flysystem\Filesystem {

        if ( ! class_exists( \League\Flysystem\Filesystem::class ) ) {
            throw new \RuntimeException( 'Flysystem is not installed.' );
        }

        $config = static::get_flysystem_config();

        if ( empty( $config['driver'] ) ) {
            throw new \RuntimeException( 'Flysystem driver not specified.' );
        }

        switch ( $config['driver'] ) {

            /**
             * ------------------------------------------------------------
             * Local Driver
             * ------------------------------------------------------------
             */
            case 'local':

                if ( empty( $config['root'] ) ) {
                    throw new \RuntimeException( 'Local Flysystem driver requires a root path.' );
                }

                if ( ! is_dir( $config['root'] ) || ! is_writable( $config['root'] ) ) {
                    throw new \RuntimeException(
                        sprintf( 'Local filesystem path "%s" is not writable.', $config['root'] )
                    );
                }

                $adapter = new \League\Flysystem\Local\LocalFilesystemAdapter(
                    $config['root']
                );
                break;

            /**
             * ------------------------------------------------------------
             * SFTP Driver
             * ------------------------------------------------------------
             */
            case 'sftp':

                foreach ( [ 'host', 'username', 'root' ] as $key ) {
                    if ( empty( $config[ $key ] ) ) {
                        throw new \RuntimeException(
                            sprintf( 'SFTP Flysystem driver requires "%s".', $key )
                        );
                    }
                }

                $adapter = new \League\Flysystem\Sftp\SftpAdapter(
                    \League\Flysystem\Sftp\SftpConnectionProvider::fromArray( $config )
                );
                break;

            /**
             * ------------------------------------------------------------
             * S3 Driver
             * ------------------------------------------------------------
             */
            case 's3':

                foreach ( [ 'key', 'secret', 'region', 'bucket' ] as $key ) {
                    if ( empty( $config[ $key ] ) ) {
                        throw new \RuntimeException(
                            sprintf( 'S3 Flysystem driver requires "%s".', $key )
                        );
                    }
                }

                $client = new \Aws\S3\S3Client( [
                    'credentials' => [
                        'key'    => $config['key'],
                        'secret' => $config['secret'],
                    ],
                    'region'  => $config['region'],
                    'version' => 'latest',
                ] );

                $adapter = new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                    $client,
                    $config['bucket'],
                    $config['prefix'] ?? ''
                );
                break;

            default:
                throw new \RuntimeException(
                    sprintf( 'Unsupported Flysystem driver "%s".', $config['driver'] )
                );
        }

        return new \League\Flysystem\Filesystem( $adapter );
    }

    /**
     * Retrieve Flysystem configuration.
     *
     * Returns a normalized configuration array for Flysystem.
     * The configuration is determined based on defined constants, in priority order:
     *
     * 1. SMLISER_FLYSYSTEM_CONFIG_CUSTOM  — User-defined custom configuration.
     * 2. SMLISER_FLYSYSTEM_CONFIG_S3      — Predefined S3 config.
     * 3. SMLISER_FLYSYSTEM_CONFIG_SFTP    — Predefined SFTP config.
     * 4. SMLISER_FLYSYSTEM_CONFIG_LOCAL   — Predefined Local config.
     *
     * If none of these constants exist, the method returns a **default local filesystem config**:
     * ```
     * [
     *     'driver' => 'local',
     *     'root'   => SMLISER_ABSPATH,
     * ]
     * ```
     *
     * **Notes on constants:**
     *
     * - SMLISER_FLYSYSTEM_CONFIG_CUSTOM
     *   Full normalized Flysystem config array as described in Flysystem docs.
     *
     * - SMLISER_FLYSYSTEM_CONFIG_S3
     *   Must contain at least:
     *     - driver => 's3'
     *     - key
     *     - secret
     *     - region
     *     - bucket
     *     - prefix (optional)
     *
     * - SMLISER_FLYSYSTEM_CONFIG_SFTP
     *   Must contain at least:
     *     - driver => 'sftp'
     *     - host
     *     - username
     *     - password (or privateKey)
     *     - root
     *     - port (optional, default 22)
     *
     * - SMLISER_FLYSYSTEM_CONFIG_LOCAL
     *   Must contain at least:
     *     - driver => 'local'
     *     - root
     *
     * @return array Normalized Flysystem configuration.
     */
    protected static function get_flysystem_config(): array {

        if ( defined( 'SMLISER_FLYSYSTEM_CONFIG_CUSTOM' ) ) {
            return \constant( 'SMLISER_FLYSYSTEM_CONFIG_CUSTOM' );
        }

        if ( defined( 'SMLISER_FLYSYSTEM_CONFIG_S3' ) ) {
            return \constant( 'SMLISER_FLYSYSTEM_CONFIG_S3' );
        }

        if ( defined( 'SMLISER_FLYSYSTEM_CONFIG_SFTP' ) ) {
            return \constant( 'SMLISER_FLYSYSTEM_CONFIG_SFTP' );
        }

        if ( defined( 'SMLISER_FLYSYSTEM_CONFIG_LOCAL' ) ) {
            return \constant( 'SMLISER_FLYSYSTEM_CONFIG_LOCAL' );
        }

        // Fallback to direct local filesystem
        return [
            'driver' => 'local',
            'root'   => \constant( 'SMLISER_ABSPATH' ),
        ];
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
