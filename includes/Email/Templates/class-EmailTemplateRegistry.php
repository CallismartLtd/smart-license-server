<?php
/**
 * Email template registry.
 *
 * Central registry for all email template types. Provides
 * the data needed to render the template list UI, preview
 * individual templates, and manage custom editor overrides.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates;

use SmartLicenseServer\Email\Templates\Accounts\OrganizationInviteEmail;
use SmartLicenseServer\Email\Templates\Accounts\OrganizationMemberRemovedEmail;
use SmartLicenseServer\Email\Templates\Accounts\PasswordChangedEmail;
use SmartLicenseServer\Email\Templates\Accounts\PasswordResetEmail;
use SmartLicenseServer\Email\Templates\Accounts\WelcomeEmail;
use SmartLicenseServer\Email\Templates\Apps\AppOwnershipChangedEmail;
use SmartLicenseServer\Email\Templates\Apps\AppPublishedEmail;
use SmartLicenseServer\Email\Templates\Apps\AppStatusChangedEmail;
use SmartLicenseServer\Email\Templates\Apps\AppUpdatedEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseActivatedEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseDeactivatedEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseExpiredEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseExpiryReminderEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseIssuedEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseRenewedEmail;
use SmartLicenseServer\Email\Templates\Licenses\LicenseSuspendedEmail;
use SmartLicenseServer\Email\Templates\Apps\NewAppVersionNotificationEmail;
use SmartLicenseServer\Email\Templates\Monetization\PaymentFailedEmail;
use SmartLicenseServer\Email\Templates\Monetization\PaymentReceivedEmail;
use SmartLicenseServer\Email\Templates\Monetization\SubscriptionCancelledEmail;
use SmartLicenseServer\Email\Templates\System\BulkMessageEmail;
use SmartLicenseServer\Email\Templates\System\SystemAlertEmail;
use SmartLicenseServer\Email\Templates\System\TestEmail;

defined( 'SMLISER_ABSPATH' ) || exit;

class EmailTemplateRegistry {

    /**
     * Registered template classes keyed by template_key().
     *
     * @var array<string, class-string<EmailTemplate>>
     */
    private static array $templates = [];

    /**
     * Whether the built-in templates have been registered.
     *
     * @var bool
     */
    private static bool $booted = false;

    /**
     * Register a template class.
     *
     * Third-party code can call this to add custom template types:
     *
     *   EmailTemplateRegistry::register( MyCustomEmail::class );
     *
     * The template key is derived automatically from the class
     * via template_key() — no need to pass it separately.
     *
     * @param  class-string<EmailTemplate> $class Fully qualified class name.
     * @return void
     * @throws \InvalidArgumentException If the class does not extend EmailTemplate.
     */
    public static function register( string $class ): void {
        
        if ( ! is_subclass_of( $class, EmailTemplate::class ) ) {
            throw new \InvalidArgumentException(
                sprintf( '"%s" must extend %s.', $class, EmailTemplate::class )
            );
        }

        $key = $class::template_key();

        static::$templates[ $key ] = $class;
    }

    /**
     * Boot the registry with all built-in template types.
     *
     * Called once — subsequent calls are no-ops.
     *
     * @return void
     */
    public static function boot(): void {
        if ( static::$booted ) {
            return;
        }

        $built_in = [
            // License
            LicenseIssuedEmail::class,
            LicenseActivatedEmail::class,
            LicenseDeactivatedEmail::class,
            LicenseExpiryReminderEmail::class,
            LicenseExpiredEmail::class,
            LicenseSuspendedEmail::class,
            LicenseRenewedEmail::class,

            // Account / Identity
            WelcomeEmail::class,
            PasswordResetEmail::class,
            PasswordChangedEmail::class,

            // Organization
            OrganizationInviteEmail::class,
            OrganizationMemberRemovedEmail::class,

            // Hosted Applications
            AppPublishedEmail::class,
            AppUpdatedEmail::class,
            NewAppVersionNotificationEmail::class,
            AppStatusChangedEmail::class,
            AppOwnershipChangedEmail::class,

            // Monetization
            PaymentReceivedEmail::class,
            PaymentFailedEmail::class,
            SubscriptionCancelledEmail::class,

            // Messaging & System
            BulkMessageEmail::class,
            TestEmail::class,
            SystemAlertEmail::class,
        ];

        foreach ( $built_in as $class ) {
            static::register( $class );
        }

        static::$booted = true;
    }

    /**
     * Get all registered templates as UI-ready entries.
     *
     * Each entry contains everything the template list UI needs
     * to render a row without instantiating the full template:
     *
     *   [
     *     'key'         => 'license_issued',
     *     'label'       => 'License Issued',
     *     'description' => 'Sent when a new license is created...',
     *     'class'       => LicenseIssuedEmail::class,
     *     'has_custom'  => true,
     *   ]
     *
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array {
        static::boot();

        $entries = [];

        foreach ( static::$templates as $key => $class ) {
            $entries[ $key ] = static::build_entry( $key, $class );
        }

        return $entries;
    }

    /**
     * Get a single UI entry by template key.
     *
     * Returns null if the key is not registered.
     *
     * @param  string $key
     * @return array<string, mixed>|null
     */
    public static function entry( string $key ): ?array {
        static::boot();

        if ( ! isset( static::$templates[ $key ] ) ) {
            return null;
        }

        return static::build_entry( $key, static::$templates[ $key ] );
    }

    /**
     * Get a preview instance of a template by key.
     *
     * Returns the result of the template's own preview() factory —
     * a fully constructed instance populated with placeholder data,
     * ready for render() to be called on it.
     *
     * @param  string $key
     * @return EmailTemplate|null
     */
    public static function preview( string $key ): ?EmailTemplate {
        static::boot();

        $class = static::$templates[ $key ] ?? null;

        if ( ! $class ) {
            return null;
        }

        return $class::preview();
    }

    /**
     * Get all registered template keys.
     *
     * @return string[]
     */
    public static function keys(): array {
        static::boot();

        return array_keys( static::$templates );
    }

    /**
     * Check whether a template key is registered.
     *
     * @param  string $key
     * @return bool
     */
    public static function has( string $key ): bool {
        static::boot();

        return isset( static::$templates[ $key ] );
    }

    /**
     * Unregister a template by key.
     *
     * Useful for third-party code that wants to replace a
     * built-in template with a custom implementation:
     *
     *   EmailTemplateRegistry::unregister( 'license_issued' );
     *   EmailTemplateRegistry::register( MyLicenseIssuedEmail::class );
     *
     * @param  string $key
     * @return void
     */
    public static function unregister( string $key ): void {
        unset( static::$templates[ $key ] );
    }

    /**
     * Reset the registry entirely.
     *
     * Intended for use in tests only.
     *
     * @return void
     */
    public static function reset(): void {
        static::$templates = [];
        static::$booted    = false;
    }

    /**
     * Build a UI-ready entry array for a single template class.
     *
     * @param  string                        $key
     * @param  class-string<EmailTemplate>   $class
     * @return array<string, mixed>
     */
    private static function build_entry( string $key, string $class ): array {
        $preview = $class::preview();

        return [
            'key'         => $key,
            'label'       => $preview->label(),
            'description' => $preview->description(),
            'class'       => $class,
            'has_custom'  => $preview->has_custom_template(),
            'is_enabled'  => $preview->is_enabled(),
        ];
    }
}