<?php
/**
 * Native PHP Mail Provider
 *
 * Sends emails using PHP's native mail() function.
 * Supports attachments, HTML, and plain text.
 *
 * @package SmartLicenseServer\Email\Providers
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailProvidersRegistry;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;

defined( 'SMLISER_ABSPATH' ) || exit;

class PHPMailProvider implements EmailProviderInterface {

    protected array $settings = [];

    public static function get_id(): string {
        return 'php_mail';
    }

    public static function get_name(): string {
        return 'PHP Mail';
    }

    public function get_settings_schema(): array {
        return [
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
                'description' => 'Default sender name.',
            ],
        ];
    }

    public function set_settings( array $settings ): void {
        $from_email = $settings['from_email'] ?? '';

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new \InvalidArgumentException(
                'PHPMailProvider: a valid "from_email" is required in settings.'
            );
        }

        $this->settings = $settings;
    }

    public function send( EmailMessage $message ): EmailResponse {
        $to      = implode( ', ', $message->get( 'to', [] ) );
        $subject = $message->get( 'subject', '' );
        $body    = $message->get( 'body', '' );

        // Resolve sender — message-level 'from' takes precedence over settings.
        $from       = $message->get( 'from' ) ?? [];
        $collection = smliser_emailProvidersRegistry();
        $from_email = $from['email'] ?? $this->settings['from_email'] ?? $collection->get_default_sender_email();
        $from_name  = $from['name']  ?? $this->settings['from_name']  ?? $collection->get_default_sender_name();

        $boundary = md5( uniqid( '', true ) );
        $headers  = $this->build_headers( $message, $from_email, $from_name, $boundary );

        $attachments = $message->get( 'attachments', [] );
        $mime_body   = ! empty( $attachments )
            ? $this->build_mime_body( $body, $attachments, $boundary )
            : $body;

        if ( ! mail( $to, $subject, $mime_body, implode( "\r\n", $headers ) ) ) {
            throw new EmailTransportException( 'Failed to send email via PHP mail().' );
        }

        return EmailResponse::ok( '' );
    }

    /**
     * Build the headers array for the outgoing message.
     *
     * @param EmailMessage $message
     * @param string       $from_email
     * @param string       $from_name
     * @param string       $boundary
     * @return string[]
     */
    protected function build_headers(
        EmailMessage $message,
        string $from_email,
        string $from_name,
        string $boundary
    ): array {
        $headers = [];

        $headers[] = 'From: ' . ( $from_name
            ? "{$from_name} <{$from_email}>"
            : $from_email
        );

        $headers[] = 'MIME-Version: 1.0';

        $attachments = $message->get( 'attachments', [] );
        $headers[]   = ! empty( $attachments )
            ? "Content-Type: multipart/mixed; boundary=\"{$boundary}\""
            : 'Content-Type: text/html; charset=UTF-8';

        // CC / BCC.
        $cc = $message->get( 'cc', [] );
        if ( ! empty( $cc ) ) {
            $headers[] = 'Cc: ' . implode( ', ', $cc );
        }

        $bcc = $message->get( 'bcc', [] );
        if ( ! empty( $bcc ) ) {
            $headers[] = 'Bcc: ' . implode( ', ', $bcc );
        }

        // Reply-To.
        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $headers[] = 'Reply-To: ' . ( $reply_to['name']
                ? "{$reply_to['name']} <{$reply_to['email']}>"
                : $reply_to['email']
            );
        }

        // Custom headers from the message.
        foreach ( $message->get( 'headers', [] ) as $name => $value ) {
            $headers[] = "{$name}: {$value}";
        }

        return $headers;
    }

    /**
     * Build a multipart/mixed MIME body with attachments.
     *
     * Each attachment must be a normalised descriptor:
     * ['type' => 'path'|'base64', 'content' => string, 'filename' => string, 'mime' => string]
     *
     * @param string                                                              $body
     * @param array<int, array{type: string, content: string, filename: string, mime: string}> $attachments
     * @param string                                                              $boundary
     * @return string
     */
    protected function build_mime_body( string $body, array $attachments, string $boundary ): string {
        $eol  = "\r\n";
        $mime = "--{$boundary}{$eol}";
        $mime .= "Content-Type: text/html; charset=UTF-8{$eol}";
        $mime .= "Content-Transfer-Encoding: 7bit{$eol}{$eol}";
        $mime .= $body . $eol;

        foreach ( $attachments as $attachment ) {
            $filename  = $attachment['filename'] ?? '';
            $mime_type = $attachment['mime']     ?? 'application/octet-stream';

            if ( $attachment['type'] === 'path' ) {
                $filepath = $attachment['content'] ?? '';
                if ( ! file_exists( $filepath ) ) {
                    continue;
                }
                $encoded = chunk_split( base64_encode( file_get_contents( $filepath ) ) );
            } else {
                $encoded = chunk_split( $attachment['content'] ?? '' );
            }

            $mime .= "--{$boundary}{$eol}";
            $mime .= "Content-Type: {$mime_type}; name=\"{$filename}\"{$eol}";
            $mime .= "Content-Transfer-Encoding: base64{$eol}";
            $mime .= "Content-Disposition: attachment; filename=\"{$filename}\"{$eol}{$eol}";
            $mime .= $encoded . $eol;
        }

        $mime .= "--{$boundary}--";
        return $mime;
    }
}