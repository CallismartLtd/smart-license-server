<?php
/**
 * Filesystem functions file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Utils\Format;

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
 * Add execute permissions (+x) to a file while preserving existing permissions.
 *
 * @param string $file Path to the target file.
 * @param bool $owner_only Whether to apply the execute bit to the owner only (0100) or all scopes (0111). Default false.
 * @return bool True on success, false on failure or if file does not exist.
 */
function smliser_chmod_add_execute( string $file, bool $owner_only = false ) : bool {

    if ( ! file_exists( $file ) ) {
        return false;
    }

    $current_mode = fileperms( $file ) & 0777;
    $execute_flag = $owner_only ? 0100 : 0111;

    return chmod( $file, $current_mode | $execute_flag );
}

/**
 * Remove execute permissions (-x) from a file while preserving existing permissions.
 *
 * @param string $file Path to the target file.
 * @param bool $owner_only Whether to remove the execute bit from the owner only (0100) or all scopes (0111). Default false.
 * @return bool True on success, false on failure or if file does not exist.
 */
function smliser_chmod_remove_execute( string $file, bool $owner_only = false ) : bool {

    if ( ! file_exists( $file ) ) {
        return false;
    }

    $current_mode = fileperms( $file ) & 0777;
    $execute_flag = $owner_only ? 0100 : 0111;

    return chmod( $file, $current_mode & ~$execute_flag );
}

/**
 * Check whether a file exists and has execute permissions.
 *
 * @param string $file Path to the target file.
 * @return bool True if the file exists and is executable, false otherwise.
 */
function smliser_is_executable( string $file ) : bool {

    if ( ! file_exists( $file ) ) {
        return false;
    }

    return is_executable( $file );
}