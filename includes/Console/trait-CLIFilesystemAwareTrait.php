<?php
/**
 * CLI filesystem aware trait file.
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Console
 * @since 0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Console;

use SmartLicenseServer\Core\Request;
use SmartLicenseServer\FileSystem\FileSystemHelper;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * CLI filesystem aware trait
 */
trait CLIFilesystemAwareTrait {
    /**
     * Resolve a local file path and verify it exists and is readable.
     *
     * Copies the file to the temp directory with SMLISER_UPLOAD_TMP_PREFIX
     * so it passes UploadedFile::is_uploaded_file() via the custom parser
     * path (prefix check), without touching $_FILES.
     *
     * Returns the temp path on success, null on failure.
     *
     * @param string $path Absolute local path.
     * @return string|null Temp path, or null on failure.
     */
    private function resolve_local_file( string $path, bool $auto_clean = true ): ?string {
        $fs = smliser_filesystem();

        if ( ! $fs->exists( $path ) ) {
            $this->error( sprintf( 'File not found: %s', $path ) );
            return null;
        }

        if ( ! $fs->is_readable( $path ) ) {
            $this->error( sprintf( 'File is not readable: %s', $path ) );
            return null;
        }

        $destination = sprintf(
            '%s/%s%s',
            SMLISER_TMP_DIR,
            SMLISER_UPLOAD_TMP_PREFIX,
            basename( $path )
        );

        if ( ! $fs->copy( $path, $destination ) ) {
            $this->error( sprintf( 'Failed to copy file to temp directory: %s', $path ) );
            return null;
        }

        if ( $auto_clean ) {
            \register_shutdown_function( function() use ( $destination ){
                @unlink( $destination );
            });
        }

        return $destination;
    }

    /**
     * Download a remote URL to the temp directory.
     *
     * Uses smliser_download_url() which already writes to SMLISER_TMP_DIR
     * with SMLISER_UPLOAD_TMP_PREFIX — passes is_uploaded_file() natively.
     *
     * Returns [ $temp_path, $cleanup_fn ] on success, null on failure.
     * The cleanup function deletes the temp file — always call it in a
     * finally block.
     *
     * @param string $url      Remote URL.
     * @param bool $auto_clean Whether to enable automatic tmp file deletion.
     * @return string|null
     */
    private function download_to_tmp( string $url, bool $auto_clean = true ): ?string {
        $this->line( sprintf( 'Downloading %s...', $url ) );

        $temp_path = smliser_download_url( $url, timeout: 60, autoclean: $auto_clean  );

        if ( $temp_path instanceof \SmartLicenseServer\Exceptions\FileRequestException ) {
            $status  = $temp_path->get_error_data()['status'] ?? 0;
            $message = $temp_path->get_error_message() ?: 'Unknown download error.';

            $this->error( $status
                ? sprintf( 'Download failed [HTTP %d]: %s', $status, $message )
                : sprintf( 'Download failed: %s', $message )
            );
            return null;
        }

        return $temp_path;
    }

    /**
     * Construct an UploadedFile from a temp path and inject it into the request.
     *
     * The file entry is shaped like a $_FILES array so UploadedFile
     * can hydrate from it. The tmp_name already has SMLISER_UPLOAD_TMP_PREFIX
     * so is_uploaded_file() passes via the custom parser path.
     *
     * Returns the modified Request on success, null on failure.
     *
     * @param Request $request  The request to inject into.
     * @param string  $tmp_path Temp file path.
     * @param string  $key      The file key (e.g. 'app_zip_file', 'asset_file').
     * @return Request|null
     */
    private function inject_uploaded_file( Request $request, string $tmp_path, string $key ): ?Request {
        $fs = smliser_filesystem();

        if ( ! $fs->exists( $tmp_path ) ) {
            $this->error( sprintf( 'Temp file missing: %s', $tmp_path ) );
            return null;
        }

        $file_entry = [
            'name'     => basename( $tmp_path ),
            'type'     => FileSystemHelper::get_mime_type( $tmp_path ),
            'tmp_name' => $tmp_path,
            'error'    => UPLOAD_ERR_OK,
            'size'     => $fs->filesize( $tmp_path ),
        ];

        // Populate $_FILES so Request::parse_uploaded_files() picks it up.
        $_FILES[ $key ] = $file_entry;
        $request->parse_uploaded_files();

        return $request;
    }


    /**
     * Call cleanup functions — tolerates null entries.
     *
     * @param callable|null ...$fns
     */
    private function cleanup( ?callable ...$fns ): void {
        foreach ( $fns as $fn ) {
            if ( $fn !== null ) {
                try {
                    $fn();
                } catch ( \Throwable ) {
                    // Cleanup failure must never mask the main result.
                }
            }
        }
    }
}