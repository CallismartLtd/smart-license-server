<?php
/**
 * Pricing Tier Class file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Schema\TableName;
use Callismart\DBPrism\Database;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Exceptions\DatabaseException;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * A pricing tier is the template a license is issued from.
 *
 * It defines what a new license grants: its term, the number of sites it
 * can be activated on and its features. It also names the one provider
 * that sells it, with that provider's own data for it (a price, a product
 * reference; the provider decides).
 *
 * At issue, the tier's terms are copied onto the license (see
 * to_license_contract()), and the license is the contract from then on:
 * changing a tier never changes licenses already issued. A tier that
 * licenses were issued from cannot be deleted; it is archived, and an
 * archived tier issues no new licenses.
 */
class PricingTier extends DataStore {
	use SanitizeAwareTrait, DatePropertyAwareTrait;

	/*
	|-----------
	| TERM UNITS
	|-----------
	*/

	/** A license that never ends. */
	public const TERM_LIFETIME = 'lifetime';

	/** A term of N days. */
	public const TERM_DAY = 'day';

	/** A term of N calendar months. */
	public const TERM_MONTH = 'month';

	/** A term of N calendar years. */
	public const TERM_YEAR = 'year';

	/*
	|---------
	| STATUSES
	|---------
	*/

	/** The tier issues new licenses. */
	public const STATUS_ACTIVE = 'active';

	/** The tier issues no new licenses; licenses issued from it are unaffected. */
	public const STATUS_ARCHIVED = 'archived';

	/** The number of sites that means "unlimited". */
	public const UNLIMITED_SITES = -1;

	/**
	 * Unique identifier for the pricing tier.
	 *
	 * @var int
	 */
	protected int $id = 0;

	/**
	 * The monetization ID this tier belongs to.
	 *
	 * @var int $monetization_id
	 */
	protected int $monetization_id = 0;

	/**
	 * The display name of the pricing tier (e.g., "Single Site", "Pro", "Lifetime").
	 *
	 * @var string
	 */
	protected string $name = '';

	/**
	 * The unit of the license term; one of the TERM_* constants.
	 *
	 * @var string
	 */
	protected string $term_unit = self::TERM_LIFETIME;

	/**
	 * The number of term units; ignored for a lifetime term.
	 *
	 * @var int
	 */
	protected int $term_length = 1;

	/**
	 * The number of sites a license from this tier can be activated on;
	 * UNLIMITED_SITES for no limit.
	 *
	 * @var int
	 */
	protected int $max_sites = 1;

	/**
	 * Features a license from this tier includes.
	 *
	 * @var list<string>
	 */
	protected array $features = [];

	/**
	 * The ID of the provider that sells this tier; empty when no provider
	 * sells it (licenses are then only issued by an administrator).
	 *
	 * @var string
	 */
	protected string $provider_id = '';

	/**
	 * The provider's own data for this tier; its shape is the provider's.
	 *
	 * @var array<string, mixed>
	 */
	protected array $provider_data = [];

	/**
	 * The tier status; one of the STATUS_* constants.
	 *
	 * @var string
	 */
	protected string $status = self::STATUS_ACTIVE;

	/**
	 * Creation date
	 *
	 * @var DateTimeImmutable|null $created_at
	 */
	protected ?DateTimeImmutable $created_at = null;

	/**
	 * Last updated date
	 *
	 * @var DateTimeImmutable|null $updated_at
	 */
	protected ?DateTimeImmutable $updated_at = null;

	/**
	 * Constructor.
	 */
	public function __construct() {}

	/*
	|-----------
	| GETTERS
	|-----------
	*/

	/**
	 * The unique identifier for this pricing tier.
	 *
	 * @return int
	 */
	public function get_id() : int {
		return $this->id;
	}

	/**
	 * Get monetization ID this tier belongs to.
	 *
	 * @return int
	 */
	public function get_monetization_id() : int {
		return $this->monetization_id;
	}

	/**
	 * The name of this pricing tier.
	 *
	 * @return string
	 */
	public function get_name() : string {
		return $this->name;
	}

	/**
	 * The unit of the license term; one of the TERM_* constants.
	 *
	 * @return string
	 */
	public function get_term_unit() : string {
		return $this->term_unit;
	}

	/**
	 * The number of term units; always 0 for a lifetime term.
	 *
	 * @return int
	 */
	public function get_term_length() : int {
		return $this->is_lifetime() ? 0 : $this->term_length;
	}

	/**
	 * The maximum number of sites a license from this tier can be activated on.
	 *
	 * @return int UNLIMITED_SITES for no limit.
	 */
	public function get_max_sites() : int {
		return $this->max_sites;
	}

	/**
	 * Get the features included in this pricing tier.
	 *
	 * @return list<string>
	 */
	public function get_features() : array {
		return $this->features;
	}

	/**
	 * The ID of the provider that sells this tier.
	 *
	 * @return string Empty when no provider sells it.
	 */
	public function get_provider_id() : string {
		return $this->provider_id;
	}

	/**
	 * The provider's own data for this tier.
	 *
	 * @return array<string, mixed>
	 */
	public function get_provider_data() : array {
		return $this->provider_data;
	}

	/**
	 * The tier status; one of the STATUS_* constants.
	 *
	 * @return string
	 */
	public function get_status() : string {
		return $this->status;
	}

	/**
	 * Get the creation date.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_created_at() : ?DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Get last updated time.
	 *
	 * @return DateTimeImmutable|null
	 */
	public function get_updated_at() : ?DateTimeImmutable {
		return $this->updated_at;
	}

	/*
	|-----------
	| SETTERS
	|-----------
	*/

	/**
	 * Set ID
	 *
	 * @param int $id
	 * @return static
	 */
	public function set_id( $id ) : static {
		$this->id = static::sanitize_int( $id );
		return $this;
	}

	/**
	 * Set monetization ID this tier belongs to.
	 *
	 * @param int $monetization_id
	 * @return static
	 */
	public function set_monetization_id( $monetization_id ) : static {
		$this->monetization_id = static::sanitize_int( $monetization_id );
		return $this;
	}

	/**
	 * Set the name of this pricing tier.
	 *
	 * @param string $name The name of the pricing tier.
	 * @return static
	 */
	public function set_name( $name ) : static {
		$this->name = static::sanitize_text( $name );
		return $this;
	}

	/**
	 * Set the license term.
	 *
	 * @param string $unit   One of the TERM_* constants.
	 * @param int    $length The number of units, at least 1; ignored for a lifetime term.
	 * @return static
	 * @throws InvalidArgumentException When the unit is unknown or the length is below 1.
	 */
	public function set_term( string $unit, int $length = 1 ) : static {
		$unit = strtolower( trim( $unit ) );

		if ( ! in_array( $unit, static::get_term_units(), true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown term unit "%s". Use one of: %s.', $unit, implode( ', ', static::get_term_units() ) ) );
		}

		if ( static::TERM_LIFETIME !== $unit && $length < 1 ) {
			throw new InvalidArgumentException( 'The term length must be at least 1.' );
		}

		$this->term_unit   = $unit;
		$this->term_length = static::TERM_LIFETIME === $unit ? 1 : $length;
		return $this;
	}

	/**
	 * Set the maximum number of sites a license from this tier can be activated on.
	 *
	 * @param int $max_sites At least 1, or UNLIMITED_SITES.
	 * @return static
	 * @throws InvalidArgumentException When the number is 0 or below -1.
	 */
	public function set_max_sites( $max_sites ) : static {
		$max_sites = (int) $max_sites;

		if ( static::UNLIMITED_SITES !== $max_sites && $max_sites < 1 ) {
			throw new InvalidArgumentException( 'The number of sites must be at least 1, or -1 for unlimited.' );
		}

		$this->max_sites = $max_sites;
		return $this;
	}

	/**
	 * Set the features for this tier.
	 *
	 * @param array<mixed> $features Feature names; empty and duplicate names are dropped.
	 * @return static
	 */
	public function set_features( array $features ) : static {
		$this->features = static::normalize_features( $features );
		return $this;
	}

	/**
	 * Set the provider that sells this tier, with its data for the tier.
	 *
	 * @param string               $provider_id The provider ID; empty for none.
	 * @param array<string, mixed> $data        The provider's data for this tier.
	 * @return static
	 */
	public function set_provider( string $provider_id, array $data = [] ) : static {
		$this->provider_id   = static::sanitize_text( $provider_id );
		$this->provider_data = '' === $this->provider_id ? [] : $data;
		return $this;
	}

	/**
	 * Set the tier status.
	 *
	 * @param string $status One of the STATUS_* constants.
	 * @return static
	 * @throws InvalidArgumentException When the status is unknown.
	 */
	public function set_status( string $status ) : static {
		if ( ! in_array( $status, [ static::STATUS_ACTIVE, static::STATUS_ARCHIVED ], true ) ) {
			throw new InvalidArgumentException( sprintf( 'Unknown pricing tier status "%s".', $status ) );
		}

		$this->status = $status;
		return $this;
	}

	/**
	 * Set date created
	 *
	 * @param string|DateTimeImmutable|null $date
	 * @return static
	 */
	public function set_created_at( string|DateTimeImmutable|null $date ) : static {
		return $this->set_date_prop( $date, 'created_at' );
	}

	/**
	 * Set date updated
	 *
	 * @param string|DateTimeImmutable|null $date
	 * @return static
	 */
	public function set_updated_at( string|DateTimeImmutable|null $date ) : static {
		return $this->set_date_prop( $date, 'updated_at' );
	}

	/*
	|------------------
	| CRUD METHODS
	|------------------
	*/

	/**
	 * Save or update this pricing tier in the database.
	 *
	 * @return bool True on success.
	 * @throws DatabaseException When the database refuses the write.
	 */
	public function save() : bool {
		return static::$DB->transactional( function( Database $db ) {
			$now  = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );
			$data = [
				'monetization_id' => $this->monetization_id,
				'name'            => $this->name,
				'term_unit'       => $this->term_unit,
				'term_length'     => $this->term_length,
				'max_sites'       => $this->max_sites,
				'features'        => Format::encode( $this->features, Format::ENCODING_PHP ),
				'provider_id'     => '' === $this->provider_id ? null : $this->provider_id,
				'provider_data'   => '' === $this->provider_id ? null : Format::encode( $this->provider_data, Format::ENCODING_PHP ),
				'status'          => $this->status,
				'updated_at'      => $now->format( 'Y-m-d H:i:s' ),
			];

			$lock_sql = static::query()
				->select( 'id' )->from( TableName::PRICING_TIERS->table() )
				->where( 'id', '=', $this->get_id() )
				->limit( 1 )->lock_for_update();
			$id = (int) $db->get_var( $lock_sql->build(), $lock_sql->get_bindings() );

			if ( $id ) {
				$result = $db->update( TableName::PRICING_TIERS->table(), $data, [ 'id' => $id ] );
			} else {
				$data['created_at'] = $now->format( 'Y-m-d H:i:s' );
				$result             = $db->insert( TableName::PRICING_TIERS->table(), $data );
			}

			if ( false === $result ) {
				throw new DatabaseException( $id ? 'update_failed' : 'insert_failed', $db->get_last_error() );
			}

			if ( ! $id ) {
				$this->set_id( $result )->set_created_at( $now );
			}

			$this->set_updated_at( $now );
			static::cache_clear();

			return true;
		} );
	}

	/**
	 * Delete this pricing tier from the database.
	 *
	 * A tier that licenses were issued from is not deleted (see
	 * has_licenses()); archive it instead.
	 *
	 * @return bool True when deleted; false when it does not exist or has licenses.
	 * @throws DatabaseException When the database refuses the delete.
	 */
	public function delete() : bool {
		if ( ! $this->id || $this->has_licenses() ) {
			return false;
		}

		return (bool) static::$DB->transactional( function( Database $db ) {
			$result = $db->delete( TableName::PRICING_TIERS->table(), [ 'id' => $this->id ] );

			if ( false === $result ) {
				throw new DatabaseException( 'delete_failed', $db->get_last_error() );
			}

			static::cache_clear();
			return true;
		} );
	}

	/**
	 * Get a pricing tier by its ID.
	 *
	 * @param int $id The ID of the pricing tier.
	 * @return static|null The Pricing Tier object if found, null otherwise.
	 */
	public static function get_by_id( $id ) : ?static {
		$cache_key = static::make_cache_key( __METHOD__, [$id] );
		/** @var static|null|false $static */
		$static = static::cache_get( $cache_key );

		if ( false === $static ) {
			$data   = static::fetch_by( 'id', $id,  TableName::PRICING_TIERS->table() );
			$static = $data ? static::from_array( $data ) : null;

			static::cache_set( $cache_key, $static );
		}

		return $static;
	}

	/**
	 * Get all pricing tiers by monetization ID.
	 *
	 * @param int $monetization_id The monetization ID.
	 * @return static[] Array of Pricing Tier objects.
	 */
	public static function get_by_monetization_id( $monetization_id ) : array {
		$cache_key = static::make_cache_key( __METHOD__, [$monetization_id] );

		/** @var static[]|false */
		$result = static::cache_get( $cache_key );

		if ( false === $result ) {
			$sql = static::query()
				->select( '*' )->from( TableName::PRICING_TIERS->table() )
				->where( 'monetization_id', '=', $monetization_id )
				->order_by( 'id', 'ASC' );

			$result = [];

			foreach ( static::$DB->get_results( $sql->build(), $sql->get_bindings() ) as $row ) {
				$result[] = static::from_array( $row );
			}

			static::cache_set( $cache_key, $result );
		}

		return $result;
	}

	/**
	 * Get the pricing tiers a provider sells.
	 *
	 * @param string $provider_id The full provider ID, with its channel when it has one.
	 * @return static[] Ordered by ID.
	 */
	public static function get_by_provider( string $provider_id ) : array {
		if ( '' === $provider_id ) {
			return [];
		}

		$sql = static::query()
			->select( '*' )->from( TableName::PRICING_TIERS->table() )
			->where( 'provider_id', '=', $provider_id )
			->order_by( 'id', 'ASC' );

		return array_map( [ static::class, 'from_array' ], static::$DB->get_results( $sql->build(), $sql->get_bindings() ) );
	}

	/*
	|---------------------
	| LICENSE TEMPLATE
	|---------------------
	*/

	/**
	 * Whether this tier is a lifetime term.
	 *
	 * @return bool
	 */
	public function is_lifetime() : bool {
		return static::TERM_LIFETIME === $this->term_unit;
	}

	/**
	 * Whether this tier issues new licenses.
	 *
	 * @return bool
	 */
	public function is_active() : bool {
		return static::STATUS_ACTIVE === $this->status;
	}

	/**
	 * The term as people read it: "Lifetime", "1 year", "3 months".
	 *
	 * @return string
	 */
	public function get_term_label() : string {
		if ( $this->is_lifetime() ) {
			return 'Lifetime';
		}

		return sprintf( '%d %s%s', $this->term_length, $this->term_unit, 1 === $this->term_length ? '' : 's' );
	}

	/**
	 * Calculate the end of a term that starts at the given date.
	 *
	 * Months and years are calendar months and years. When the start day
	 * does not exist in the end month, the term ends on that month's last
	 * day: one month from 31 January is 28 (or 29) February.
	 *
	 * @param DateTimeImmutable $start The start of the term.
	 * @return DateTimeImmutable|null The end of the term; null for a lifetime term.
	 */
	public function calculate_end_date( DateTimeImmutable $start ) : ?DateTimeImmutable {
		if ( $this->is_lifetime() ) {
			return null;
		}

		if ( static::TERM_DAY === $this->term_unit ) {
			return $start->modify( sprintf( '+%d days', $this->term_length ) );
		}

		$months = static::TERM_YEAR === $this->term_unit ? $this->term_length * 12 : $this->term_length;
		$target = $start->modify( 'first day of this month' )->modify( sprintf( '+%d months', $months ) );
		$day    = min( (int) $start->format( 'j' ), (int) $target->format( 't' ) );

		return $target->setDate( (int) $target->format( 'Y' ), (int) $target->format( 'n' ), $day );
	}

	/**
	 * The terms a license issued from this tier starts with.
	 *
	 * @param DateTimeImmutable $start The start of the license.
	 * @return array{
	 *  max_allowed_domains: int,
	 *  start_date: DateTimeImmutable,
	 *  end_date: DateTimeImmutable|null,
	 *  features: list<string>
	 * }
	 */
	public function to_license_contract( DateTimeImmutable $start ) : array {
		return [
			'max_allowed_domains' => $this->max_sites,
			'start_date'          => $start,
			'end_date'            => $this->calculate_end_date( $start ),
			'features'            => $this->features,
		];
	}

	/**
	 * Whether any license was issued from this tier.
	 *
	 * @return bool
	 */
	public function has_licenses() : bool {
		if ( ! $this->id ) {
			return false;
		}

		$sql = static::query()->select( 'id' )->from( TableName::LICENSES->table() )
			->where( 'tier_id', '=', $this->id )
			->limit( 1 );

		return null !== static::$DB->get_var( $sql->build(), $sql->get_bindings() );
	}

	/*
	|---------------------
	| UTILITY METHODS
	|---------------------
	*/

	/**
	 * The term units, in the order they are offered.
	 *
	 * @return list<string>
	 */
	public static function get_term_units() : array {
		return [ static::TERM_LIFETIME, static::TERM_DAY, static::TERM_MONTH, static::TERM_YEAR ];
	}

	/**
	 * Normalize a list of feature names: trimmed text, no empty or
	 * duplicate names, in the given order.
	 *
	 * @param array<mixed> $features
	 * @return list<string>
	 */
	public static function normalize_features( array $features ) : array {
		$names = [];

		foreach ( $features as $feature ) {
			if ( ! is_scalar( $feature ) ) {
				continue;
			}

			$feature = static::sanitize_text( trim( (string) $feature ) );

			if ( '' !== $feature && ! in_array( $feature, $names, true ) ) {
				$names[] = $feature;
			}
		}

		return $names;
	}

	/**
	 * Hydrate from array
	 *
	 * @param array<string, mixed> $data
	 * @return static
	 */
	public static function from_array( array $data ) : static {
		$static = ( new static() )
			->set_id( $data['id'] ?? 0 )
			->set_monetization_id( $data['monetization_id'] ?? 0 )
			->set_name( $data['name'] ?? '' )
			->set_created_at( $data['created_at'] ?? null )
			->set_updated_at( $data['updated_at'] ?? null );

		// Stored values are trusted as written by save(); no validation here.
		$static->term_unit     = (string) ( $data['term_unit'] ?? static::TERM_LIFETIME );
		$static->term_length   = (int) ( $data['term_length'] ?? 1 );
		$static->max_sites     = (int) ( $data['max_sites'] ?? 1 );
		$static->status        = (string) ( $data['status'] ?? static::STATUS_ACTIVE );
		$features      = Format::decode( $data['features'] ?? null );
		$provider_data = Format::decode( $data['provider_data'] ?? null );

		$static->features      = is_array( $features ) ? static::normalize_features( $features ) : [];
		$static->provider_id   = (string) ( $data['provider_id'] ?? '' );
		$static->provider_data = '' !== $static->provider_id && is_array( $provider_data ) ? $provider_data : [];

		return $static;
	}

	/**
	 * Format pricing tier to array
	 *
	 * @return array<string, mixed>
	 */
	public function to_array() : array {
		return [
			'id'              => $this->get_id(),
			'monetization_id' => $this->get_monetization_id(),
			'name'            => $this->get_name(),
			'term_unit'       => $this->get_term_unit(),
			'term_length'     => $this->get_term_length(),
			'max_sites'       => $this->get_max_sites(),
			'features'        => $this->get_features(),
			'provider_id'     => $this->get_provider_id(),
			'provider_data'   => $this->get_provider_data(),
			'status'          => $this->get_status(),
			'created_at'      => $this->get_created_at()?->format( DateTimeImmutable::ATOM ),
			'updated_at'      => $this->get_updated_at()?->format( DateTimeImmutable::ATOM ),
		];
	}

	/**
	 * The tier as buyers see it: its terms and its provider's offer.
	 *
	 * The provider's tier data is not included; the provider decides what
	 * of it buyers see (MonetizationProviderInterface::get_offer()).
	 *
	 * @return array{
	 *  id: int,
	 *  name: string,
	 *  term_unit: string,
	 *  term_length: int,
	 *  term_label: string,
	 *  max_sites: int,
	 *  features: list<string>,
	 *  offer: array{price: string, checkout_url: string}
	 * }
	 */
	public function to_public_array() : array {
		$provider = '' === $this->provider_id ? null : MonetizationRegistry::instance()->get_provider( $this->provider_id );

		return [
			'id'          => $this->get_id(),
			'name'        => $this->get_name(),
			'term_unit'   => $this->get_term_unit(),
			'term_length' => $this->get_term_length(),
			'term_label'  => $this->get_term_label(),
			'max_sites'   => $this->get_max_sites(),
			'features'    => $this->get_features(),
			'offer'       => $provider?->get_offer( $this ) ?? [ 'price' => '', 'checkout_url' => '' ],
		];
	}

	/**
	 * Tells whether a tier exists.
	 *
	 * @return bool
	 */
	public function exists() : bool {
		return (bool) $this->get_id();
	}
}