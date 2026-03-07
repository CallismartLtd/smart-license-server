<?php
/**
 * Brevo Email Provider
 *
 * Sends transactional emails via the Brevo REST API v3.
 * Uses the POST /v3/smtp/email endpoint with htmlContent
 * and textContent, supporting attachments and custom headers.
 *
 * @see     https://developers.brevo.com/reference/sendtransacemail
 * @package SmartLicenseServer\Email\Providers
 * @since 0.2.0
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

class BrevoProvider extends AbstractRestEmailProvider {

    protected const API_ENDPOINT = 'https://api.brevo.com/v3/smtp/email';

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'brevo';
    }

    public function get_name(): string {
        return 'Brevo';
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
                'description' => 'Your Brevo API key. Found under SMTP & API in your Brevo account.',
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email address. Must be a verified sender in Brevo.',
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
        $from_email = trim( $settings['from_email'] ?? '' );

        if ( empty( $api_key ) ) {
            throw new InvalidArgumentException( 'BrevoProvider: "api_key" is required.' );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'BrevoProvider: a valid "from_email" is required.' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the Brevo API request payload from the EmailMessage.
     *
     * @param EmailMessage $message
     * @return array<string, mixed>
     * @throws InvalidArgumentException On invalid sender or recipient addresses.
     */
    protected function build_payload( EmailMessage $message ): array {
        $sender = $this->resolve_sender( $message );
        $this->validate_sender( $sender['email'] );

        $to  = $message->get( 'to',  [] );
        $cc  = $message->get( 'cc',  [] );
        $bcc = $message->get( 'bcc', [] );

        $this->validate_recipients( $to );

        $payload = [
            'sender'      => $this->format_address( $sender['email'], $sender['name'] ),
            'to'          => $this->format_address_list( $to ),
            'subject'     => $message->get( 'subject', '' ),
            'htmlContent' => $message->get( 'body', '' ),
            'textContent' => $message->get( 'text', '' ),
        ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $payload['cc'] = $this->format_address_list( $cc );
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $payload['bcc'] = $this->format_address_list( $bcc );
        }

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['replyTo'] = $this->format_address(
                $reply_to['email'],
                $reply_to['name'] ?? ''
            );
        }

        $attachments = $message->get( 'attachments', [] );
        if ( ! empty( $attachments ) ) {
            $payload['attachment'] = $this->build_attachments( $attachments );
        }

        $headers = $message->get( 'headers', [] );
        if ( ! empty( $headers ) ) {
            $payload['headers'] = $headers;
        }

        return $payload;
    }

    /*
    |------------
    | FORMATTING
    |------------
    */

    /**
     * Format a single address for the Brevo API.
     *
     * Brevo expects: {'email': '...', 'name': '...'}
     *
     * @param string $email
     * @param string $name
     * @return array{email: string, name?: string}
     */
    protected function format_address( string $email, string $name = '' ): array {
        $address = [ 'email' => $email ];

        if ( $name !== '' ) {
            $address['name'] = $name;
        }

        return $address;
    }

    /**
     * Format a list of plain email strings into Brevo address objects.
     *
     * @param string[] $addresses
     * @return array<int, array{email: string}>
     */
    protected function format_address_list( array $addresses ): array {
        return array_values(
            array_map(
                fn( string $email ) => $this->format_address( $email ),
                $addresses
            )
        );
    }

    /**
     * Build the Brevo attachment array.
     *
     * Brevo expects:
     * [
     *     'name'    => string,  // filename
     *     'content' => string,  // base64-encoded
     * ]
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{name: string, content: string}>
     */
    protected function build_attachments( array $attachments ): array {
        return array_values( array_map(
            fn( array $a ) => [
                'name'    => $a['filename'],
                'content' => $a['content'],
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
     * POST the payload to the Brevo API.
     *
     * Brevo authenticates via the api-key header.
     * On success it returns HTTP 201 with body: {"messageId": "<...>"}
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function dispatch( array $payload ): EmailResponse {
        $request = HttpRequest::post( self::API_ENDPOINT )
            ->with_json( $payload )
            ->with_header( 'api-key', $this->settings['api_key'] );

        $response = $this->http_client->send( $request );

        return $this->parse_response( $response );
    }

    /*
    |-----------------
    | RESPONSE PARSER
    |-----------------
    */

    /**
     * Parse the Brevo API response into an EmailResponse.
     *
     * Brevo returns:
     *   201 Created  — success, JSON body with 'messageId'.
     *   4xx/5xx      — failure, JSON body with 'code' and 'message'.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        $data = $response->json() ?? [];

        if ( $response->is_error() ) {
            $error = $data['message'] ?? $data['code'] ?? 'Unknown Brevo API error.';
            throw new EmailTransportException(
                "BrevoProvider: API returned HTTP {$response->status_code} — {$error}"
            );
        }

        return EmailResponse::ok( $data['messageId'] ?? '', $data );
    }
}