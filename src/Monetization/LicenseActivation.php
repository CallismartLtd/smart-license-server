<?php
/**
 * License Activation class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use DateTimeImmutable;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * One domain a license is activated on.
 *
 * Activations are created and removed through the license
 * (License::activate_domain(), License::remove_activated_domain()), which
 * enforces the license's domain limit; this class reads them.
 */
class LicenseActivation extends DataStore {
	use SanitizeAwareTrait, DatePropertyAwareTrait;

	/**
	 * The activation ID.
	 *
	 * @var int
	 */
	protected int $id = 0;

	/**
	 * The license ID.
	 *
	 * @var int
	 */
	protected int $license_id = 0;

	/**
	 * The activated host, e.g. "example.com".
	 *
	 * @var string
	 */
	protected string $host = '';

	/**
	 * The origin the license was activated from, e.g. "https://example.com".
	 *
	 * @var string
	 */
	protected string $origin = '';

	/**
	 * The HMAC of the site secret handed to the site at activation.
	 *
	 * @var string
	 */
	protected string $secret_hash = '';

	/**
	 * When the domain was activated.
	 *
	 * @var DateTimeImmutable|null
	 */
	protected ?DateTimeImmutable $activated_at = null;

	/*
	|---------
	| GETTERS
	|---------
	*/

	/**
	 * @return int
	 */
	public function get_id() : int {
		return $this->id;
	}

	/**
	 * @return int
	 */
	public function get_license_id() : int {
		return $this->license_id;
	}

	/**
	 * @return string
	 */
	public function get_host() : string {
		return $this->host;
	}

	/**
	 * @return string
	 */
	public function get_origin() : string {
		return $this->origin;
	}

	/**
	 * @return string
	 */
	public function get_secret_hash() : string {
		return $this->secret_hash;
	}

	/**
	 * @return DateTimeImmutable|null
	 */
	public function get_activated_at() : ?DateTimeImmutable {
		return $this->activated_at;
	}

	/*
	|---------
	| READERS
	|---------
	*/

	/**
	 * Get the activations of a license, keyed by host, oldest first.
	 *
	 * @param int $license_id
	 * @return array<string, static>
	 */
	public static function get_for_license( int $license_id ) : array {
		$sql = static::query()->select( '*' )->from( TableName::LICENSE_ACTIVATIONS->table() )
			->where( 'license_id', '=', $license_id )
			->order_by( 'id', 'ASC' );

		$activations = [];

		foreach ( static::$DB->get_results( $sql->build(), $sql->get_bindings() ) as $row ) {
			$activation = static::from_array( $row );

			$activations[ $activation->get_host() ] = $activation;
		}

		return $activations;
	}

	/**
	 * Count the activations of a license.
	 *
	 * @param int $license_id
	 * @return int
	 */
	public static function count_for_license( int $license_id ) : int {
		$sql = static::query()->select( 'COUNT(*)' )->from( TableName::LICENSE_ACTIVATIONS->table() )
			->where( 'license_id', '=', $license_id );

		return (int) static::$DB->get_var( $sql->build(), $sql->get_bindings() );
	}

	/**
	 * Hydrate from a database row.
	 *
	 * @param array<string, mixed> $data
	 * @return static
	 */
	public static function from_array( array $data ) : static {
		$static = new static();

		$static->id          = (int) ( $data['id'] ?? 0 );
		$static->license_id  = (int) ( $data['license_id'] ?? 0 );
		$static->host        = (string) ( $data['host'] ?? '' );
		$static->origin      = (string) ( $data['origin'] ?? '' );
		$static->secret_hash = (string) ( $data['secret_hash'] ?? '' );
		$static->set_date_prop( $data['activated_at'] ?? null, 'activated_at' );

		return $static;
	}

	/**
	 * The activation as the array the license API has always returned for a domain.
	 *
	 * @return array{origin: string, secret: string, activated_at: string|null}
	 */
	public function to_array() : array {
		return [
			'origin'       => $this->origin,
			'secret'       => $this->secret_hash,
			'activated_at' => $this->activated_at?->format( DateTimeImmutable::ATOM ),
		];
	}
}
