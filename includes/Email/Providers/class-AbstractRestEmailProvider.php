<?php
/**
 * Abstract REST Email Provider.
 *
 * Base class for all REST API-based email providers.
 * Handles the common concerns shared across every provider:
 *
 *  - HttpClient injection and auto-detection
 *  - Settings storage and configured guard
 *  - Sender and recipient validation
 *  - Shared dispatch → parse_response flow contract
 *
 * Concrete providers must implement:
 *  - get_id()
 *  - get_name()
 *  - get_settings_schema()
 *  - set_settings()         — call parent::set_settings() after own validation
 *  - build_payload()        — build the provider-specific request payload
 *  - dispatch()             — build and send the HttpRequest
 *  - parse_response()       — map HttpResponse → EmailResponse
 *
 * @package SmartLicenseServer\Email\Providers
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;
use SmartLicenseServer\Http\HttpClient;
use SmartLicenseServer\Http\HttpResponse;
use InvalidArgumentException;
use SmartLicenseServer\Email\EmailProviderCollection;

defined( 'SMLISER_ABSPATH' ) || exit;

abstract class AbstractRestEmailProvider implements EmailProviderInterface {

    /**
     * Holds the provider configuration set via set_settings().
     *
     * @var array<string, mixed>
     */
    protected array $settings = [];

    /**
     * Whether set_settings() has been called with valid configuration.
     *
     * @var bool
     */
    protected bool $configured = false;

    /**
     * HTTP client used to dispatch API requests.
     *
     * @var HttpClient
     */
    protected HttpClient $http_client;

    /*
    |-------------
    | CONSTRUCTOR
    |-------------
    */

    /**
     * Constructor.
     *
     * Accepts an optional HttpClient for testing or custom adapter use.
     * Defaults to auto-detected adapter when none is provided.
     *
     * @param HttpClient|null $http_client
     */
    public function __construct( ?HttpClient $http_client = null ) {
        $this->http_client = $http_client ?? new HttpClient();
    }

    /*
    |----------------------
    | INTERFACE — SETTINGS
    |----------------------
    */

    /**
     * Store validated settings and mark the provider as configured.
     *
     * Concrete providers should perform their own validation first,
     * then delegate here to persist the settings:
     *
     *   public function set_settings( array $settings ): void {
     *       // provider-specific validation...
     *       parent::set_settings( $settings );
     *   }
     *
     * @param array<string, mixed> $settings
     */
    public function set_settings( array $settings ): void {
        $this->settings   = $settings;
        $this->configured = true;
    }

    /*
    |--------------------
    | INTERFACE — SEND
    |--------------------
    */

    /**
     * Send an email via the provider's REST API.
     *
     * Enforces the configured guard, then delegates to the provider's
     * build_payload() → dispatch() → parse_response() pipeline.
     *
     * @param EmailMessage $message
     * @return EmailResponse
     * @throws InvalidArgumentException If called before set_settings().
     * @throws EmailTransportException  On API or transport failure.
     */
    public function send( EmailMessage $message ): EmailResponse {
        if ( ! $this->configured ) {
            throw new InvalidArgumentException(
                static::class . ': call set_settings() before send().'
            );
        }

        $payload = $this->build_payload( $message );
        return $this->dispatch( $payload );
    }

    /*
    |------------------
    | ABSTRACT METHODS
    |------------------
    */

    /**
     * Build the provider-specific API request payload.
     *
     * @param EmailMessage $message
     * @return array<string, mixed>
     */
    abstract protected function build_payload( EmailMessage $message ): array;

    /**
     * Dispatch the payload to the provider's API endpoint.
     *
     * Responsible for constructing the HttpRequest (method, headers,
     * auth) and calling $this->http_client->send().
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    abstract protected function dispatch( array $payload ): EmailResponse;

    /**
     * Map a raw HttpResponse from the API into a normalised EmailResponse.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException On non-2xx response.
     */
    abstract protected function parse_response( HttpResponse $response ): EmailResponse;

    /*
    |-------------------
    | SHARED VALIDATION
    |-------------------
    */

    /**
     * Assert the envelope sender address is valid.
     *
     * @param string $email
     * @throws InvalidArgumentException
     */
    protected function validate_sender( string $email ): void {
        if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException(
                static::class . ": invalid sender address '{$email}'."
            );
        }
    }

    /**
     * Assert a recipient list is non-empty and contains only valid addresses.
     *
     * @param string[] $recipients
     * @param string   $field      Field name used in error messages.
     * @throws InvalidArgumentException
     */
    protected function validate_recipients( array $recipients, string $field = 'to' ): void {
        if ( empty( $recipients ) ) {
            throw new InvalidArgumentException(
                static::class . ": '{$field}' must contain at least one recipient."
            );
        }

        foreach ( $recipients as $address ) {
            if ( ! filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
                throw new InvalidArgumentException(
                    static::class . ": invalid address '{$address}' in '{$field}'."
                );
            }
        }
    }

    /*
    |------------------
    | SHARED UTILITIES
    |------------------
    */

    /**
     * Resolve the sender email and name from the message or settings fallback.
     *
     * @param EmailMessage $message
     * @return array{email: string, name: string}
     */
    protected function resolve_sender( EmailMessage $message ): array {
        $from               = $message->get( 'from' ) ?? [];
        $collection         = EmailProviderCollection::instance();
        $default_from_email = $collection->get_default_sender_email();
        $default_from_name  = $collection->get_default_sender_name();
        return [
            'email' => $from['email'] ?? $this->settings['from_email'] ?? $default_from_email,
            'name'  => $from['name']  ?? $this->settings['from_name']  ?? $default_from_name,
        ];
    }

    /**
     * Build the normalised attachment payload from EmailMessage descriptors.
     *
     * Returns a provider-agnostic array of:
     * [
     *     'filename' => string,
     *     'content'  => string,  // always base64-encoded
     *     'mime'     => string,
     * ]
     *
     * Subclasses can call this and re-map the keys to match their API's
     * expected shape.
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{filename: string, content: string, mime: string}>
     */
    protected function build_base_attachments( array $attachments ): array {
        $result = [];

        foreach ( $attachments as $attachment ) {
            $filename = $attachment['filename'] ?? '';
            $mime     = $attachment['mime']     ?? 'application/octet-stream';

            if ( $attachment['type'] === 'path' ) {
                $filepath = $attachment['content'] ?? '';

                if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
                    continue;
                }

                $encoded = base64_encode( file_get_contents( $filepath ) );
            } else {
                $encoded = $attachment['content'] ?? '';
            }

            $result[] = [
                'filename' => $filename,
                'content'  => $encoded,
                'mime'     => $mime,
            ];
        }

        return $result;
    }
}