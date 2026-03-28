<?php
/**
 * Filesystem functions file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

use SmartLicenseServer\FileSystem\FileSystemPermission;

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