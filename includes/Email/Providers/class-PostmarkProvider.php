<?php
/**
 * Postmark Email Provider.
 *
 * Sends transactional emails via the Postmark API.
 * Uses the POST /email endpoint.
 *
 * @see     https://postmarkapp.com/developer/api/email-api
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

class PostmarkProvider extends AbstractRestEmailProvider {

    protected const API_ENDPOINT = 'https://api.postmarkapp.com/email';

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'postmark';
    }

    public static function get_name(): string {
        return 'Postmark';
    }

    /*
    |------------------------------
    | INTERFACE — SETTINGS SCHEMA
    |------------------------------
    */

    public function get_settings_schema(): array {
        return [
            'server_token' => [
                'type'        => 'password',
                'label'       => 'Server API Token',
                'required'    => true,
                'description' => 'Your Postmark Server API Token. Found under your server\'s API Tokens tab.',
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email address. Must be a verified Sender Signature in Postmark.',
            ],
            'from_name' => [
                'type'        => 'text',
                'label'       => 'From Name',
                'required'    => false,
                'description' => 'Default sender display name.',
            ],
            'message_stream' => [
                'type'        => 'text',
                'label'       => 'Message Stream',
                'required'    => false,
                'description' => 'Postmark message stream ID. Defaults to "outbound" if not specified.',
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
        $server_token = trim( $settings['server_token'] ?? '' );
        $from_email   = trim( $settings['from_email']   ?? '' );

        if ( empty( $server_token ) ) {
            throw new InvalidArgumentException( 'PostmarkProvider: "server_token" is required.' );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'PostmarkProvider: a valid "from_email" is required.' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the Postmark API request payload.
     *
     * Postmark uses a flat JSON structure — no personalizations envelope,
     * no nested content array. Recipients are passed as comma-separated
     * RFC 2822 address strings rather than arrays of objects.
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
            'From'          => $this->format_address_string( $sender['email'], $sender['name'] ),
            'To'            => $this->format_address_string_list( $to ),
            'Subject'       => $message->get( 'subject', '' ),
            'HtmlBody'      => $html_body,
            'TextBody'      => $text,
            'MessageStream' => $this->settings['message_stream'] ?? 'outbound',
        ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $payload['Cc'] = $this->format_address_string_list( $cc );
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $payload['Bcc'] = $this->format_address_string_list( $bcc );
        }

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['ReplyTo'] = $this->format_address_string(
                $reply_to['email'],
                $reply_to['name'] ?? ''
            );
        }

        $attachments = $message->get( 'attachments', [] );
        if ( ! empty( $attachments ) ) {
            $payload['Attachments'] = $this->build_attachments( $attachments );
        }

        // Postmark passes custom headers as an array of {'Name': ..., 'Value': ...} objects.
        $headers = $message->get( 'headers', [] );
        if ( ! empty( $headers ) ) {
            $payload['Headers'] = $this->format_headers( $headers );
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
     * Postmark expects plain strings for all address fields:
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
     * Build the Postmark attachment array.
     *
     * Postmark expects:
     * [
     *     'Name'        => string,  // filename
     *     'Content'     => string,  // base64-encoded
     *     'ContentType' => string,  // MIME type
     * ]
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{Name: string, Content: string, ContentType: string}>
     */
    protected function build_attachments( array $attachments ): array {
        return array_values( array_map(
            fn( array $a ) => [
                'Name'        => $a['filename'],
                'Content'     => $a['content'],
                'ContentType' => $a['mime'],
            ],
            $this->build_base_attachments( $attachments )
        ) );
    }

    /**
     * Format custom headers into Postmark's expected shape.
     *
     * Postmark expects headers as an array of objects:
     * [{'Name': 'X-Custom', 'Value': 'foo'}, ...]
     *
     * @param array<string, string> $headers
     * @return array<int, array{Name: string, Value: string}>
     */
    protected function format_headers( array $headers ): array {
        return array_values( array_map(
            fn( string $name, string $value ) => [
                'Name'  => $name,
                'Value' => $value,
            ],
            array_keys( $headers ),
            $headers
        ) );
    }

    /*
    |----------
    | DISPATCH
    |----------
    */

    /**
     * POST the payload to the Postmark API.
     *
     * Postmark authenticates via the X-Postmark-Server-Token header.
     * On success it returns HTTP 200 with a JSON body containing
     * 'MessageID' and 'SubmittedAt'.
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function dispatch( array $payload ): EmailResponse {
        $request = HttpRequest::post( self::API_ENDPOINT )
            ->with_json( $payload )
            ->with_header( 'X-Postmark-Server-Token', $this->settings['server_token'] );

        $response = $this->http_client->send( $request );

        return $this->parse_response( $response );
    }

    /*
    |-----------------
    | RESPONSE PARSER
    |-----------------
    */

    /**
     * Parse the Postmark API response into an EmailResponse.
     *
     * Postmark returns:
     *   200 OK   — success, JSON body with 'MessageID', 'SubmittedAt', 'ErrorCode' = 0.
     *   422      — API-level error (bad data), JSON body with 'ErrorCode' and 'Message'.
     *   500      — server error.
     *
     * Note: Postmark returns 200 even for some soft failures — an ErrorCode
     * of 0 is the only reliable success signal alongside the HTTP status.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        $data = $response->json() ?? [];

        if ( $response->is_error() ) {
            $error = $data['Message'] ?? 'Unknown Postmark API error.';
            $code  = $data['ErrorCode'] ?? $response->status_code;
            throw new EmailTransportException(
                "PostmarkProvider: API returned HTTP {$response->status_code} (ErrorCode: {$code}) — {$error}"
            );
        }

        // Guard against a 200 response that still carries a non-zero ErrorCode.
        $error_code = (int) ( $data['ErrorCode'] ?? 0 );
        if ( $error_code !== 0 ) {
            $error = $data['Message'] ?? 'Unknown Postmark API error.';
            throw new EmailTransportException(
                "PostmarkProvider: send rejected (ErrorCode: {$error_code}) — {$error}"
            );
        }

        return EmailResponse::ok( $data['MessageID'] ?? '', $data );
    }
}