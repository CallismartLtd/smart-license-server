<?php
/**
 * The license class file
 * 
 * @author Callistus <admin@callismart.com.ng>
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Schema\TableName;
use SmartLicenseServer\Security\Actors\User;
use Callismart\DBPrism\Database;
use DateTimeImmutable;
use DateTimeZone;
use SmartLicenseServer\Core\DataStore;
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\HostedApps\HostedApplicationService;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\Utils\DatePropertyAwareTrait;
use SmartLicenseServer\Utils\Format;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

/**
 * The license class represents a licensing model for hosted applications in the repository.
 * 
 * @author Callistus Nwachukwu <admin@callismart.com.ng>
 */
class License extends DataStore {
	use SanitizeAwareTrait, DatePropertyAwareTrait;
	/**
	 * The license ID
	 * 
	 * @var int $id
	 */
	protected int $id = 0;

	/**
	 * The full name of the licensee.
	 * 
	 * @var string $licensee_fullname
	 */
	protected string $licensee_fullname = '';

	/**
	 * The licensee's email address.
	 *
	 * The license is delivered to it, and it is the only value used to link
	 * a registered user to the license.
	 *
	 * @var string $licensee_email
	 */
	protected string $licensee_email = '';

	/**
	 * The ID of the registered user the license is linked to; 0 when the
	 * licensee is anonymous.
	 *
	 * @var int $user_id
	 */
	protected int $user_id = 0;

	/**
	 * The license key
	 * 
	 * @var string $license_key
	 */
	protected string $license_key = '';

	/**
	 * The ID of the service associated with the license.
	 * This property will be required for license activation.
	 * 
	 * @var string $service_id
	 */
	protected string $service_id = '';

	/**
	 * The type and slug of the hosted application this license is issued to.
	 * 
	 * @var array $app_prop
	 */
	protected array $app_prop = [
		'type' => '',
		'slug' => ''
	];

	/**
	 * The ID of the pricing tier the license was issued from; 0 when it was
	 * not issued from a tier. Informational only: the license holds its own
	 * terms.
	 *
	 * @var int $tier_id
	 */
	protected int $tier_id = 0;

	/**
	 * The ID of the provider that sold the license; empty when none did.
	 *
	 * @var string $provider_id
	 */
	protected string $provider_id = '';

	/**
	 * The provider's reference for the sale (an order, a subscription, a
	 * purchase request); empty when none. A provider and reference identify
	 * one license.
	 *
	 * @var string $external_ref
	 */
	protected string $external_ref = '';

	/**
	 * Features the license includes.
	 *
	 * @var list<string> $features
	 */
	protected array $features = [];

	/**
	 * The domains the license is activated on, keyed by host; loaded on first use.
	 *
	 * @var array<string, LicenseActivation>|null $activations
	 */
	protected ?array $activations = null;

	/**
	 * The instance of the hosted aplication this license is issued to.
	 * 
	 * @var AbstractHostedApp
	 */
	protected ?AbstractHostedApp $app;

	/**
	 * The status of the license.
	 * 
	 * @var string $status
	 */
	protected string $status = '';

	/**
	 * The license commencement date.
	 * 
	 * @var DateTimeImmutable $start_date
	 */
	protected ?DateTimeImmutable $start_date = null;

	/**
	 * The end date of the license.
	 * 
	 * @var DateTimeImmutable  $end_date
	 */
	protected ?DateTimeImmutable $end_date = null;

	/**
	 * Creation date.
	 * 
	 * @var DateTimeImmutable $created_at
	 */
	protected ?DateTimeImmutable $created_at = null;

	/**
	 * Update date.
	 * 
	 * @var DateTimeImmutable $updated_at
	 */
	protected ?DateTimeImmutable $updated_at = null;

	/**
	 * Number of allowed domains that can request license activation.
	 * 
	 * @var int $max_allowed_domains
	 */
	protected int $max_allowed_domains = 0;

	/**
	 * The license meta data
	 * 
	 * @var array $meta_data
	 */
	protected array $meta_data = [];

	public const STATUS_ACTIVE      = 'active';
	public const STATUS_EXPIRED     = 'expired';
	public const STATUS_LIFETIME    = 'lifetime';
	public const STATUS_INACTIVE    = 'inactive';
	public const STATUS_PENDING     = 'pending';
	public const STATUS_SUSPENDED   = 'suspended';
	public const STATUS_REVOKED     = 'revoked';
	public const STATUS_DEACTIVATED = 'deactivated';


	/**
	 * Class constructor
	 */
	public function __construct() {}

	/**
	|-------------
	| SETTERS
	|-------------
	 */

	/**
	 * Set the license ID.
	 * 
	 * @param int $id
	 * @return static
	 */
	public function set_id( $id ) : static {
		$this->id = static::sanitize_int( $id );

		return $this;
	}

	/**
	 * Set licensee full name.
	 * 
	 * @param string $name
	 * @return static
	 */
	public function set_licensee_fullname( $name ) : static {
		$this->licensee_fullname = static::sanitize_text( $name );

		return $this;
	}

	/**
	 * Set the licensee's email address.
	 *
	 * @param string $email A valid email address, or empty.
	 * @return static
	 * @throws \InvalidArgumentException When the value is not empty and not a valid email address.
	 */
	public function set_licensee_email( $email ) : static {
		$email = strtolower( trim( (string) $email ) );

		if ( '' !== $email && false === filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			throw new \InvalidArgumentException( sprintf( '"%s" is not a valid email address.', $email ) );
		}

		$this->licensee_email = $email;

		return $this;
	}

	/**
	 * Set the ID of the registered user the license is linked to.
	 *
	 * @param int $user_id 0 for none.
	 * @return static
	 */
	public function set_user_id( $user_id ) : static {
		$this->user_id = max( 0, (int) $user_id );

		return $this;
	}

	/**
	 * Set the pricing tier the license was issued from.
	 *
	 * @param int $tier_id 0 for none.
	 * @return static
	 */
	public function set_tier_id( $tier_id ) : static {
		$this->tier_id = max( 0, (int) $tier_id );

		return $this;
	}

	/**
	 * Set where the license came from: the provider and its reference for the sale.
	 *
	 * @param string $provider_id  The provider ID; empty for none.
	 * @param string $external_ref The provider's reference; empty for none.
	 * @return static
	 */
	public function set_source( string $provider_id, string $external_ref ) : static {
		$this->provider_id  = static::sanitize_text( $provider_id );
		$this->external_ref = static::sanitize_text( $external_ref );

		return $this;
	}

	/**
	 * Set the features the license includes.
	 *
	 * @param array<mixed> $features Feature names; empty and duplicate names are dropped.
	 * @return static
	 */
	public function set_features( array $features ) : static {
		$this->features = PricingTier::normalize_features( $features );

		return $this;
	}

	/**
	 * Set the license key
	 *
	 * @param string $value
	 * @return static
	 */
	public function set_license_key( $value ) : static {
		$this->license_key = static::sanitize_text( $value );

		return $this;
	}

	/**
	 * Set the service ID for this license
	 * 
	 * @param string $service_id The service ID for this license.
	 * @return static
	 */
	public function set_service_id( $service_id ) : static {
		$this->service_id = static::sanitize_text( $service_id );

		return $this;
	}

	/**
	 * Set the properties of the app associated with this license.
	 * 
	 * @param string $app_type  The app type
	 * @param string $app_slug  The app slug.
	 * @return static
	 */
	public function set_app_prop( string $app_type, string $app_slug ) : static {
		$this->app_prop['type'] = static::sanitize_text( $app_type );
		$this->app_prop['slug'] = static::sanitize_text( $app_slug );

		return $this;
	}

	/**
	 * Set the app this license is issued to.
	 * 
	 * @param AbstractHostedApp|null $app
	 * @return static
	 */
	public function set_app( ?AbstractHostedApp $app ) : static {
		$this->app = $app;

		if ( $app instanceof AbstractHostedApp ) {
			$this->set_app_prop( $app->get_type(), $app->get_slug() );
		}

		return $this;
	}

	/**
	 * Set license status.
	 * 
	 * @param string $status
	 * @return static
	 */
	public function set_status( $status ) : static {
		$this->status = $status;

		return $this;
	}

	/**
	 * Set the license start date
	 * 
	 * @param string|DateTimeImmutable $start_date
	 * @return static
	 */
	public function set_start_date( $start_date ) : static {
		return $this->set_date_prop( $start_date, 'start_date' );
	}

	/**
	 * Set the license end date
	 * 
	 * @param string|DateTimeImmutable|null $end_date Null or an empty string for no end (lifetime).
	 * @return static
	 */
	public function set_end_date( $end_date ) : static {
		if ( null === $end_date || '' === $end_date ) {
			$this->end_date = null;
			return $this;
		}

		return $this->set_date_prop( $end_date, 'end_date' );
	}

	/**
	 * Set the maximum allowed domains that can request license activation.
	 * 
	 * @param int $number_of_domains
	 * @return static
	 */
	public function set_max_allowed_domains( $number_of_domains ) : static {
		$this->max_allowed_domains = max( -1, intval( $number_of_domains ) );

		return $this;
	}

	/**
	 * Set the value of the given meta data name
	 * 
	 * @param string $meta_name The name of the meta data to set.
	 * @param mixed $meta_value The value of the meta data.
	 * @return static
	 */
	public function set_meta( $meta_name, $meta_value ) : static {
		$this->meta_data[static::sanitize_key( $meta_name )] = static::sanitize_auto( $meta_value );

		return $this;
	}

	/**
	 * Set the entire meta data of this license
	 * 
	 * @param array $meta_data
	 * @return static
	 */
	public function set_meta_data( array $meta_data ) : static {

		foreach( $meta_data as $name => $value ) {
			if ( \is_numeric( $name ) ) {
				continue;
			}

			$this->set_meta( $name, $value );
		}

		return $this;
	}

	/**
	 * Set date created
	 * 
	 * @param mixed $date
	 */
	public function set_created_at( mixed $date ) : static {
		return $this->set_date_prop( $date, 'created_at' );
	}

	/**
	 * Set date updated
	 * 
	 * @param string|DateTimeImmutable $date
	 */
	public function set_updated_at( mixed $date ) : static {
		return $this->set_date_prop( $date, 'updated_at' );
	}

	/**
	|-------------
	| GETTERS
	|-------------
	 */

	/**
	 * Get the license ID.
	 * 
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Get the licensee's name.
	 *
	 * A license linked to a registered user shows that user's display name,
	 * so the name follows the account. An anonymous license keeps the name
	 * it was issued with.
	 *
	 * @return string
	 */
	public function get_licensee_fullname() {
		if ( $this->user_id > 0 ) {
			$name = (string) User::get_by_id( $this->user_id )?->get_display_name();

			if ( '' !== $name ) {
				return $name;
			}
		}

		return $this->licensee_fullname;
	}

	/**
	 * Get the license key
	 *
	 * @return string
	 */
	public function get_license_key() {
		return $this->license_key;
	}

	/**
	 * Get the service ID for this license
	 * 
	 * @return string
	 */
	public function get_service_id() {
		return $this->service_id;
	}

	/**
	 * Get the licensee's email address.
	 *
	 * @return string
	 */
	public function get_licensee_email() : string {
		return $this->licensee_email;
	}

	/**
	 * Get the ID of the registered user the license is linked to.
	 *
	 * @return int 0 when the licensee is anonymous.
	 */
	public function get_user_id() : int {
		return $this->user_id;
	}

	/**
	 * Get the ID of the pricing tier the license was issued from.
	 *
	 * @return int 0 when it was not issued from a tier.
	 */
	public function get_tier_id() : int {
		return $this->tier_id;
	}

	/**
	 * Get the ID of the provider that sold the license.
	 *
	 * @return string Empty when none did.
	 */
	public function get_provider_id() : string {
		return $this->provider_id;
	}

	/**
	 * Get the provider's reference for the sale.
	 *
	 * @return string Empty when none.
	 */
	public function get_external_ref() : string {
		return $this->external_ref;
	}

	/**
	 * Get the features the license includes.
	 *
	 * @return list<string>
	 */
	public function get_features() : array {
		return $this->features;
	}

	/**
	 * Get the properties of the app associated with this license.
	 * 
	 * @param string $context The context to retrieve the app prop.
	 * @return array|string
	 */
	public function get_app_prop( string $context = 'view' ) : array|string {
		if ( 'view' === $context ) {
			$prop      = array_filter( $this->app_prop );
			$formatted = implode( '/', $prop );
			return $formatted;
		}

		return $this->app_prop;
	}

	/**
	 * Get the app this license is issued to.
	 * 
	 * Intentionally lazy loaded because the app instance is not always required
	 * when working with licenses. This is to optimize performance by avoiding unnecessary 
	 * loading of app instances.
	 * 
	 * @return AbstractHostedApp|null $app
	 */
	public function get_app() : ?AbstractHostedApp {

		if ( ! isset( $this->app ) ) {
			if ( 2 !== count( $this->app_prop ) ) {
				return null;
			}

			$app_type = $this->app_prop['type'] ?? '';
			$app_slug = $this->app_prop['slug'] ?? '';

			$app = HostedApplicationService::get_app_by_slug( $app_type, $app_slug );

			$this->set_app( $app );
		}

		return $this->app;
	}

	/**
	 * Get license status.
	 *
	 * When $context is 'edit' the stored property value is returned as-is.
	 * When $context is 'view' (default) – if the stored status is empty the
	 * method derives the status from start/end dates. If the stored status
	 * is present it will be returned (normalized).
	 *
	 * @param string $context Context for the status: 'view' or 'edit'. Default 'view'.
	 * @return string
	 */
	public function get_status( string $context = 'view' ) : string {
		// When editing we want the raw stored value (no derivation).
		if ( 'edit' === $context ) {
			return (string) $this->status;
		}

		// Normalize stored status if present.
		$stored = trim( (string) $this->status );
		if ( '' !== $stored ) {
			return strtolower( $stored );
		}

		// Derive status from dates because stored status is empty.
		$start = $this->get_start_date();
		$end   = $this->get_end_date();

		// Lifetime when there is no end date.
		if ( empty( $end ) ) {
			return static::STATUS_LIFETIME;
		}

		$now      = \time();
		$start_ts = $start?->getTimestamp() ?? 0;
		$end_ts   = $end->getTimestamp();

		// If start defined and now is before start -> pending.
		if ( $start_ts > 0 && $now < $start_ts ) {
			return static::STATUS_PENDING;
		}

		// If now is after end -> expired.
		if ( $now > $end_ts ) {
			return static::STATUS_EXPIRED;
		}

		// Otherwise we are within the active window.
		return static::STATUS_ACTIVE;
	}


	/**
	 * Get the license start date
	 * 
	 * @return DateTimeImmutable|null $start_date
	 */
	public function get_start_date() : ?DateTimeImmutable {
		return $this->start_date;
	}

	/**
	 * Get the license end date
	 * 
	 * @return DateTimeImmutable|null $end_date
	 */
	public function get_end_date() : ?DateTimeImmutable {
		return $this->end_date;
	}

	/**
	 * Get date created.
	 * 
	 * @return DateTimeImmutable|null
	 */
	public function get_created_at() : ?DateTimeImmutable {
		return $this->created_at;
	}

	/**
	 * Get date updated.
	 * 
	 * @return DateTimeImmutable|null
	 */
	public function get_updated_at() : ?DateTimeImmutable {
		return $this->updated_at;
	}

	/**
	 * Get the maximum allowed domains that can request license activation.
	 * 
	 * @param string $context
	 * @return string|int $number_of_domains
	 */
	public function get_max_allowed_domains( string $context = 'view' ) : string|int {

		if ( 'view' === $context ) {
			if ( $this->max_allowed_domains < 0 ) {
				return 'Unlimited';
			}
			if ( empty( $this->max_allowed_domains ) ) {
				return 'None';
			}
		}

		if ( 'edit' === $context ) {
			if ( $this->max_allowed_domains < 0 ) {
				return '';
			}
		}

		return $this->max_allowed_domains;
	}

	/**
	 * Get the value of the given meta data key
	 * 
	 * @param string $meta_name The name of the meta key.
	 * @param mixed $default    The default value to return when the meta name is not found.
	 * @return mixed
	 */
	public function get_meta( $meta_name, $default = null ) {
		return $this->meta_data[$meta_name] ?? $default;
	}

	/**
	 * Get the meta data
	 * 
	 * @return array
	 */
	public function get_meta_data() {
		return $this->meta_data;
	}

	/*
	|-----------------
	|   CRUD METHODS
	|-----------------
	*/

	/**
	 * Get license object using service ID and the license key
	 * 
	 * @param string $service_id The service ID associated with the licence
	 * @param string $license_key   The license key
	 * @return static|null
	 */
	public static function get_license( $service_id, $license_key ) : ?static {
		$key = static::make_cache_key( __METHOD__, [$service_id, $license_key] );

		/** @var static|false $result */
		$result = static::cache_get( $key );

		if ( false === $result || ! ( $result instanceof static ) ) {
			$table = TableName::LICENSES->table();
			$sql   = static::query()->select( '*' )->from( $table )
				->where( 'service_id', '=', $service_id )
				->where( 'license_key', '=', $license_key );

			$data = static::$DB->get_row( $sql->build(), $sql->get_bindings() );
			
			if ( $data ) {
				$result = static::from_array( $data );
			} else {
				$result = null;
			}

			static::cache_set( $key, $result, static::default_ttl() );
		}

		return $result;

	}

	/**
	 * Get license by ID
	 * 
	 * @param int $id
	 * @return static|null
	 */
	public static function get_by_id( $id ) : ?static {
		$key = static::make_cache_key( __METHOD__, [$id] );

		$license = static::cache_get( $key );

		if ( false === $license || ! ( $license instanceof static ) ) {
			$data    = static::fetch_by( 'id', $id, TableName::LICENSES->table() );
			$license = $data ? static::from_array( $data ) : null;

			static::cache_set( $key, $license, static::default_ttl() );
		}

		return $license;
	}

	/**
	 * Get a license by its key.
	 *
	 * @param string $license_key
	 * @return static|null
	 */
	public static function get_by_key( string $license_key ) : ?static {
		if ( '' === $license_key ) {
			return null;
		}

		$data = static::fetch_by( 'license_key', $license_key, TableName::LICENSES->table() );

		return $data ? static::from_array( $data ) : null;
	}

	/**
	 * Get the license a provider sold under the given reference.
	 *
	 * @param string $provider_id  The provider ID.
	 * @param string $external_ref The provider's reference for the sale.
	 * @return static|null
	 */
	public static function get_by_source( string $provider_id, string $external_ref ) : ?static {
		if ( '' === $provider_id || '' === $external_ref ) {
			return null;
		}

		$sql = static::query()->select( '*' )->from( TableName::LICENSES->table() )
			->where( 'provider_id', '=', $provider_id )
			->where( 'external_ref', '=', $external_ref )
			->limit( 1 );

		$data = static::$DB->get_row( $sql->build(), $sql->get_bindings() );

		return $data ? static::from_array( $data ) : null;
	}

	/**
	 * The license document: a plain-text certificate of this license.
	 *
	 * Served by the document download routes and attached to the email that
	 * delivers a license bought by manual payment.
	 *
	 * @return string
	 */
	public function to_document() : string {
		$license_key = $this->get_license_key();
		$service_id  = $this->get_service_id();
		$date_issued = $this->get_start_date()?->format( \smliser_datetime_format() ) ?? 'N/A';
		$expiry      = $this->get_end_date()?->format( \smliser_datetime_format() ) ?? 'N/A';
		$status      = $this->get_status();
		$max_domains = $this->get_max_allowed_domains();
		$licensee    = $this->get_licensee_fullname();
		$today       = gmdate( 'F j, Y g:i:s a' );
		$issuer      = Settings::instance()->get( Settings::REPOSITORY_NAME, SMLISER_APP_NAME );
		$terms_url   = Settings::instance()->get( Settings::TERMS_URL, '' );
		$app_id      = $this->is_issued() ? $this->get_app_id() : 'N/A';

		$document = <<<LICENSE
        ==================================================
        SOFTWARE LICENSE CERTIFICATE
        Issued by:  {$issuer}
        Licensee:   {$licensee}
        ==================================================
        --------------------------------------------------
        License Details
        --------------------------------------------------
        Status:         {$status}
        Service ID:     {$service_id}
        License Key:    {$license_key}
        App ID:         {$app_id}

        --------------------------------------------------
        License Validity
        --------------------------------------------------
        Start Date:     {$date_issued}
        End Date:       {$expiry}
        Allowed Sites:  {$max_domains}

        --------------------------------------------------
        Activation Guide
        --------------------------------------------------
        Use the Service ID and License Key above to activate this software.

        Note:
        - The software already includes its internal ID.
        - Activation may vary by product. Refer to product documentation.

        --------------------------------------------------
        License Terms (Summary)
        --------------------------------------------------
        ✔ Use on "{$max_domains}" domain(s)
        ✔ Allowed for personal or client projects
        ✘ Not allowed to resell, redistribute, or modify for resale

        Full License Agreement:
        {$terms_url}

        --------------------------------------------------
        Issued By:          {$issuer}
        Auto Generated On:  {$today}
        ==================================================
        LICENSE;

		return $document;
	}

	/**
	 * Link the user's anonymous licenses to their account.
	 *
	 * Called once the user has proved they own their email address: every
	 * license issued to that address and not linked to anyone yet becomes
	 * theirs. Licenses already linked to another user are left alone.
	 *
	 * @param User $user A user whose email address is verified.
	 * @return int Number of licenses linked.
	 */
	public static function link_to_user( User $user ) : int {
		if ( ! $user->get_id() || ! $user->is_email_verified() ) {
			return 0;
		}

		$sql = static::query()->update( TableName::LICENSES->table() )
			->set( [ 'user_id' => (int) $user->get_id() ] )
			->where( 'licensee_email', '=', strtolower( trim( $user->get_email() ) ) )
			->where_null( 'user_id' );

		return static::$DB->execute( $sql->build(), $sql->get_bindings() );
	}

	/**
	 * Get all licenses with pagination support.
	 *
	 * @param int $page  Current page number.
	 * @param int $limit Items per page.
	 *
	 * @return array{
	 *  items: static[], 
	 *  pagination: 
	 *      array{
	 *          page: int,
	 *          limit: int,
	 *          total: int,
	 *          total_pages: int
	 *      }
	 *  } 
	 */
	public static function get_all( int $page = 1, int $limit = 30 ) : array {

		$page  = (int) max( 1, $page );
		$limit = (int) max( 1, $limit );

		$key  = static::make_cache_key( __METHOD__, [ $page, $limit ] );
		$data = false; //static::cache_get( $key );

		if ( false !== $data && is_array( $data ) ) {
			return $data;
		}

		$items = \array_map(
			[static::class, 'from_array'],
			static::fetch( TableName::LICENSES->table(), $page, $limit )
		);

		/**
		 * Fetch total count
		 */
		$count_sql = self::query()->select( 'COUNT(*)' )->from( TableName::LICENSES->table() );
		$total     = (int) static::$DB->get_var( $count_sql->build_raw() );

		$result = [
			'items'      => $items,
			'pagination' => [
				'page'        => $page,
				'limit'       => $limit,
				'total'       => $total,
				'total_pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 0,
			],
		];

		static::cache_set( $key, $result, static::default_ttl() );

		return $result;
	}

	/**
	 * Search licenses in the database.
	 *
	 * @param array $args.
	 *
	 * @return array {
	 *     @type static[] $items
	 *     @type array    $pagination
	 * }
	 */
	public static function search( array $args = [] ) : array {

		$defaults = [
			'search_term' => '',
			'page'        => 1,
			'limit'       => 10
		];

		$args = parse_args( $args, $defaults );

		$key  = static::make_cache_key( __METHOD__, $args );
		$data = static::cache_get( $key );

		if ( false !== $data ) {
			return $data;
		}

		$db     = static::$DB;
		$table  = TableName::LICENSES->table();
		$page   = max( 1, $args['page'] );
		$limit  = max( 1, $args['limit'] );
		$offset = $db->calculate_query_offset( $page, $limit );

		$like = $args['search_term'] . '%';

		$sql = static::query()->select( '*' )->from( $table )
			->where( 'licensee_fullname', 'LIKE', $like )
			->or_where( 'licensee_email', 'LIKE', $like )
			->or_where( 'license_key', 'LIKE', $like )
			->or_where( 'service_id', 'LIKE', $like )
			->order_by( 'id', 'DESC' )
			->limit( $limit )->offset( $offset );
		
		$rows = $db->get_results( $sql->build(), $sql->get_bindings() );

		$items = [];

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$items[] = static::from_array( $row );
			}
		}

		/**
		 * Fetch total count
		 */
		$count_sql = static::query()->select( 'COUNT(*)' )->from( $table )
			->where( 'licensee_fullname', 'LIKE', $like )
			->or_where( 'licensee_email', 'LIKE', $like )
			->or_where( 'license_key', 'LIKE', $like )
			->or_where( 'service_id', 'LIKE', $like );

		$total = (int) $db->get_var( $count_sql->build(), $count_sql->get_bindings() );

		$result = [
			'items'      => $items,
			'pagination' => [
				'page'        => $page,
				'limit'       => $limit,
				'total'       => $total,
				'total_pages' => $limit > 0 ? (int) ceil( $total / $limit ) : 0,
			],
		];

		static::cache_set( $key, $result, static::default_ttl() );

		return $result;
	}

	/**
	 * Save the license.
	 *
	 * A new license gets a license key, and a service ID when it has none.
	 * Metadata is saved with it, one row per key.
	 * 
	 * @return bool True when the license and all its metadata are saved, false otherwise.
	 */
	public function save() : bool {
		$table      = TableName::LICENSES->table();
		$meta_table = TableName::LICENSE_META->table();
		$now        = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		$data = [
			'licensee_fullname'   => $this->get_licensee_fullname(),
			'licensee_email'      => '' === $this->licensee_email ? null : $this->licensee_email,
			'user_id'             => $this->user_id ?: null,
			'service_id'          => $this->get_service_id(),
			'app_prop'            => $this->get_app_prop( 'view' ),
			'tier_id'             => $this->tier_id ?: null,
			'provider_id'         => '' === $this->provider_id ? null : $this->provider_id,
			'external_ref'        => '' === $this->external_ref ? null : $this->external_ref,
			'features'            => Format::encode( $this->features, Format::ENCODING_PHP ),
			'max_allowed_domains' => $this->get_max_allowed_domains( 'save' ),
			'status'              => $this->get_status( 'edit' ),
			'start_date'          => $this->get_start_date()?->format( 'Y-m-d H:i:s' ),
			'end_date'            => $this->get_end_date()?->format( 'Y-m-d H:i:s' ),
			'updated_at'          => $now->format( 'Y-m-d H:i:s' ),
		];

		try {
			$saved = static::$DB->transactional( function( Database $db ) use ( $table, $meta_table, $now, $data ) : bool {
				if ( $this->exists() ) {
					if ( false === $db->update( $table, $data, [ 'id' => $this->id ] ) ) {
						return false;
					}
				} else {
					if ( '' === $this->service_id ) {
						$this->set_service_id( static::make_service_id() );
						$data['service_id'] = $this->service_id;
					}

					$data['license_key'] = $this->generate_license_key( '', false, 20 );
					$data['created_at']  = $now->format( 'Y-m-d H:i:s' );
					$inserted            = $db->insert( $table, $data );

					if ( false === $inserted ) {
						return false;
					}

					$this->set_id( $inserted );
					$this->set_created_at( $now );
				}

				foreach ( $this->meta_data as $key => $value ) {
					$sql = static::query()->select( 'id' )->from( $meta_table )
						->where( 'license_id', '=', $this->id )
						->where( 'meta_key', '=', $key )
						->limit( 1 );

					$meta_id = $db->get_var( $sql->build(), $sql->get_bindings() );
					$stored  = Format::encode( $value, Format::ENCODING_PHP );

					$done = $meta_id
						? $db->update( $meta_table, [ 'meta_value' => $stored ], [ 'id' => $meta_id ] )
						: $db->insert( $meta_table, [ 'license_id' => $this->id, 'meta_key' => $key, 'meta_value' => $stored ] );

					if ( false === $done ) {
						throw new Exception( 'license_meta_error', sprintf( 'Saving the license meta "%s" failed: %s', $key, $db->get_last_error() ) );
					}
				}

				return true;
			} );
		} catch ( Exception $e ) {
			$saved = false;
		}

		if ( $saved ) {
			$this->set_updated_at( $now );
		}

		static::cache_clear();

		return $saved;
	}

	/**
	 * Load the metadata from the database
	 *
	 * @return static
	 */
	public function load_meta() : static {
		$db    = static::$DB;
		$table = TableName::LICENSE_META->table();
		$sql   = $this->query()
			->select( 'meta_key', 'meta_value' )
			->from( $table )
			->where( 'license_id', '=', $this->get_id() );

		$results = $db->get_results( $sql->build(), $sql->get_bindings() );

		foreach( $results as $result ) {
			$value      = $result['meta_value'] ?? '';
			$meta_name  = $result['meta_key'] ?? '';
			$meta_value = Format::decode( $value );

			if ( ! empty( $meta_name ) ) {
				$this->set_meta( $meta_name, $meta_value );
			}
		}


		return $this;
	}

	/**
	 * Delete license from the database along with its associated metadata.
	 * 
	 * @return bool True on success, false otherwise.
	 * @throws Exception If a concurrent modification is detected or if database execution fails.
	 */
	public function delete() : bool {
		if ( ! $this->id ) {
			return false;
		}

		$table      = TableName::LICENSES->table();
		$meta_table = TableName::LICENSE_META->table();

		// Compile query structures via the Query Builder.
		$delete_license_sql = static::query()->delete( $table )->where( 'id', '=', $this->id );
		$delete_meta_sql    = static::query()->delete( $meta_table )->where( 'license_id', '=', $this->id );
		$delete_activations = static::query()->delete( TableName::LICENSE_ACTIVATIONS->table() )->where( 'license_id', '=', $this->id );
		$lock_query         = static::query()->select( 'id' )->from( $table )->where( 'id', '=', $this->id )->limit( 1 );

		$deleted = false;

		static::$DB->transactional( function( Database $db ) use ( $lock_query, $delete_license_sql, $delete_meta_sql, $delete_activations, &$deleted ) {
			$lock_sql = $lock_query->lock_for_update()->build();

			$exists = $db->get_row( $lock_sql, $lock_query->get_bindings() );

			if ( ! $exists ) {
				throw new Exception( 'delete_error', 'License record locked or already removed by a concurrent request.' );
			}

			if ( 0 === $db->execute( $delete_meta_sql->build(), $delete_meta_sql->get_bindings() ) && ! empty( $db->get_last_error() ) ) {
				throw new Exception( 'delete_error', 'Database execution failure dropping license metadata entries.' );
			}

			// A license may have no activations, so 0 fails only with an error.
			if ( 0 === $db->execute( $delete_activations->build(), $delete_activations->get_bindings() ) && ! empty( $db->get_last_error() ) ) {
				throw new Exception( 'delete_error', 'Database execution failure dropping license activations.' );
			}

			if ( 0 === $db->execute( $delete_license_sql->build(), $delete_license_sql->get_bindings() ) ) {
				throw new Exception( 'delete_error', 'Database execution failure dropping primary license record.' );
			}

			$deleted = true;
		} );

		if ( $deleted ) {
			static::cache_clear();
		}

		return $deleted;
	}

	/**
	|---------------------
	| UTILITY METHODS
	|---------------------
	 */

	/**
	 * Get the domains this license is activated on.
	 *
	 * @param string $context 'view' for a comma-separated list of hosts ("N/A"
	 *                        when none); 'edit' for an array keyed by host.
	 * @return string|array<string, array{origin: string, secret: string, activated_at: string|null}>
	 */
	public function get_active_domains( $context = 'view' ) {
		$activations = $this->get_activations();

		if ( 'view' === $context ) {
			return empty( $activations ) ? 'N/A' : implode( ', ', array_keys( $activations ) );
		}

		return array_map( static fn ( LicenseActivation $activation ) : array => $activation->to_array(), $activations );
	}

	/**
	 * Get the activations of this license, keyed by host.
	 *
	 * @return array<string, LicenseActivation>
	 */
	public function get_activations() : array {
		if ( null === $this->activations ) {
			$this->activations = $this->id ? LicenseActivation::get_for_license( $this->id ) : [];
		}

		return $this->activations;
	}

	/**
	 * Converts and return associative array to object of this class.
	 * 
	 * @param array $data   Associative array containing result from database
	 */
	public static function from_array( $data ) : static {
		$static = new static();
		$static->set_id( $data['id'] ?? 0 );
		$static->set_licensee_fullname( $data['licensee_fullname'] ?? '' );
		$static->licensee_email = strtolower( (string) ( $data['licensee_email'] ?? '' ) );
		$static->set_user_id( $data['user_id'] ?? 0 );
		$static->set_tier_id( $data['tier_id'] ?? 0 );
		$static->set_source( (string) ( $data['provider_id'] ?? '' ), (string) ( $data['external_ref'] ?? '' ) );

		$features = Format::decode( $data['features'] ?? null );
		$static->set_features( is_array( $features ) ? $features : [] );
		$static->set_service_id( $data['service_id'] ?? '' );
		$static->set_license_key( $data['license_key'] ?? '' );
		$static->set_status( $data['status'] ?? '' );
		$static->set_start_date( $data['start_date'] ?? '' );
		$static->set_end_date( $data['end_date'] ?? '' );
		$static->set_max_allowed_domains( $data['max_allowed_domains'] ?? 0 );
		$static->set_created_at( $data['created_at'] ?? null );
		$static->set_updated_at( $data['updated_at'] ?? null );

		$app_prop = $data['app_prop'] ?? '';

		if ( ! empty( $app_prop ) && is_string( $app_prop ) && preg_match( '#^[^/]+/[^/]+$#', $app_prop ) ) {
			list( $app_type, $app_slug ) = explode( '/', $app_prop, 2 );
			$static->set_app_prop( $app_type, $app_slug );
		} elseif ( is_array( $app_prop ) && 2 === count( $app_prop ) ) {
			$app_type = (string) \array_first( $app_prop );
			$app_slug = (string) \array_last( $app_prop );
			$static->set_app_prop( $app_type, $app_slug );
		}

		$static->load_meta();

		return $static;
	}

	/**
	 * Convert to array
	 * 
	 * @return array
	 */
	public function to_array() : array {
		return get_object_vars( $this );
	}

	/**
	 * Get total domains using this license.
	 *
	 * @return int
	 */
	public function get_total_active_domains() {
		return count( $this->get_activations() );
	}

	/**
	 * Get the value of a domain from the active list.
	 * 
	 * @param string $url The domain or website name.
	 * @param bool $all_data  Whether to include the values of the domain.
	 * @return array{origin: string, secret: string, activated_at: string|null}|string|null The domain data, or its origin when
	 *                                                                                      $all_data is false; null when not activated.
	 */
	public function get_active_domain( $url, $all_data = true ) {
		$host       = $this->ensure_domain( $url )->get_host();
		$activation = $this->get_activations()[ $host ] ?? null;

		if ( null === $activation ) {
			return null;
		}

		return $all_data ? $activation->to_array() : $activation->get_origin();
	}

	/**
	 * Activate this license on a new domain.
	 *
	 * Runs in a transaction that locks the license, so two activations at
	 * the same time cannot exceed the domain limit or record one domain twice.
	 *
	 * @param string|URL $url         The site URL.
	 * @param string     $secret_hash The HMAC of the site secret handed to the site.
	 * @return true|Exception True when activated; an Exception (with a "status") when the
	 *                        domain is already active, the limit is reached or the write fails.
	 */
	public function activate_domain( string|URL $url, string $secret_hash ) : true|Exception {
		if ( ! $this->id ) {
			return new Exception( 'license_error', 'The license does not exist.', [ 'status' => 404 ] );
		}

		$url = $this->ensure_domain( $url );

		try {
			static::$DB->transactional( function( Database $db ) use ( $url, $secret_hash ) : void {
				$lock = static::query()->select( 'id', 'max_allowed_domains' )->from( TableName::LICENSES->table() )
					->where( 'id', '=', $this->id )
					->limit( 1 )->lock_for_update();
				$row  = $db->get_row( $lock->build(), $lock->get_bindings() );

				if ( ! $row ) {
					throw new Exception( 'license_error', 'The license does not exist.', [ 'status' => 404 ] );
				}

				$current = LicenseActivation::get_for_license( $this->id );
				$limit   = (int) $row['max_allowed_domains'];

				if ( isset( $current[ $url->get_host() ] ) ) {
					throw new Exception( 'domain_already_active', sprintf( 'The license is already activated on %s.', $url->get_host() ), [ 'status' => 409 ] );
				}

				if ( -1 !== $limit && count( $current ) >= $limit ) {
					throw new Exception( 'license_error', 'Maximum allowed domains has been reached.', [ 'status' => 409 ] );
				}

				$inserted = $db->insert( TableName::LICENSE_ACTIVATIONS->table(), [
					'license_id'   => $this->id,
					'host'         => $url->get_host(),
					'origin'       => $url->get_origin(),
					'secret_hash'  => $secret_hash,
					'activated_at' => gmdate( 'Y-m-d H:i:s' ),
				] );

				if ( false === $inserted ) {
					throw new Exception( 'license_error', 'The activation could not be saved.', [ 'status' => 503 ] );
				}
			} );
		} catch ( Exception $e ) {
			return $e;
		} finally {
			$this->activations = null;
			static::cache_clear();
		}

		return true;
	}

	/**
	 * Remove a domain from activated list.
	 * 
	 * @param string|URL $domain The domain
	 * @return bool True when the domain was active and is removed.
	 */
	public function remove_activated_domain( string|URL $domain ) : bool {
		$host = $this->ensure_domain( $domain )->get_host();

		if ( ! $this->id || ! isset( $this->get_activations()[ $host ] ) ) {
			return false;
		}

		$deleted = static::$DB->delete( TableName::LICENSE_ACTIVATIONS->table(), [ 'license_id' => $this->id, 'host' => $host ] );

		$this->activations = null;
		static::cache_clear();

		return false !== $deleted && $deleted > 0;
	}

	/**
	 * Whether license has reached max allowed domains.
	 */
	public function has_reached_max_allowed_domains() {
		if ( -1 === $this->max_allowed_domains ) {
			return false;
		}
		
		return $this->get_total_active_domains() >= $this->max_allowed_domains;
	}

	/**
	 * Checks whether the a given domain is a new domain
	 * 
	 * @param string $domain The name of the website.
	 */
	public function is_new_domain( string $domain ) {
		return ! isset( $this->get_activations()[ $this->ensure_domain( $domain )->get_host() ] );
	}

	/**
	 * Helper method to ensure a given domain is a valid URL.
	 * 
	 * @param string|URL $domain
	 */
	private function ensure_domain( string|URL $domain ) : URL {
		if ( ( $domain instanceof URL ) ) {
			$url = $domain;
			if ( ! $url->has_scheme() ) {
				// Malformed url, rebuild it.
				$url = $url->set_scheme( 'https' );
				$url = URL::from( $url->url() );
			}

			return $url;
		}

		$url = URL::from( $domain );
		return $this->ensure_domain( $url );
	}

	/**
	 * Tells whether this license is issued to an app.
	 * 
	 * @return bool
	 */
	public function is_issued() : bool {
		if ( ! isset( $this->app ) ) {
			$this->get_app();
		}
		
		return ( $this->app instanceof AbstractHostedApp );
	}

	/**
	 * Tells whether the license is deactivated.
	 * 
	 * @return bool
	 */
	public function is_deactivated() : bool {
		return strtolower( $this->get_status() ) === static::STATUS_DEACTIVATED;
	}

	/**
	 * Tells whether we can perform actions that require license validity.
	 *
	 * @param int $app_id The App ID associated with the license.
	 * @return true|Exception True if license can be served, otherwise Exception.
	 */
	public function can_serve_license( int $app_id ) {
		if ( ! $this->is_issued() ) {
			return new Exception(
				'license_error',
				'License is not issued to any application.',
				[ 'status' => 400 ]
			);
		}

		if ( $app_id !== $this->get_app_id() ) {
			return new Exception(
				'license_error',
				'License was not issued to this application.',
				[ 'status' => 403 ] // fixed
			);
		}

		$status = $this->get_status();

		switch ( $status ) {

			case static::STATUS_EXPIRED:
				return new Exception(
					'license_expired',
					'This license has expired. Please renew it.',
					[ 'status' => 403 ]
				);

			case static::STATUS_SUSPENDED:
				return new Exception(
					'license_suspended',
					'This license has been suspended. Please contact support.',
					[ 'status' => 403 ]
				);

			case static::STATUS_REVOKED:
				return new Exception(
					'license_revoked',
					'This license has been revoked. Please contact support.',
					[ 'status' => 403 ]
				);

			case static::STATUS_DEACTIVATED:
				return new Exception(
					'license_deactivated',
					'This license is deactivated.',
					[ 'status' => 409 ]
				);

			default:
				return true;
		}
	}

	/**
	 * Get the ID of the item associated with this license.
	 * 
	 * @return int
	 */
	public function get_app_id() {
		return $this->is_issued() ? $this->app->get_id() : 0;
	}

	/**
	 * Make a service ID for a license that was given none.
	 *
	 * @return string
	 */
	public static function make_service_id() : string {
		return 'svc_' . bin2hex( random_bytes( 12 ) );
	}

	/**
	 * Generate a license key.
	 *
	 * @param string $prefix The prefix to be added to the license key.
	 * @return string The generated license key.
	 */
	public function make_licence_key( string $prefix = '' ) {
		if ( '' === $prefix ) {
			$prefix = (string) ( Settings::instance()->get( Settings::LICENSE_KEY_PREFIX, 'SMLISER' ) ?? 'SMLISER' );
		}

		$uid          = sha1( uniqid( '', true ) );
		$secure_bytes = random_bytes( 16 );
		$random_hex   = bin2hex( $secure_bytes );
		$combined_key = strtoupper( str_replace( '-', '', $uid ) . $random_hex );
		$license_key  = $prefix . $combined_key;

		// Insert hyphens at every 8-character interval.
		$real_license_key = '';
		for ( $i = 0; $i < strlen( $license_key ); $i += 8 ) {
			if ( $i > 0 ) {
				$real_license_key .= '-';
			}
			$real_license_key .= substr( $license_key, $i, 8 );
		}

		return $real_license_key;
	}

	/**
	 * Obfuscate the license key and return a partial ending...
	 * 
	 * @return string
	 */
	public function get_partial_key() : string {
		$license_key = $this->get_license_key();
		$partial     = substr( $license_key, strlen( $license_key ) - 12 );
		$obfuscated  = rtrim( str_repeat( '****-', 4 ), '-' );
		return sprintf( '%s-%s', $obfuscated, $partial );
	}

	/**
	 * Generate a unique license key.
	 *
	 * This will generate a new, unique license key and optionally persist it to the database.
	 * The method updates the license key property.
	 *
	 * @param string  $prefix  Optional prefix.
	 * @param bool    $persist Whether to persist the new key to the database. Default true.
	 * @param int     $tries   Maximum attempts to generate a unique key. Default 10.
	 * @return string The newly generated license key.
	 * @throws Exception If a unique key cannot be generated or DB persist fails.
	 */
	public function generate_license_key( string $prefix = '', bool $persist = true, int $tries = 10 ) : string {
		$db    = static::$DB;
		$table = TableName::LICENSES->table();

		$attempt = 0;
		$new_key = '';
		$tries   = static::sanitize_positive_int( $tries );

		do {
			$attempt++;
			$new_key = $this->make_licence_key( $prefix );

			$sql = $this->query()
				->select( 'COUNT(*)' )->from( $table )
				->where( 'license_key', '=', $new_key );
			$count = (int) $db->get_var( $sql->build(), $sql->get_bindings() );

			if ( 0 === $count ) {
				break;
			}
		} while ( $attempt < max( 1, $tries ) );

		if ( $attempt >= max( 1, $tries ) && $count > 0 ) {
			throw new Exception(
				'license_regeneration_failed',
				'Unable to generate a unique license key after multiple attempts.',
				[ 'status' => 500 ]
			);
		}

		$this->set_license_key( $new_key );

		return $new_key;
	}

	/**
	 * Tells if a license exists.
	 * 
	 * @return bool
	 */
	public function exists() : bool {
		return (bool) $this->get_id();
	}

	/**
	 * Get allowed statuses
	 */
	public static function get_allowed_statuses() {
		return [
			static::STATUS_ACTIVE,
			static::STATUS_DEACTIVATED,
			static::STATUS_EXPIRED,
			static::STATUS_INACTIVE,
			static::STATUS_LIFETIME,
			static::STATUS_PENDING,
			static::STATUS_REVOKED,
			static::STATUS_SUSPENDED,
		];
	}

}