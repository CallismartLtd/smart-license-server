<?php
/**
 * Filesystem functions file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\FileSystem\FileSystemPermission;
use SmartLicenseServer\Utils\Format;

/**
 * Get the filesystem abstraction class
 * 
 * @return FileSystem
 */
function smliser_filesystem() : FileSystem {
    return FileSystem::instance();
}

/**
 * Derive file or directory permission from the given path.
 * 
 * @param string $path File or directory path.
 * @return int Permission mode (e.g., 0644 for files, 0755
 */
function smliser_get_default_permissions( string $path ) : int {
    if ( is_dir( $path ) ) {
        return FileSystemPermission::get_mode( FileSystemPermission::TYPE_DIR, FileSystemPermission::VISIBILITY_PUBLIC );
    } else {
        return FileSystemPermission::get_mode( FileSystemPermission::TYPE_FILE, FileSystemPermission::VISIBILITY_PUBLIC );
    }
}

/**
 * Auto-derive permissions for a path based on the current directory permissions.
 * 
 * @param string $path File or directory path.
 * @return int Permission mode.
 */
function smliser_auto_derive_permissions( string $path, string $type ) : int {

    $parent_dir = dirname( $path );
    $stats      = @stat( $parent_dir );

    if ( ! $stats ) {
        return ( 'dir' === $type ) ? 0755 : 0644;
    }

    $parent_perm = $stats['mode'] & 0777;

    if ( 'dir' === $type ) {
        return $parent_perm | 0755;
    }

    return $parent_perm | 0644;
}

/**
 * Sanitize and normalize a file path to prevent directory traversal attacks.
 *
 * @param string $path The input path.
 * @return string|\SmartLicenseServer\Exceptions\Exception The sanitized and normalized path, or Exception on failure.
 */
function smliser_sanitize_path( $path ) {
    return FileSystemHelper::sanitize_path( $path );
}

/**
 * Get the size of a directory by recursively summing the sizes of its contents.
 * 
 * @param string $directory The directory path.
 * @param bool $human_readable Whether to return the size in a human-readable
 * format(optional: default false).
 * @return int|string The total size in bytes or human-readable format.
 */
function smliser_dirsize( string $directory, bool $human_readable = false ) : int|string {
    $size   = 0;
    $files  = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator( $directory, RecursiveDirectoryIterator::SKIP_DOTS )
    );

    /** @var SplFileInfo $file */
    foreach ( $files as $file ) {
        $size += (int) $file->getSize();
    }

    return $human_readable ? Format::bytes( $size ) : $size;
}

/**
 * Get the effective maximum upload file size.
 *
 * The returned value is the smallest positive limit imposed by PHP and the
 * optional application limit.
 *
 * @param int|null $application_limit Optional application limit in bytes.
 * @return int Maximum upload file size in bytes. Returns 0 if no limit exists.
 */
function smliser_max_upload_size( ?int $application_limit = null ): int {

	$limits = [
		Format::parse_bytes( ini_get( 'upload_max_filesize' ) ),
		Format::parse_bytes( ini_get( 'post_max_size' ) ),
	];

	if ( null !== $application_limit ) {
		$limits[] = $application_limit;
	}

	$limits = array_filter(
		$limits,
		static fn ( int $limit ) => $limit > 0
	);

	return empty( $limits ) ? 0 : min( $limits );
}

/**
 * Get windows resrved names.
 * 
 * @return string[] List of reserved names.
 */
function smliser_get_windows_reserved_names() : array {
    return [
        'con', 'prn', 'aux', 'nul','com1', 'com2', 'com3', 'com4', 'com5', 
        'com6', 'com7', 'com8', 'com9','lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5',
        'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];
}

/**
 * Intercept stray /favicon.ico requests early to prevent unnecessary app booting.
 *
 * @return void
 */
function intercept_stray_favicon_request(): void {
    if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
        return;
    }

    $requestPath = \strtok( $_SERVER['REQUEST_URI'], '?' );

    if ( '/favicon.ico' === $requestPath ) {
        $favicon = \SMLISER_RUNTIME_DIR . 'assets/images/smart-license-server.svg';

        if ( \file_exists( $favicon ) ) {
            // Clean any preceding output buffers to avoid file corruption
            while ( \ob_get_level() > 0 ) {
                \ob_end_clean();
            }

            \header( 'Content-Type: image/svg+xml' );
            \header( 'Content-Length: ' . \filesize( $favicon ) );
            \header( 'Cache-Control: public, max-age=604800, immutable' );
            \readfile( $favicon );
        } else {
            \http_response_code( 204 );
            \header( 'Cache-Control: public, max-age=604800' );
        }

        exit( 0 );
    }
}