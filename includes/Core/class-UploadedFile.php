<?php
/**
 * Uploaded File Handler
 * 
 * Wraps a single $_FILES entry and handles its validation and storage.
 * 
 * @package SmartLicenseServer\FileSystem
 * @author Callistus Nwachukwu
 */

namespace SmartLicenseServer\Core;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystemAwareTrait;
use SmartLicenseServer\FileSystem\FileSystemHelper;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents an uploaded file with validation and storage capabilities.
 */
class UploadedFile {
    use FileSystemAwareTrait;

    /**
     * Raw file data from $_FILES array.
     * 
     * @var array
     */
    protected array $file_data;

    /**
     * The field name/key from the upload form.
     * 
     * @var string
     */
    protected string $key;

    /**
     * Cached validation result to avoid redundant checks.
     * 
     * @var string|null
     */
    private ?string $validated_path = null;

    /**
     * Constructor.
     * 
     * @param array  $file_data Raw data from $_FILES array
     * @param string $key       Form field name (default: 'file')
     */
    public function __construct( array $file_data, string $key = 'file' ) {
        $this->file_data = $file_data;
        $this->key       = $key;
    }

    /**
     * Create instance from $_FILES global array.
     * 
     * @param string $key The field name in $_FILES
     * @return static|null Returns null if key doesn't exist
     */
    public static function from_files( string $key ): ?static {
        if ( ! isset( $_FILES[ $key ] ) ) {
            return null;
        }

        return new static( $_FILES[ $key ], $key );
    }

    /**
     * Get the original client-side filename.
     * 
     * @return string
     */
    public function get_client_name(): string {
        return $this->file_data['name'] ?? '';
    }

    /**
     * Get the filename without extension.
     * 
     * @return string
     */
    public function get_base_name(): string {
        return FileSystemHelper::remove_extension( $this->get_client_name() );
    }

    /**
     * Get the current temporary path on the server.
     * 
     * @return string
     */
    public function get_tmp_path(): string {
        return $this->file_data['tmp_name'] ?? '';
    }

    /**
     * Get the file size in bytes.
     * 
     * @return int
     */
    public function get_size(): int {
        return (int) ( $this->file_data['size'] ?? 0 );
    }

    /**
     * Get human-readable file size.
     * 
     * @param int $decimals Number of decimal places
     * @return string
     */
    public function get_size_formatted( int $decimals = 2 ): string {
        return FileSystemHelper::format_file_size( $this->get_size(), $decimals );
    }

    /**
     * Get the file extension (lowercase, no dot).
     * 
     * @return string
     */
    public function get_extension(): string {
        return FileSystemHelper::get_extension( $this->get_client_name() );
    }

    /**
     * Get the canonical extension based on actual file content.
     * More reliable than get_extension() as it checks MIME type.
     * 
     * @return string
     */
    public function get_canonical_extension(): string {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return $this->get_extension();
        }

        return FileSystemHelper::get_canonical_extension( $tmp_path );
    }

    /**
     * Get the MIME type of the uploaded file.
     * 
     * @return string|null
     */
    public function get_mime_type(): ?string {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) ) {
            // Fallback to client-provided type
            return $this->file_data['type'] ?? null;
        }

        // Use FileSystemHelper for accurate detection
        $mime = FileSystemHelper::get_mime_type( $tmp_path );
        
        // Fallback to client-provided if detection fails
        return $mime ?? ( $this->file_data['type'] ?? null );
    }

    /**
     * Get the upload error code.
     * 
     * @return int
     */
    public function get_error(): int {
        return (int) ( $this->file_data['error'] ?? UPLOAD_ERR_NO_FILE );
    }

    /**
     * Check if the file was uploaded successfully (no errors).
     * 
     * @return bool
     */
    public function is_valid(): bool {
        return $this->get_error() === UPLOAD_ERR_OK;
    }

    /**
     * Get human-readable error message.
     * 
     * @return string
     */
    public function get_error_message(): string {
        return FileSystemHelper::interpret_upload_error( $this->get_error(), $this->key );
    }

    /**
     * Check if file has a specific extension.
     * 
     * @param string|array $extensions Extension(s) to check (without dot)
     * @return bool
     */
    public function has_extension( $extensions ): bool {
        return FileSystemHelper::has_allowed_extension( $this->get_client_name(), (array) $extensions );
    }

    /**
     * Check if file matches a specific MIME type pattern.
     * 
     * @param string|array $mime_types MIME type(s) to check (supports wildcards like 'image/*')
     * @return bool
     */
    public function has_mime_type( $mime_types ): bool {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return false;
        }

        return FileSystemHelper::has_mime( $tmp_path, $mime_types );
    }

    /**
     * Check if file is an image.
     * 
     * @return bool
     */
    public function is_image(): bool {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return false;
        }

        return FileSystemHelper::is_image( $tmp_path );
    }

    /**
     * Check if file is an archive.
     * 
     * @return bool
     */
    public function is_archive(): bool {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return false;
        }

        return FileSystemHelper::is_archive( $tmp_path );
    }

    /**
     * Check if file exceeds a maximum size.
     * 
     * @param int $max_size Maximum size in bytes
     * @return bool
     */
    public function exceeds_size( int $max_size ): bool {
        return $this->get_size() > $max_size;
    }

    /**
     * Generate a checksum for the uploaded file.
     * 
     * @param string $algo Hash algorithm (default: sha256)
     * @return string|null
     */
    public function checksum( string $algo = 'sha256' ): ?string {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return null;
        }

        return FileSystemHelper::checksum( $tmp_path, $algo );
    }

    /**
     * Verify the uploaded file against a known checksum.
     * 
     * @param string $expected_hash Expected hash value
     * @param string $algo          Algorithm used (default: sha256)
     * @return bool
     */
    public function verify_checksum( string $expected_hash, string $algo = 'sha256' ): bool {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return false;
        }

        return FileSystemHelper::verify_checksum( $tmp_path, $expected_hash, $algo );
    }

    /**
     * Get comprehensive file inspection data.
     * 
     * @return array|null
     */
    public function inspect(): ?array {
        $tmp_path = $this->get_tmp_path();
        
        if ( empty( $tmp_path ) || ! $this->exists( $tmp_path ) ) {
            return null;
        }

        return FileSystemHelper::inspect( $tmp_path );
    }

    /**
     * Validate the upload using FileSystemHelper.
     * 
     * @return string The validated temporary path
     * @throws Exception If the file is invalid or was not uploaded via POST
     */
    public function validate(): string {
        // Return cached result if already validated
        if ( null !== $this->validated_path ) {
            return $this->validated_path;
        }

        // Perform validation using FileSystemHelper
        $this->validated_path = FileSystemHelper::validate_uploaded_file( $this->file_data, $this->key );

        return $this->validated_path;
    }

    /**
     * Securely store the file.
     *
     * @param string      $directory    The destination directory
     * @param string|null $filename     Optional custom name (will be sanitized)
     * @param bool        $unique       Whether to make filename unique if it exists
     * @param int|null    $permissions  File permissions (default: FS_CHMOD_FILE)
     * @return string The final absolute path of the stored file
     * @throws Exception
     */
    public function store( string $directory, ?string $filename = null, bool $unique = false, ?int $permissions = null ): string {
        // 1. Validate the upload first
        $tmp_path = $this->validate();

        // 2. Determine and sanitize the filename using FileSystemHelper
        $target_name = $filename ?? $this->get_client_name();
        $safe_name   = FileSystemHelper::sanitize_filename( $target_name );

        // 3. Make filename unique if requested
        if ( $unique ) {
            $safe_name = $this->make_unique_filename( $directory, $safe_name );
        }

        // 4. Build the final path using FileSystemHelper::join_path
        $final_path = FileSystemHelper::join_path( $directory, $safe_name );
        
        if ( is_smliser_error( $final_path ) ) {
            throw $final_path;
        }

        // 5. Ensure directory exists
        if ( ! $this->exists( $directory ) ) {
            $created = $this->mkdir_recursive( $directory, FS_CHMOD_DIR );
            
            if ( ! $created ) {
                throw new Exception( 
                    'directory_creation_error', 
                    "Could not create directory: {$directory}" 
                );
            }
        }

        // 6. Move the file using FileSystemAwareTrait
        if ( ! $this->move( $tmp_path, $final_path, true ) ) {
            throw new Exception( 
                'storage_error', 
                "Could not move uploaded file to {$final_path}" 
            );
        }

        // 7. Set proper permissions
        $perms = $permissions ?? FS_CHMOD_FILE;
        $this->chmod( $final_path, $perms );

        return $final_path;
    }

    /**
     * Store the file with a custom name generator callback.
     * 
     * @param string   $directory       The destination directory
     * @param callable $name_generator  Callback that receives UploadedFile and returns filename
     * @param bool     $unique          Whether to make filename unique if it exists
     * @param int|null $permissions     File permissions
     * @return string The final absolute path
     * @throws Exception
     */
    public function store_as( string $directory, callable $name_generator, bool $unique = false, ?int $permissions = null ): string {
        $filename = call_user_func( $name_generator, $this );
        return $this->store( $directory, $filename, $unique, $permissions );
    }

    /**
     * Store avatar file using the existing avatar upload logic.
     * 
     * @param string $type     Avatar type (user, organization, service_account)
     * @param string $filename Base filename (without extension)
     * @return bool True on success, false on failure
     */
    public function store_avatar( string $type, string $filename ): bool {
        return FileSystemHelper::upload_avatar( $this->file_data, $type, $filename );
    }

    /**
     * Generate a unique filename if file already exists.
     * 
     * @param string $directory Target directory
     * @param string $filename  Desired filename
     * @return string Unique filename
     */
    private function make_unique_filename( string $directory, string $filename ): string {
        $path_info = pathinfo( $filename );
        $base_name = $path_info['filename'];
        $extension = isset( $path_info['extension'] ) ? '.' . $path_info['extension'] : '';

        $counter    = 1;
        $final_name = $filename;
        
        while ( true ) {
            $test_path = FileSystemHelper::join_path( $directory, $final_name );
            
            if ( is_smliser_error( $test_path ) || ! $this->exists( $test_path ) ) {
                break;
            }
            
            $final_name = $base_name . '-' . $counter . $extension;
            $counter++;
        }

        return $final_name;
    }

    /**
     * Delete the temporary file if it still exists.
     * 
     * @return bool
     */
    public function delete_tmp(): bool {
        $tmp_path = $this->get_tmp_path();
        
        if ( $tmp_path && $this->exists( $tmp_path ) ) {
            return $this->delete( $tmp_path );
        }

        return false;
    }

    /**
     * Get file info as an array.
     * 
     * @return array
     */
    public function to_array(): array {
        return [
            'name'               => $this->get_client_name(),
            'base_name'          => $this->get_base_name(),
            'extension'          => $this->get_extension(),
            'canonical_extension' => $this->get_canonical_extension(),
            'mime_type'          => $this->get_mime_type(),
            'size'               => $this->get_size(),
            'size_formatted'     => $this->get_size_formatted(),
            'tmp_path'           => $this->get_tmp_path(),
            'error'              => $this->get_error(),
            'error_message'      => $this->get_error_message(),
            'is_valid'           => $this->is_valid(),
            'is_image'           => $this->is_image(),
            'is_archive'         => $this->is_archive(),
            'checksum'           => $this->checksum(),
            'key'                => $this->key,
        ];
    }
}