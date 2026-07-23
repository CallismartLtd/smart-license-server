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
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;

class PHPMailProvider implements EmailProviderInterface {

	protected array $settings = [];

	public static function get_id(): string {
		return 'php_mail';
	}

	public static function get_name(): string {
		return 'PHP Mail';
	}

	public static function get_settings_schema(): array {
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
		if ( ! \function_exists( 'mail' ) ) {
			throw new EmailTransportException( 'Cannot send email. The function "mail()" does not exist.' );
		}

		$to      = $this->sanitize_address_list( $message->get( 'to', [] ) );
		$subject = $this->encode_subject( $message->get( 'subject', '' ) );
		$body    = $message->get( 'body', '' );

		if ( '' === $to ) {
			throw new EmailTransportException( 'Cannot send email. No valid "to" recipient was provided.' );
		}

		// Resolve sender — message-level 'from' takes precedence over settings.
		$from       = $message->get( 'from' ) ?? [];
		$collection = smliser_emailProvidersRegistry();
		$from_email = $from['email'] ?? $this->settings['from_email'] ?? $collection->get_default_sender_email();
		$from_name  = $from['name']  ?? $this->settings['from_name']  ?? $collection->get_default_sender_name();

		if ( ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
			throw new EmailTransportException( 'Cannot send email. The resolved "from" address is invalid.' );
		}

		$attachments = $message->get( 'attachments', [] );
		$boundary    = md5( uniqid( '', true ) );
		$headers     = $this->build_headers( $message, $from_email, $from_name, $boundary, $attachments );

		$mime_body = ! empty( $attachments )
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
	 * @param array        $attachments Already-resolved attachments list (avoids re-fetching from $message).
	 * @return string[]
	 */
	protected function build_headers(
		EmailMessage $message,
		string $from_email,
		string $from_name,
		string $boundary,
		array $attachments
	): array {
		$headers = [];

		$from_name = $this->sanitize_header_value( $from_name );
		$headers[] = 'From: ' . ( $from_name
			? $this->encode_header_phrase( $from_name ) . " <{$from_email}>"
			: $from_email
		);

		$headers[] = 'MIME-Version: 1.0';

		$headers[] = ! empty( $attachments )
			? "Content-Type: multipart/mixed; boundary=\"{$boundary}\""
			: 'Content-Type: text/html; charset=UTF-8';

		// Only declare a top-level transfer encoding for the non-multipart case;
		// each MIME part in build_mime_body() declares its own encoding.
		if ( empty( $attachments ) ) {
			$headers[] = 'Content-Transfer-Encoding: 8bit';
		}

		// CC / BCC.
		$cc = $this->sanitize_address_list( $message->get( 'cc', [] ) );
		if ( '' !== $cc ) {
			$headers[] = 'Cc: ' . $cc;
		}

		$bcc = $this->sanitize_address_list( $message->get( 'bcc', [] ) );
		if ( '' !== $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}

		// Reply-To.
		$reply_to = $message->get( 'reply_to' );
		if ( ! empty( $reply_to['email'] ) && filter_var( $reply_to['email'], FILTER_VALIDATE_EMAIL ) ) {
			$reply_name = $this->sanitize_header_value( $reply_to['name'] ?? '' );
			$headers[]  = 'Reply-To: ' . ( $reply_name
				? $this->encode_header_phrase( $reply_name ) . " <{$reply_to['email']}>"
				: $reply_to['email']
			);
		}

		// Custom headers from the message.
		foreach ( $message->get( 'headers', [] ) as $name => $value ) {
			$name  = $this->sanitize_header_value( (string) $name );
			$value = $this->sanitize_header_value( (string) $value );

			if ( '' === $name ) {
				continue;
			}

			$headers[] = "{$name}: {$value}";
		}

		return $headers;
	}

	/**
	 * Strip CR/LF (and anything after them) from a header value to prevent
	 * header injection via mail(). Also trims stray control characters.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function sanitize_header_value( string $value ): string {
		// Cut the value at the first CR or LF — anything after is a potential
		// injected header/body and must never reach mail().
		$value = preg_split( "/[\r\n]/", $value )[0] ?? '';

		return trim( $value );
	}

	/**
	 * Sanitize and validate a list of email addresses (to/cc/bcc), dropping
	 * any entry that isn't a syntactically valid email after sanitization.
	 *
	 * @param array $addresses
	 * @return string Comma-separated list of valid, sanitized addresses.
	 */
	protected function sanitize_address_list( array $addresses ): string {
		$valid = [];

		foreach ( $addresses as $address ) {
			$address = $this->sanitize_header_value( (string) $address );

			if ( '' !== $address && filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
				$valid[] = $address;
			}
		}

		return implode( ', ', $valid );
	}

	/**
	 * RFC 2047-encode a header "phrase" (e.g. a display name) if it contains
	 * non-ASCII characters, so clients render it correctly.
	 *
	 * @param string $value
	 * @return string
	 */
	protected function encode_header_phrase( string $value ): string {
		if ( '' === $value || mb_check_encoding( $value, 'ASCII' ) ) {
			return $value;
		}

		return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
	}

	/**
	 * RFC 2047-encode the subject line if it contains non-ASCII characters.
	 *
	 * @param string $subject
	 * @return string
	 */
	protected function encode_subject( string $subject ): string {
		$subject = $this->sanitize_header_value( $subject );

		if ( '' === $subject || mb_check_encoding( $subject, 'ASCII' ) ) {
			return $subject;
		}

		return '=?UTF-8?B?' . base64_encode( $subject ) . '?=';
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
		$mime .= "Content-Transfer-Encoding: 8bit{$eol}{$eol}";
		$mime .= $body . $eol;

		foreach ( $attachments as $attachment ) {
			$filename  = $attachment['filename'] ?? '';
			$mime_type = $attachment['mime']     ?? 'application/octet-stream';

			if ( $attachment['type'] === 'path' ) {
				$filepath = $attachment['content'] ?? '';

				if ( ! file_exists( $filepath ) ) {
					continue;
				}

				$raw = file_get_contents( $filepath );

				if ( false === $raw ) {
					throw new EmailTransportException(
						"Failed to read attachment file for sending: {$filepath}"
					);
				}

				$encoded = chunk_split( base64_encode( $raw ) );
			} else {
				$encoded = chunk_split( $attachment['content'] ?? '' );
			}

			$mime .= "--{$boundary}{$eol}";
			$mime .= "Content-Type: {$mime_type}; name=\"{$filename}\"{$eol}";
			$mime .= "Content-Transfer-Encoding: base64{$eol}";
			$mime .= "Content-Disposition: attachment; filename=\"{$filename}\"{$eol}{$eol}";
			$mime .= $encoded . $eol;
		}

		$mime .= "--{$boundary}--{$eol}";
		return $mime;
	}
}