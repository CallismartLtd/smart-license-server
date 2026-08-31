<?php
/**
 * Authentication attempt monitoring and brute-force detection.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security\Authentication;

use SmartLicenseServer\Cache\Cache;

/**
 * Monitors authentication activity and detects suspicious login behavior.
 *
 * This class does not authenticate credentials and does not itself impose
 * account bans. It records authentication events, evaluates behavioral
 * patterns, and returns the detected threats.
 *
 * Detection is based on multiple independent dimensions:
 *
 * - Per-account brute force.
 * - Per-source/IP brute force.
 * - Distributed attacks against a single account.
 * - Password spraying against multiple accounts.
 * - Credential stuffing patterns.
 * - High-velocity authentication failures.
 * - Successful authentication following suspicious activity.
 *
 * Cache keys intentionally contain hashed identifiers rather than raw
 * usernames, account identifiers, IP addresses, or other authentication
 * subjects.
 *
 * Event records are mutated via the cache adapter's atomic `modify()`
 * primitive rather than a manual get/set pair, so concurrent authentication
 * attempts against the same account/source/credential cannot clobber one
 * another's recorded events.
 */
final class AuthenticationMonitor {

	/**
	 * Cache key namespace.
	 */
	private const CACHE_PREFIX = 'smliser_auth_monitor';

	/**
	 * Default monitoring window in seconds.
	 */
	private const DEFAULT_WINDOW = 900; // 15 minutes.

	/**
	 * Maximum number of events retained in one cache record.
	 *
	 * This prevents an attacker from causing unbounded cache growth.
	 */
	private const MAX_EVENTS = 100;

	/**
	 * Window, in seconds, used by the high-velocity check.
	 */
	private const VELOCITY_WINDOW_SECONDS = 60;

	/**
	 * Detection thresholds.
	 */
	private const ACCOUNT_FAILURE_THRESHOLD  = 5;
	private const SOURCE_FAILURE_THRESHOLD   = 15;
	private const ACCOUNT_SOURCE_THRESHOLD   = 8;
	private const SOURCE_ACCOUNT_THRESHOLD   = 10;
	private const SOURCE_CREDENTIAL_THRESHOLD = 10;
	private const VELOCITY_THRESHOLD         = 8;

	/**
	 * Constructor.
	 *
	 * @param string $hash_key Secret used to hash identifiers stored in
	 *                              cache keys and records.
	 */
	public function __construct(
		protected Cache $cache,
		private string $hash_key = SMLISER_SECRET
	) {}

	/**
	 * Record a failed authentication attempt.
	 *
	 * @param string      $account     Account identifier supplied by the
	 *                                 authentication request.
	 * @param string      $source      Source identifier, normally an IP address.
	 * @param string|null $credential  Optional credential fingerprint.
	 * @param int|null    $timestamp   Event timestamp. Defaults to current time.
	 *
	 * @return array<string,mixed> Detection result.
	 */
	public function record_failure(
		string $account,
		string $source,
		?string $credential = null,
		?int $timestamp = null
	): array {

		$timestamp = $timestamp ?? time();

		$account_key    = $this->identifier( 'account', $account );
		$source_key     = $this->identifier( 'source', $source );
		$credential_key = null !== $credential
			? $this->identifier( 'credential', $credential )
			: null;

		$event = array(
			'timestamp'  => $timestamp,
			'account'    => $account_key,
			'source'     => $source_key,
			'credential' => $credential_key,
		);

		/*
		 * append_event() now returns the post-write, window-filtered event
		 * list for that key, so detect() can consume it directly instead of
		 * issuing a second get() right after the write.
		 */
		$account_events = $this->append_event(
			'account',
			$account_key,
			$event,
			$timestamp
		);

		$source_events = $this->append_event(
			'source',
			$source_key,
			$event,
			$timestamp
		);

		$credential_events = null;

		if ( null !== $credential_key ) {
			$credential_events = $this->append_event(
				'credential',
				$credential_key,
				$event,
				$timestamp
			);
		}

		$result = $this->detect(
			$account_events,
			$source_events,
			$credential_events
		);

		return array(
			'detected' => ! empty( $result['threats'] ),
			'threats'  => $result['threats'],
			'score'    => $result['score'],
			'severity' => $result['severity'],
			'window'   => self::DEFAULT_WINDOW,
		);
	}

	/**
	 * Record a successful authentication.
	 *
	 * A successful authentication does not erase historical failures.
	 * It records the event separately so that suspicious success patterns
	 * can be observed.
	 *
	 * @param string    $account   Account identifier.
	 * @param string    $source    Source identifier.
	 * @param int|null  $timestamp Event timestamp.
	 *
	 * @return array<string,mixed> Authentication activity information.
	 */
	public function record_success(
		string $account,
		string $source,
		?int $timestamp = null
	): array {

		$timestamp  = $timestamp ?? time();
		$account_key = $this->identifier( 'account', $account );
		$source_key  = $this->identifier( 'source', $source );

		$failures = $this->get_events(
			'account',
			$account_key,
			$timestamp
		);

		$recent_failures = count( $failures );

		$event = array(
			'timestamp' => $timestamp,
			'account'   => $account_key,
			'source'    => $source_key,
			'failures'  => $recent_failures,
		);

		$this->append_event(
			'success',
			$account_key,
			$event,
			$timestamp
		);

		return array(
			'suspicious'      => $recent_failures >= self::ACCOUNT_FAILURE_THRESHOLD,
			'recent_failures' => $recent_failures,
		);
	}

	/**
	 * Determine whether an account is currently under attack.
	 *
	 * @param string   $account
	 * @param int|null $timestamp
	 *
	 * @return bool
	 */
	public function is_account_under_attack(
		string $account,
		?int $timestamp = null
	): bool {

		$timestamp = $timestamp ?? time();

		$key = $this->identifier( 'account', $account );

		$events = $this->get_events(
			'account',
			$key,
			$timestamp
		);

		return count( $events ) >= self::ACCOUNT_FAILURE_THRESHOLD;
	}

	/**
	 * Determine whether a source is currently behaving suspiciously.
	 *
	 * @param string   $source
	 * @param int|null $timestamp
	 *
	 * @return bool
	 */
	public function is_source_suspicious(
		string $source,
		?int $timestamp = null
	): bool {

		$timestamp = $timestamp ?? time();

		$key = $this->identifier( 'source', $source );

		$events = $this->get_events(
			'source',
			$key,
			$timestamp
		);

		return count( $events ) >= self::SOURCE_FAILURE_THRESHOLD;
	}

	/**
	 * Detect brute-force and credential-abuse behavior.
	 *
	 * @param array<int,array<string,mixed>>      $account_events    Post-write
	 *                                                                account event list.
	 * @param array<int,array<string,mixed>>      $source_events     Post-write
	 *                                                                source event list.
	 * @param array<int,array<string,mixed>>|null $credential_events Post-write
	 *                                                                credential event list, or
	 *                                                                null when no credential
	 *                                                                fingerprint was supplied.
	 *
	 * @return array{
	 *     threats: string[],
	 *     score: int,
	 *     severity: string
	 * }
	 */
	private function detect(
		array $account_events,
		array $source_events,
		?array $credential_events
	): array {

		$threats = array();
		$score   = 0;

		/*
		 * 1. Traditional account brute force.
		 *
		 * Many failed credentials against one account.
		 */
		if ( count( $account_events ) >= self::ACCOUNT_FAILURE_THRESHOLD ) {

			$threats[] = 'account_bruteforce';
			$score    += 3;
		}

		/*
		 * 2. Source/IP brute force.
		 *
		 * Many failed authentication attempts originating from one source.
		 */
		if ( count( $source_events ) >= self::SOURCE_FAILURE_THRESHOLD ) {

			$threats[] = 'source_bruteforce';
			$score    += 3;
		}

		/*
		 * 3. Distributed attack against one account.
		 *
		 * Many different sources targeting the same account.
		 */
		$account_sources = $this->unique_event_values(
			$account_events,
			'source'
		);

		if ( count( $account_sources ) >= self::ACCOUNT_SOURCE_THRESHOLD ) {

			$threats[] = 'distributed_account_attack';
			$score    += 4;
		}

		/*
		 * 4. Password spraying.
		 *
		 * One source attempting many different accounts.
		 */
		$source_accounts = $this->unique_event_values(
			$source_events,
			'account'
		);

		if ( count( $source_accounts ) >= self::SOURCE_ACCOUNT_THRESHOLD ) {

			$threats[] = 'password_spraying';
			$score    += 4;
		}

		/*
		 * 5. Credential stuffing.
		 *
		 * The same credential/fingerprint being tried against many accounts.
		 */
		if ( null !== $credential_events ) {

			$credential_accounts = $this->unique_event_values(
				$credential_events,
				'account'
			);

			if (
				count( $credential_accounts )
				>= self::SOURCE_CREDENTIAL_THRESHOLD
			) {

				$threats[] = 'credential_stuffing';
				$score    += 5;
			}
		}

		/*
		 * 6. High velocity.
		 *
		 * Detect a burst of failures concentrated into a very short period.
		 */
		if ( $this->is_high_velocity( $source_events ) ) {

			$threats[] = 'high_velocity';
			$score    += 2;
		}

		$severity = match ( true ) {
			$score >= 9 => 'critical',
			$score >= 6 => 'high',
			$score >= 3 => 'medium',
			default     => 'low',
		};

		return array(
			'threats'  => array_values( array_unique( $threats ) ),
			'score'    => $score,
			'severity' => $severity,
		);
	}

	/**
	 * Determine whether authentication failures are arriving at a high rate.
	 *
	 * @param array<int,array<string,mixed>> $events
	 *
	 * @return bool
	 */
	private function is_high_velocity( array $events ): bool {

		$recent   = 0;
		$now      = time();

		foreach ( $events as $event ) {

			$event_time = (int) ( $event['timestamp'] ?? 0 );

			if ( $now - $event_time <= self::VELOCITY_WINDOW_SECONDS ) {
				$recent++;
			}
		}

		return $recent >= self::VELOCITY_THRESHOLD;
	}

	/**
	 * Atomically append an authentication event to a monitoring record.
	 *
	 * Uses the cache adapter's `modify()` primitive so that concurrent
	 * failures against the same account/source/credential (the exact
	 * condition a real attack produces) cannot race each other's read of
	 * the event list — each append is applied against the value the
	 * adapter guarantees is current at write time, not a value this class
	 * read moments earlier.
	 *
	 * @param string              $type
	 * @param string              $identifier
	 * @param array<string,mixed> $event
	 * @param int                 $timestamp
	 *
	 * @return array<int,array<string,mixed>> The window-filtered event list
	 *                                        immediately after this event
	 *                                        was appended.
	 */
	private function append_event(
		string $type,
		string $identifier,
		array $event,
		int $timestamp
	): array {

		$key = $this->cache_key( $type, $identifier );

		$events = $this->cache->modify(
			$key,
			static function ( $current ) use ( $event, $timestamp ): array {

				$events = is_array( $current ) ? $current : array();

				$events[] = $event;

				/*
				 * Remove events outside the monitoring window.
				 */
				$events = array_values(
					array_filter(
						$events,
						static function ( $item ) use ( $timestamp ): bool {

							return is_array( $item )
								&& isset( $item['timestamp'] )
								&& ( $timestamp - (int) $item['timestamp'] )
									<= self::DEFAULT_WINDOW;
						}
					)
				);

				/*
				 * Hard upper bound protects the cache from abuse.
				 */
				if ( count( $events ) > self::MAX_EVENTS ) {

					$events = array_slice(
						$events,
						-self::MAX_EVENTS
					);
				}

				return $events;
			},
			self::DEFAULT_WINDOW,
			array()
		);

		if ( ! is_array( $events ) ) {

			/*
			 * modify() failed at the adapter level (lock timeout, backend
			 * unreachable, etc.) rather than the callback aborting — the
			 * callback above always returns an array, never false.
			 *
			 * Falling back to an empty list would silently undercount an
			 * attack in progress, which is the wrong failure mode for a
			 * security monitor. Fall back to a single-event list instead,
			 * so this attempt is still visible to detect() even though
			 * prior history for this key is temporarily unavailable.
			 */
			$events = array( $event );
		}

		return $events;
	}

	/**
	 * Retrieve recent events.
	 *
	 * @param string $type
	 * @param string $identifier
	 * @param int    $timestamp
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function get_events(
		string $type,
		string $identifier,
		int $timestamp
	): array {

		$key    = $this->cache_key( $type, $identifier );
		$events = $this->cache->get( $key );

		if ( ! is_array( $events ) ) {
			return array();
		}

		$events = array_values(
			array_filter(
				$events,
				static function ( $event ) use ( $timestamp ): bool {

					return is_array( $event )
						&& isset( $event['timestamp'] )
						&& ( $timestamp - (int) $event['timestamp'] )
							<= self::DEFAULT_WINDOW;
				}
			)
		);

		return $events;
	}

	/**
	 * Retrieve unique values from authentication events.
	 *
	 * @param array<int,array<string,mixed>> $events
	 * @param string                         $field
	 *
	 * @return array<int,string>
	 */
	private function unique_event_values(
		array $events,
		string $field
	): array {

		$values = array();

		foreach ( $events as $event ) {

			if (
				isset( $event[ $field ] )
				&& is_string( $event[ $field ] )
			) {
				$values[] = $event[ $field ];
			}
		}

		return array_values( array_unique( $values ) );
	}

	/**
	 * Generate a stable, non-reversible identifier.
	 *
	 * @param string $type
	 * @param string $value
	 *
	 * @return string
	 */
	private function identifier(
		string $type,
		string $value
	): string {

		return hash_hmac(
			'sha256',
			$type . ':' . $value,
			$this->hash_key
		);
	}

	/**
	 * Build a cache key.
	 *
	 * @param string $type
	 * @param string $identifier
	 *
	 * @return string
	 */
	private function cache_key(
		string $type,
		string $identifier
	): string {

		return sprintf(
			'%s:%s:%s',
			self::CACHE_PREFIX,
			$type,
			$identifier
		);
	}
}