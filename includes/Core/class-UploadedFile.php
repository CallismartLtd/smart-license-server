<?php
/**
 * Uploaded File Handler
 * 
 * Wraps a single $_FILES entry and handles its validation and storage.
 * 
 * @package SmartLicenseServer\FileSystem
 * @author Callistus Nwachukwu
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\FileSystem\FileSystem;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents a single client-uploaded file.
 *
 * This object assumes the file is dangerous by default.
 * It does not auto-validate — it exposes state and allows controlled actions.
 */
final class UploadedFile {

    /**
     * Raw $_FILES entry.
     *
     * @var array<string,mixed>|null
     */
    private ?array $file;

    /**
     * Logical key name.
     */
    private string $key;

    /**
     * Filesystem abstraction.
     */
    private FileSystem $fs;

    /**
     * Lifecycle flags.
     */
    private bool $moved    = false;
    private bool $rejected = false;

    /**
     * @param array<string,mixed>|null $file
     * @param string                   $key
     */
    public function __construct( ?array $file, string $key = 'file' ) {
        $this->file = $file;
        $this->key  = $key;
        $this->fs   = FileSystem::instance();
    }

    /**
     * Create from $_FILES global.
     */
    public static function from_files( string $key ) : self {
        return new self( $_FILES[ $key ] ?? null, $key );
    }

    /**
     * Whether file key exists.
     */
    public function exists() : bool {
        return is_array( $this->file );
    }

    /**
     * Whether structure is valid.
     */
    public function has_valid_structure() : bool {

        if ( ! $this->exists() ) {
            return false;
        }

        foreach ( [ 'name', 'type', 'tmp_name', 'error', 'size' ] as $field ) {
            if ( ! array_key_exists( $field, $this->file ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Upload error code.
     */
    public function get_error_code() : ?int {
        return $this->exists()
            ? (int) ( $this->file['error'] ?? UPLOAD_ERR_NO_FILE )
            : null;
    }

    /**
     * Human readable upload error.
     */
    public function get_error_message() : string {

        if ( ! $this->exists() ) {
            return sprintf( 'No %s was uploaded.', $this->key );
        }

        return FileSystemHelper::interpret_upload_error(
            $this->get_error_code(),
            $this->key
        );
    }

    /**
     * Whether upload succeeded.
     */
    public function is_upload_successful() : bool {
        return $this->get_error_code() === UPLOAD_ERR_OK;
    }

    /**
     * Temporary file path.
     */
    public function get_tmp_path() : ?string {
        return $this->exists()
            ? (string) ( $this->file['tmp_name'] ?? '' )
            : null;
    }

    /**
     * Original client filename.
     */
    public function get_client_name() : ?string {
        return $this->exists()
            ? (string) ( $this->file['name'] ?? '' )
            : null;
    }

    /**
     * Sanitized safe filename.
     */
    public function get_sanitized_name() : ?string {

        $name = $this->get_client_name();

        return $name
            ? FileSystemHelper::sanitize_filename( $name )
            : null;
    }

    /**
     * File size in bytes.
     */
    public function get_size() : ?int {
        return $this->exists()
            ? (int) ( $this->file['size'] ?? 0 )
            : null;
    }

    /**
     * Whether file was uploaded via HTTP POST.
     */
    public function is_uploaded_via_http() : bool {

        $tmp = $this->get_tmp_path();

        if ( ! $tmp ) {
            return false;
        }

        return is_uploaded_file( $tmp );
    }

    /**
     * Server-detected MIME type.
     */
    public function get_detected_mime() : ?string {

        $tmp = $this->get_tmp_path();

        if ( ! $tmp || ! $this->fs->exists( $tmp ) ) {
            return null;
        }

        return FileSystemHelper::get_mime_type( $tmp );
    }

    /**
     * Canonical extension based on file content.
     */
    public function get_canonical_extension() : string {

        $tmp = $this->get_tmp_path();

        if ( ! $tmp ) {
            return '';
        }

        return FileSystemHelper::get_canonical_extension( $tmp );
    }

    /**
     * File checksum.
     */
    public function checksum( string $algo = 'sha256' ) : ?string {

        $tmp = $this->get_tmp_path();

        if ( ! $tmp ) {
            return null;
        }

        return FileSystemHelper::checksum( $tmp, $algo );
    }

    /**
     * Whether file is moveable.
     */
    public function is_moveable() : bool {

        return $this->exists()
            && $this->has_valid_structure()
            && $this->is_upload_successful()
            && $this->is_uploaded_via_http()
            && ! $this->moved
            && ! $this->rejected;
    }

    /**
     * Reject and delete temporary upload.
     */
    public function reject() : bool {

        $tmp = $this->get_tmp_path();

        if ( ! $tmp || ! $this->fs->exists( $tmp ) ) {
            return false;
        }

        $deleted = $this->fs->delete( $tmp );

        if ( $deleted ) {
            $this->rejected = true;
        }

        return $deleted;
    }

    /**
     * Move upload to destination.
     *
     * @throws Exception
     */
    public function move( string $directory, ?string $filename = null ) : string {

        if ( ! $this->is_moveable() ) {
            throw new Exception(
                'upload_not_moveable',
                $this->get_error_message()
            );
        }

        $safe_directory = FileSystemHelper::join_path( $directory );

        if ( is_smliser_error( $safe_directory ) ) {
            throw $safe_directory;
        }

        if ( ! $this->fs->is_dir( $safe_directory ) ) {
            $this->fs->mkdir( $safe_directory, FS_CHMOD_DIR );
        }

        $filename = $filename
            ? FileSystemHelper::sanitize_filename( $filename )
            : $this->get_sanitized_name();

        $destination = FileSystemHelper::join_path(
            $safe_directory,
            $filename
        );

        if ( is_smliser_error( $destination ) ) {
            throw $destination;
        }

        if ( ! $this->fs->move( $this->get_tmp_path(), $destination, true ) ) {
            throw new Exception(
                'upload_move_failed',
                sprintf(
                    'Failed to move uploaded %s.',
                    $this->key
                )
            );
        }

        $this->fs->chmod( $destination, FS_CHMOD_FILE );

        $this->moved = true;

        return $destination;
    }

    /**
     * Whether file has been moved.
     */
    public function is_moved() : bool {
        return $this->moved;
    }

    /**
     * Whether file was rejected.
     */
    public function is_rejected() : bool {
        return $this->rejected;
    }

    /**
     * Debug inspection snapshot.
     */
    public function inspect() : array {

        return [
            'exists'         => $this->exists(),
            'structure_ok'   => $this->has_valid_structure(),
            'error_code'     => $this->get_error_code(),
            'error_message'  => $this->get_error_message(),
            'is_successful'  => $this->is_upload_successful(),
            'is_http_upload' => $this->is_uploaded_via_http(),
            'size'           => $this->get_size(),
            'mime'           => $this->get_detected_mime(),
            'checksum'       => $this->checksum(),
            'moved'          => $this->moved,
            'rejected'       => $this->rejected,
        ];
    }
}


