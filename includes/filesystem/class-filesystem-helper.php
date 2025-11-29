<?php
/**
 * Filesystem Helper Class file
 *
 * Provides file analysis, validation, integrity checking, and metadata utilities.
 * Independent of environment (WordPress, Laravel, or bare PHP).
 *
 * @author Callistus Nwachukwu
 * @since 0.0.6
 */

namespace SmartLicenseServer;

defined( 'SMLISER_PATH' ) || exit; // phpcs:ignore

/**
 * Provides static helpers for file inspection and validation.
 */
class FileSystemHelper {
    /**
     * File extension to mime type map
     *
     * @var array $ext_mime_type_map
     */
    protected static $ext_mime_type_map;

    /**
     * File content mime type to exension map
     * @var array $mimes_to_ext_map
     */
    protected static $mimes_to_ext_map;

    /**
     * Initializes the map properties.
     */
    protected static function init_maps(): void {
        if ( isset( self::$ext_mime_type_map, self::$mimes_to_ext_map ) ) {
            return;
        }

        self::$ext_mime_type_map = include \SMLISER_PATH . 'includes/filesystem/bundles/ext-2-mime-type-map.php';
        self::$mimes_to_ext_map  = include \SMLISER_PATH . 'includes/filesystem/bundles/mime-type-2-ext-map.php';
    }

    /**
     * Get file extension (lowercased, no leading dot).
     *
     * @param string $path Absolute path.
     * @return string
     */
    public static function get_extension( string $path ): string {
        $ext = pathinfo( $path, PATHINFO_EXTENSION );
        return $ext ? strtolower( $ext ) : '';
    }

    /**
     * Get the canonical extension of the given file.
     * 
     * @param string $path Path to the file.
     * @return string
     */
    public static function get_canonical_extension( $path ) {
        if ( empty( $path ) || ! is_string( $path ) ) {
            return '';
        }
        
        self::init_maps();

        $mime   = self::get_mime_type( $path );

        if ( ! $mime ) {
            return '';
        }

        return self::$mimes_to_ext_map[$mime] ?? '';
    }

    /**
     * Get the MIME type of a file in a portable way.
     *
     * @param string $path Absolute path.
     * @return string|null
     */
    public static function get_mime_type( string $path ): ?string {
        $fs = FileSystem::instance();

        if ( ! $fs->exists( $path ) || ! $fs->is_readable( $path ) ) {
            return null;
        }

        // Try finfo first (most accurate)
        if ( function_exists( 'finfo_open' ) ) {
            $finfo = @finfo_open( FILEINFO_MIME_TYPE );
            if ( $finfo ) {
                $mime = finfo_file( $finfo, $path );
                // finfo_close( $finfo ); // Deprecated in PHP 8.0, no longer needed.
                if ( $mime ) {
                    return strtolower( $mime );
                }
            }
        }

        // Fallback to mime_content_type()
        if ( function_exists( 'mime_content_type' ) ) {
            return strtolower( mime_content_type( $path ) );
        }

        // Last fallback: use extension map
        $ext = self::get_extension( $path );
        return $ext ? self::guess_mime_from_extension( $ext ) : null;
    }

    /**
     * Guess a MIME type based on file extension.
     *
     * @param string $ext File extension (no dot).
     * @return string|null
     */
    public static function guess_mime_from_extension( string $ext ): ?string {
        self::init_maps();

        return self::$ext_mime_type_map[ strtolower( basename( $ext ) ) ] ?? 'application/octet-stream';
    }

    /**
     * Check if file extension is among allowed types.
     *
     * @param string $path
     * @param array  $allowed_extensions
     * @return bool
     */
    public static function has_allowed_extension( string $path, array $allowed_extensions ): bool {
        $ext = self::get_extension( $path );
        return $ext && in_array( $ext, array_map( 'strtolower', $allowed_extensions ), true );
    }

    /**
     * Check if a file is of given MIME type.
     *
     * @param string $path
     * @param string|array $mimes
     * @return bool
     */
    public static function has_mime( string $path, $mimes ): bool {
        $mime = self::get_mime_type( $path );
        if ( ! $mime ) {
            return false;
        }

        $mimes = (array) $mimes;
        foreach ( $mimes as $check ) {
            if ( str_starts_with( $check, '*' ) ) {
                $suffix = substr( $check, 1 );
                if ( str_ends_with( $mime, $suffix ) ) {
                    return true;
                }
            } elseif ( str_ends_with( $check, '/*' ) ) {
                $prefix = rtrim( $check, '/*' );
                if ( str_starts_with( $mime, $prefix . '/' ) ) {
                    return true;
                }
            } elseif ( strtolower( $mime ) === strtolower( $check ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Basic validity check for a file.
     *
     * Ensures existence, readability, non-zero size, and optionally allowed type.
     *
     * @param string $path Absolute path
     * @param array  $allowed_extensions Optional
     * @return bool
     */
    public static function is_valid_file( string $path, array $allowed_extensions = [] ): bool {
        $fs = FileSystem::instance();

        if ( ! $fs->exists( $path ) || ! $fs->is_readable( $path ) || $fs->is_dir( $path ) ) {
            return false;
        }

        $size = $fs->filesize( $path );
        if ( $size === false || $size <= 0 ) {
            return false;
        }

        if ( ! empty( $allowed_extensions ) ) {
            return self::has_allowed_extension( $path, $allowed_extensions );
        }

        return true;
    }

    /**
     * Check if a file looks like an image.
     *
     * @param string $path
     * @return bool
     */
    public static function is_image( string $path ): bool {
        $mime = self::get_mime_type( $path );
        return $mime && str_starts_with( $mime, 'image/' );
    }

    /**
     * Check if a file looks like a compressed archive (ZIP, RAR, etc.)
     *
     * @param string $path
     * @return bool
     */
    public static function is_archive( string $path ): bool {
        $mime = self::get_mime_type( $path );
        return $mime && preg_match( '/(zip|rar|7z|tar|gzip|x-gzip)/i', $mime );
    }

    /**
     * Generate a checksum for a file using the given algorithm.
     *
     * @param string $path Absolute path.
     * @param string $algo Hash algorithm (e.g., md5, sha1, sha256).
     * @return string|null
     */
    public static function checksum( string $path, string $algo = 'sha256' ): ?string {
        $fs = FileSystem::instance();

        if ( ! $fs->exists( $path ) || ! $fs->is_readable( $path ) ) {
            return null;
        }

        if ( ! in_array( strtolower( $algo ), hash_algos(), true ) ) {
            return null;
        }

        return hash_file( $algo, $path ) ?: null;
    }

    /**
     * Verify a file against a known checksum value.
     *
     * @param string $path Absolute path.
     * @param string $expected_hash Expected hash value.
     * @param string $algo Algorithm used (default sha256).
     * @return bool
     */
    public static function verify_checksum( string $path, string $expected_hash, string $algo = 'sha256' ): bool {
        $actual = self::checksum( $path, $algo );
        return $actual && hash_equals( $expected_hash, $actual );
    }

    /**
     * Compare two files by checksum.
     *
     * @param string $file1
     * @param string $file2
     * @param string $algo
     * @return bool
     */
    public static function compare_files( string $file1, string $file2, string $algo = 'sha256' ): bool {
        $hash1 = self::checksum( $file1, $algo );
        $hash2 = self::checksum( $file2, $algo );
        return $hash1 && $hash2 && hash_equals( $hash1, $hash2 );
    }

    /**
     * Get a safe summary of file properties.
     *
     * @param string $path
     * @return array|null
     */
    public static function inspect( string $path ): ?array {
        $fs = FileSystem::instance();

        if ( ! $fs->exists( $path ) ) {
            return null;
        }

        return [
            'path'          => $path,
            'exists'        => true,
            'is_dir'        => $fs->is_dir( $path ),
            'is_file'       => $fs->is_file( $path ),
            'size'          => $fs->filesize( $path ),
            'mtime'         => $fs->filemtime( $path ),
            'extension'     => self::get_extension( $path ),
            'mime_type'     => self::get_mime_type( $path ),
            'checksum'      => self::checksum( $path ),
            'is_image'      => self::is_image( $path ),
            'is_archive'    => self::is_archive( $path ),
            'is_valid_file' => self::is_valid_file( $path ),
        ];
    }
}
