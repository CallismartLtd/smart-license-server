<?php
/**
 * The system management class file.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 */

namespace SmartLicenseServer\Admin\ActionHandlers;

use Callismart\Http\HttpClient;
use Callismart\Http\Exceptions\HttpTimeoutException;
use SmartLicenseServer\Cache\Adapters\RuntimeCacheAdapter;
use SmartLicenseServer\Cache\Cache;
use SmartLicenseServer\Contracts\AdminRequests\SystemSettingsHandlerInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;

/**
 * Handles systems administration actions and form requests.
 */
class SystemManagement implements SystemSettingsHandlerInterface {

	/**
	 * The update server this application receives updates from.
	 */
	const DEPENDENT_HOST = 'https://apiv1.callismart.com.ng';

	/**
	 * Per-probe HTTP timeout in seconds, kept well under the site health
	 * page's 30-second client-side timeout.
	 */
	const PROBE_TIMEOUT = 10;

	/**
	 * Cache key prefix for the cross-request persistence probe.
	 */
	const PROBE_KEY_PREFIX = 'smliser_health_probe_';

	/**
	 * Lifetime of a persistence probe in seconds — long enough for the
	 * client's follow-up verify request, short enough that an abandoned
	 * probe expires on its own.
	 */
	const PROBE_TTL = 120;

	/**
	 * Check IDs and labels. health.js uses the same values for the row it
	 * shows when a request fails outright, so keep the two in sync.
	 */
	private const REMOTE_CHECK_ID    = 'remote_service_connection';
	private const REMOTE_CHECK_LABEL = 'Update Server Connection';
	private const CACHE_CHECK_ID     = 'cache_persistence';
	private const CACHE_CHECK_LABEL  = 'Cache Persistence';

	public function __construct(
		protected HttpClient $http_client,
		protected Cache $cache
	) {}

	public function handle_database_migration_request( Request $request ) : Response {
		throw new \Exception( 'Not implemented', 1 );
	}

	/*
	|-----------------------
	| SITE HEALTH ENDPOINTS
	|-----------------------
	*/

	/**
	 * GET site-health-check/remote-service
	 *
	 * Response: { "checks": [ check ] }
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_remote_service_check( Request $request ) : Response {
		return $this->checks_response( [ $this->check_dependent_host() ] );
	}

	/**
	 * GET site-health-check/cache-seed
	 *
	 * First half of the cache persistence check: writes a probe value and
	 * returns its token. The client then calls cache-verify in a separate
	 * request — the only way to prove a value survives beyond the request
	 * that wrote it.
	 *
	 * Response: { "token": string, "checks": [] } when the probe was written,
	 * or { "token": null, "checks": [ check ] } when the adapter is unusable.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_cache_seed( Request $request ) : Response {
		$failure = $this->check_cache_usable();

		if ( null !== $failure ) {
			return $this->seed_response( null, $failure );
		}

		$adapter_id = $this->cache->get_id();
		$token      = \bin2hex( \random_bytes( 16 ) );

		try {
			$written = $this->cache->set( self::PROBE_KEY_PREFIX . $token, $token, self::PROBE_TTL );
		} catch ( \Throwable $e ) {
			return $this->seed_response(
				null,
				$this->cache_failure( \sprintf( 'The "%s" cache adapter could not store a value: %s', $adapter_id, $e->getMessage() ) )
			);
		}

		if ( ! $written ) {
			return $this->seed_response(
				null,
				$this->cache_failure( \sprintf( 'The "%s" cache adapter could not store a value.', $adapter_id ) )
			);
		}

		return $this->seed_response( $token );
	}

	/**
	 * GET site-health-check/cache-verify?token=…
	 *
	 * Second half of the cache persistence check: reads back the value
	 * written by cache-seed in an earlier request, then deletes it.
	 *
	 * Response: { "checks": [ check ] }, or HTTP 400 { "message": string }
	 * when the token is missing or malformed.
	 *
	 * @param Request $request
	 * @return Response
	 */
	public function handle_cache_verify( Request $request ) : Response {
		$token = (string) $request->get( 'token', '' );

		if ( 1 !== \preg_match( '/^[a-f0-9]{32}$/', $token ) ) {
			return Response::json(
				data: [ 'message' => 'Missing or invalid cache probe token.' ],
				status_code: 400
			);
		}

		$failure = $this->check_cache_usable();

		if ( null !== $failure ) {
			return $this->checks_response( [ $failure ] );
		}

		$adapter_id = $this->cache->get_id();
		$key        = self::PROBE_KEY_PREFIX . $token;

		try {
			$read = $this->cache->get( $key );
			$this->cache->delete( $key );
		} catch ( \Throwable $e ) {
			return $this->checks_response( [
				$this->cache_failure( \sprintf( 'The "%s" cache adapter could not read a value: %s', $adapter_id, $e->getMessage() ) ),
			] );
		}

		if ( $read !== $token ) {
			$message = RuntimeCacheAdapter::get_id() === $adapter_id
				? 'The active cache adapter is the in-memory runtime cache, so a value written in one request was gone in the next.'
				: \sprintf( 'A value written to the "%s" cache adapter in one request was gone in the next.', $adapter_id );

			return $this->checks_response( [
				$this->make_check(
					self::CACHE_CHECK_ID,
					self::CACHE_CHECK_LABEL,
					'critical',
					$message . ' Password resets, email OTPs and rate limiting cannot work reliably.',
					'Configure Redis, Memcached, APCu or SQLite as the cache adapter, and check that its backing service is running and not evicting keys.'
				),
			] );
		}

		return $this->checks_response( [
			$this->make_check(
				self::CACHE_CHECK_ID,
				self::CACHE_CHECK_LABEL,
				'pass',
				\sprintf(
					'The "%s" cache adapter kept a value between two separate requests, so password resets, email OTPs and rate limiting can rely on it.',
					$adapter_id
				)
			),
		] );
	}

	/*
	|--------------------
	| SITE HEALTH CHECKS
	|--------------------
	*/

	/**
	 * Check that the server can reach the update server.
	 *
	 * A timeout or transport failure is critical: automatic updates cannot
	 * work. A non-2xx response is only a warning — the connection works,
	 * so the problem is on the remote side.
	 *
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	private function check_dependent_host() : array {
		$adapter = ( new \ReflectionClass( $this->http_client->get_adapter() ) )->getShortName();
		$started = \hrtime( true );

		try {
			$response = $this->http_client->get( self::DEPENDENT_HOST, [], [ 'timeout' => self::PROBE_TIMEOUT ] );
		} catch ( HttpTimeoutException $e ) {
			return $this->make_check(
				self::REMOTE_CHECK_ID,
				self::REMOTE_CHECK_LABEL,
				'critical',
				\sprintf(
					'Request to the update server %s timed out after %d seconds (%s). Automatic updates will fail until the server can reach it.',
					self::DEPENDENT_HOST,
					self::PROBE_TIMEOUT,
					$adapter
				),
				'Check that the server firewall allows outbound HTTPS traffic and that DNS resolves the host.'
			);
		} catch ( \Throwable $e ) {
			return $this->make_check(
				self::REMOTE_CHECK_ID,
				self::REMOTE_CHECK_LABEL,
				'critical',
				\sprintf(
					'Could not reach the update server %s (%s), so automatic updates will fail: %s',
					self::DEPENDENT_HOST,
					$adapter,
					$e->getMessage()
				),
				'Check outbound HTTPS access, DNS resolution, and the server\'s CA certificate bundle.'
			);
		}

		$elapsed_ms = (int) \round( ( \hrtime( true ) - $started ) / 1e6 );

		if ( ! $response->is_success() ) {
			return $this->make_check(
				self::REMOTE_CHECK_ID,
				self::REMOTE_CHECK_LABEL,
				'warning',
				\sprintf(
					'The update server %s is reachable but did not return a success response (%d ms, %s). Update checks may fail until it recovers.',
					self::DEPENDENT_HOST,
					$elapsed_ms,
					$adapter
				),
				'The connection works; the update server may be down or rejecting requests. Retry later before changing server configuration.'
			);
		}

		return $this->make_check(
			self::REMOTE_CHECK_ID,
			self::REMOTE_CHECK_LABEL,
			'pass',
			\sprintf( 'Reached the update server %s in %d ms via %s.', self::DEPENDENT_HOST, $elapsed_ms, $adapter )
		);
	}

	/**
	 * Confirm the active cache adapter can run before probing it.
	 *
	 * Shared by both halves of the persistence check so each request
	 * reports an unusable adapter the same way.
	 *
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}|null
	 *         A failed check, or null when the adapter is usable.
	 */
	private function check_cache_usable() : ?array {
		$adapter_id = $this->cache->get_id();

		if ( ! $this->cache->is_supported() ) {
			return $this->cache_failure(
				\sprintf( 'The active "%s" cache adapter cannot run on this server.', $adapter_id ),
				'Enable the PHP extension this adapter requires, or select a different adapter.'
			);
		}

		if ( ! $this->cache->is_active() ) {
			return $this->cache_failure(
				\sprintf( 'The "%s" cache adapter is configured but not active.', $adapter_id ),
				'Check the adapter\'s connection settings (host, port, credentials) in the cache settings.'
			);
		}

		return null;
	}

	/*
	|---------
	| HELPERS
	|---------
	*/

	/**
	 * Build a critical cache persistence check.
	 *
	 * @param string      $message
	 * @param string|null $recommendation Defaults to checking the backing service.
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	private function cache_failure( string $message, ?string $recommendation = null ) : array {
		return $this->make_check(
			self::CACHE_CHECK_ID,
			self::CACHE_CHECK_LABEL,
			'critical',
			$message,
			$recommendation ?? 'Check the adapter\'s connection settings and that its backing service is running.'
		);
	}

	/**
	 * @param array<int, array> $checks
	 * @return Response
	 */
	private function checks_response( array $checks ) : Response {
		return Response::json( data: [ 'checks' => $checks ], status_code: 200 );
	}

	/**
	 * @param string|null $token   Probe token, or null when seeding failed.
	 * @param array|null  $failure The failed check, when seeding failed.
	 * @return Response
	 */
	private function seed_response( ?string $token, ?array $failure = null ) : Response {
		return Response::json(
			data: [
				'token'  => $token,
				'checks' => null === $failure ? [] : [ $failure ],
			],
			status_code: 200
		);
	}

	/**
	 * Build one check entry in the shape the site health page expects.
	 *
	 * @param string      $id             Unique check ID (also used in an element id).
	 * @param string      $label          Human-readable check name.
	 * @param string      $status         One of pass, info, warning, critical.
	 * @param string      $message        What was found.
	 * @param string|null $recommendation What to do about it, or null when passing.
	 * @return array{id: string, label: string, status: string, message: string, recommendation: ?string}
	 */
	private function make_check( string $id, string $label, string $status, string $message, ?string $recommendation = null ) : array {
		return [
			'id'             => $id,
			'label'          => $label,
			'status'         => $status,
			'message'        => $message,
			'recommendation' => $recommendation,
		];
	}
}