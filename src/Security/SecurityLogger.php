<?php
/**
 * Dedicated Security Logger for audit trails and security-related operations.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Security
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Security;

use SmartLicenseServer\FileSystem\FileSystem;

final class SecurityLogger {

	/**
	 * SecurityLogger constructor.
	 *
	 * @param FileSystem $fs Filesystem API.
	 */
	public function __construct(
		protected FileSystem $fs
	) {}

	/**
	 * Log a security or audit event to the dedicated security log.
	 *
	 * @param string               $level   Severity level (INFO, WARNING, CRITICAL).
	 * @param string               $event   Event identifier (e.g. login_failure, mfa_reset, unauthorized_access).
	 * @param string               $message Description of the security event.
	 * @param array<string, mixed> $context Additional structured context data.
	 * @return void
	 */
	public function log( string $level, string $event, string $message, array $context = [] ) : void {
		$timestamp   = gmdate( 'Y-m-d H:i:s' );
		$level       = strtoupper( trim( $level ) ?: 'INFO' );
		$event       = trim( $event ) ?: 'general_security_event';
		$context_str = ! empty( $context ) ? ' | Context: ' . json_encode( $context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) : '';

		$log_line = sprintf( '[%s] [%s] [%s] %s%s', $timestamp, $level, $event, $message, $context_str ) . PHP_EOL;

		if ( ! $this->fs->is_dir( SMLISER_LOGS_DIR ) ) {
			$this->fs->mkdir( SMLISER_LOGS_DIR );
		}

		$log_file = SMLISER_LOGS_DIR . 'security.log';
		$existing = $this->fs->exists( $log_file ) ? $this->fs->get_contents( $log_file ) : '';
		if ( false === $existing ) {
			$existing = '';
		}

		$this->fs->put_contents( $log_file, $existing . $log_line );
	}

	/**
	 * Log an informational security event.
	 *
	 * @param string               $event
	 * @param string               $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	public function info( string $event, string $message, array $context = [] ) : void {
		$this->log( 'INFO', $event, $message, $context );
	}

	/**
	 * Log a warning security event.
	 *
	 * @param string               $event
	 * @param string               $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	public function warning( string $event, string $message, array $context = [] ) : void {
		$this->log( 'WARNING', $event, $message, $context );
	}

	/**
	 * Log a critical security event.
	 *
	 * @param string               $event
	 * @param string               $message
	 * @param array<string, mixed> $context
	 * @return void
	 */
	public function critical( string $event, string $message, array $context = [] ) : void {
		$this->log( 'CRITICAL', $event, $message, $context );
	}
}
