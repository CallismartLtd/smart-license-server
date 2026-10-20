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
 *
 * The same server is the marketplace for packages this application
 * installs (site themes, see ThemeInstaller): latest_of() and
 * download_artifact() do the same for any hosted app on it, and
 * latest() and download() are those calls for this application.
 */
final class UpdateServer {

	use UpdateProgressTrait;

	/**
	 * The update server. Set by hand; point it at a mock server to test.
	 *
	 * @var string
	 */
	// public const HOST = 'https://apiv1.callismart.com.ng';
	public const HOST = 'https://smliser.local';

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
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{version: string, security: bool, source: string}
	 * @throws UpdateException When the server cannot be reached or does not answer as expected.
	 */
	public function latest( ?callable $progress = null ) : array {
		return $this->latest_of( self::APP_TYPE, self::APP_SLUG, $progress );
	}

	/**
	 * The latest version of a hosted app the update server publishes.
	 *
	 * @param string        $app_type Hosted app type on the update server.
	 * @param string        $app_slug Hosted app slug on the update server.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return array{version: string, security: bool, source: string}
	 * @throws UpdateException When the server cannot be reached, does not publish the app, or does not answer as expected.
	 */
	public function latest_of( string $app_type, string $app_slug, ?callable $progress = null ) : array {
		$url = self::HOST . '/smliser/v1/repository/' . rawurlencode( $app_type ) . '/' . rawurlencode( $app_slug );

		$this->report( $progress, 'check', self::STATUS_INFO, sprintf( 'Asking %s for the latest version.', $url ) );

		try {
			$response = $this->http->get(
				url: $url,
				headers: [
					'Accept'     => 'application/json',
					'User-Agent' => \smliser_http_ua( true )
				],
				options: ['timeout' => self::TIMEOUT ]
			);

		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( 'Could not reach the update server: %s', $e->getMessage() ), 0, $e );
		}

		if ( 404 === $response->status_code ) {
			throw new UpdateException(
				self::APP_TYPE === $app_type && self::APP_SLUG === $app_slug
					? 'The update server does not publish this application.'
					: sprintf( 'The update server does not publish "%s".', $app_slug )
			);
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( 'The update server answered with HTTP %d; try again later.', $response->status_code ) );
		}

		$app     = $response->json()[ $app_type ] ?? null;
		$version = is_array( $app ) ? ( $app['version'] ?? null ) : null;

		if ( ! is_string( $version ) || 1 !== preg_match( '/^\d+\.\d+\.\d+([.+-][0-9A-Za-z.+-]+)?$/', $version ) ) {
			throw new UpdateException( 'The update server did not report a valid version; try again later.' );
		}

		$security = true === ( $app['manifest']['security'] ?? false );

		$this->report( $progress, 'check', self::STATUS_OK, sprintf( 'The update server publishes %s %s%s.', $app_slug, $version, $security ? ' (security release)' : '' ) );

		return [
			'version'  => $version,
			'security' => $security,
			'source'   => $url,
		];
	}

	/**
	 * Download a version's package and verify it.
	 *
	 * @param string $version        Version to download.
	 * @param string $dir            Existing, writable directory to download into.
	 * @param bool          $trust_unsigned Accept a package without a valid signature.
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{zip: string, source: string, sha256: string, signed_by: ?string, warnings: string[]}
	 *         The verified zip, the URL it came from, its SHA-256, the key that signed it, and warnings.
	 * @throws UpdateException When a download or a check fails; nothing downloaded is kept.
	 */
	public function download( string $version, string $dir, bool $trust_unsigned = false, ?callable $progress = null ) : array {
		return $this->download_artifact( self::APP_TYPE, self::APP_SLUG, self::package_base( $version ), $dir, $trust_unsigned, $progress );
	}

	/**
	 * Download a hosted app's package artifacts and verify them.
	 *
	 * The app publishes <base>.zip, <base>.sha256 and (signed packages)
	 * <base>.sha256.sig as artifacts; see PackageVerifier.
	 *
	 * @param string        $app_type       Hosted app type on the update server.
	 * @param string        $app_slug       Hosted app slug on the update server.
	 * @param string        $base           Artifact base name, e.g. "smliser-theme-acme-1.0.0".
	 * @param string        $dir            Existing, writable directory to download into.
	 * @param bool          $trust_unsigned Accept a package without a valid signature.
	 * @param callable|null $progress       Progress callback, see UpdateProgressTrait.
	 * @return array{zip: string, source: string, sha256: string, signed_by: ?string, warnings: string[]}
	 * @throws UpdateException When a download or a check fails; nothing downloaded is kept.
	 */
	public function download_artifact( string $app_type, string $app_slug, string $base, string $dir, bool $trust_unsigned = false, ?callable $progress = null ) : array {
		$zip = rtrim( $dir, '/\\' ) . '/' . $base . '.zip';
		$url = $this->artifact_url( $app_type, $app_slug, $base . '.zip' );

		$checksums = $this->fetch( $app_type, $app_slug, $base . '.sha256', false, $progress );
		$signature = $this->fetch( $app_type, $app_slug, $base . '.sha256.sig', true, $progress );

		$this->report( $progress, 'download', self::STATUS_INFO, sprintf( 'Downloading %s.', $url ) );

		try {
			$response = $this->http->download(
				url: $url,
				destination: $zip,
				headers: [
					'User-Agent' => \smliser_http_ua( true )
				],
				options: [ 'timeout' => self::DOWNLOAD_TIMEOUT ]
			);
		} catch ( Throwable $e ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( 'The download of %s failed: %s', basename( $zip ), $e->getMessage() ), 0, $e );
		}

		if ( ! $response->is_success() ) {
			$this->discard( $zip );
			throw new UpdateException( sprintf( 'The download of %s failed with HTTP %d.', basename( $zip ), $response->status_code ) );
		}

		$this->report( $progress, 'download', self::STATUS_OK, sprintf( 'Downloaded %s (%s) to %s.', basename( $zip ), $this->size( $zip ), $zip ) );

		try {
			$verified = $this->verifier->verify( $zip, $checksums, $signature, $trust_unsigned, $progress );
		} catch ( UpdateException $e ) {
			$this->discard( $zip );
			throw $e;
		}

		return [ 'zip' => $zip, 'source' => $url ] + $verified;
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
	 * @param string        $app_type Hosted app type on the update server.
	 * @param string        $app_slug Hosted app slug on the update server.
	 * @param string        $name     Artifact file name.
	 * @param bool          $optional Return null instead of failing when it is not published.
	 * @param callable|null $progress Progress callback, see UpdateProgressTrait.
	 * @return string|null
	 * @throws UpdateException When it cannot be fetched.
	 */
	private function fetch( string $app_type, string $app_slug, string $name, bool $optional = false, ?callable $progress = null ) : ?string {
		$url = $this->artifact_url( $app_type, $app_slug, $name );

		$this->report( $progress, 'download', self::STATUS_INFO, sprintf( 'Fetching %s.', $url ) );

		try {
			$response = $this->http->get(
				url: $url,
				headers: [
					'User-Agent' => \smliser_http_ua( true )
				],
				options: [ 'timeout' => self::TIMEOUT ]
			);
		} catch ( Throwable $e ) {
			throw new UpdateException( sprintf( 'Could not download %s: %s', $name, $e->getMessage() ), 0, $e );
		}

		if ( $optional && 404 === $response->status_code ) {
			$this->report( $progress, 'download', self::STATUS_WARNING, sprintf( '%s is not published.', $name ) );
			return null;
		}

		if ( ! $response->is_success() ) {
			throw new UpdateException( sprintf( 'Could not download %s (HTTP %d).', $name, $response->status_code ) );
		}

		return $response->body;
	}

	/**
	 * Public URL of a hosted app's artifact.
	 *
	 * @param string $app_type Hosted app type on the update server.
	 * @param string $app_slug Hosted app slug on the update server.
	 * @param string $name     Artifact file name.
	 * @return string
	 */
	private function artifact_url( string $app_type, string $app_slug, string $name ) : string {
		return self::HOST . '/downloads/' . rawurlencode( $app_type ) . '/' . rawurlencode( $app_slug ) . '/artifacts/' . rawurlencode( $name );
	}

	/**
	 * A file's size for messages.
	 *
	 * @param string $path File path.
	 * @return string E.g. "4.2 MB".
	 */
	private function size( string $path ) : string {
		$bytes = (int) $this->fs->filesize( $path );

		return $bytes >= 1048576 ? sprintf( '%.1f MB', $bytes / 1048576 ) : sprintf( '%d KB', (int) ceil( $bytes / 1024 ) );
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