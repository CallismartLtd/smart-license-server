<?php
/**
 * Amazon SES Email Provider.
 *
 * Sends transactional emails via the Amazon SES API v2.
 * Uses the POST /v2/email/outbound-emails endpoint.
 *
 * Authenticates using AWS Signature Version 4 — no SDK required.
 *
 * @see     https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html
 * @package SmartLicenseServer\Email\Providers
 * @since   0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;
use SmartLicenseServer\Http\AwsSignatureV4;
use SmartLicenseServer\Http\HttpRequest;
use SmartLicenseServer\Http\HttpResponse;
use InvalidArgumentException;

defined( 'SMLISER_ABSPATH' ) || exit;

class AmazonSESProvider extends AbstractRestEmailProvider {

    /**
     * SES API v2 endpoint template.
     * Region placeholder is replaced at dispatch time.
     */
    protected const API_ENDPOINT = 'https://email.{region}.amazonaws.com/v2/email/outbound-emails';

    /**
     * Supported AWS regions for SES v2.
     *
     * @var string[]
     */
    protected const SUPPORTED_REGIONS = [
        'us-east-1',
        'us-east-2',
        'us-west-1',
        'us-west-2',
        'eu-west-1',
        'eu-west-2',
        'eu-west-3',
        'eu-central-1',
        'eu-north-1',
        'ap-south-1',
        'ap-northeast-1',
        'ap-northeast-2',
        'ap-northeast-3',
        'ap-southeast-1',
        'ap-southeast-2',
        'ca-central-1',
        'sa-east-1',
        'me-south-1',
        'af-south-1',
    ];

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'amazon_ses';
    }

    public static function get_name(): string {
        return 'Amazon SES';
    }

    /*
    |------------------------------
    | INTERFACE — SETTINGS SCHEMA
    |------------------------------
    */

    public static function get_settings_schema(): array {
        return [
            'access_key' => [
                'type'        => 'text',
                'label'       => 'AWS Access Key ID',
                'required'    => true,
                'description' => 'Your AWS IAM Access Key ID with SES send permissions.',
            ],
            'secret_key' => [
                'type'        => 'password',
                'label'       => 'AWS Secret Access Key',
                'required'    => true,
                'description' => 'Your AWS IAM Secret Access Key.',
            ],
            'region' => [
                'type'          => 'select',
                'label'         => 'AWS Region',
                'required'      => true,
                'description'   => 'AWS region where your SES is configured. e.g. us-east-1',
                'options'       => \array_combine(
                    static::SUPPORTED_REGIONS, 
                    array_map( 
                        static fn ( $v) => ucwords( \str_replace( '-', ' ', $v ) ), 
                        static::SUPPORTED_REGIONS
                    ) 
                )
            ],
            'from_email' => [
                'type'        => 'text',
                'label'       => 'From Email',
                'required'    => true,
                'description' => 'Default sender email address. Must be verified in Amazon SES.',
            ],
            'from_name' => [
                'type'        => 'text',
                'label'       => 'From Name',
                'required'    => false,
                'description' => 'Default sender display name.',
            ],
            'configuration_set' => [
                'type'        => 'text',
                'label'       => 'Configuration Set Name',
                'required'    => false,
                'description' => 'Optional SES configuration set for tracking and event publishing.',
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
        $access_key = trim( $settings['access_key'] ?? '' );
        $secret_key = trim( $settings['secret_key'] ?? '' );
        $region     = trim( $settings['region']     ?? '' );
        $from_email = trim( $settings['from_email'] ?? '' );

        if ( empty( $access_key ) ) {
            throw new InvalidArgumentException( 'AmazonSESProvider: "access_key" is required.' );
        }

        if ( empty( $secret_key ) ) {
            throw new InvalidArgumentException( 'AmazonSESProvider: "secret_key" is required.' );
        }

        if ( empty( $region ) ) {
            throw new InvalidArgumentException( 'AmazonSESProvider: "region" is required.' );
        }

        if ( ! in_array( $region, self::SUPPORTED_REGIONS, true ) ) {
            throw new InvalidArgumentException(
                "AmazonSESProvider: unsupported region '{$region}'. "
                . 'Supported regions: ' . implode( ', ', self::SUPPORTED_REGIONS ) . '.'
            );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'AmazonSESProvider: a valid "from_email" is required.' );
        }

        parent::set_settings( $settings );
    }

    /*
    |-----------------
    | PAYLOAD BUILDER
    |-----------------
    */

    /**
     * Build the SES API v2 request payload.
     *
     * SES v2 uses a deeply nested JSON structure with a 'Destination'
     * envelope for recipients and a 'Content' block for the message body.
     * All address fields accept plain email strings.
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

        $html_body = $message->get( 'body', '' );

        // Build the Destination envelope.
        $destination = [ 'ToAddresses' => $to ];

        if ( ! empty( $cc ) ) {
            $this->validate_recipients( $cc, 'cc' );
            $destination['CcAddresses'] = $cc;
        }

        if ( ! empty( $bcc ) ) {
            $this->validate_recipients( $bcc, 'bcc' );
            $destination['BccAddresses'] = $bcc;
        }

        // Build the message content block.
        $content = [
            'Simple' => [
                'Subject' => [
                    'Data'    => $message->get( 'subject', '' ),
                    'Charset' => 'UTF-8',
                ],
                'Body' => [
                    'Text' => [
                        'Data'    => $message->get( 'text', '' ),
                        'Charset' => 'UTF-8',
                    ],
                    'Html' => [
                        'Data'    => $html_body,
                        'Charset' => 'UTF-8',
                    ],
                ],
            ],
        ];

        // Attachments switch the content type from 'Simple' to 'Raw'.
        $attachments = $message->get( 'attachments', [] );
        if ( ! empty( $attachments ) ) {
            unset( $content['Simple'] );
            $content['Raw'] = [
                'Data' => base64_encode(
                    $this->build_raw_mime( $message, $sender, $html_body, $attachments )
                ),
            ];
        }

        $payload = [
            'FromEmailAddress' => $this->format_address_string( $sender['email'], $sender['name'] ),
            'Destination'      => $destination,
            'Content'          => $content,
        ];

        // Reply-To.
        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $payload['ReplyToAddresses'] = [
                $this->format_address_string( $reply_to['email'], $reply_to['name'] ?? '' ),
            ];
        }

        // Optional configuration set for tracking.
        $config_set = $this->settings['configuration_set'] ?? '';
        if ( $config_set !== '' ) {
            $payload['ConfigurationSetName'] = $config_set;
        }

        // Custom email headers.
        $headers = $message->get( 'headers', [] );
        if ( ! empty( $headers ) ) {
            $payload['EmailTags'] = $this->format_headers( $headers );
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
     * Format custom headers into SES EmailTags shape.
     *
     * SES EmailTags expect: [{'Name': ..., 'Value': ...}, ...]
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

    /**
     * Build a raw MIME message string for sends with attachments.
     *
     * SES v2 requires the entire MIME message to be base64-encoded
     * and submitted under Content.Raw.Data when attachments are present.
     * The structure mirrors the SMTP payload builder.
     *
     * @param EmailMessage                                                                      $message
     * @param array{email: string, name: string}                                               $sender
     * @param string                                                                            $html_body
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @return string
     */
    protected function build_raw_mime(
        EmailMessage $message,
        array        $sender,
        string       $html_body,
        array        $attachments
    ): string {
        $eol             = "\r\n";
        $mixed_boundary  = 'mixed-' . md5( uniqid( '', true ) );
        $alt_boundary    = 'alt-'   . md5( uniqid( '', true ) );

        $to  = implode( ', ', $message->get( 'to',  [] ) );
        $cc  = $message->get( 'cc', [] );

        // Headers.
        $mime  = "From: "    . $this->format_address_string( $sender['email'], $sender['name'] ) . $eol;
        $mime .= "To: {$to}" . $eol;

        if ( ! empty( $cc ) ) {
            $mime .= 'Cc: ' . implode( ', ', $cc ) . $eol;
        }

        $mime .= 'Subject: ' . $message->get( 'subject', '' ) . $eol;
        $mime .= 'MIME-Version: 1.0' . $eol;
        $mime .= "Content-Type: multipart/mixed; boundary=\"{$mixed_boundary}\"" . $eol . $eol;

        // Nested multipart/alternative.
        $mime .= "--{$mixed_boundary}{$eol}";
        $mime .= "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"{$eol}{$eol}";

        // Plain text part.
        $mime .= "--{$alt_boundary}{$eol}";
        $mime .= "Content-Type: text/plain; charset=UTF-8{$eol}";
        $mime .= "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}";
        $mime .= quoted_printable_encode( $message->get( 'text', '' ) ) . $eol;

        // HTML part.
        $mime .= "--{$alt_boundary}{$eol}";
        $mime .= "Content-Type: text/html; charset=UTF-8{$eol}";
        $mime .= "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}";
        $mime .= quoted_printable_encode( $html_body ) . $eol;

        $mime .= "--{$alt_boundary}--{$eol}";

        // Attachments.
        foreach ( $this->build_base_attachments( $attachments ) as $attachment ) {
            $filename  = $attachment['filename'];
            $mime_type = $attachment['mime'];
            $encoded   = chunk_split( $attachment['content'] );

            $mime .= "--{$mixed_boundary}{$eol}";
            $mime .= "Content-Type: {$mime_type}; name=\"{$filename}\"{$eol}";
            $mime .= "Content-Transfer-Encoding: base64{$eol}";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"{$eol}{$eol}";
            $mime .= $encoded . $eol;
        }

        $mime .= "--{$mixed_boundary}--";

        return $mime;
    }

    /*
    |----------
    | DISPATCH
    |----------
    */

    /**
     * POST the payload to the SES API v2, signed with AWS Signature V4.
     *
     * SES v2 returns HTTP 200 with a JSON body containing 'MessageId' on success.
     *
     * @param array<string, mixed> $payload
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function dispatch( array $payload ): EmailResponse {
        $region   = $this->settings['region'];
        $endpoint = str_replace( '{region}', $region, self::API_ENDPOINT );

        $signer  = new AwsSignatureV4(
            access_key : $this->settings['access_key'],
            secret_key : $this->settings['secret_key'],
            region     : $region,
        );

        // Build the request first so the signer has access to the final body.
        $request = HttpRequest::post( $endpoint )
            ->with_json( $payload );

        // Sign the request — returns a new immutable HttpRequest with
        // X-Amz-Date, X-Amz-Content-Sha256, and Authorization headers added.
        $signed_request = $signer->sign( $request );

        $response = $this->http_client->send( $signed_request );

        return $this->parse_response( $response );
    }

    /*
    |-----------------
    | RESPONSE PARSER
    |-----------------
    */

    /**
     * Parse the SES API v2 response into an EmailResponse.
     *
     * SES v2 returns:
     *   200 OK   — success, JSON body with 'MessageId'.
     *   4xx/5xx  — failure, JSON body with '__type' and 'message'.
     *
     * @param HttpResponse $response
     * @return EmailResponse
     * @throws EmailTransportException
     */
    protected function parse_response( HttpResponse $response ): EmailResponse {
        $data = $response->json() ?? [];

        if ( $response->is_error() ) {
            // SES v2 error shape: {'__type': 'ErrorType', 'message': '...'}
            $type    = $data['__type'] ?? 'Error';
            $message = $data['message'] ?? 'Unknown Amazon SES error.';
            throw new EmailTransportException(
                "AmazonSESProvider: API returned HTTP {$response->status_code} ({$type}) — {$message}"
            );
        }

        return EmailResponse::ok( $data['MessageId'] ?? '', $data );
    }
}