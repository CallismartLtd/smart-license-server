<?php
/**
 * FileSystem aware trait.
 *
 * Provides easy access to the FileSystem singleton and proxies all methods
 * defined in FileSystemAdapterInterface.
 *
 * Use this trait in classes that need filesystem operations without extending FileSystem.
 *
 * @package SmartLicenseServer\FileSystem
 */

namespace SmartLicenseServer\FileSystem;

defined( 'SMLISER_ABSPATH' ) || exit;

trait FileSystemAwareTrait {

    /**
     * Get the FileSystem singleton instance.
     *
     * @return FileSystem
     */
    public function fs(): FileSystem {
        return FileSystem::instance();
    }

    // Core checks.
    public function is_dir( string $path ): bool {
        return $this->fs()->is_dir( $path );
    }

    public function is_file( string $path ): bool {
        return $this->fs()->is_file( $path );
    }

    public function exists( string $path ): bool {
        return $this->fs()->exists( $path );
    }

    public function is_readable( string $path ): bool {
        return $this->fs()->is_readable( $path );
    }

    public function is_writable( string $path ): bool {
        return $this->fs()->is_writable( $path );
    }

    public function is_stream( mixed $thing ): bool {
        return $this->fs()->is_stream( $thing );
    }

    // File operations.
    public function get_contents( string $file ): string|false {
        return $this->fs()->get_contents( $file );
    }

    public function put_contents( string $path, string $contents, int $mode = FS_CHMOD_FILE ): bool {
        return $this->fs()->put_contents( $path, $contents, $mode );
    }

    public function delete( string $file, bool $recursive = false, string|false $type = false ): bool {
        return $this->fs()->delete( $file, $recursive, $type );
    }

    public function mkdir( string $path, int|false $chmod = false, bool $recursive = true ): bool {
        return $this->fs()->mkdir( $path, $chmod, $recursive );
    }

    public function mkdir_recursive( string $path, int|false $chmod = false ): bool {
        return $this->fs()->mkdir_recursive( $path, $chmod );
    }

    public function rmdir( string $path, bool $recursive = false ): bool {
        return $this->fs()->rmdir( $path, $recursive );
    }

    public function copy( string $source, string $dest, bool $overwrite = false ): bool {
        return $this->fs()->copy( $source, $dest, $overwrite );
    }

    public function move( string $source, string $dest, bool $overwrite = false ): bool {
        return $this->fs()->move( $source, $dest, $overwrite );
    }

    public function rename( string $source, string $dest ): bool {
        return $this->fs()->rename( $source, $dest );
    }

    public function chmod( string $file, int|false $mode = false, bool $recursive = false ): bool {
        return $this->fs()->chmod( $file, $mode, $recursive );
    }

    public function chown( string $file, string|int $owner, bool $recursive = false ): bool {
        return $this->fs()->chown( $file, $owner, $recursive );
    }

    // Directory / listing.
    public function list( string|null $path = null ): array|false {
        return $this->fs()->list( $path );
    }

    // File information.
    public function filesize( string $path ): int|false {
        return $this->fs()->filesize( $path );
    }

    public function filemtime( string $path ): int|false {
        return $this->fs()->filemtime( $path );
    }

    public function stat( string $path ): array|false {
        return $this->fs()->stat( $path );
    }

    // Utilities.
    public function readfile( string $path, int $start = 0, int $length = 0, int $chunk_size = 1048576 ): bool {
        return $this->fs()->readfile( $path, $start, $length, $chunk_size );
    }
}
