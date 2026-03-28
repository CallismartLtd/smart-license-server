<?php
/**
 * FileSystemPermission class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\FileSystem
 * @since 0.2.0
 */
namespace SmartLicenseServer\FileSystem;
/**
 * Filesystem Permission Manager
 *
 * Provides centralized permission handling for files and directories
 * using visibility abstraction (public/private).
 */
class FileSystemPermission {

    const VISIBILITY_PUBLIC  = 'public';
    const VISIBILITY_PRIVATE = 'private';

    const TYPE_FILE = 'file';
    const TYPE_DIR  = 'dir';

    /**
     * Permission map.
     *
     * @var array<string, array<string, int>> $permissions
     */
    protected static $permissions = [
        self::TYPE_FILE => [
            self::VISIBILITY_PUBLIC  => 0644,
            self::VISIBILITY_PRIVATE => 0600,
        ],
        self::TYPE_DIR => [
            self::VISIBILITY_PUBLIC  => 0755,
            self::VISIBILITY_PRIVATE => 0700,
        ],
    ];

    /**
     * Get permission mode.
     *
     * @param string $type File or dir.
     * @param string $visibility Public or private.
     * @return int
     */
    public static function get_mode( $type, $visibility ) : int {
        if ( isset( self::$permissions[ $type ][ $visibility ] ) ) {
            return self::$permissions[ $type ][ $visibility ];
        }

        // Safe fallback.
        return ( self::TYPE_DIR === $type ) ? 0755 : 0644;
    }

    /**
     * Get file permission.
     *
     * @param string $visibility Visibility.
     * @return int
     */
    public static function file( $visibility = self::VISIBILITY_PUBLIC ) : int{
        return self::get_mode( self::TYPE_FILE, $visibility );
    }

    /**
     * Get directory permission.
     *
     * @param string $visibility Visibility.
     * @return int
     */
    public static function dir( $visibility = self::VISIBILITY_PUBLIC ) : int {
        return self::get_mode( self::TYPE_DIR, $visibility );
    }
}