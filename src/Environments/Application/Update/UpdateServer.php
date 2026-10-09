<?php
/**
 * UpdateServer class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Environments\Application\Update
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Environments\Application\Update;

use Callismart\Http\HttpClient;
use SmartLicenseServer\FileSystem\FileSystem;
use Throwable;

/**
 * The Smart License Server instance that publishes this application's
 * releases, and the only source of updates.
 *
 * Asks the REST API for the latest version, then downloads that version's
 * zip, checksums and checksums signature from the public artifact route
 * and verifies them before returning the zip.
 */
final class UpdateServer {

	/**
	 * The update server. Set by hand; point it at a mock server to test.
	 *
	 * @var string
	 */
	public const HOST = 'https://apiv1.callismart.com.ng';

	/**
	 * Hosted app type of this application on the update server.
	 *
	 * @var string
	 */
	public const APP_TYPE = 'software';

	/**
	 * Hosted app slug, also the release package name.
	 *
	 * @var string
	 */
	public const APP_SLUG = 'smart-license-server';

	/**
	 * Build target this installation runs.
	 *
	 * @var string
	 */
	public const TARGET = 'standalone';

	/**
	 * Seconds allowed for the info request and the small artifacts.
	 *
	 * @var int
	 */
	private const TIMEOUT = 20;

	/**
	 * Seconds allowed for the zip download.
	 *
	 * @var int
	 */
	private const DOWNLOAD_TIMEOUT = 600;

	/**
	 * Constructor.
	 *
	 * @param HttpClient      $http     Outgoing HTTP client.
	 * @param PackageVerifier $verifier Checks the downloaded package.
	 * @param FileSystem      $fs       Filesystem API.
	 */
	public function __construct(
		private HttpClient $http,
		private PackageVerifier $verifier,
		private FileSystem $fs
	) {}

	/**
	 * The latest release the update server publishes.
	 *
	 * A release is a security release when its app.json (served as
	 * "manifest") has "security": true.
	 *
	 * @return array{version: string, security: bool}
	 * @throws UpdateException When the server cannot be reached or does not answer as expected.
	 */
	public function latest() : array {
		$url = self::HOST . '/smliser/v1/repository/' . self::APP_TYPE . '/' . self::APP_SLUG;

		try {
			$response = $this->http->get( $url, array( 'Accept' => 'application/json' ), array( 'timeout' => self::TIMEOUT ) );
		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( 'Could not reach the update server: %s', $e->getMessage() ), 0, $e );
		}

		if ( 404 === $response->status_code ) {
			throw new UpdateException( 'The update server does not publish this application.' );
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( 'The update server answered with HTTP %d; try again later.', $response->status_code ) );
		}

		$app     = $response->json()[ self::APP_TYPE ] ?? null;
		$version = is_array( $app ) ? ( $app['version'] ?? null ) : null;

		if ( ! is_string( $version ) || 1 !== preg_match( '/^\d+\.\d+\.\d+([.+-][0-9A-Za-z.+-]+)?$/', $version ) ) {
			throw new UpdateException( 'The update server did not report a valid version; try again later.' );
		}

		return array(
			'version'  => $version,
			'security' => true === ( $app['manifest']['security'] ?? false ),
		);
	}

	/**
	 * Download a version's package and verify it.
	 *
	 * @param string $version        Version to download.
	 * @param string $dir            Existing, writable directory to download into.
	 * @param bool   $trust_unsigned Accept a package without a valid signature.
	 * @return array{zip: string, warnings: string[]}
	 * @throws UpdateException When a download or a check fails; nothing downloaded is kept.
	 */
	public function download( string $version, string $dir, bool $trust_unsigned = false ) : array {
		$base = self::package_base( $version );
		$zip  = rtrim( $dir, '/\\' ) . '/' . $base . '.zip';

		$checksums = $this->fetch( $base . '.sha256' );
		$signature = $this->fetch( $base . '.sha256.sig', true );

		try {
			$response = $this->http->download( $this->artifact_url( $base . '.zip' ), $zip, array(), array( 'timeout' => self::DOWNLOAD_TIMEOUT ) );
		} catch ( Throwable $e ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( 'The download of %s failed: %s', basename( $zip ), $e->getMessage() ), 0, $e );
		}

		if ( ! $response->is_success() ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( 'The download of %s failed with HTTP %d.', basename( $zip ), $response->status_code ) );
		}

		try {
			$warnings = $this->verifier->verify( $zip, $checksums, $signature, $trust_unsigned );
		} catch ( UpdateException $e ) {
			$this->discard( $zip );
			throw $e;
		}

		return array( 'zip' => $zip, 'warnings' => $warnings );
	}

	/**
	 * Release artifact base name of a version.
	 *
	 * @param string $version Version.
	 * @return string E.g. "smart-license-server-1.2.0-standalone".
	 */
	public static function package_base( string $version ) : string {
		return self::APP_SLUG . '-' . $version . '-' . self::TARGET;
	}

	/**
	 * Fetch a small artifact into memory.
	 *
	 * @param string $name     Artifact file name.
	 * @param bool   $optional Return null instead of failing when it is not published.
	 * @return string|null
	 * @throws UpdateException When it cannot be fetched.
	 */
	private function fetch( string $name, bool $optional = false ) : ?string {
		try {
			$response = $this->http->get( $this->artifact_url( $name ), array(), array( 'timeout' => self::TIMEOUT ) );
		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( 'Could not download %s: %s', $name, $e->getMessage() ), 0, $e );
		}

		if ( $optional && 404 === $response->status_code ) {
			return null;
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( 'Could not download %s (HTTP %d).', $name, $response->status_code ) );
		}

		return $response->body;
	}

	/**
	 * Public URL of a release artifact.
	 *
	 * @param string $name Artifact file name.
	 * @return string
	 */
	private function artifact_url( string $name ) : string {
		return self::HOST . '/downloads/' . self::APP_TYPE . '/' . self::APP_SLUG . '/artifacts/' . rawurlencode( $name );
	}

	/**
	 * Remove a download that will not be used.
	 *
	 * @param string $path File path.
	 * @return void
	 */
	private function discard( string $path ) : void {
		if ( $this->fs->is_file( $path ) ) {
			$this->fs->delete( $path );
		}
	}
}