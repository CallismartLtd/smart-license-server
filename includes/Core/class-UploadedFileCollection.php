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

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Collection of UploadedFile objects.
 *
 * @implements IteratorAggregate<int, UploadedFile>
 */
final class UploadedFileCollection implements IteratorAggregate, Countable {

    /**
     * @var array<int, UploadedFile>
     */
    private array $files = [];

    /**
     * Logical key name.
     */
    private string $key;

    /**
     * Private constructor.
     *
     * @param string                   $key
     * @param array<int, UploadedFile> $files
     */
    private function __construct( string $key, array $files ) {
        $this->key   = $key;
        $this->files = $files;
    }

    /**
     * Create collection from $_FILES global.
     *
     * Automatically detects single or multi-file shape.
     *
     * @param string $key
     */
    public static function from_files( string $key ) : self {

        $entry = $_FILES[ $key ] ?? null;

        if ( ! is_array( $entry ) ) {
            return new self( $key, [] );
        }

        // Multi-file shape.
        if ( isset( $entry['name'] ) && is_array( $entry['name'] ) ) {
            return new self(
                $key,
                self::normalize_multi_entry( $key, $entry )
            );
        }

        // Single file shape.
        return new self(
            $key,
            [ new UploadedFile( $entry, $key ) ]
        );
    }

    /**
     * Normalize multi-file $_FILES structure.
     *
     * @param string              $key
     * @param array<string,mixed> $entry
     * @return array<int,UploadedFile>
     */
    private static function normalize_multi_entry( string $key, array $entry ) : array {

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

            $files[] = new UploadedFile( $file, $key );
        }

        return $files;
    }

    /**
     * Whether collection is empty.
     */
    public function is_empty() : bool {
        return empty( $this->files );
    }

    /**
     * Number of files.
     */
    public function count() : int {
        return count( $this->files );
    }

    /**
     * Get iterator.
     *
     * @return ArrayIterator<int, UploadedFile>
     */
    public function getIterator() : ArrayIterator {
        return new ArrayIterator( $this->files );
    }

    /**
     * Get file by index.
     */
    public function get( int $index ) : ?UploadedFile {
        return $this->files[ $index ] ?? null;
    }

    /**
     * Get all files.
     *
     * @return array<int, UploadedFile>
     */
    public function all() : array {
        return $this->files;
    }

    /**
     * Filter only successfully uploaded files.
     *
     * @return array<int, UploadedFile>
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
     * Reject all files (delete temp files).
     */
    public function reject_all() : void {

        foreach ( $this->files as $file ) {
            $file->reject();
        }
    }

    /**
     * Move all files to a directory.
     *
     * Transaction-safe:
     * - If one move fails,
     * - Previously moved files are NOT rolled back automatically
     *   (explicit decision to avoid destructive assumptions).
     *
     * @param string $directory
     * @return array<int,string> Destination paths
     *
     * @throws Exception
     */
    public function move_all( string $directory ) : array {

        $paths = [];

        foreach ( $this->files as $file ) {
            $paths[] = $file->move( $directory );
        }

        return $paths;
    }

    /**
     * Debug inspection snapshot.
     *
     * @return array<int,array<string,mixed>>
     */
    public function inspect() : array {

        $snapshot = [];

        foreach ( $this->files as $index => $file ) {
            $snapshot[ $index ] = $file->inspect();
        }

        return $snapshot;
    }

    /**
     * Logical key name.
     */
    public function get_key() : string {
        return $this->key;
    }
}
