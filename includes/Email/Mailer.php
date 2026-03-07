<?php
/**
 * Mailer class file.
 *
 * Central email API for SmartLicenseServer.
 * Wraps the active email provider and exposes a clean sending interface.
 *
 * Basic usage — send via default provider:
 *
 *   Mailer::instance()->send(
 *       new EmailMessage([
 *           'to'      => 'user@example.com',
 *           'subject' => 'Your license key',
 *           'body'    => '<p>Here is your key...</p>',
 *       ])
 *   );
 *
 * Fluent usage:
 *
 *   Mailer::instance()
 *       ->to( 'user@example.com' )
 *       ->subject( 'Your license key' )
 *       ->body( '<p>Here is your key...</p>' )
 *       ->send();
 *
 * Using a specific provider:
 *
 *   Mailer::instance()->with_provider( 'sendgrid' )->send( $message );
 *
 * @package SmartLicenseServer\Email
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Email\Providers\EmailProviderInterface;
use SmartLicenseServer\Exceptions\EmailTransportException;
use InvalidArgumentException;
use RuntimeException;

defined( 'SMLISER_ABSPATH' ) || exit;

class Mailer {
    /**
     * The active email provider.
     *
     * @var EmailProviderInterface|null
     */
    protected ?EmailProviderInterface $provider = null;

    /**
     * Fluent message being built.
     *
     * Reset after each send().
     *
     * @var array<string, mixed>
     */
    protected array $pending = [];

    /**
     * Class constructor.
     * 
     * @param EmailProviderInterface $provider
     */
    public function __construct( ?EmailProviderInterface $provider = null ) {
        $this->provider = $provider;
    }

    /*
    |-------------------
    | PROVIDER CONTROL
    |-------------------
    */

    /**
     * Return a copy of the mailer using a specific provider.
     *
     * @param string|EmailProviderInterface $provider_id
     * @return static
     * @throws InvalidArgumentException If the provider is not registered.
     */
    public static function with_provider( string|EmailProviderInterface $provider_id ): static {
        $provider = ( $provider_id instanceof EmailProviderInterface ) 
            ? $provider_id
            : EmailProviderCollection::instance()->get_provider_with_settings( $provider_id );

        if ( $provider === null ) {
            throw new InvalidArgumentException(
                "Mailer: provider '{$provider_id}' is not registered."
            );
        }

        $static           = new static( $provider );
        $static->provider = $provider;

        return $static;
    }

    /**
     * Set the active provider directly.
     *
     * Useful for testing — inject a mock provider without touching
     * the EmailProviderCollection.
     *
     * @param EmailProviderInterface $provider
     * @return static Fluent.
     */
    public function set_provider( EmailProviderInterface $provider ): static {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Return the currently active provider, resolving from the collection
     * if none has been set explicitly.
     *
     * @return EmailProviderInterface
     * @throws RuntimeException If no provider is configured.
     */
    public function get_provider(): EmailProviderInterface {
        if ( $this->provider === null ) {
            $this->provider = EmailProviderCollection::instance()
                ->get_provider_with_settings();
        }

        if ( $this->provider === null ) {
            throw new RuntimeException(
                'Mailer: no email provider is configured. '
                . 'Set a default provider via EmailProviderCollection::set_default_provider().'
            );
        }

        return $this->provider;
    }

    /*
    |----------------
    | FLUENT BUILDER
    |----------------
    */

    /**
     * Set the recipient(s).
     *
     * @param string|string[] $to
     * @return static Fluent.
     */
    public function to( string|array $to ): static {
        $this->pending['to'] = $to;
        return $this;
    }

    /**
     * Set the CC recipient(s).
     *
     * @param string|string[] $cc
     * @return static Fluent.
     */
    public function cc( string|array $cc ): static {
        $this->pending['cc'] = $cc;
        return $this;
    }

    /**
     * Set the BCC recipient(s).
     *
     * @param string|string[] $bcc
     * @return static Fluent.
     */
    public function bcc( string|array $bcc ): static {
        $this->pending['bcc'] = $bcc;
        return $this;
    }

    /**
     * Set the sender.
     *
     * Accepts a plain email string or ['email' => '...', 'name' => '...'].
     *
     * @param string|array<string,string> $from
     * @return static Fluent.
     */
    public function from( string|array $from ): static {
        $this->pending['from'] = $from;
        return $this;
    }

    /**
     * Set the email subject.
     *
     * @param string $subject
     * @return static Fluent.
     */
    public function subject( string $subject ): static {
        $this->pending['subject'] = $subject;
        return $this;
    }

    /**
     * Set the email body (HTML).
     *
     * @param string $body
     * @return static Fluent.
     */
    public function body( string $body ): static {
        $this->pending['body'] = $body;
        return $this;
    }

    /**
     * Set the reply-to address.
     *
     * @param string|array<string,string> $reply_to
     * @return static Fluent.
     */
    public function reply_to( string|array $reply_to ): static {
        $this->pending['reply_to'] = $reply_to;
        return $this;
    }

    /**
     * Add an attachment to the pending message.
     *
     * @param string $path     Absolute file path.
     * @param string $filename Filename shown in the email. Defaults to basename.
     * @param string $mime     MIME type. Defaults to application/octet-stream.
     * @return static Fluent.
     */
    public function attach( string $path, string $filename = '', string $mime = 'application/octet-stream' ): static {
        $this->pending['attachments'][] = [
            'type'     => 'path',
            'content'  => $path,
            'filename' => $filename !== '' ? $filename : basename( $path ),
            'mime'     => $mime,
        ];

        return $this;
    }

    /**
     * Add a custom header to the pending message.
     *
     * @param string $name
     * @param string $value
     * @return static Fluent.
     */
    public function header( string $name, string $value ): static {
        $this->pending['headers'][ $name ] = $value;
        return $this;
    }

    /*
    |--------
    | SEND
    |--------
    */

    /**
     * Send an email.
     *
     * Accepts either an explicit EmailMessage or uses the fluent pending
     * data accumulated via the builder methods.
     *
     * Resets the fluent builder state after a successful or failed send.
     *
     * @param EmailMessage|null $message Explicit message, or null to use fluent builder.
     * @return EmailResponse
     * @throws InvalidArgumentException If the message data is invalid.
     * @throws EmailTransportException  On provider send failure.
     * @throws RuntimeException         If no provider is configured.
     */
    public function send( ?EmailMessage $message = null ): EmailResponse {
        if ( $message === null ) {
            $message = $this->build_message_from_pending();
        }

        try {
            return $this->get_provider()->send( $message );
        } finally {
            // Always reset the fluent builder, even if send() throws.
            $this->pending = [];
        }
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Build an EmailMessage from the accumulated fluent builder state.
     *
     * @return EmailMessage
     * @throws InvalidArgumentException If required fields are missing.
     */
    protected function build_message_from_pending(): EmailMessage {
        if ( empty( $this->pending ) ) {
            throw new InvalidArgumentException(
                'Mailer: no message data provided. '
                . 'Use the fluent builder or pass an EmailMessage to send().'
            );
        }

        return new EmailMessage( $this->pending );
    }
}