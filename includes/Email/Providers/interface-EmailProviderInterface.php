<?php
/**
 * Email Provider Interface.
 *
 * Defines the contract that all email service providers must implement.
 *
 * @package SmartLicenseServer\Email
 * @since 0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;

interface EmailProviderInterface {

    /**
     * Get unique provider identifier.
     *
     * Example: "php_mail", "brevo", "sendgrid".
     *
     * @return string
     */
    public function get_id() : string;

    /**
     * Get provider display name.
     *
     * Example: "PHP Mail", "Brevo", "SendGrid".
     *
     * @return string
     */
    public function get_name() : string;

    /**
     * Return required configuration fields.
     *
     * This allows the system to dynamically build a settings UI.
     *
     * Example return structure:
     * [
     *     'api_key' => [
     *         'type'        => 'text',
     *         'label'       => 'API Key',
     *         'required'    => true,
     *         'description' => 'Your provider API key.'
     *     ]
     * ]
     *
     * @return array<string, array<string, mixed>>
     */
    public function get_settings_schema() : array;

    /**
     * Set provider configuration.
     *
     * @param array<string, mixed> $settings
     * @return void
     */
    public function set_settings( array $settings ) : void;

    /**
     * Send an email message.
     *
     * @param EmailMessage $message
     * @return EmailResponse
     *
     * @throws EmailTransportException
     */
    public function send( EmailMessage $message ) : EmailResponse;

}