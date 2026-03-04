<?php
/**
 * Email Provider Collection Class file.
 *
 * Manages registration, retrieval, and settings persistence
 * for all available email providers.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Email
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Email\Providers\EmailProviderInterface;
use SmartLicenseServer\Email\Providers\PHPMailProvider;
use SmartLicenseServer\Email\Providers\SMTPProvider;
use SmartLicenseServer\Email\Providers\BrevoProvider;
use SmartLicenseServer\Email\Providers\SendGridProvider;
use SmartLicenseServer\Email\Providers\MailgunProvider;
use SmartLicenseServer\Email\Providers\PostmarkProvider;
use SmartLicenseServer\Email\Providers\ResendProvider;
use SmartLicenseServer\Email\Providers\AmazonSESProvider;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

class EmailProviderCollection {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * Registered providers keyed by provider ID.
     *
     * @var array<string, EmailProviderInterface>
     */
    protected array $providers = [];

    /**
     * In-memory cache for persisted provider options.
     *
     * Keyed by provider ID. Busted whenever update_option() is called.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $options_cache = [];

    /**
     * Private constructor — use instance() or create().
     */
    private function __construct() {}

    /*
    |------------------
    | SINGLETON ACCESS
    |------------------
    */

    /**
     * Return the singleton instance, creating and loading it if needed.
     *
     * @return static
     */
    public static function instance(): static {
        if ( static::$instance === null ) {
            static::$instance = new static();
            static::$instance->load_core_providers();
        }

        return static::$instance;
    }

    /*
    |---------------------
    | PROVIDER MANAGEMENT
    |---------------------
    */

    /**
     * Register an email provider.
     *
     * Replaces any previously registered provider with the same ID.
     *
     * @param EmailProviderInterface $provider
     * @return void
     */
    public function register_provider( EmailProviderInterface $provider ): void {
        $this->providers[ $provider->get_id() ] = $provider;
    }

    /**
     * Unregister a provider by its ID.
     *
     * @param string $provider_id
     * @return bool True if the provider was found and removed, false otherwise.
     */
    public function unregister_provider( string $provider_id ): bool {
        if ( ! isset( $this->providers[ $provider_id ] ) ) {
            return false;
        }

        unset( $this->providers[ $provider_id ] );
        return true;
    }

    /**
     * Check whether a provider is registered.
     *
     * @param string $provider_id
     * @return bool
     */
    public function has_provider( string $provider_id ): bool {
        return isset( $this->providers[ $provider_id ] );
    }

    /**
     * Return a registered provider by ID without settings applied.
     *
     * @param string $provider_id
     * @return EmailProviderInterface|null
     */
    public function get_provider( string $provider_id ): ?EmailProviderInterface {
        return $this->providers[ $provider_id ] ?? null;
    }

    /**
     * Return all registered providers.
     *
     * @param bool $assoc Whether to key the array by provider ID.
     * @return array<string, EmailProviderInterface>|EmailProviderInterface[]
     */
    public function get_providers( bool $assoc = true ): array {
        return $assoc ? $this->providers : array_values( $this->providers );
    }

    /**
     * Return a provider with its persisted settings applied.
     *
     * If no provider ID is given, the default provider is used.
     *
     * A clone is returned so the shared registered instance is never
     * mutated — concurrent calls with different settings stay isolated.
     *
     * @param string|null $provider_id
     * @return EmailProviderInterface|null
     * @throws InvalidArgumentException If settings validation fails.
     */
    public function get_provider_with_settings( ?string $provider_id = null ): ?EmailProviderInterface {
        $provider_id = $provider_id ?? static::get_default_provider_id();

        if ( $provider_id === null ) {
            return null;
        }

        $provider = $this->get_provider( $provider_id );

        if ( $provider === null ) {
            return null;
        }

        // Clone before applying settings to protect the shared registered instance.
        $provider = clone $provider;

        $saved_settings = [];
        foreach ( $provider->get_settings_schema() as $key => $_ ) {
            $saved_settings[ $key ] = static::get_option( $provider_id, $key );
        }

        $provider->set_settings( $saved_settings );

        return $provider;
    }

    /*
    |------------------
    | DEFAULT PROVIDER
    |------------------
    */

    /**
     * Return the default provider ID, or null if none is set.
     *
     * @return string|null
     */
    public static function get_default_provider_id(): ?string {
        $default = \smliser_settings_adapter()->get( 'smliser_email_default_provider', '' );
        return $default !== '' ? $default : null;
    }

    /**
     * Set the default email provider.
     *
     * @param string $provider_id
     * @return bool True if saved successfully.
     * @throws InvalidArgumentException If the provider is not registered.
     */
    public static function set_default_provider( string $provider_id ): bool {
        if ( ! static::instance()->has_provider( $provider_id ) ) {
            throw new InvalidArgumentException(
                "EmailProviderCollection: cannot set default — provider '{$provider_id}' is not registered."
            );
        }

        return (bool) \smliser_settings_adapter()->set( 'smliser_email_default_provider', $provider_id );
    }

    /*
    |---------------------
    | OPTIONS PERSISTENCE
    |---------------------
    */

    /**
     * Return a persisted option value for a provider.
     *
     * Results are cached in memory for the duration of the request.
     * The cache is keyed by provider ID and busted on write.
     *
     * @param string $provider_id
     * @param string $option_name
     * @return mixed
     */
    public static function get_option( string $provider_id, string $option_name ): mixed {
        if ( ! isset( static::$options_cache[ $provider_id ] ) ) {
            $all_options = \smliser_settings_adapter()->get( 'smliser_email_providers_options', [] );
            static::$options_cache[ $provider_id ] = $all_options[ $provider_id ] ?? [];
        }

        return static::$options_cache[ $provider_id ][ $option_name ] ?? '';
    }

    /**
     * Persist an option value for a provider and bust the cache.
     *
     * @param string $provider_id
     * @param string $option_name
     * @param mixed  $value
     * @return bool
     */
    public static function update_option( string $provider_id, string $option_name, mixed $value ): bool {
        $all_options = \smliser_settings_adapter()->get( 'smliser_email_providers_options', [] );

        if ( ! isset( $all_options[ $provider_id ] ) || ! is_array( $all_options[ $provider_id ] ) ) {
            $all_options[ $provider_id ] = [];
        }

        $all_options[ $provider_id ][ $option_name ] = $value;

        $saved = (bool) \smliser_settings_adapter()->set( 'smliser_email_providers_options', $all_options );

        if ( $saved ) {
            // Bust the cache for this provider so the next get_option() reads fresh data.
            unset( static::$options_cache[ $provider_id ] );
        }

        return $saved;
    }

    /**
     * Persist all settings for a provider at once and bust the cache.
     *
     * More efficient than calling update_option() per key when saving
     * an entire settings form submission.
     *
     * @param string               $provider_id
     * @param array<string, mixed> $settings
     * @return bool
     */
    public static function update_provider_settings( string $provider_id, array $settings ): bool {
        $all_options = \smliser_settings_adapter()->get( 'smliser_email_providers_options', [] );

        $all_options[ $provider_id ] = $settings;

        $saved = (bool) \smliser_settings_adapter()->set( 'smliser_email_providers_options', $all_options );

        if ( $saved ) {
            unset( static::$options_cache[ $provider_id ] );
        }

        return $saved;
    }

    /*
    |----------------
    | CORE PROVIDERS
    |----------------
    */

    /**
     * Register all built-in providers.
     *
     * Called once by instance() on first access. Third-party providers
     * can be registered afterward via register_provider().
     *
     * @return void
     */
    protected function load_core_providers(): void {
        $core_providers = [
            new PHPMailProvider(),
            new SMTPProvider(),
            new BrevoProvider(),
            new SendGridProvider(),
            new MailgunProvider(),
            new PostmarkProvider(),
            new ResendProvider(),
            new AmazonSESProvider(),
        ];

        foreach ( $core_providers as $provider ) {
            $this->register_provider( $provider );
        }
    }
}