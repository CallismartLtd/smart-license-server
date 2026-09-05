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

use RuntimeException;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Represents a single client-uploaded file.
 *
 * This object assumes the file is dangerous by default.
 * It does not auto-validate — it exposes state and allows controlled actions.
 *
 * @phpstan-type UploadedFileArray array{
 *     name: string,
 *     type: string,
 *     tmp_name: string,
 *     error: int,
 *     size: int
 * }
 */
final class UploadedFile {

	/**
	 * Raw single entry from $_FILES, or an equivalent normalized array
	 * passed via from_array(). Null when no matching upload was found
	 * (e.g. from_files() called with a key absent from $_FILES).
	 *
	 * @var UploadedFileArray|null
	 */
	private ?array $file;

	/**
	 * Logical key name (the $_FILES index, or a caller-supplied label
	 * when constructed via from_array()). Used only for error messages.
	 */
	private string $key;

	/**
	 * Caller-supplied name that overrides the client-supplied filename.
	 * Sanitized and stored without extension. Left uninitialized until
	 * set_new_name() is called; get_name() falls back to the sanitized
	 * client name via isset() when it hasn't been set.
	 *
	 * @var ?string
	 */
	private ?string $new_name = null;

	/**
	 * Filesystem abstraction used for all disk I/O (existence checks,
	 * moving, deleting, chmod-ing).
	 */
	private FileSystem $fs;

	/**
	 * Whether move() has already succeeded for this instance.
	 */
	private bool $moved = false;

	/**
	 * Whether reject() has already succeeded for this instance.
	 */
	private bool $rejected = false;

	/**
	 * @param UploadedFileArray|null $file Single $_FILES-style entry, or null if absent.
	 * @param string                 $key  Logical key, used only in error messages.
	 * @param ?FileSystem            $fs   Filesystem abstraction. Defaults to smliser_filesystem().
	 */
	public function __construct( ?array $file, string $key = 'file', ?FileSystem $fs = null ) {
		$this->file = $file;
		$this->key  = $key;
		$this->fs   = $fs ?? smliser_filesystem();
	}

	/**
	 * Create an instance from the superglobal $_FILES array.
	 *
	 * @param string $key The file input's name, i.e. $_FILES[ $key ].
	 * @return static
	 */
	public static function from_files( string $key ) : static {
		return new static( $_FILES[ $key ] ?? null, $key );
	}

	/**
	 * Create an instance from an already-normalized single-file array,
	 * e.g. one entry produced by your own multi-file $_FILES normalizer,
	 * or a synthetic array in tests.
	 *
	 * @param UploadedFileArray $file The normalized single-file array (name, type, tmp_name, error, size).
	 * @param string            $key  Logical identifier for the file input, used only in error messages.
	 * @param ?FileSystem       $fs   Filesystem abstraction. Defaults to smliser_filesystem().
	 * @return static
	 */
	public static function from_array( array $file, string $key = 'file', ?FileSystem $fs = null ) : static {
		return new static( $file, $key, $fs );
	}

	/**
	 * Override the client-supplied filename with a caller-chosen one.
	 * The extension is still resolved separately at move() time from
	 * the file's detected content type, not from this name.
	 *
	 * @param string $name Desired base name (with or without extension; extension is ignored).
	 * @return static
	 * @throws RuntimeException If $name is an empty string.
	 */
	public function set_new_name( string $name ) : static {
		if ( '' === $name ) {
			throw new RuntimeException( 'New file name must not be an empty string.' );
		}

		$this->new_name = FileSystemHelper::sanitize_filename( $name );

		return $this;
	}

	/**
	 * Get the file's base name: the caller-supplied name from set_new_name()
	 * if one was set, otherwise the sanitized client-supplied name.
	 *
	 * @param bool $with_ext Whether to keep the extension. Note: the "extension"
	 *                        here is whatever trailing extension is present in the
	 *                        client-supplied or overridden name — it is NOT the
	 *                        canonical, content-detected extension used by move().
	 * @return ?string Null only when there is no underlying file (exists() is false).
	 */
	public function get_name( bool $with_ext = true ) : ?string {
		if ( isset( $this->new_name ) ) {
			$name = $this->new_name;
		} else {
			$name = $this->get_sanitized_name();
		}

		return $with_ext ? $name : FileSystemHelper::remove_extension( $name );
	}

	/**
	 * Whether a $_FILES-style entry was present at construction time.
	 * This says nothing about whether the upload succeeded — see
	 * is_upload_successful() and has_valid_structure() for that.
	 */
	public function exists() : bool {
		return is_array( $this->file );
	}

	/**
	 * Whether the underlying array has all five keys a genuine $_FILES
	 * entry is expected to have: name, type, tmp_name, error, size.
	 * Returns false (rather than erroring) if exists() is false.
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
	 * The raw PHP upload error code (one of the UPLOAD_ERR_* constants).
	 *
	 * @return ?int Null if exists() is false. If the entry exists but is
	 *              missing the 'error' key, UPLOAD_ERR_NO_FILE is assumed.
	 */
	public function get_error_code() : ?int {
		return $this->exists()
			? (int) ( $this->file['error'] ?? UPLOAD_ERR_NO_FILE )
			: null;
	}

	/**
	 * Human-readable message describing the current upload error state,
	 * suitable for surfacing to a caller or logging.
	 *
	 * @return string Always non-empty; describes "no file" when exists() is false,
	 *                 and delegates to FileSystemHelper::interpret_upload_error()
	 *                 for the standard PHP error codes otherwise.
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
	 * Whether the upload completed with no PHP-level error, i.e.
	 * get_error_code() === UPLOAD_ERR_OK. Does not confirm the file is
	 * actually reachable on disk — see is_uploaded_file() for that.
	 */
	public function is_upload_successful() : bool {
		return $this->get_error_code() === UPLOAD_ERR_OK;
	}

	/**
	 * The temporary file path as reported by the upload entry.
	 *
	 * @return ?string Null if exists() is false. Empty string if exists()
	 *                  is true but 'tmp_name' is missing/empty — callers
	 *                  should treat both null and '' as "no usable path".
	 */
	public function get_tmp_path() : ?string {
		return $this->exists()
			? (string) ( $this->file['tmp_name'] ?? '' )
			: null;
	}

	/**
	 * The original, untrusted filename as supplied by the client.
	 * Never use this directly for filesystem paths — use get_sanitized_name()
	 * or get_name() instead.
	 *
	 * @return ?string Null if exists() is false.
	 */
	public function get_client_name() : ?string {
		return $this->exists()
			? (string) ( $this->file['name'] ?? '' )
			: null;
	}

	/**
	 * The client-supplied filename after sanitization (path stripped,
	 * unsafe characters removed). Does not verify the extension matches
	 * the file's real content — see get_canonical_extension() for that.
	 *
	 * @return ?string Null if exists() is false.
	 */
	public function get_sanitized_name() : ?string {

		$name = $this->get_client_name();

		return $name
			? FileSystemHelper::sanitize_filename( $name )
			: null;
	}

	/**
	 * File size in bytes, as reported by the upload entry (not re-measured
	 * from disk).
	 *
	 * @return ?int Null if exists() is false.
	 */
	public function get_size() : ?int {
		return $this->exists()
			? (int) ( $this->file['size'] ?? 0 )
			: null;
	}

	/**
	 * Whether the temp file is a genuine upload artifact rather than an
	 * arbitrary path an attacker slipped into the array: true if PHP's
	 * is_uploaded_file() confirms it as a native POST upload, OR if the
	 * path exists on disk and its basename carries the SMLISER_UPLOAD_TMP_PREFIX
	 * marker used by this codebase's own non-POST upload/stream parser.
	 */
	public function is_uploaded_file() : bool {

		$tmp = $this->get_tmp_path();

		if ( ! $tmp ) {
			return false;
		}

		// Native PHP POST uploads.
		if ( is_uploaded_file( $tmp ) ) {
			return true;
		}

		$tmp_basename = basename( $tmp );

		// Uploads produced by our own stream parser (e.g. PUT/multipart-outside-POST).
		return $this->fs->exists( $tmp )
			&& str_starts_with( $tmp_basename, SMLISER_UPLOAD_TMP_PREFIX );
	}

	/**
	 * Server-detected MIME type of the temp file's actual contents
	 * (not the client-supplied 'type' field, which is untrusted).
	 *
	 * @return ?string Null if there is no tmp path or it doesn't exist on disk.
	 */
	public function get_detected_mime() : ?string {

		$tmp = $this->get_tmp_path();

		if ( ! $tmp || ! $this->fs->exists( $tmp ) ) {
			return null;
		}

		return FileSystemHelper::get_mime_type( $tmp );
	}

	/**
	 * The extension that matches the file's actual detected content type
	 * (e.g. a real PNG gets 'png' regardless of what the client named it).
	 * This is the extension move() appends to the destination path.
	 *
	 * @return string Empty string if there is no tmp path.
	 */
	public function get_canonical_extension() : string {

		$tmp = $this->get_tmp_path();

		if ( ! $tmp ) {
			return '';
		}

		return FileSystemHelper::get_canonical_extension( $tmp );
	}

	/**
	 * Read the entire contents of the temp file into a string.
	 *
	 * @return string|false The file contents, or false if there is no tmp
	 *                       path or the underlying read fails.
	 */
	public function get_contents() : bool|string {
		$tmp = $this->get_tmp_path();

		if ( ! $tmp ) {
			return false;
		}

		return $this->fs->get_contents( $tmp );
	}

	/**
	 * Checksum of the temp file's contents.
	 *
	 * @param string $algo Any algorithm accepted by hash(), e.g. 'sha256', 'md5'.
	 * @return ?string Null if there is no tmp path.
	 */
	public function checksum( string $algo = 'sha256' ) : ?string {

		$tmp = $this->get_tmp_path();

		if ( ! $tmp ) {
			return null;
		}

		return FileSystemHelper::checksum( $tmp, $algo );
	}

	/**
	 * Whether move() can currently be called successfully. All of the
	 * following must hold: the entry exists, has a valid structure, its
	 * upload error code is UPLOAD_ERR_OK, its temp path is a genuine
	 * upload (see is_uploaded_file()), and it has not already been moved
	 * or rejected.
	 */
	public function is_moveable() : bool {

		return $this->exists()
			&& $this->has_valid_structure()
			&& $this->is_upload_successful()
			&& $this->is_uploaded_file()
			&& ! $this->moved
			&& ! $this->rejected;
	}

	/**
	 * Discard the upload by deleting its temp file, without moving it
	 * anywhere. Once rejected, is_moveable() will always return false.
	 *
	 * @return bool True on successful deletion. False if there is no tmp
	 *               path, it doesn't exist on disk, or deletion fails —
	 *               in all of these cases $rejected is left unset.
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
	 * Move the uploaded temp file to its final destination, appending the
	 * canonical (content-detected) extension to the target filename.
	 *
	 * @param string      $directory  Destination directory. Created if missing.
	 * @param ?string     $filename   Base filename (without extension) to use
	 *                                 instead of get_name( false ). Null or ''
	 *                                 both fall back to get_name( false ).
	 * @param ?bool       $overwrite  Whether an existing file at the destination
	 *                                 may be overwritten. Defaults to false.
	 * @return string The final destination path the file was moved to.
	 * @throws Exception With code 'upload_not_moveable' if is_moveable() is false.
	 * @throws Exception With code 'malicious_directory' if $directory fails path safety checks.
	 * @throws Exception With code 'filesystem_error' if the destination directory
	 *                     doesn't exist and cannot be created.
	 * @throws Exception With code 'upload_move_failed' if the underlying move fails
	 *                     (commonly because $overwrite is false and a file already
	 *                     exists at the destination).
	 */
	public function move( string $directory, ?string $filename = '', ?bool $overwrite = false ) : string {

		if ( ! $this->is_moveable() ) {
			throw new Exception(
				'upload_not_moveable',
				$this->get_error_message()
			);
		}

		$safe_directory = FileSystemHelper::join_path( $directory );

		if ( '' === $safe_directory ) {
			throw new Exception( 'malicious_directory', 'The provided directory name is not safe.' );
		}

		if ( ! $this->fs->is_dir( $safe_directory ) && ! $this->fs->mkdir( $safe_directory, SMLISER_DIR_PERMISSION ) ) {
			throw new Exception( 'filesystem_error', 'Unable to created destination directory.' );
		}

		$filename    = $filename ? FileSystemHelper::remove_extension( $filename ) : $this->get_name( false );
		$path        = FileSystemHelper::join_path( $safe_directory, $filename );
		$destination = sprintf( '%s.%s', $path, $this->get_canonical_extension() );

		if ( ! $this->fs->move( $this->get_tmp_path(), $destination, $overwrite ) ) {
			$message = 'Failed to move uploaded file';
			if ( ! $overwrite ) {
				$message .= ' likely due to duplicate file entry';
			}

			$message .= '.';
			throw new Exception(
				'upload_move_failed',
				sprintf( '%s %s.', $message, $this->key )
			);
		}

		@$this->fs->chmod( $destination, SMLISER_FILE_PERMISSION );

		$this->moved = true;

		return $destination;
	}

	/**
	 * Whether move() has already completed successfully for this instance.
	 */
	public function is_moved() : bool {
		return $this->moved;
	}

	/**
	 * Whether reject() has already completed successfully for this instance.
	 */
	public function is_rejected() : bool {
		return $this->rejected;
	}

	/**
	 * Debug inspection snapshot of the current state, useful for logging.
	 *
	 * @return array{
	 *     exists: bool,
	 *     structure_ok: bool,
	 *     error_code: ?int,
	 *     error_message: string,
	 *     is_successful: bool,
	 *     is_http_upload: bool,
	 *     size: ?int,
	 *     mime: ?string,
	 *     checksum: ?string,
	 *     moved: bool,
	 *     rejected: bool
	 * }
	 */
	public function inspect() : array {

		return [
			'exists'         => $this->exists(),
			'structure_ok'   => $this->has_valid_structure(),
			'error_code'     => $this->get_error_code(),
			'error_message'  => $this->get_error_message(),
			'is_successful'  => $this->is_upload_successful(),
			'is_http_upload' => $this->is_uploaded_file(),
			'size'           => $this->get_size(),
			'mime'           => $this->get_detected_mime(),
			'checksum'       => $this->checksum(),
			'moved'          => $this->moved,
			'rejected'       => $this->rejected,
		];
	}
}