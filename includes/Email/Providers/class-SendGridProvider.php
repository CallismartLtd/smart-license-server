<?php
/**
 * SendGrid Email Provider.
 *
 * Sends transactional emails via the SendGrid Web API v3.
 * Uses the POST /v3/mail/send endpoint.
 *
 * @see     https://docs.sendgrid.com/api-reference/mail-send/mail-send
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

class SendGridProvider extends AbstractRestEmailProvider {

    protected const API_ENDPOINT = 'https://api.sendgrid.com/v3/mail/send';

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'sendgrid';
    }

    public function get_name(): string {
        return 'SendGrid';
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
                'description' => 'Your SendGrid API key. Requires Mail Send permission.',
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email. Must be a verified sender identity in SendGrid.',
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
            throw new InvalidArgumentException( 'SendGridProvider: "api_key" is required.' );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'SendGridProvider: a valid "from_email" is required.' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the SendGrid API request payload.
     *
     * SendGrid uses a "personalizations" envelope model — each object
     * in the array represents one set of recipients and their overrides.
     * We use a single personalisation for all recipients.
     *
     * @see https://docs.sendgrid.com/api-reference/mail-send/mail-send
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

        // Build the single personalisation envelope.
        $personalisation = [
            'to' => $this->format_address_list( $to ),
        ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $personalisation['cc'] = $this->format_address_list( $cc );
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $personalisation['bcc'] = $this->format_address_list( $bcc );
        }

        $html_body  = $message->get( 'body', '' );
        $text       = $message->get( 'text', '' );

        $payload = [
            'personalizations' => [ $personalisation ],
            'from'             => $this->format_address( $sender['email'], $sender['name'] ),
            'subject'          => $message->get( 'subject', '' ),
            'content'          => [
                [
                    'type'  => 'text/plain',
                    'value' => $text,
                ],
                [
                    'type'  => 'text/html',
                    'value' => $html_body,
                ],
            ],
        ];

        // Reply-To.
        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['reply_to'] = $this->format_address(
                $reply_to['email'],
                $reply_to['name'] ?? ''
            );
        }

        // Attachments.
        $attachments = $message->get( 'attachments', [] );
        if ( ! empty( $attachments ) ) {
            $payload['attachments'] = $this->build_attachments( $attachments );
        }

        // Custom headers.
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
     * Format a single address for the SendGrid API.
     *
     * SendGrid expects: {'email': '...', 'name': '...'}
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
     * Format a list of email strings into SendGrid address objects.
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
     * Build the SendGrid attachment array.
     *
     * SendGrid expects:
     * [
     *     'content'     => string,  // base64-encoded
     *     'type'        => string,  // MIME type
     *     'filename'    => string,
     *     'disposition' => 'attachment',
     * ]
     *
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return array<int, array{content: string, type: string, filename: string, disposition: string}>
     */
    protected function build_attachments( array $attachments ): array {
        return array_values( array_map(
            fn( array $a ) => [
                'content'     => $a['content'],
                'type'        => $a['mime'],
                'filename'    => $a['filename'],
                'disposition' => 'attachment',
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
     * POST the payload to the SendGrid API.
     *
     * SendGrid authenticates via Bearer token in the Authorization header.
     * On success it returns HTTP 202 Accepted with an empty body.
     * The Message-ID is carried in the X-Message-Id response header.
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
     * Parse the SendGrid API response into an EmailResponse.
     *
     * SendGrid returns:
     *   202 Accepted  — success, empty body, Message-ID in X-Message-Id header.
     *   4xx/5xx       — failure, JSON body with 'errors' array.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        if ( $response->is_error() ) {
            $data   = $response->json() ?? [];
            $errors = $data['errors'] ?? [];
            $error  = ! empty( $errors )
                ? implode( ' | ', array_column( $errors, 'message' ) )
                : 'Unknown SendGrid API error.';

            throw new EmailTransportException(
                "SendGridProvider: API returned HTTP {$response->status_code} — {$error}"
            );
        }

        // SendGrid returns 202 with no body — message ID is in the response header.
        $message_id = $response->get_header( 'x-message-id' ) ?? '';

        return EmailResponse::ok( $message_id, $response->json() ?? [] );
    }
}