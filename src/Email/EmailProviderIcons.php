<?php
/**
 * Email provider icon registry.
 *
 * Third-party providers register icons via register() before
 * the UI renders.
 *
 * @package SmartLicenseServer\Email
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Utils\SanitizeAwareTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

class EmailProviderIcons {
    use SanitizeAwareTrait;

    /**
     * Custom icons registered at runtime by third-party providers.
     * Keyed by provider ID.
     *
     * @var array<string, string>
     */
    protected static array $custom = [];

    /**
     * Register an icon for a third-party provider.
     *
     * Must be called before get() is invoked for the given provider ID.
     * Cannot override built-in provider icons.
     *
     * @param string $provider_id
     * @param string $icon  Asset URL or CSS class string.
     * @return void
     */
    public static function register( string $provider_id, string $icon ): void {
        static::$custom[ $provider_id ] = $icon;
    }

    /**
     * Return the icon for a provider ID.
     *
     * Built-in icons take precedence over registered custom icons.
     * Falls back to a generic mail icon if no match is found.
     *
     * @param  string $provider_id
     * @return string
     */
    public static function get( string $provider_id ): string {
        $built_in = static::built_in();

        // Built-ins take precedence.
        if ( isset( $built_in[ $provider_id ] ) ) {
            return $built_in[ $provider_id ];
        }

        return static::$custom[ $provider_id ] ?? 'dashicons dashicons-email-alt';
    }

    /**
     * Render the icon for a provider as an HTML string.
     *
     * Returns an <img> tag for URL-based icons and a <span> for
     * CSS class-based icons (dashicons, icon fonts).
     *
     * @param  string $provider_id
     * @param  string $alt  Alt text for img tags. Defaults to provider ID.
     * @return string
     */
    public static function render( string $provider_id, string $alt = '' ): string {
        $icon = static::get( $provider_id );
        $alt  = $alt !== '' ? $alt : $provider_id;

        if ( filter_var( $icon, FILTER_VALIDATE_URL ) ) {
            return sprintf(
                '<img src="%s" alt="%s" class="smliser-provider-icon" width="24" height="24" />',
                static::sanitize_web_url( $icon ),
                static::sanitize_text( $alt )
            );
        }

        return sprintf(
            '<span class="%s smliser-provider-icon" aria-label="%s"></span>',
            static::sanitize_text( $icon ),
            static::sanitize_text( $alt )
        );
    }

    /**
     * Built-in provider icon map.
     *
     * @return array<string, string>
     */
    protected static function built_in(): array {
        static $map = null;

        if ( $map === null ) {
            $map = [
                'php_mail'   => SMLISER_URL . 'assets/images/email-providers/php-mail.svg',
                'smtp'       => SMLISER_URL . 'assets/images/email-providers/smtp-mail.svg',
                'amazon_ses' => SMLISER_URL . 'assets/images/email-providers/aws.svg',
                'brevo'      => SMLISER_URL . 'assets/images/email-providers/brevo.svg',
                'sendgrid'   => SMLISER_URL . 'assets/images/email-providers/sendgrid.svg',
                'mailgun'    => SMLISER_URL . 'assets/images/email-providers/mailgun.svg',
                'postmark'   => SMLISER_URL . 'assets/images/email-providers/postmark.svg',
                'resend'     => SMLISER_URL . 'assets/images/email-providers/resend.svg',
            ];
        }

        return $map;
    }
}