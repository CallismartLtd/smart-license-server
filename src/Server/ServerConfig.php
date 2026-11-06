<?php
/**
 * Server config class file.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Server
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Server;

/**
 * Where the development server listens, and the URL the application is reached at.
 *
 * The two are separate on purpose. Behind a tunnel or reverse proxy the public
 * URL has nothing to do with the address listened on, and the public URL is
 * never used to choose where to listen.
 */
final class ServerConfig {

	/**
	 * Constructor.
	 *
	 * @param string      $host       Address to listen on.
	 * @param int         $port       Port to listen on.
	 * @param string|null $public_url The URL the application is publicly reached at; null when it is the listening address.
	 */
	public function __construct(
		public readonly string $host,
		public readonly int $port,
		public readonly ?string $public_url = null
	) {}

	/**
	 * Host and port, with an IPv6 host in brackets.
	 *
	 * @return string
	 */
	public function authority() : string {
		return self::join( $this->host, $this->port );
	}

	/**
	 * The URL the server is reached at from this machine.
	 *
	 * A wildcard host (0.0.0.0, ::) is where the server listens, not an
	 * address a browser can open, so the URL uses localhost instead.
	 *
	 * @return string
	 */
	public function url() : string {
		$host = in_array( $this->host, [ '0.0.0.0', '::' ], true ) ? 'localhost' : $this->host;

		return 'http://' . self::join( $host, $this->port );
	}

	/**
	 * The URL the application is reached at: the public URL when there is one.
	 *
	 * @return string
	 */
	public function app_url() : string {
		return $this->public_url ?? $this->url();
	}

	/**
	 * The same address with another public URL.
	 *
	 * @param string|null $public_url The URL, or null for the listening address.
	 * @return self
	 */
	public function with_public_url( ?string $public_url ) : self {
		return new self( $this->host, $this->port, $public_url );
	}

	/**
	 * @return array{host: string, port: int, public_url: string|null}
	 */
	public function to_array() : array {
		return [
			'host'       => $this->host,
			'port'       => $this->port,
			'public_url' => $this->public_url,
		];
	}

	/**
	 * Rebuild a config from to_array() output.
	 *
	 * @param array<string,mixed> $data
	 * @return self|null Null when the data is not a usable config.
	 */
	public static function from_array( array $data ) : ?self {
		if ( ! is_string( $data['host'] ?? null ) || ! is_int( $data['port'] ?? null ) || $data['port'] < 1 || $data['port'] > 65535 ) {
			return null;
		}

		$public_url = $data['public_url'] ?? null;

		return new self( $data['host'], $data['port'], is_string( $public_url ) && '' !== $public_url ? $public_url : null );
	}

	/**
	 * @param string $host
	 * @param int    $port
	 * @return string
	 */
	private static function join( string $host, int $port ) : string {
		return str_contains( $host, ':' ) ? "[{$host}]:{$port}" : "{$host}:{$port}";
	}
}
