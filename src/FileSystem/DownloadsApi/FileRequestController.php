<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Resource download handler for Smart License Server.
 */
class FileRequestController {
    use SanitizeAwareTrait, SecurityAwareTrait;
    
    /**
     * Process and serve download request for a hosted application zip file.
     *
     * @param FileRequest $request The file request object.
     * @return FileResponse
     */
    public static function get_application_zip_file( FileRequest $request ): FileResponse {
        try {

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $app        = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new FileRequestException( 'app_not_found' );
            }

            static::check_monetization( $app, $request );

            $file_path = $app->get_zip_file();

            if ( ( $file_path instanceof Exception ) || empty( $file_path ) ) {
                throw new FileRequestException( 'file_not_found' );
            }

            $request->set( 'file_path', $file_path );
            
            $response = new FileResponse( $file_path, ['type' => $app->get_type()] );

            $response->register_after_serve_callback( [AppsAnalytics::class,'log_download'], [$app] );
            $response->register_after_serve_callback( [AppsAnalytics::class,'log_client_access'], [$app, 'download'] );
            
            return $response;

        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }
    }

    /**
     * Process and serve download request for a hosted application zip file.
     *
     * @param FileRequest $request The file request object.
     * @return FileResponse
     */
    public static function get_application_artifact_file( FileRequest $request ): FileResponse {
        try {

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $app        = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

            if ( ! $app ) {
                throw new FileRequestException( 'app_not_found' );
            }

            static::check_monetization( $app, $request );

            $repo_class = HostedAppsRegistry::instance()->get_app_type_directory_class( $app_type );

            if ( ! $repo_class ) {
                throw new FileRequestException( 'file_not_found' );
            }

            $artifact_filename  = (string) $request->getTyped( 'artifact_filename', 'string', '' );

            if ( '' === $artifact_filename ) {
                throw new FileRequestException(
                    'missing_file_parameter',
                    'Artifact file name is required.'
                );
            }

            $artifact   = $repo_class->get_artifact( $app->get_slug(), $artifact_filename );

            if ( ! $artifact ) {
                throw new FileRequestException( 'file_not_found' );
            }

            $file_path = $artifact['path'] ?? '';

            if (  empty( $file_path ) ) {
                throw new FileRequestException( 'file_not_found' );
            }

            $request->set( 'file_path', $file_path );
            
            $response = new FileResponse( $file_path, ['type' => $app->get_type()] );

            $response->register_after_serve_callback( [AppsAnalytics::class,'log_download'], [$app] );
            $response->register_after_serve_callback( [AppsAnalytics::class,'log_client_access'], [$app, 'download'] );
            
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
            static::is_system_admin();

            $app_type   = $request->get( 'app_type', '' );
            $id         = $request->get( 'app_id' );
            $app        = HostedApplicationService::get_app_by_id( $app_type, $id );

            if ( ! $app ) {
                throw new FileRequestException( 'app_not_found' );
            }

            $file_path  = $app->get_zip_file();
            $response   = new FileResponse( $file_path, ['type' => $app->get_type()] );
            
            $response->register_after_serve_callback( [AppsAnalytics::class,'log_download'], [$app] );
            
            return $response;
            
        } catch ( FileRequestException $e ) {
            // Catch any specialized exception and package it into the FileResponse
            return new FileResponse( $e );
        }
    }

    /**
     * Serve license document download to admins.
     * @param FileRequest $request The file request object.
     * @return FileResponse
     */
    public static function get_admin_license_document( FileRequest $request ): FileResponse {
        try {
            static::is_system_admin();
            $license_id = (int) $request->get( 'license_id' );

            if ( empty( $license_id ) ) {
                throw new FileRequestException( 'invalid_param_license_id' );
            }

            $license = License::get_by_id( $license_id );
            if ( ! $license ) {
                throw new FileRequestException( 'file_not_found' );
            }
            
            $document = static::generate_license_document( $license, smliser_settings() );

            $file_props = [
                'type'         => 'document',
                'is_file'      => false,
                'name'         => sprintf( 'license-document-%d.txt', $license_id ),
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

            $url    = new URL( $asset_url );

            if ( ! $url->is_valid() ) {
                throw new FileRequestException( 'malformed_request', 'The provided URL is not valid.' );
            }
            
            $file       = smliser_download_url( $asset_url );
            $asset_name = $request->get( 'asset_name' );
            
            $response   = new FileResponse( $file );

            if ( ! $file instanceof FileRequestException ) {
                $response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset_name, '', true ) );
            }
            
            return $response;
            
        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }    
    }

    /**
     * Process and serve static assets from the smliser-uploads directory.
     *
     * @param FileRequest $request
     * @return FileResponse
     */
    public static function get_uploads_dir_asset( FileRequest $request ): FileResponse {
        try {
            $file_path = $request->get( 'file_path' );

            if ( ! $file_path ) {
                throw new FileRequestException( 'missing_parameter', 'File path is required.', ['status' => 400] );
            }

            $sanitized_path = FileSystemHelper::sanitize_path( $file_path );
            $file_path      = \is_smliser_error( $sanitized_path ) ? $sanitized_path : FileSystemHelper::join_path( SMLISER_UPLOADS_DIR, $sanitized_path );

            if ( is_smliser_error( $file_path ) || ! FileSystemHelper::is_valid_file( $file_path ) ) {
                throw new FileRequestException( 'file_not_found' );
            }

            $response = ( new FileResponse( $file_path ) )
                ->set_header( 'Cache-Control', 'max-age=31536000, immutable' )
                ->set_header( 'Expires', sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', time() + 31536000 ) ) )
                ->remove_header( 'X-Content-Type-Options');

            if ( ! $file_path instanceof FileRequestException ) {
                $response   = $response->set_header( 'Content-Disposition', $response->get_content_disposition( '', '', true ) );
            }
            
            return $response;

        } catch ( FileRequestException $e ) {
            return new FileResponse( $e );
        }
    }

    /**
     * Process and serve static asset for all hosted applications.
     * 
     * @param FileRequest $request The file request object.
     */
    public static function get_app_static_asset( FileRequest $request ){
        try {

            $app_type   = $request->get( 'app_type' );
            $app_slug   = $request->get( 'app_slug' );
            $asset_name = $request->get( 'asset_name' );

            $repo_class = HostedAppsRegistry::instance()->get_app_type_directory_class( $app_type );
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
            if ( ! $file_path instanceof FileRequestException ) {
                $response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset_name, '', true ) );
                $response->set_header( 'Cache-Control', 'max-age=31536000, immutable' );
                $response->set_header( 'Expires', sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', time() + 31536000 ) ) );                
            }

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

            if ( ! is_int( $license_id ) && @preg_match( '#^license-document-(\d+)(?:\.txt|$)#', $license_id, $m )) {
                $license_id = (int) $m[1];
            }

            $license = License::get_by_id( $license_id );
            if ( ! $license ) {
                throw new FileRequestException( 'file_not_found' );
            }

            if ( empty( $token ) ) {
                $token  = $request->bearerToken();
            }

            if ( empty( $token ) ) {
                throw new FileRequestException( 'license_payment_required' );
            }

            if ( \is_smliser_error( smliser_verify_item_token( $token, $license->get_app() ) ) ) {
                throw new FileRequestException( 'invalid_token' );
            }
            
            $document = static::generate_license_document( $license, smliser_settings() );

            $file_props = [
                'type'         => 'document',
                'is_file'      => false,
                'name'         => sprintf( 'license-document-%d.txt', $license_id ),
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
     * @param License $license   License object.
     * @param Settings Settings API instance (used for issuer, terms URL, etc.).
     * @return string The formatted license document.
     */
    protected static function generate_license_document( $license, Settings $settingsAPI ): string {
        $license_key    = $license->get_license_key();
        $service_id     = $license->get_service_id();
        $date_issued    = $license->get_start_date()?->format( \smliser_datetime_format() ) ?? 'N/A';
        $expiry         = $license->get_end_date()?->format( \smliser_datetime_format() ) ?? 'N/A';
        $status         = $license->get_status();
        $max_domains    = $license->get_max_allowed_domains();
        $licensee       = $license->get_licensee_fullname();
        $today          = gmdate( 'F j, Y g:i:s a' );
        $issuer         = $settingsAPI->get( 'repository_name', SMLISER_APP_NAME, true );
        $terms_url      = $settingsAPI->get( 'terms_url', '', true );
        $app_id         = $license->is_issued() ? $license->get_app_id() : 'N/A';

        $document = <<<LICENSE
        ==================================================
        SOFTWARE LICENSE CERTIFICATE
        Issued by:  {$issuer}
        Licensee:   {$licensee}
        ==================================================
        --------------------------------------------------
        License Details
        --------------------------------------------------
        Status:         {$status}
        Service ID:     {$service_id}
        License Key:    {$license_key}
        App ID:         {$app_id}

        --------------------------------------------------
        License Validity
        --------------------------------------------------
        Start Date:     {$date_issued}
        End Date:       {$expiry}
        Allowed Sites:  {$max_domains}

        --------------------------------------------------
        Activation Guide
        --------------------------------------------------
        Use the Service ID and License Key above to activate this software.

        Note:
        - The software already includes its internal ID.
        - Activation may vary by product. Refer to product documentation.

        --------------------------------------------------
        License Terms (Summary)
        --------------------------------------------------
        ✔ Use on "{$max_domains}" domain(s)
        ✔ Allowed for personal or client projects
        ✘ Not allowed to resell, redistribute, or modify for resale

        Full License Agreement:
        {$terms_url}

        --------------------------------------------------
        Issued By:          {$issuer}
        Auto Generated On:  {$today}
        ==================================================
        LICENSE;

        return $document;
    }

    /**
     * Validates monetized app file download request
     */
    protected static function check_monetization( HostedAppsInterface $app, FileRequest $request ) : void {
        if ( ! $app->is_monetized() ) {
            return;
        }

        if ( Guard::get_principal()?->is( 'system_admin' ) ) {
            return;
        }

        $token  = $request->get( 'download_token', '', false );

        if ( empty( $token ) ) {
            $token = $request->bearerToken();
            $request->set( 'download_token', $token );
        }

        if ( empty( $token ) ) {
            throw new FileRequestException( 'payment_required' );
        }

        $verify_token_result    = smliser_verify_item_token( $token, $app );

        if ( $verify_token_result instanceof Exception ) {
            throw new FileRequestException( 'invalid_token' );
        }
        
    }

}
