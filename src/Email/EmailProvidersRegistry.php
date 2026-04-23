<?php
/**
 * Email Provider Registry Class file.
 *
 * Manages registration, retrieval, and settings persistence
 * for all available email providers.
 *
 * @author  Callistus Nwachukwu
 * @package SmartLicenseServer\Email
 * @since   0.2.0
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
use SmartLicenseServer\Contracts\AbstractRegistry;
use SmartLicenseServer\Exceptions\EmailTransportException;
use SmartLicenseServer\SettingsAPI\Settings;

defined( 'SMLISER_ABSPATH' ) || exit;
/**
 * Email service provider registry.
 * 
 * Holds the class string of available email provider and manages their
 * settings.
 * 
 * @method class-string<EmailProviderInterface>|null get( string $provider_id ) Get the class string of
 *  a registered email provider.
 * 
 * 
 */
class EmailProvidersRegistry  extends AbstractRegistry {

    /**
     * Singleton instance.
     *
     * @var self|null
     */
    protected static ?self $instance = null;

    /**
     * The settings API used to set/get settings from storage.
     * 
     * @var Settings $settings
     */
    protected Settings $settings;

    /**
     * Core email service providers.
     * 
     * @var array<int, class-string<EmailProviderInterface>>
     */
    private $core_providers = [
        PHPMailProvider::class,
        SMTPProvider::class,
        BrevoProvider::class,
        SendGridProvider::class,
        MailgunProvider::class,
        PostmarkProvider::class,
        ResendProvider::class,
        AmazonSESProvider::class,
    ];

    /**
     * In-memory cache for persisted provider options.
     *
     * Keyed by adapter ID. Busted whenever update_option() is called.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $settings_store = [];

    const DEFAULT_PROVIDER_KEY      = 'email_default_provider';
    const SETTINGS_KEY              = 'email_providers_options';
    const DEFAULT_SENDER_NAME_KEY   = 'email_from_name';
    const DEFAULT_SENDER_EMAIL_KEY  = 'email_from_address';

    /**
     * Private constructor — use instance() or create().
     */
    private function __construct( Settings $settings ) {
        $this->settings = $settings;
    }

    /*
    |------------------
    | SINGLETON ACCESS
    |------------------
    */

    /**
     * Return the singleton instance, creating and loading it if needed.
     *
     * @param Settings|null $settings The storage API, required on first initialization.
     * @return static
     */
    public static function instance( ?Settings $settings = null ): static {
        if ( static::$instance === null ) {
            if ( ! $settings ) {
                throw new EmailTransportException( 'Email provider collection API requires a storage API' );
            }

            static::$instance = new static( $settings );
        }

        return static::$instance;
    }

    /*
    |---------------------
    | PROVIDER MANAGEMENT
    |---------------------
    */

    /**
     * Return a provider with its persisted settings applied.
     *
     * If no provider ID is given, the default provider is used.
     *
     *
     * @param string|null $provider_id
     * @return EmailProviderInterface|null
     * @throws InvalidArgumentException If settings validation fails.
     */
    public function get_provider( ?string $provider_id = null ): ?EmailProviderInterface {
        $provider_id    = $provider_id ?? static::get_default_provider_id();
        $class_string   = $this->get( $provider_id );
        $provider       = null;
        if ( $class_string ) {
            /** @var EmailProviderInterface $provider */
            $provider = new $class_string;
            $settings = [];

            foreach ( $provider->get_settings_schema() as $key => $data ) {
                $settings[ $key ] = static::get_option( $provider_id, $key, $data['default'] ?? null );
            }

            $provider->set_settings( $settings );       
        }

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
    public static function get_default_provider_id(): string {
        return (string) static::instance()->settings->get( static::DEFAULT_PROVIDER_KEY, 'php_mail', true );
    }

    /**
     * Set the default email provider.
     *
     * @param string $provider_id
     * @return bool True if saved successfully.
     * @throws InvalidArgumentException If the provider is not registered.
     */
    public static function set_default_provider( string $provider_id ): bool {
        if ( ! static::instance()->has( $provider_id ) ) {
            throw new InvalidArgumentException(
                "EmailProvidersRegistry: cannot set default — provider '{$provider_id}' is not registered."
            );
        }

        return static::instance()->settings->set( static::DEFAULT_PROVIDER_KEY, $provider_id, true );
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
     * @param mixed  $default
     * @return mixed
     */
    public static function get_option( string $provider_id, string $option_name, $default = null ): mixed {
        if ( ! isset( static::$settings_store[ $provider_id ] ) ) {
            $all_options    = static::instance()->settings->get( static::SETTINGS_KEY, [], true );
            static::$settings_store[ $provider_id ] = $all_options[ $provider_id ] ?? [];
        }

        if ( 'from_email' === $option_name ) {
            $default    = static::instance()->get_default_sender_email();
        } elseif( 'from_name' === $option_name ) {
            $default    = static::instance()->get_default_sender_name();
        }

        return static::$settings_store[$provider_id][ $option_name ] ?? $default;
    }

    /**
     * Persist an option value for a provider.
     *
     * @param string $provider_id
     * @param string $option_name
     * @param mixed  $value
     * @return bool
     */
    public static function update_option( string $provider_id, string $option_name, mixed $value ): bool {
        $settings       = static::instance()->settings;
        $all_options    = $settings->get( static::SETTINGS_KEY, [], true );

        if ( ! is_array( $all_options ) ) {
            $all_options    = [];
        }

        $all_options[ $provider_id ][ $option_name ] = $value;

        $saved = $settings->set( static::SETTINGS_KEY, $all_options, true );

        if ( $saved ) {
            // Bust the cache for this adapter so the next get_option() reads fresh data.
            unset( static::$settings_store[ $provider_id ] );
        }

        return $saved;
    }

    /**
     * Persist all settings for a provider at once.
     *
     * More efficient than calling update_option() per key when saving
     * an entire settings form submission.
     *
     * @param string               $provider_id
     * @param array<string, mixed> $settings
     * @return bool
     */
    public static function update_provider_settings( string $provider_id, array $settings ): bool {
        $storage                        = static::instance()->settings;
        $all_options                    = $storage->get( static::SETTINGS_KEY, [], true );
        $all_options[ $provider_id ]    = $settings;
        
        return $storage->set( static::SETTINGS_KEY, $all_options, true );
    }

    /**
     * Get the default sender name settings.
     * 
     * This is the default system overriding name.
     */
    public function get_default_sender_name() : string {
        return (string) $this->settings->get( static::DEFAULT_SENDER_NAME_KEY, SMLISER_APP_NAME, true );
    }

    /**
     * Get the default sender email address settings.
     * 
     * This is the default system overriding name.
     */
    public function get_default_sender_email() : string {
        $url        = \url();
        $default    = \sprintf( 'smliser@%s', $url->get_host() );
        return (string) $this->settings->get( static::DEFAULT_SENDER_EMAIL_KEY, $default , true );
    }

    /**
     * Set the default sender name.
     * 
     * @param string $name Default system overriding name.
     */
    public function set_default_sender_name( string $name ) : bool {
        return $this->settings->set( static::DEFAULT_SENDER_NAME_KEY, $name, true );
    }

    /**
     * Set the default sender email address.
     * 
     * @param string $email Default system overriding name.
     */
    public function set_default_sender_email( string $email ) : bool {
        return $this->settings->set( static::DEFAULT_SENDER_EMAIL_KEY, $email, true );
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
    protected function load_core(): void {
        if ( $this->core_loaded ) {
            return;
        }

        foreach ( $this->core_providers as $provider ) {
            $this->core[$provider::get_id()] = $provider;
        }

        unset( $this->core_providers );

        $this->core_loaded = true;
    }
}