<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\DownloadsApi;

use SmartLicenseServer\Exception;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Resource download handler for Smart License Server.
 */
class FileRequestController {
    /**
     * Process and serve the requested download.
     *
     * @param FileRequest $request The download request object.
     */
    public static function serve_package( FileRequest $request ) {

        $app_type = $request->get( 'app_type' );
        $app_slug = $request->get( 'app_slug' );
        $token    = $request->get( 'download_token' );

        $app_class = \Smliser_Software_Collection::get_app_class( $app_type );
        $method    = 'get_by_slug';

        if ( ! method_exists( $app_class, $method ) ) {
            smliser_abort_request(
                __( 'Unsupported application type', 'smliser' ),
                'Invalid App Type',
                array( 'response' => 400 )
            );
        }

        /** @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface $app */
        $app = $app_class::$method( $app_slug );

        if ( ! $app ) {
            smliser_abort_request(
                __( 'The requested application was not found', 'smliser' ),
                'Not Found',
                array( 'response' => 404 )
            );
        }

        // Handle licensed / monetized downloads.
        if ( $app->is_monetized() ) {

            // If no token was passed in query, try authorization header.
            if ( empty( $token ) ) {
                $auth_header = $request->get( 'authorization' );
                if ( $auth_header ) {
                    $parts = explode( ' ', $auth_header );
                    $token = sanitize_text_field( unslash( $parts[1] ?? '' ) );
                    $request->set( 'download_token', $token );
                }
            }

            if ( empty( $token ) ) {
                smliser_abort_request(
                    __( 'Monetized application, please purchase a license.', 'smliser' ),
                    'Payment Required',
                    array( 'response' => 402 )
                );
            }

            if ( ! smliser_verify_item_token( $token, $app ) ) {
                smliser_abort_request(
                    __( 'Invalid download token', 'smliser' ),
                    'Unauthorized',
                    array( 'response' => 401 )
                );
            }
        }

        $repo_class = \Smliser_Software_Collection::get_app_repository_class( $app_type );

        if ( ! $repo_class ) {
            smliser_abort_request(
                __( 'This application type is not supported.', 'smliser' ),
                'Unsupported Type',
                array( 'response' => 400 )
            );
        }

        $file_path = $app->get_zip_file();
        $request->set( 'file_path', $file_path );

        if ( ! $repo_class->exists( $file_path ) || ! $repo_class->is_valid_zip( $file_path ) ) {
            smliser_abort_request(
                __( 'The requested file was not found.', 'smliser' ),
                'File Not Found',
                array( 'response' => 404 )
            );
        }

        // Serve file for download
        if ( $repo_class->is_readable( $file_path ) ) {

            /**
             * Fires for download stats synchronization.
             */
            do_action( 'smliser_stats', sprintf( '%s_download', $app->get_type() ), $app );

            status_header( 200 );
            header( 'x-content-type-options: nosniff' );
            header( 'x-Robots-tag: noindex, nofollow', true );
            header( 'content-description: file transfer' );

            if ( strpos( (string) $request->get( 'user_agent' ), 'MSIE' ) !== false ) {
                header( 'content-Type: application/force-download' );
            } else {
                header( 'content-Type: application/zip' );
            }

            header( 'content-disposition: attachment; filename="' . basename( $file_path ) . '"' );
            header( 'expires: 0' );
            header( 'cache-control: must-revalidate' );
            header( 'pragma: public' );
            header( 'content-length: ' . $repo_class->filesize( $file_path ) );
            header( 'content-transfer-encoding: binary' );

            $repo_class->readfile( $file_path );
            exit;
        }

        \smliser_abort_request(
            __( 'Error: The file cannot be read.', 'smliser' ),
            'File Reading Error',
            array( 'response' => 500 )
        );
    }

    /**
     * Process and serve admin package download.
     * 
     * @param FileRequest $request The download request object.
     * @return FileResponse
     */
    public static function serve_admin_download( FileRequest $request ): FileResponse {
        $app_type   = $request->get( 'app_type', '' );
        $app_class  = \Smliser_Software_Collection::get_app_class( $app_type );
        $method     = "get_{$app_type}";

        if ( ! \method_exists( $app_class, $method ) ) {
            // Return an error response instead of aborting.
            $error = new Exception( 
                'invalid_app_type', 
                __( 'Invalid application type.', 'smliser' ), 
                array( 'status' => 400, 'title' => 'Invalid Type' )
            );
            return new FileResponse( $error );
        }

        $id = $request->get( 'app_id' );

        /**
         * @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface|null $app
         */
        $app = $app_class::$method( $id );

        if ( ! $app ) {
            // Return an error response instead of aborting.
            $error = new Exception(
                'app_not_found',
                __( 'The requested application was not found.', 'smliser' ),
                array( 'status' => 404, 'title' => 'Not Found' )
            );
            return new FileResponse( $error );
        }

        $file_path  = $app->get_zip_file();
        $response   = new FileResponse( $file_path, $app_type );
        
        // Use a conditional check to prevent errors if stats logic isn't ready
        if ( \function_exists( 'do_action' ) ) {
            do_action( 'smliser_stats', \sprintf( '%s_download', $app->get_type() ), $app );
        }
        
        return $response;
    }
}
