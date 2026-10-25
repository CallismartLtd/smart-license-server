<?php
/**
 * Resource download handler file
 * 
 * @author Callistus
 * @package SmartLicenseServer\FileRequestController
 */

namespace SmartLicenseServer\FileSystem\DownloadsApi;

use SmartLicenseServer\Analytics\AppsAnalytics;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\FileRequestException;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Exceptions\StorageException;
use SmartLicenseServer\FileSystem\FileSystemHelper;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\HostedAppsRegistry;
use SmartLicenseServer\HostedApps\Storage\AppFile;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Monetization\PurchaseRequest;
use SmartLicenseServer\Monetization\PurchaseRequestService;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\Security\SecurityAwareTrait;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * Resource download handler for Smart License Server.
 */
class FileRequestController {
	use SanitizeAwareTrait, SecurityAwareTrait;

	public function __construct(
		protected AppsAnalytics $apps_analytics,
		protected Settings $settings,
		protected PurchaseRequestService $purchase_requests,
		Guard $guard
	) {
		$this->guard = $guard;
	}
	
	/**
	 * Process and serve download request for a hosted application zip file.
	 *
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_application_zip_file( FileRequest|Request $request ) : FileResponse {
		try {

			$app_type = $request->get( 'app_type' );
			$app_slug = $request->get( 'app_slug' );
			$app      = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

			if ( ! $app ) {
				throw new FileRequestException( 'app_not_found' );
			}

			static::check_monetization( $app, $request );

			$package = $app->get_package();

			if ( null === $package ) {
				throw new FileRequestException( 'file_not_found' );
			}
			
			$response = new FileResponse(
				$package,
				$request,
				['type' => $app->get_type()]
			);

			$response->post_response_action(
				[$this->apps_analytics, 'log_download'],
				$app,
				$request->ip(),
				(string) $request->userAgent()
			);

			$response->post_response_action(
				[$this->apps_analytics, 'log_client_access'],
				$app,
				'download',
				$request->ip(),
				(string) $request->userAgent()
			);
			
			return $response;

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Process and serve download request for a hosted application zip file.
	 *
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_application_artifact_file( FileRequest|Request $request ) : FileResponse {
		try {

			$app_type = $request->get( 'app_type' );
			$app_slug = $request->get( 'app_slug' );
			$app      = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

			if ( ! $app ) {
				throw new FileRequestException( 'app_not_found' );
			}

			static::check_monetization( $app, $request );

			$repo_class = HostedAppsRegistry::instance()->get_app_type_directory_class( $app_type );

			if ( ! $repo_class ) {
				throw new FileRequestException( 'file_not_found' );
			}

			$artifact_filename = (string) $request->getTyped( 'artifact_filename', 'string', '' );

			if ( '' === $artifact_filename ) {
				throw new FileRequestException(
					'missing_file_parameter',
					'Artifact file name is required.'
				);
			}

			$artifact = $repo_class->get_artifact( $app->get_slug(), $artifact_filename );

			if ( ! $artifact ) {
				throw new FileRequestException( 'file_not_found' );
			}

			$response = new FileResponse(
				$artifact['file'],
				$request,
				['type' => $app->get_type()]
			);

			$response->post_response_action(
				[$this->apps_analytics, 'log_download'],
				$app,
				$request->ip(),
				(string) $request->userAgent()
			);

			$response->post_response_action(
				[$this->apps_analytics, 'log_client_access'],
				$app,
				'download',
				$request->ip(),
				(string) $request->userAgent()
			);
			
			return $response;

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Process and serve admin package download.
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_admin_application_zip_file( FileRequest|Request $request ) : FileResponse {
		try {
			static::is_system_admin();

			$app_type = $request->get( 'app_type', '' );
			$id       = $request->get( 'app_id' );
			$app      = HostedApplicationService::get_app_by_id( $app_type, $id );

			if ( ! $app ) {
				throw new FileRequestException( 'app_not_found' );
			}

			$package = $app->get_package();

			if ( null === $package ) {
				throw new FileRequestException( 'file_not_found' );
			}

			$response = new FileResponse(
				$package,
				$request,
				['type' => $app->get_type()]
			);
			
			$response->post_response_action(
				[$this->apps_analytics, 'log_download'],
				$app,
				$request->ip(),
				(string) $request->userAgent()
			);

			return $response;
			
		} catch ( FileRequestException $e ) {
			// Catch any specialized exception and package it into the FileResponse
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Serve any file of a hosted app to the repository browser.
	 *
	 * The file is named the way the browser lists it: app_type, app_slug,
	 * kind (an AppFile kind) and name (the file name for artifacts, assets
	 * and documents; empty for the package and the manifest). Images are
	 * served inline, everything else as an attachment.
	 *
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_admin_app_file( FileRequest|Request $request ) : FileResponse {
		try {
			try {
				static::check_permissions( 'hosted_apps.access_files' );
			} catch ( RequestException ) {
				throw new FileRequestException( 'user_not_authorized' );
			}

			$app_type = HostedAppsRegistry::instance()->normalize_app_type( (string) $request->get( 'app_type', '' ) );
			$app_slug = (string) $request->get( 'app_slug', '' );
			$app      = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

			if ( $app ) {
				try {
					static::check_app_ownership( $app );
				} catch ( RequestException ) {
					throw new FileRequestException( 'user_not_authorized' );
				}
			}

			try {
				$file = new AppFile( $app_type, $app_slug, (string) $request->get( 'kind', '' ), (string) $request->get( 'name', '' ) );
			} catch ( StorageException ) {
				throw new FileRequestException( 'file_not_found' );
			}

			$response = new FileResponse( $file, $request, [ 'type' => $app_type ] );

			if ( $response->ok() ) {
				$response->set_header( 'Content-Disposition', $response->get_content_disposition( '', '', true ) );
			}

			return $response;

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Serve license document download to admins.
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_admin_license_document( FileRequest|Request $request ) : FileResponse {
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
			
			$document = static::generate_license_document( $license );

			$file_props = [
				'type'         => 'document',
				'is_file'      => false,
				'name'         => sprintf( 'license-document-%d.txt', $license_id ),
				'content_type' => 'text/plain',
			];


			return new FileResponse( $document, $request, $file_props );

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		} catch ( \Throwable $e ) {
			$exception = new FileRequestException(
				'unexpected_repo_failure',
				$e->getMessage(),
				[ 'trace' => $e->getTraceAsString() ]
			);
			return FileResponse::error_response( $exception, $request );
		}
	}

	/**
	 * Serve a purchase request's receipt to a reviewer.
	 *
	 * Receipts live in the private directory and are served only here, to
	 * principals who can issue licenses (the reviewers of purchase requests).
	 * The review page shows them in a modal (purchase-review.js). Opened
	 * directly, the sandbox policy keeps any script from running as this
	 * site, so browsers may refuse to render a PDF there. Nothing is cached.
	 *
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_purchase_receipt( FileRequest|Request $request ) : FileResponse {
		try {
			$principal = $this->guard->get_principal();

			if ( ! $principal ) {
				throw new FileRequestException( 401 );
			}

			if ( ! $principal->can( 'monetization.license.issue' ) ) {
				throw new FileRequestException( 403 );
			}

			$purchase = PurchaseRequest::get_by_id( (int) $request->get( 'request_id' ) );
			$path     = $purchase ? $this->purchase_requests->get_receipt_path( $purchase ) : null;

			if ( null === $path ) {
				throw new FileRequestException( 'file_not_found' );
			}

			// Named after the request; the stored name is random.
			$name = sprintf( 'purchase-request-%d-receipt.%s', $purchase->get_id(), pathinfo( $path, PATHINFO_EXTENSION ) );

			return ( new FileResponse( $path, $request, [ 'name' => $name, 'content_type' => $purchase->get_receipt_mime() ] ) )
				->set_header( 'Content-Disposition', sprintf( 'inline; filename="%s"', $name ) )
				->set_header( 'Cache-Control', 'private, no-store' )
				// Uploaded by the public: never sniffed into another type, never run as a page of this site.
				->set_header( 'X-Content-Type-Options', 'nosniff' )
				->set_header( 'Content-Security-Policy', "default-src 'none'; img-src 'self'; sandbox" );

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Serve one page image of a PDF receipt (PurchaseRequestService::render_receipt_pages()) to a reviewer.
	 *
	 * Same access as the receipt itself (get_purchase_receipt()).
	 *
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_purchase_receipt_page( FileRequest|Request $request ) : FileResponse {
		try {
			$principal = $this->guard->get_principal();

			if ( ! $principal ) {
				throw new FileRequestException( 401 );
			}

			if ( ! $principal->can( 'monetization.license.issue' ) ) {
				throw new FileRequestException( 403 );
			}

			$purchase = PurchaseRequest::get_by_id( (int) $request->get( 'request_id' ) );
			$pages    = $purchase ? $this->purchase_requests->get_receipt_pages( $purchase ) : [];
			$path     = $pages[ (int) $request->get( 'page' ) - 1 ] ?? null;

			if ( null === $path ) {
				throw new FileRequestException( 'file_not_found' );
			}

			return ( new FileResponse( $path, $request, [ 'name' => basename( $path ), 'content_type' => 'image/png' ] ) )
				->set_header( 'Content-Disposition', 'inline' )
				->set_header( 'Cache-Control', 'private, no-store' )
				->set_header( 'X-Content-Type-Options', 'nosniff' )
				->set_header( 'Content-Security-Policy', "default-src 'none'; sandbox" );

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Serves remote asset as a proxy, bypassing CORs restrictions for clients.
	 * 
	 * @param FileRequest|Request $request The file request object.
	 */
	public function get_proxy_asset( FileRequest|Request $request ) {
		try {
			$asset_url = $request->get( 'asset_url' );

			if ( ! $asset_url ) {
				throw new FileRequestException( 'missing_parameter', 'Asset URL is required.', ['status' => 400] );
			}

			$url = URL::from( $asset_url );

			if ( ! $url->is_valid() ) {
				throw new FileRequestException( 'malformed_request', 'The provided URL is not valid.' );
			}
			
			$file       = smliser_download_url( $asset_url );
			$asset_name = (string) $request->get( 'asset_name' );
			
			$response = new FileResponse( $file, $request );

			if ( ! $file instanceof FileRequestException ) {
				$response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset_name, '', true ) );
			}
			
			return $response;
			
		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Process and serve static assets from the uploads directory.
	 *
	 * @param FileRequest|Request $request
	 * @return FileResponse
	 */
	public function get_uploads_dir_asset( FileRequest|Request $request ) : FileResponse {
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

			$response = ( new FileResponse( $file_path, $request ) )
				->set_header( 'Cache-Control', 'max-age=31536000, immutable' )
				->set_header( 'Expires', sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', time() + 31536000 ) ) )
				->remove_header( 'X-Content-Type-Options' );

			if ( ! $file_path instanceof FileRequestException ) {
				$response = $response->set_header( 'Content-Disposition', $response->get_content_disposition( '', '', true ) );
			}
			
			return $response;

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Process and serve static asset for all hosted applications.
	 * 
	 * @param FileRequest|Request $request The file request object.
	 */
	public function get_app_static_asset( FileRequest|Request $request ) {
		try {

			$app_type   = $request->get( 'app_type' );
			$app_slug   = $request->get( 'app_slug' );
			$asset_name = $request->get( 'asset_name' );

			$repo_class = HostedAppsRegistry::instance()->get_app_type_directory_class( $app_type );
			if ( ! $repo_class ) {
				throw new FileRequestException( 'unsupported_repo_type' );
			}

			try {
				$asset = $repo_class->asset_file( (string) $app_slug, (string) $asset_name );
			} catch ( StorageException ) {
				throw new FileRequestException( 'file_not_found' );
			}

			$file_props = [
				'type' => $app_type,
				'name' => $asset->name,
			];

			$response = new FileResponse( $asset, $request, $file_props );
			if ( $response->ok() ) {
				$response->set_header( 'Content-Disposition', $response->get_content_disposition( $asset->name, '', true ) );
				$response->set_header( 'Cache-Control', 'max-age=31536000, immutable' );
				$response->set_header( 'Expires', sprintf( '%s GMT', gmdate( 'D, d M Y H:i:s', time() + 31536000 ) ) );
			}

			return $response;

		} catch ( FileRequestException $e ) {
			return FileResponse::error_response( $e, $request );
		}
	}

	/**
	 * Computes and generate the document for the given license ID.
	 * 
	 * @param FileRequest|Request $request The file request object.
	 * @return FileResponse
	 */
	public function get_license_document( FileRequest|Request $request ) : FileResponse {
		try {
			$license_id = $request->get( 'license_id' );
			$token      = $request->get( 'download_token' );

			if ( empty( $license_id ) ) {
				throw new FileRequestException( 'invalid_param_license_id' );
			}

			if ( ! is_int( $license_id ) && @preg_match( '#^license-document-(\d+)(?:\.txt|$)#', $license_id, $m ) ) {
				$license_id = (int) $m[1];
			}

			$license = License::get_by_id( $license_id );
			if ( ! $license ) {
				throw new FileRequestException( 'file_not_found' );
			}

			if ( empty( $token ) ) {
				$token = $request->bearerToken();
			}

			if ( empty( $token ) ) {
				throw new FileRequestException( 'license_payment_required' );
			}

			if ( \is_smliser_error( smliser_verify_item_token( $token, $license->get_app() ) ) ) {
				throw new FileRequestException( 'invalid_token' );
			}
			
			$document = static::generate_license_document( $license );

			$file_props = [
				'type'         => 'document',
				'is_file'      => false,
				'name'         => sprintf( 'license-document-%d.txt', $license_id ),
				'content_type' => 'text/plain',
			];


			return new FileResponse( $document, $request, $file_props );

		} catch ( FileRequestException $e ) {
			return new FileResponse( $e, $request );
		} catch ( \Throwable $e ) {
			$exception = new FileRequestException(
				'unexpected_repo_failure',
				$e->getMessage(),
				[ 'trace' => $e->getTraceAsString() ]
			);

			return FileResponse::error_response( $exception, $request );
		}
	}

	/**
	 * The license document served for a license (License::to_document()).
	 *
	 * @param License $license
	 * @return string
	 */
	protected function generate_license_document( License $license ) : string {
		return $license->to_document();
	}

	/**
	 * Validates monetized app file download request
	 */
	protected function check_monetization( HostedAppsInterface $app, FileRequest|Request $request ) : void {
		if ( ! $app->is_monetized() ) {
			return;
		}

		if ( $this->guard->get_principal()?->is( 'system_admin' ) ) {
			return;
		}

		$token = $request->get( 'download_token', '', false );

		if ( empty( $token ) ) {
			$token = $request->bearerToken();
			$request->set( 'download_token', $token );
		}

		if ( empty( $token ) ) {
			throw new FileRequestException( 'payment_required' );
		}

		$verify_token_result = smliser_verify_item_token( $token, $app );

		if ( $verify_token_result instanceof Exception ) {
			throw new FileRequestException( 'invalid_token' );
		}
		
	}

}