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

use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Utils\SanitizeAwareTrait;

class EmailProviderIcons {
    use SanitizeAwareTrait;

    public function __construct( protected URLManager $urlmanager ) {}
    
    /**
     * Custom icons registered at runtime by third-party providers.
     * Keyed by provider ID.
     *
     * @var array<string, string>
     */
    protected array $custom = [];

    protected $core = [
        'php_mail'   => 'images/email-providers/php-mail.svg',
        'smtp'       => 'images/email-providers/smtp-mail.svg',
        'amazon_ses' => 'images/email-providers/aws.svg',
        'brevo'      => 'images/email-providers/brevo.svg',
        'sendgrid'   => 'images/email-providers/sendgrid.svg',
        'mailgun'    => 'images/email-providers/mailgun.svg',
        'postmark'   => 'images/email-providers/postmark.svg',
        'resend'     => 'images/email-providers/resend.svg',
    ];

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
    public function register( string $provider_id, string $icon ): void {
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
    public function get( string $provider_id ): string {
        $built_in = $this->built_in();

        // Built-ins take precedence.
        if ( isset( $built_in[ $provider_id ] ) ) {
            return $this->urlmanager->assets_url( $built_in[ $provider_id ] )->url();
        }

        return static::$custom[ $provider_id ] ?? '';
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
    public function render( string $provider_id, string $alt = '' ): string {
        $icon = $this->get( $provider_id );
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
    protected function built_in(): array {
        return $this->core;
    }
}