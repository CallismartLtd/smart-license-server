<?php
/**
 * Monetization Provider Registry Class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 * @since 0.2.0
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Core\Container\Container;
use SmartLicenseServer\Exceptions\EnvironmentBootstrapException;
use SmartLicenseServer\Monetization\Providers\MonetizationProviderInterface;
use SmartLicenseServer\SettingsAPI\Settings;

/**
 * Registry of available monetization providers.
 * 
 * This class manages the registration and retrieval of different
 * monetization providers integrated with the Smart License Server.
 */
final class MonetizationRegistry extends AbstractRegistry {
	protected static Settings $storage;
	protected static ?self $instance = null;

	/**
	 * The providers that ship with the core. Every other provider is
	 * registered by an extension.
	 *
	 * @var class-string<MonetizationProviderInterface>[]
	 */
	private array $core_providers = [];

	/**
	 * In-memory cache for persisted provider options.
	 *
	 * Keyed by provider ID. Busted whenever update_option() is called.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	protected static array $settings_store = [];

	/**
	 * Private constructor to prevent direct instantiation.
	 * 
	 * @param Container $container
	 */
	private function __construct(
		protected Container $container
	) {
		static::$storage = $container->get( Settings::class );
	}

	/**
	 * Get or set class instance.
	 * 
	 * @param Container|null $container
	 * @return self
	 */
	public static function instance( Container|null $container = null ) : self {
		if ( null === static::$instance ) {
			if ( null === $container ) {
				throw new EnvironmentBootstrapException(
					'misconfiguration',
					'The monetization provider registry must be initialized early with the DIC instance.'
				);
			}
			
			static::$instance = new self( $container );
		}

		return self::$instance;
	}


	/**
	 * Load core monetization providers into the registry.
	 * 
	 * @return void
	 */
	protected function load_core() : void {
		if ( $this->core_loaded ) {
			return;
		}

		foreach ( $this->core_providers as $class_string ) {
			$this->assert_implements_interface( $class_string );

			$this->core[$class_string::get_id()] = $class_string;
		}

		$this->core_loaded = true;
	}

	/**
	 * Get options for a monetization provider.
	 * 
	 * @param string $provider_id  The provider ID.
	 * @param string $option_name  The option name.
	 * @return mixed The option value or empty string if not set.
	 */
	public static function get_option( $provider_id, $option_name ) {
		if ( ! isset( static::$settings_store[ $provider_id ] ) ) {
			$all_options                            = self::$storage->get( 'smliser_monetization_providers_options', [] );
			static::$settings_store[ $provider_id ] = $all_options[ $provider_id ] ?? [];
		}

		return static::$settings_store[ $provider_id ][ $option_name ] ?? null;
	}

	/**
	 * Update an option for a monetization provider.
	 * 
	 * @param string $provider_id  The provider ID.
	 * @param string $option_name  The option name.
	 * @param mixed  $value        The option value to set.
	 * @return bool True if option value has changed, false if not or if update failed.
	 */
	public static function update_option( $provider_id, $option_name, $value ) {
		$all_options = self::$storage->get( 'smliser_monetization_providers_options', [] );

		if ( ! isset( $all_options[ $provider_id ] ) || ! is_array( $all_options[ $provider_id ] ) ) {
			$all_options[ $provider_id ] = [];
		}

		$all_options[ $provider_id ][ $option_name ] = $value;
		$updated                                     = self::$storage->set( 'smliser_monetization_providers_options', $all_options );

		if ( $updated ) {
			unset( static::$settings_store[ $provider_id ] );
		}

		return $updated;
	}

	/**
	 * Get a monetization provider.
	 * 
	 * @param string $provider_id The provider ID
	 * @return MonetizationProviderInterface|null
	 */
	public function get_provider( string $provider_id ) : ?MonetizationProviderInterface {
		if ( '' === $provider_id ) {
			return null;
		}

		$class_string = $this->get( $provider_id );

		if ( ! $class_string ) {
			return null;
		}

		
		/** @var MonetizationProviderInterface $provider */
		$provider = $this->container->get( $class_string );
		$settings = [];

		foreach ( $provider->get_settings_schema() as $key => $_ ) {
			$settings[$key] = static::get_option( $provider::get_id(), $key );
		}

		$provider->set_settings( $settings );
		

		return $provider;
	}
}