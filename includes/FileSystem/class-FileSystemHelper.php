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

namespace SmartLicenseServer\FileSystem;

use Normalizer;
use SmartLicenseServer\Exceptions\Exception;

defined( 'SMLISER_ABSPATH' ) || exit; // phpcs:ignore

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

        self::$ext_mime_type_map = include \SMLISER_PATH . 'includes/FileSystem/bundles/ext-2-mime-type-map.php';
        self::$mimes_to_ext_map  = include \SMLISER_PATH . 'includes/FileSystem/bundles/mime-type-2-ext-map.php';
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
     * Validate an uploaded file (native PHP upload).
     *
     * Ensures:
     * - Proper upload array structure
     * - No PHP upload error
     * - File was uploaded via HTTP POST
     * - Temp file exists and is readable
     *
     * @param array  $file  One entry from $_FILES.
     * @param string $name  Logical name for error messages (e.g. "app.json").
     *
     * @return string       Temporary file path.
     * @throws \SmartLicenseServer\Exceptions\Exception
     */
    public static function validate_uploaded_file( array $file, string $name = 'file' ): string {

        if ( empty( $file ) ) {
            throw new Exception(
                'no_upload',
                sprintf( 'No %s was uploaded.', $name )
            );
        }

        if ( ! isset( $file['error'], $file['tmp_name'] ) ) {
            throw new Exception(
                'invalid_upload_array',
                sprintf( 'Malformed upload data for %s.', $name )
            );
        }

        if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
            throw new Exception(
                'upload_error',
                self::interpret_upload_error( (int) $file['error'], $name )
            );
        }

        if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
            throw new Exception(
                'invalid_upload_source',
                sprintf(
                    '%s was not uploaded via HTTP POST.',
                    $name
                )
            );
        }

        if ( ! is_readable( $file['tmp_name'] ) ) {
            throw new Exception(
                'unreadable_upload',
                sprintf(
                    'Uploaded %s file is not readable.',
                    $name
                )
            );
        }

        return $file['tmp_name'];
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

    /**
     * Sanitizes a file name string for safe cross-platform filesystem use.
     *
     * - Removes traversal sequences (../, ..\, ./, .\)
     * - Decodes URL-encoded characters
     * - Normalizes Unicode (if intl extension exists) + transliteration (iconv)
     * - Removes invalid OS characters
     * - Collapses separators (spaces, dots, underscores, hyphens)
     * - Preserves extension optionally
     * - Removes leading dots → prevents hidden/system files (.env, .htaccess)
     * - Handles Windows reserved device names
     * - Multibyte-safe
     *
     * @param string $filename           Raw file name.
     * @param bool   $preserve_extension Whether to preserve the last dot segment as extension.
     *
     * @return string Sanitized file name.
     */
    public static function sanitize_filename( string $filename, bool $preserve_extension = true ): string {
        $extension  = '';
        $max_length = 255;

        $filename = rawurldecode( $filename );
        $filename = trim( $filename );

        // Remove traversal sequences.
        $filename = preg_replace( '#(\.\./|\.\.\\\\|\.\/|\\\\\.)+#u', '', $filename );

        if ( class_exists( 'Normalizer' ) ) {
            $filename = Normalizer::normalize( $filename, Normalizer::FORM_C );
        }

        // Transliteration (fixes full-width, accents, symbols).
        if ( function_exists( 'iconv' ) ) {
            $converted = @iconv( 'UTF-8', 'ASCII//TRANSLIT', $filename );
            if ( $converted !== false ) {
                $filename = $converted;
            }
        }

        $base_filename = $filename;

        if ( $preserve_extension ) {
            $parts = explode( '.', $filename );

            if ( count( $parts ) > 1 && end( $parts ) !== '' ) {
                $extension      = array_pop( $parts );
                $base_filename  = implode( '.', $parts );

                // Strict extension sanitization: letters + numbers only.
                $extension = preg_replace( '/[^a-zA-Z0-9]/', '', $extension );
                $extension = $extension ? '.' . $extension : '';
            }
        }

        $filename = $base_filename;

        $filename = preg_replace( '/[\x00-\x1F\x7F]/u', '', $filename );

        // Replace invalid OS characters.
        $invalid = array( '\\', '/', ':', '*', '?', '"', '<', '>', '|' );
        $filename = str_replace( $invalid, '-', $filename );

        // Remove emojis and invalid unicode symbols.
        $filename = preg_replace( '/[^\p{L}\p{N}\.\-\_ ]/u', '', $filename );

        $filename = str_replace(
            array( '–', '—', '−' ),
            '-',
            $filename
        );

        // Convert spaces, dots, and underscores to hyphens.
        $filename = preg_replace( '/[\s\.\_]+/u', '-', $filename );

        // Collapse repeated hyphens.
        $filename = preg_replace( '/-+/u', '-', $filename );

        $filename = trim( $filename, ".-_" );

        // Windows reserved device names.
        $reserved = array(
            'con', 'prn', 'aux', 'nul',
            'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
            'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
        );

        if ( in_array( strtolower( $filename ), $reserved, true ) ) {
            $filename .= '_';
        }

        $name_len = mb_strlen( $filename, 'UTF-8' );
        $ext_len  = mb_strlen( $extension, 'UTF-8' );

        if ( ( $name_len + $ext_len ) > $max_length ) {
            $filename = mb_substr( $filename, 0, $max_length - $ext_len, 'UTF-8' );
        }

        if ( empty( $filename ) ) {
            return 'untitled' . $extension;
        }

        return $filename . $extension;
    }

    /**
     * Safely join multiple path segments into a single path using aggressive cleaning.
     *
     * @param string ...$segments Path segments to join.
     * @return string Normalized path.
     */
    public static function join_path( string ...$segments ) {
        if ( empty( $segments ) ) {
            return '';
        }

        $cleaned_segments   = array();
        $segments           = array_filter( $segments );

        $first_segment = \str_replace( '\\', '/', $segments[0] ?? '' );
        $last_segment  = str_replace( '\\', '/', $segments[count( $segments ) - 1] ?? '' );

        $has_leading_slash  = str_starts_with( $first_segment, '/' ) || ( preg_match( '/^[A-Za-z]:\\\\/', $first_segment ) === 1 );
        $has_trailing_slash = str_ends_with( $last_segment, '/' ) || str_ends_with( $last_segment, '\\' );

        foreach( $segments as $segment ) {
            $part = trim( $segment, "/\\ " );
            if ( $part === '' || $part === '.' || $part === '\\' || $part === '/' ) {
                continue;
            }
            $cleaned_segments[] = $part;
        }
        $joined = implode( '/', $cleaned_segments );

        if ( $has_leading_slash ) {
            $joined = \sprintf( '/%s', \ltrim( $joined, '/' ) );
        }

        if ( $has_trailing_slash ) {
            $joined = \sprintf( '%s/', rtrim( $joined, '/' ) );
        }

        return $joined;
    }

    /**
     * Sanitize and normalize a filesystem path (no dependencies).
     *
     * - Prevents directory traversal ("..")
     * - Blocks encoded traversal (%2e%2e etc.)
     * - Blocks unicode traversal
     * - Normalizes slashes
     * - Cross-platform (Linux + Windows)
     * - Allows only safe filename characters
     * - Preserves absolute paths
     *
     * @param string $path
     * @return string|\SmartLicenseServer\Exception
     */
    public static function sanitize_path( $path ) {

        if ( ! is_string( $path ) || trim( $path ) === '' ) {
            return new Exception( 'invalid_path', 'Path must be a non-empty string.' );
        }

        // Normalize slashes early.
        $path = str_replace( array( '\\', '/' ), '/', $path );

        // Determine absolute paths: "/var/", "C:/var/"
        $is_windows_abs = (bool) preg_match( '#^[A-Za-z]:/#', $path );
        $is_linux_abs   = str_starts_with( $path, '/' );

        // Extract drive letter if present.
        $drive = '';
        if ( $is_windows_abs ) {
            $drive = substr( $path, 0, 2 ); // e.g., "C:"
            $path  = substr( $path, 2 );    // remove drive prefix
        }

        // Split into segments.
        $parts = explode( '/', $path );
        $safe  = array();

        foreach ( $parts as $part ) {
            $part = trim( $part );

            // Skip empty / dot segments.
            if ( $part === '' || $part === '.' ) {
                continue;
            }

            // Block directory traversal.
            if ( $part === '..' ) {
                return new Exception( 'invalid_path', 'Parent directory references not allowed.' );
            }

            // Disallow encoded characters.
            if ( preg_match( '/(%|&#)/i', $part ) ) {
                return new Exception( 'invalid_path', 'Encoded characters are not allowed.' );
            }

            // Allow safe characters only.
            // You can expand allowed characters if needed.
            if ( ! preg_match( '/^[A-Za-z0-9._-]+$/', $part ) ) {
                return new Exception( 'invalid_chars', "Illegal characters in path segment: {$part}" );
            }

            $safe[] = $part;
        }

        // Rebuild the path.
        $normalized = implode( '/', $safe );

        // Restore absolute prefix.
        if ( $is_windows_abs ) {
            $normalized = $drive . '/' . $normalized;
        } elseif ( $is_linux_abs ) {
            $normalized = '/' . $normalized;
        }

        // Null byte check.
        if ( strpos( $normalized, "\0" ) !== false ) {
            return new Exception( 'invalid_path', 'Null bytes not allowed.' );
        }

        return $normalized;
    }

    /**
     * Formats a filesize into a human-readable string.
     *
     * @param int|float $bytes     The size in bytes.
     * @param int       $decimals  Number of decimals to include.
     *
     * @return string Formatted size string.
     */
    public static function format_file_size( $bytes, $decimals = 2 ) {
       if ( $bytes <= 0 ) {
            return sprintf( '%.' . (int) $decimals . 'f %s', 0, 'B' );
        }

        $units = array( 'B', 'KB', 'MB', 'GB', 'TB', 'PB' );
        $factor = 0;

        if ( $bytes > 0 ) {
            $factor = (int) floor( log( $bytes, 1024 ) );
            $factor = min( $factor, count( $units ) - 1 ); // Safety bound.
        }

        $size = $bytes / pow( 1024, $factor );

        return sprintf( '%.' . (int) $decimals . 'f %s', $size, $units[ $factor ] );
    }

    /**
     * Interpret PHP file upload error codes.
     *
     * @param int    $error Upload error code (UPLOAD_ERR_*).
     * @param string $name  Logical file name for messaging.
     *
     * @return string
     */
    public static function interpret_upload_error( int $error, string $name = 'file' ): string {

        switch ( $error ) {

            case UPLOAD_ERR_OK:
                return sprintf( '%s uploaded successfully.', $name );

            case UPLOAD_ERR_INI_SIZE:
                return sprintf(
                    '%s exceeds the upload_max_filesize directive.',
                    $name
                );

            case UPLOAD_ERR_FORM_SIZE:
                return sprintf(
                    '%s exceeds the MAX_FILE_SIZE directive specified in the form.',
                    $name
                );

            case UPLOAD_ERR_PARTIAL:
                return sprintf(
                    '%s was only partially uploaded.',
                    $name
                );

            case UPLOAD_ERR_NO_FILE:
                return sprintf(
                    'No %s was uploaded.',
                    $name
                );

            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder on the server.';

            case UPLOAD_ERR_CANT_WRITE:
                return sprintf(
                    'Failed to write %s to disk.',
                    $name
                );

            case UPLOAD_ERR_EXTENSION:
                return sprintf(
                    '%s upload was stopped by a PHP extension.',
                    $name
                );

            default:
                return sprintf(
                    'Unknown upload error occurred for %s.',
                    $name
                );
        }
    }

}
