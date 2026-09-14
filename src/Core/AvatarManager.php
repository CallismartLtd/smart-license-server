<?php
/**
 * Avatar manager class file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\FileSystem\FileSystem;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\Security\Owner;

/**
 * Handles the avatar features.
 */
class AvatarManager {
    /**
     * Class constructor
     */
    public function __construct(
        protected FileSystem $file_system,
        protected URLManager $urlmanager
    ) {}

    /**
     * Upload an avatar.
     * 
     * @param UploadedFile $file
     * @param string $type
     * @return bool
     */
    public function upload( UploadedFile $file, string $type ) : bool {
        $filename   = $file->get_name(false);
        $dest       = $this->full_path( $type, $filename );
        $tmp        = $file->get_tmp_path();

        $moved      = $this->file_system->move( $tmp, $dest, true );

        if ( $moved ) {
            $this->file_system->chmod( $dest, false, true );
        }

        return $moved;

    }

    /**
     * Rename an avatar.
     * 
     * @param string $type
     * @param string $from  The current avatar file name.
     * @param string $to    The new avatar file name.
     * 
     * @return bool
     */
    public function rename( string $type, string $from, string $to ) : bool {
        $source = $this->full_path( $type, $from );
        $dest   = $this->full_path( $type, $to );

        if ( $this->file_system->rename( $source, $dest ) ) {
            $this->file_system->chmod( $dest );

            return true;
        }

        return false;

    }

    /**
     * Delete an avatar.
     * 
     * @param string $type
     * @param string $filename
     * @return bool
     */
    public function delete( string $type, $filename ) : bool {
        $path   = $this->full_path( $type, $filename );

        if ( ! $this->file_system->exists( $path ) || $this->file_system->is_dir( $path ) ) {
            return false;
        }

        return $this->file_system->delete( $path );
    }

    /**
     * The absolute path to the avatar directory.
     * 
     * The returned directory string contains a trailing slash.
     * 
     * @return string
     */
    public function directory() : string {
        return SMLISER_UPLOADS_DIR . 'avatars/';
    }

    /**
     * Construct full avatar type path.
     * 
     * @param string $type
     * @param string $filename
     * 
     * @return string
     */
    public function full_path( string $type, string $filename = '' ) : string {
        $type   = $this->normalize( $type );
        
        return FileSystemHelper::join_path( $this->directory(), $type, $filename );
    }

    /**
     * Normalizes an avatar type.
     * 
     * @param string $type
     * @return string
     */
    public function normalize( string $type ) : string {
        $type   = strtolower( str_replace( [' ', '_'], '-', $type ) );
        return match( $type ) {
            Owner::TYPE_INDIVIDUAL, 'user'  => 'users',
            Owner::TYPE_ORGANIZATION        => 'organizations',
            default                         => $type
        };
    }
}