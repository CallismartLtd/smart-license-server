<?php
/**
 * Mailgun Email Provider.
 *
 * Sends transactional emails via the Mailgun Messages API v3.
 * Uses the POST /v3/{domain}/messages endpoint.
 *
 * @see     https://documentation.mailgun.com/docs/mailgun/api-reference/openapi-final/tag/Messages
 * @package SmartLicenseServer\Email\Providers
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;
use SmartLicenseServer\Http\HttpRequest;
use SmartLicenseServer\Http\HttpResponse;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

class MailgunProvider extends AbstractRestEmailProvider {

    /**
     * Mailgun API base URL.
     *
     * EU customers must use the EU regional endpoint:
     * https://api.eu.mailgun.net/v3/{domain}/messages
     *
     * Region is resolved at dispatch time from the 'region' setting.
     */
    protected const API_BASE_US = 'https://api.mailgun.net/v3';
    protected const API_BASE_EU = 'https://api.eu.mailgun.net/v3';

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'mailgun';
    }

    public function get_name(): string {
        return 'Mailgun';
    }

    /*
    |------------------------------
    | INTERFACE — SETTINGS SCHEMA
    |------------------------------
    */

    public function get_settings_schema(): array {
        return [
            'api_key' => [
                'type'        => 'password',
                'label'       => 'API Key',
                'required'    => true,
                'description' => 'Your Mailgun private API key. Found under API Security in your Mailgun account.',
            ],
            'domain' => [
                'type'        => 'text',
                'label'       => 'Sending Domain',
                'required'    => true,
                'description' => 'Your verified Mailgun sending domain. e.g. mg.yourdomain.com',
            ],
            'region' => [
                'type'        => 'select',
                'label'       => 'API Region',
                'required'    => false,
                'options'     => [
                    'us' => 'US (default)',
                    'eu' => 'EU',
                ],
                'description' => 'Use EU region if your Mailgun account is hosted in Europe.',
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email address.',
            ],
            'from_name' => [
                'type'        => 'text',
                'label'       => 'From Name',
                'required'    => false,
                'description' => 'Default sender display name.',
            ],
        ];
    }

    /*
    |----------------------
    | INTERFACE — SETTINGS
    |----------------------
    */

    /**
     * Validate and store provider configuration.
     *
     * @param array<string, mixed> $settings
     * @throws InvalidArgumentException
     */
    public function set_settings( array $settings ): void {
        $api_key    = trim( $settings['api_key']    ?? '' );
        $domain     = trim( $settings['domain']     ?? '' );
        $from_email = trim( $settings['from_email'] ?? '' );
        $region     = trim( $settings['region']     ?? 'us' );

        if ( empty( $api_key ) ) {
            throw new InvalidArgumentException( 'MailgunProvider: "api_key" is required.' );
        }

        if ( empty( $domain ) ) {
            throw new InvalidArgumentException( 'MailgunProvider: "domain" is required.' );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'MailgunProvider: a valid "from_email" is required.' );
        }

        if ( ! in_array( $region, [ 'us', 'eu' ], true ) ) {
            throw new InvalidArgumentException( 'MailgunProvider: "region" must be "us" or "eu".' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the Mailgun API request payload.
     *
     * Mailgun's Messages API accepts multipart/form-data rather than JSON.
     * Addresses are passed as RFC 2822 formatted strings rather than objects.
     * Multiple recipients are passed as comma-separated values.
     *
     * @param EmailMessage $message
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    protected function build_payload( EmailMessage $message ): array {
        $sender = $this->resolve_sender( $message );
        $this->validate_sender( $sender['email'] );

        $to  = $message->get( 'to',  [] );
        $cc  = $message->get( 'cc',  [] );
        $bcc = $message->get( 'bcc', [] );

        $this->validate_recipients( $to );

        $html_body  = $message->get( 'body', '' );
        $text       = $message->get( 'text', '' );

        $payload = [
            'from'    => $this->format_address_string( $sender['email'], $sender['name'] ),
            'to'      => $this->format_address_string_list( $to ),
            'subject' => $message->get( 'subject', '' ),
            'html'    => $html_body,
            'text'    => $text,
        ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $payload['cc'] = $this->format_address_string_list( $cc );
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $payload['bcc'] = $this->format_address_string_list( $bcc );
        }

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['h:Reply-To'] = $this->format_address_string(
                $reply_to['email'],
                $reply_to['name'] ?? ''
            );
        }

        // Custom headers are prefixed with 'h:' per Mailgun convention.
        foreach ( $message->get( 'headers', [] ) as $name => $value ) {
            $payload[ "h:{$name}" ] = $value;
        }

        return $payload;
    }

    /*
    |------------
    | FORMATTING
    |------------
    */

    /**
     * Format a single address as an RFC 2822 string.
     *
     * Mailgun expects plain strings rather than objects:
     *   "Display Name <email@example.com>" or "email@example.com"
     *
     * @param string $email
     * @param string $name
     * @return string
     */
    protected function format_address_string( string $email, string $name = '' ): string {
        return $name !== ''
            ? "{$name} <{$email}>"
            : $email;
    }

    /**
     * Format a list of email addresses as a comma-separated RFC 2822 string.
     *
     * @param string[] $addresses
     * @return string
     */
    protected function format_address_string_list( array $addresses ): string {
        return implode( ', ', array_map(
            fn( string $email ) => $this->format_address_string( $email ),
            $addresses
        ) );
    }

    /**
     * Build the Mailgun attachment array.
     *
     * Mailgun multipart/form-data attachments are posted under the 'attachment'
     * key as an array of ['filename' => ..., 'content' => ..., 'mime' => ...].
     * The HTTP client encodes these as file parts in the multipart body.
     *
     * Since our HttpClient sends JSON, we encode content as base64 and
     * use Mailgun's inline base64 attachment format via the 'inline' key.
     * For standard attachments the content is decoded back to binary and
     * posted under 'attachment'.
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{filename: string, data: string, contentType: string}>
     */
    protected function build_attachments( array $attachments ): array {
        return array_values( array_map(
            fn( array $a ) => [
                'filename'    => $a['filename'],
                'data'        => $a['content'],
                'contentType' => $a['mime'],
            ],
            $this->build_base_attachments( $attachments )
        ) );
    }

    /*
    |----------
    | DISPATCH
    |----------
    */

    /**
     * POST the payload to the Mailgun API.
     *
     * Mailgun authenticates via HTTP Basic Auth using the literal
     * string "api" as the username and the API key as the password.
     *
     * The endpoint includes the sending domain:
     *   https://api.mailgun.net/v3/{domain}/messages
     *
     * Mailgun's API accepts application/x-www-form-urlencoded rather
     * than JSON — the payload is encoded accordingly.
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function dispatch( array $payload ): EmailResponse {
        $domain   = $this->settings['domain'];
        $region   = $this->settings['region'] ?? 'us';
        $base_url = $region === 'eu' ? self::API_BASE_EU : self::API_BASE_US;
        $endpoint = "{$base_url}/{$domain}/messages";

        // Mailgun uses HTTP Basic Auth: username 'api', password = API key.
        $credentials = base64_encode( 'api:' . $this->settings['api_key'] );

        $attachments = $payload['attachments'] ?? [];
        unset( $payload['attachments'] );

        $request = HttpRequest::post( $endpoint, http_build_query( $payload ) )
            ->with_header( 'Authorization', "Basic {$credentials}" )
            ->with_header( 'Content-Type', 'application/x-www-form-urlencoded' );

        $response = $this->http_client->send( $request );

        return $this->parse_response( $response );
    }

    /*
    |-----------------
    | RESPONSE PARSER
    |-----------------
    */

    /**
     * Parse the Mailgun API response into an EmailResponse.
     *
     * Mailgun returns:
     *   200 OK  — success, JSON body with 'id' and 'message'.
     *   4xx     — failure, JSON body with 'message'.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        $data = $response->json() ?? [];

        if ( $response->is_error() ) {
            $error = $data['message'] ?? 'Unknown Mailgun API error.';
            throw new EmailTransportException(
                "MailgunProvider: API returned HTTP {$response->status_code} — {$error}"
            );
        }

        // Mailgun returns the message ID wrapped in angle brackets — strip them.
        $message_id = trim( $data['id'] ?? '', '<>' );

        return EmailResponse::ok( $message_id, $data );
    }
}