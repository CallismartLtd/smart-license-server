<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\DownloadsApi;

use SmartLicenseServer\Exception\FileRequestException;

defined( 'SMLISER_PATH' ) || exit;

/**
 * Resource download handler for Smart License Server.
 */
class FileRequestController {
    /**
     * Process and serve download request for a hosted application zip file.
     *
     * @param FileRequest $request The file request object.
     * @return \SmartLicenseServer\DownloadsApi\FileResponse
     */
    public static function get_application_zip_file( FileRequest $request ): FileResponse {
        try {
            $app_type = $request->get( 'app_type' );
            $app_slug = $request->get( 'app_slug' );
            $token    = $request->get( 'download_token' );

            $app_class = \Smliser_Software_Collection::get_app_class( $app_type );
            $method    = 'get_by_slug';

            if ( ! method_exists( $app_class, $method ) ) {
                throw new FileRequestException( 'invalid_app_type_method' );
            }

            /** @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface $app */
            $app = $app_class::$method( $app_slug );

            if ( ! $app ) {
                throw new FileRequestException( 'app_not_found' );
            }
            
            if ( $app->is_monetized() ) {

                // Try to find token in Authorization header if missing from query.
                if ( empty( $token ) ) {
                    $auth_header = $request->get( 'authorization' );
                    if ( $auth_header && str_starts_with( strtolower($auth_header), 'bearer ' ) ) {
                        $parts = explode( ' ', $auth_header );
                        $token = sanitize_text_field( unslash( $parts[1] ?? '' ) ); 
                        $request->set( 'download_token', $token );
                    }
                }

                if ( empty( $token ) ) {
                    throw new FileRequestException( 'payment_required' );
                }

                if ( ! smliser_verify_item_token( $token, $app ) ) {
                    throw new FileRequestException( 'invalid_token' );
                }
            }

            $file_path = $app->get_zip_file();
            $request->set( 'file_path', $file_path );
            
            $response = new FileResponse( $file_path, ['type' => $app->get_type()] );
            
            do_action( 'smliser_stats', sprintf( '%s_download', $app->get_type() ), $app );

            return $response;

        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }
    }

    /**
     * Process and serve admin package download.
     * @param FileRequest $request The file request object.
     * @return FileResponse
     */
    public static function get_admin_application_zip_file( FileRequest $request ): FileResponse {
        try {
            $app_type   = $request->get( 'app_type', '' );
            $app_class  = \Smliser_Software_Collection::get_app_class( $app_type );
            $method     = "get_{$app_type}";

            if ( ! \method_exists( $app_class, $method ) ) {
                // Uses FileRequestException with error slug 'invalid_app_type_method'
                throw new FileRequestException( 'invalid_app_type_method' );
            }

            $id = $request->get( 'app_id' );

            /**
             * @var \SmartLicenseServer\HostedApps\Hosted_Apps_Interface|null $app
             */
            $app = $app_class::$method( $id );

            if ( ! $app ) {
                // Uses FileRequestException with error slug 'app_not_found'
                throw new FileRequestException( 'app_not_found' );
            }

            $file_path  = $app->get_zip_file();
            $response   = new FileResponse( $file_path, ['type' => $app->get_type()] );
            
            // Use a conditional check for stats logic
            if ( \function_exists( 'do_action' ) ) {
                do_action( 'smliser_stats', \sprintf( '%s_download', $app->get_type() ), $app );
            }
            
            return $response;
            
        } catch ( FileRequestException $e ) {
            // Catch any specialized exception and package it into the FileResponse
            return new FileResponse( $e );
        }
    }

    /**
     * Serves remote asset as a proxy, bypassing CORs restrictions for clients.
     * 
     * @param FileRequest $request The file request object.
     */
    public static function get_proxy_asset( FileRequest $request ) {
        try {
            $asset_url  = $request->get( 'asset_url' );

            if ( ! $asset_url ) {
                throw new FileRequestException( 'missing_parameter', 'Asset URL is required.', ['status' => 400] );
            }
            
            $file       = smliser_download_url( $asset_url );
            $asset_name = $request->get( 'asset_name' );
            
            $response   = new FileResponse( $file );
            $response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset_name, '', true ) );

            return $response;
            
        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }    
    }

    /**
     * Process and server static asset for all hosted applications.
     * 
     * @param FileRequest $request The file request object.
     */
    public static function get_app_static_asset( FileRequest $request ){
        try {
            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_name = $request->get( 'asset_name' );

            $repo_class = \Smliser_Software_Collection::get_app_repository_class( $app_type );
            if ( ! $repo_class ) {
                throw new FileRequestException( 'unsupported_repo_type' );
            }

            $file_path = $repo_class->get_asset_path( $app_slug, $asset_name );

            if ( is_smliser_error( $file_path ) ) {
                throw  ( new FileRequestException( 'file_not_found' ) )->merge_from( $file_path );
            }

            $file_props = [
                'type'         => $app_type,
                'name'         => $asset_name,
            ];

            $response = new FileResponse( $file_path, $file_props );
            $response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset_name, '', true ) );
            $response->set_header( 'Cache-Control', 'max-age=31536000, immutable' );
            $response->set_header( 'Expires', sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', time() + 31536000 ) ) );
            return $response;

        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }
    }

    /**
     * Computes and generate the document for the given license ID.
     * 
     * @param FileRequest $request The file request object.
     * @return FileResponse
     */
    public static function get_license_document( FileRequest $request ): FileResponse {
        try {
            $license_id = $request->get( 'license_id' );
            $token      = $request->get( 'download_token' );

            if ( empty( $license_id ) ) {
                throw new FileRequestException( 'invalid_param_license_id' );
            }

            $license = \Smliser_license::get_by_id( $license_id );
            if ( ! $license ) {
                throw new FileRequestException( 'file_not_found' );
            }

            if ( ! (bool) $request->get( 'is_authorized', false ) ) {
                // Token extraction and verification
                if ( empty( $token ) ) {
                    $auth_header = $request->get( 'authorization' );
                    if ( $auth_header && str_starts_with( strtolower( $auth_header ), 'bearer ' ) ) {
                        $parts = explode( ' ', $auth_header, 2 );
                        $token = sanitize_text_field( unslash( $parts[1] ?? '' ) ); 
                        $request->set( 'download_token', $token );
                    }
                }

                if ( empty( $token ) ) {
                    throw new FileRequestException( 'license_payment_required' );
                }

                if ( ! smliser_verify_item_token( $token, $license->get_app() ) ) {
                    throw new FileRequestException( 'invalid_token' );
                }
            }

            $document = self::generate_license_document( $license, $request );

            $file_props = [
                'type'         => 'document',
                'is_file'      => false,
                'name'         => 'license-document.txt',
                'content_type' => 'text/plain',
            ];

            return new FileResponse( $document, $file_props );

        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        } catch ( \Throwable $e ) {
            $exception = new FileRequestException(
                'unexpected_repo_failure',
                $e->getMessage(),
                [ 'trace' => $e->getTraceAsString() ]
            );
            return new FileResponse( $exception );
        }
    }

    /**
     * Generates a textual license certificate for a given license and request context.
     *
     * @param object $license   License object.
     * @param FileRequest $request Request context (used for issuer, terms URL, etc.).
     * @return string The formatted license document.
     */
    protected static function generate_license_document( $license, FileRequest $request ): string {
        $license_key = $license->get_license_key();
        $service_id  = $license->get_service_id();
        $issued      = $license->get_start_date();
        $expiry      = $license->get_end_date();
        $site_limit  = $license->get_allowed_sites();
        $today       = date_i18n( 'Y-m-d H:i:s' );
        $issuer      = $request->get( 'issuer', SMLISER_APP_NAME );
        $terms_url   = $request->get( 'terms_url', '#' );
        $item_id     = $license->get_item_id() ?? 0;

        $document = <<<EOT
    ========================================
    SOFTWARE LICENSE CERTIFICATE
    Issued by {$issuer}
    ========================================
    ----------------------------------------
    License Details
    ----------------------------------------
    Service ID:     {$service_id}
    License Key:    {$license_key}
    (Ref: Item ID {$item_id})

    ----------------------------------------
    License Validity
    ----------------------------------------
    Start Date:     {$issued}
    End Date:       {$expiry}
    Allowed Sites:  {$site_limit}

    ----------------------------------------
    Activation Guide
    ----------------------------------------
    Use the Service ID and License Key above to activate this software.

    Note:
    - The software already includes its internal ID.
    - Activation may vary by product. Refer to product documentation.

    ----------------------------------------
    License Terms (Summary)
    ----------------------------------------
    ✔ Use on up to {$site_limit} site(s)
    ✔ Allowed for personal or client projects
    ✘ Not allowed to resell, redistribute, or modify for resale

    Full License Agreement:
    {$terms_url}

    ----------------------------------------
    Issued By:      {$issuer}
    Generated On:   {$today}
    ========================================
    EOT;

        return $document;
    }

}
