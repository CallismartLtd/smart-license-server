<?php
/**
 * Resend Email Provider.
 *
 * Sends transactional emails via the Resend API.
 * Uses the POST /emails endpoint.
 *
 * @see     https://resend.com/docs/api-reference/emails/send-email
 * @package SmartLicenseServer\Email\Providers
 * @since   0.2.0
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

class ResendProvider extends AbstractRestEmailProvider {

    protected const API_ENDPOINT = 'https://api.resend.com/emails';

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'resend';
    }

    public static function get_name(): string {
        return 'Resend';
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
                'description' => 'Your Resend API key. Found under API Keys in your Resend dashboard.',
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email address. Must be from a verified domain in Resend.',
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
            throw new InvalidArgumentException( 'ResendProvider: "api_key" is required.' );
        }

        if ( ! str_starts_with( $api_key, 're_' ) ) {
            throw new InvalidArgumentException(
                'ResendProvider: "api_key" appears invalid — Resend API keys begin with "re_".'
            );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'ResendProvider: a valid "from_email" is required.' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the Resend API request payload.
     *
     * Resend has the most minimal and consistent API shape of all providers —
     * flat JSON, snake_case keys, arrays for all recipient fields, and a
     * single 'from' string in RFC 2822 format.
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
            'to'      => $to,
            'subject' => $message->get( 'subject', '' ),
            'html'    => $html_body,
            'text'    => $text,
        ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $payload['cc'] = $cc;
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $payload['bcc'] = $bcc;
        }

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['reply_to'] = $this->format_address_string(
                $reply_to['email'],
                $reply_to['name'] ?? ''
            );
        }

        $attachments = $message->get( 'attachments', [] );
        if ( ! empty( $attachments ) ) {
            $payload['attachments'] = $this->build_attachments( $attachments );
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
     * Format a single address as an RFC 2822 string.
     *
     * Resend expects the 'from' field as a plain RFC 2822 string:
     *   "Display Name <email@example.com>" or "email@example.com"
     *
     * All other address fields (to, cc, bcc) accept plain string arrays.
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
     * Build the Resend attachment array.
     *
     * Resend expects:
     * [
     *     'filename' => string,  // filename shown in the email
     *     'content'  => string,  // base64-encoded content
     * ]
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{filename: string, content: string}>
     */
    protected function build_attachments( array $attachments ): array {
        return array_values( array_map(
            fn( array $a ) => [
                'filename' => $a['filename'],
                'content'  => $a['content'],
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
     * POST the payload to the Resend API.
     *
     * Resend authenticates via Bearer token in the Authorization header.
     * On success it returns HTTP 200 with a JSON body containing 'id'.
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function dispatch( array $payload ): EmailResponse {
        $request = HttpRequest::post( self::API_ENDPOINT )
            ->with_json( $payload )
            ->with_header( 'Authorization', 'Bearer ' . $this->settings['api_key'] );

        $response = $this->http_client->send( $request );

        return $this->parse_response( $response );
    }

    /*
    |-----------------
    | RESPONSE PARSER
    |-----------------
    */

    /**
     * Parse the Resend API response into an EmailResponse.
     *
     * Resend returns:
     *   200 OK   — success, JSON body with 'id'.
     *   4xx/5xx  — failure, JSON body with 'name', 'message', and 'statusCode'.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        $data = $response->json() ?? [];

        if ( $response->is_error() ) {
            $name    = $data['name']    ?? 'Error';
            $message = $data['message'] ?? 'Unknown Resend API error.';
            throw new EmailTransportException(
                "ResendProvider: API returned HTTP {$response->status_code} ({$name}) — {$message}"
            );
        }

        return EmailResponse::ok( $data['id'] ?? '', $data );
    }
}