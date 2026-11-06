<?php
/**
 * Server info class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Server
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Server;

/**
 * A development server that was started in the background: how it was
 * configured, which process it is, and where it writes.
 *
 * This is the record kept in the state file, so it holds only what is needed
 * to find, describe and restart the server.
 */
final class ServerInfo {

	/**
	 * Constructor.
	 *
	 * @param ServerConfig $config     How it was configured.
	 * @param int          $pid        Process ID of the server.
	 * @param string       $started_at When it started, UTC, 'Y-m-d H:i:s'.
	 * @param string       $log        Absolute path of the file it writes to.
	 */
	public function __construct(
		public readonly ServerConfig $config,
		public readonly int $pid,
		public readonly string $started_at,
		public readonly string $log
	) {}

	/**
	 * @return array<string,mixed>
	 */
	public function to_array() : array {
		return [
			'pid'        => $this->pid,
			'started_at' => $this->started_at,
			'log'        => $this->log,
		] + $this->config->to_array();
	}

	/**
	 * Rebuild a record from to_array() output.
	 *
	 * @param mixed $data Decoded state file.
	 * @return self|null Null when the data is not a usable record.
	 */
	public static function from_array( mixed $data ) : ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}

		$config = ServerConfig::from_array( $data );

		if ( null === $config || ! is_int( $data['pid'] ?? null ) || $data['pid'] < 1
			|| ! is_string( $data['started_at'] ?? null ) || ! is_string( $data['log'] ?? null ) ) {
			return null;
		}

		return new self( $config, $data['pid'], $data['started_at'], $data['log'] );
	}
}
