<?php
/**
 * Uploaded File Collection
 *
 * Represents one or multiple uploaded files under a single $_FILES key.
 *
 * @package SmartLicenseServer\Core
 * @author Callistus Nwachukwu
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Core;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\FileSystem\FileSystem;

/**
 * Collection of UploadedFile objects sharing one logical field key,
 * covering both PHP's single-file and multi-file $_FILES shapes.
 *
 * @implements IteratorAggregate<int, UploadedFile>
 *
 * @phpstan-type RawFilesEntry array{
 *     name: string|array<int,?string>,
 *     type: string|array<int,?string>,
 *     tmp_name: string|array<int,?string>,
 *     error: int|array<int,?int>,
 *     size: int|array<int,?int>
 * }
 */
final class UploadedFileCollection implements IteratorAggregate, Countable {

	/**
	 * @var array<int, UploadedFile>
	 */
	private array $files = [];

	/**
	 * Logical field key this collection was built from (e.g. the
	 * $_FILES index, or the caller-supplied label for from_array()).
	 * Passed through unchanged to every UploadedFile in the collection,
	 * so per-file error messages don't distinguish index 0 from index 3
	 * of a multi-upload — they all report against this same key.
	 */
	private string $key;

	/**
	 * @param string                   $key
	 * @param array<int, UploadedFile> $files
	 */
	private function __construct( string $key, array $files ) {
		$this->key   = $key;
		$this->files = $files;
	}

	/**
	 * Build a collection from the $_FILES superglobal.
	 *
	 * @param string      $key The field name, i.e. $_FILES[ $key ].
	 * @param ?FileSystem $fs  Filesystem abstraction passed to every UploadedFile
	 *                          created. Defaults to smliser_filesystem() per file.
	 * @return self Empty collection (count() === 0) if $key is absent from
	 *               $_FILES or its value isn't an array.
	 */
	public static function from_files( string $key, ?FileSystem $fs = null ) : self {

		$entry = $_FILES[ $key ] ?? null;

		if ( ! is_array( $entry ) ) {
			return new self( $key, [] );
		}

		return self::from_array( $key, $entry, $fs );
	}

	/**
	 * Build a collection from an already-available $_FILES-style entry,
	 * without touching the superglobal. Useful for tests, or sources
	 * other than a native HTTP file upload (e.g. a custom stream parser).
	 *
	 * Auto-detects single-file shape (name/type/tmp_name/error/size each
	 * a scalar) vs. multi-file shape (each of those keys an array) from
	 * the 'name' field alone. An entry that has an array 'name' but
	 * scalar values elsewhere is still treated as multi-file — indices
	 * missing from a given key normalize to null (see normalize_multi_entry()).
	 *
	 * @param string          $key   Logical field key, propagated to every UploadedFile.
	 * @param RawFilesEntry   $entry Single- or multi-file $_FILES-style array.
	 * @param ?FileSystem     $fs    Filesystem abstraction passed to every UploadedFile
	 *                                created. Defaults to smliser_filesystem() per file.
	 * @return self
	 */
	public static function from_array( string $key, array $entry, ?FileSystem $fs = null ) : self {

		// Multi-file shape.
		if ( isset( $entry['name'] ) && is_array( $entry['name'] ) ) {
			return new self(
				$key,
				self::normalize_multi_entry( $key, $entry, $fs )
			);
		}

		// Single-file shape.
		return new self(
			$key,
			[ new UploadedFile( $entry, $key, $fs ) ]
		);
	}

	/**
	 * Split PHP's parallel-array multi-file $_FILES structure into one
	 * flat, per-file array each, in the shape UploadedFile expects.
	 *
	 * The file count is taken from count( $entry['name'] ); if 'type',
	 * 'tmp_name', 'error', or 'size' are shorter than 'name' at a given
	 * index, that field normalizes to null (name/type/tmp_name) or a
	 * safe default (UPLOAD_ERR_NO_FILE / 0) rather than raising a notice.
	 *
	 * @param string        $key   Logical field key, propagated to every UploadedFile.
	 * @param RawFilesEntry $entry Multi-file $_FILES-style array (name/type/... each an array).
	 * @param ?FileSystem   $fs    Filesystem abstraction passed to every UploadedFile created.
	 * @return UploadedFile[]
	 */
	private static function normalize_multi_entry( string $key, array $entry, ?FileSystem $fs = null ) : array {

		$files = [];
		$count = count( $entry['name'] );

		for ( $i = 0; $i < $count; $i++ ) {

			$file = [
				'name'     => $entry['name'][ $i ] ?? null,
				'type'     => $entry['type'][ $i ] ?? null,
				'tmp_name' => $entry['tmp_name'][ $i ] ?? null,
				'error'    => $entry['error'][ $i ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $entry['size'][ $i ] ?? 0,
			];

			$files[] = new UploadedFile( $file, $key, $fs );
		}

		return $files;
	}

	/**
	 * Whether the collection holds zero files. True both when the source
	 * entry was absent/invalid (from_files()) and when a multi-file entry
	 * had count( $entry['name'] ) === 0.
	 */
	public function is_empty() : bool {
		return empty( $this->files );
	}

	/**
	 * Number of UploadedFile instances in the collection, regardless of
	 * whether each one's upload actually succeeded — use successful()
	 * to filter first if that distinction matters.
	 */
	public function count() : int {
		return count( $this->files );
	}

	/**
	 * @return ArrayIterator<int, UploadedFile>
	 */
	public function getIterator() : ArrayIterator {
		return new ArrayIterator( $this->files );
	}

	/**
	 * Get the file at a given position (0-based, in the order PHP listed
	 * them under this field — i.e. the original $_FILES sub-index).
	 *
	 * @param int $index
	 * @return ?UploadedFile Null if $index is out of range.
	 */
	public function get( int $index ) : ?UploadedFile {
		return $this->files[ $index ] ?? null;
	}

	/**
	 * All UploadedFile instances in the collection, successful or not.
	 *
	 * @return array<int, UploadedFile>
	 */
	public function all() : array {
		return $this->files;
	}

	/**
	 * Files whose upload completed with no PHP-level error, i.e.
	 * UploadedFile::is_upload_successful() is true. Note this checks
	 * only the error code — it does not confirm each file is still
	 * moveable (already-moved/rejected files are not excluded here);
	 * check UploadedFile::is_moveable() individually if that matters.
	 *
	 * @return array<int, UploadedFile> Re-indexed from 0; original
	 *                                    positions are not preserved.
	 */
	public function successful() : array {

		return array_values(
			array_filter(
				$this->files,
				static fn( UploadedFile $file ) => $file->is_upload_successful()
			)
		);
	}

	/**
	 * Reject every file in the collection (delete each temp file via
	 * UploadedFile::reject()). Per-file failures are silent here — call
	 * reject() on individual files directly if you need to know which
	 * ones failed to delete.
	 */
	public function reject_all() : void {

		foreach ( $this->files as $file ) {
			$file->reject();
		}
	}

	/**
	 * Move every file in the collection into $directory.
	 *
	 * Not transactional: files are moved one at a time in collection
	 * order, and the loop stops at the first failure. Files already
	 * moved before that point stay moved — this method does not roll
	 * them back — and files after that point are never attempted. On
	 * failure the caller only learns which files succeeded by checking
	 * UploadedFile::is_moved() on each instance in the collection; the
	 * partial $paths array built so far is discarded with the exception.
	 *
	 * @param string $directory Destination directory, created if missing.
	 * @return array<int,string> Destination paths, one per file, in collection order.
	 * @throws Exception Whatever UploadedFile::move() throws for the failing file
	 *                     ('upload_not_moveable', 'malicious_directory',
	 *                     'filesystem_error', or 'upload_move_failed').
	 */
	public function move_all( string $directory ) : array {

		$paths = [];

		foreach ( $this->files as $file ) {
			$paths[] = $file->move( $directory );
		}

		return $paths;
	}

	/**
	 * Debug inspection snapshot of every file, keyed by its position in
	 * the collection.
	 *
	 * @return array<int, array{
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
	 * }>
	 */
	public function inspect() : array {

		$snapshot = [];

		foreach ( $this->files as $index => $file ) {
			$snapshot[ $index ] = $file->inspect();
		}

		return $snapshot;
	}

	/**
	 * The logical field key this collection was built from.
	 */
	public function get_key() : string {
		return $this->key;
	}
}