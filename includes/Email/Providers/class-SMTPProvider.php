<?php
/**
 * SMTP Mail Provider
 *
 * Sends emails over a raw PHP socket SMTP connection.
 * Supports plain, SSL, TLS, and STARTTLS encryption.
 * Authentication is used automatically when credentials are supplied.
 *
 * Maintains a persistent connection across multiple send() calls to
 * avoid reconnection overhead in bulk and burst scenarios.
 *
 * @package SmartLicenseServer\Email\Providers
 * @since 0.2.0
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\Email\Providers;

use Automattic\WooCommerce\StoreApi\Routes\V1\Agentic\Messages\Message;
use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;
use InvalidArgumentException;
use SmartLicenseServer\Email\EmailProviderCollection;

defined( 'SMLISER_ABSPATH' ) || exit;

class SMTPProvider implements EmailProviderInterface {

    public const ENCRYPTION_NONE     = '';
    public const ENCRYPTION_SSL      = 'ssl';
    public const ENCRYPTION_TLS      = 'tls';       // Alias for STARTTLS — explicit in-session upgrade.
    public const ENCRYPTION_STARTTLS = 'starttls';

    protected const DEFAULT_TIMEOUT          = 15;
    protected const DEFAULT_PORT             = 587;
    protected const MAX_SENDS_PER_CONNECTION = 100;

    /**
     * Maximum bytes read per fgets() call on the socket.
     *
     * 998 + 2 (CRLF) = 1000 — the RFC 5321 maximum line length.
     */
    protected const SOCKET_READ_LENGTH = 1000;

    /**
     * Holds the settings.
     *
     * @var array
     */
    protected array $settings = [];

    /**
     * The current socket reference.
     *
     * @var resource|null
     */
    protected mixed $socket = null;

    /**
     * Stores last server response.
     *
     * @var string
     */
    protected string $last_response = '';

    /**
     * Number of messages sent over the current connection.
     *
     * @var int
     */
    protected int $send_count = 0;

    /**
     * Whether set_settings() has been called.
     *
     * @var bool
     */
    protected bool $configured = false;

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public function get_id(): string {
        return 'smtp';
    }

    public function get_name(): string {
        return 'SMTP';
    }

    /*
    |------------------------------
    | INTERFACE — SETTINGS SCHEMA
    |------------------------------
    */

    public function get_settings_schema(): array {
        return [
            'host' => [
                'type'        => 'text',
                'label'       => 'SMTP Host',
                'required'    => true,
                'description' => 'SMTP server hostname. e.g. smtp.gmail.com',
            ],
            'port' => [
                'type'        => 'number',
                'label'       => 'Port',
                'required'    => true,
                'description' => 'Typically 25 (plain), 465 (SSL), or 587 (STARTTLS/TLS).',
            ],
            'encryption' => [
                'type'        => 'select',
                'label'       => 'Encryption',
                'required'    => false,
                'options'     => [
                    self::ENCRYPTION_NONE     => 'None',
                    self::ENCRYPTION_SSL      => 'SSL',
                    self::ENCRYPTION_TLS      => 'TLS',
                    self::ENCRYPTION_STARTTLS => 'STARTTLS',
                ],
                'description' => 'Transport encryption method.',
            ],
            'username' => [
                'type'        => 'text',
                'label'       => 'Username',
                'required'    => false,
                'description' => 'SMTP username. Leave blank for open relay.',
            ],
            'password' => [
                'type'        => 'password',
                'label'       => 'Password',
                'required'    => false,
                'description' => 'SMTP password. Leave blank for open relay.',
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
            'timeout' => [
                'type'        => 'number',
                'label'       => 'Connection Timeout (seconds)',
                'required'    => false,
                'description' => 'Socket connection timeout. Default: ' . self::DEFAULT_TIMEOUT . 's.',
            ],
        ];
    }

    /*
    |----------------------
    | INTERFACE — SETTINGS
    |----------------------
    */

    /**
     * Set and validate provider configuration.
     *
     * @param array<string, mixed> $settings
     * @throws InvalidArgumentException
     */
    public function set_settings( array $settings ): void {
        $host       = trim( $settings['host']       ?? '' );
        $port       = (int) ( $settings['port']     ?? self::DEFAULT_PORT );
        $from_email = trim( $settings['from_email'] ?? '' );
        $encryption = $settings['encryption']       ?? self::ENCRYPTION_NONE;

        if ( empty( $host ) ) {
            throw new InvalidArgumentException( 'SMTPProvider: "host" is required.' );
        }

        if ( $port < 1 || $port > 65535 ) {
            throw new InvalidArgumentException( 'SMTPProvider: "port" must be between 1 and 65535.' );
        }

        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException( 'SMTPProvider: a valid "from_email" is required.' );
        }

        $valid_encryptions = [
            self::ENCRYPTION_NONE,
            self::ENCRYPTION_SSL,
            self::ENCRYPTION_TLS,
            self::ENCRYPTION_STARTTLS,
        ];

        if ( ! in_array( $encryption, $valid_encryptions, true ) ) {
            throw new InvalidArgumentException(
                'SMTPProvider: "encryption" must be one of: ' . implode( ', ', $valid_encryptions ) . '.'
            );
        }

        // Drop any existing connection when settings change so the next
        // send() opens a fresh socket against the new host/credentials.
        if ( $this->is_connected() ) {
            $this->disconnect();
        }

        $this->settings   = $settings;
        $this->configured = true;
    }

    /*
    |--------------------
    | INTERFACE — SEND
    |--------------------
    */

    /**
     * Send an email message over SMTP.
     *
     * Reuses an existing open connection when available. Reconnects
     * automatically if the connection has been dropped or the per-connection
     * send limit has been reached.
     *
     * @param EmailMessage $message
     * @return EmailResponse
     * @throws EmailTransportException
     * @throws InvalidArgumentException If called before set_settings().
     */
    public function send( EmailMessage $message ): EmailResponse {
        if ( ! $this->configured ) {
            throw new InvalidArgumentException(
                'SMTPProvider: call set_settings() before send().'
            );
        }

        $this->ensure_connected();

        try {
            $message_id = $this->transmit( $message );
            $this->send_count++;
        } catch ( EmailTransportException $e ) {
            // The connection may be in an undefined state after a mid-flight
            // failure — tear it down so the next send() starts clean.
            $this->force_disconnect();
            throw $e;
        }

        return EmailResponse::ok( $message_id );
    }

    /**
     * Explicitly close the connection.
     *
     * Call this after a bulk send batch is complete to release the socket
     * cleanly rather than waiting for the destructor or server-side timeout.
     */
    public function close(): void {
        $this->disconnect();
    }

    /*
    |------------
    | CONNECTION
    |------------
    */

    /**
     * Ensure a healthy connection is available, reconnecting if needed.
     *
     * @throws EmailTransportException
     */
    protected function ensure_connected(): void {
        $limit_reached = $this->send_count >= self::MAX_SENDS_PER_CONNECTION;
        $stale         = $this->is_connected() && $this->is_socket_stale();

        if ( $limit_reached || $stale ) {
            $this->disconnect();
        }

        if ( ! $this->is_connected() ) {
            $this->connect();
        }
    }

    /**
     * Return whether a socket is currently open.
     */
    protected function is_connected(): bool {
        return $this->socket !== null;
    }

    /**
     * Check whether the socket has timed out or been closed by the server.
     *
     * Uses stream_get_meta_data() which is cheap — no actual read/write.
     */
    protected function is_socket_stale(): bool {
        if ( ! is_resource( $this->socket ) ) {
            return true;
        }

        $meta = stream_get_meta_data( $this->socket );
        return $meta['timed_out'] || feof( $this->socket );
    }

    /**
     * Open socket, perform handshake, and authenticate if credentials are set.
     *
     * ENCRYPTION_SSL      → implicit TLS via ssl:// wrapper (port 465).
     * ENCRYPTION_TLS      → treated as STARTTLS; plain connect then upgraded (port 587).
     * ENCRYPTION_STARTTLS → plain connect then upgraded via STARTTLS command (port 587).
     * ENCRYPTION_NONE     → plain unencrypted connection (port 25).
     *
     * @throws EmailTransportException
     */
    protected function connect(): void {
        $host       = $this->settings['host'];
        $port       = (int) ( $this->settings['port']    ?? self::DEFAULT_PORT );
        $encryption = $this->settings['encryption']      ?? self::ENCRYPTION_NONE;
        $timeout    = (int) ( $this->settings['timeout'] ?? self::DEFAULT_TIMEOUT );

        // Only SSL uses the ssl:// stream wrapper — TLS and STARTTLS connect
        // plain first and upgrade in-session via the STARTTLS command.
        $socket_host = ( $encryption === self::ENCRYPTION_SSL )
            ? "ssl://{$host}"
            : $host;

        $errno  = 0;
        $errstr = '';

        $socket = fsockopen( $socket_host, $port, $errno, $errstr, $timeout );

        if ( $socket === false ) {
            throw new EmailTransportException(
                "SMTPProvider: could not connect to {$host}:{$port} — {$errstr} ({$errno})."
            );
        }

        $this->socket     = $socket;
        $this->send_count = 0;

        stream_set_timeout( $this->socket, $timeout );

        // Read server greeting.
        $this->read_response( 220 );

        // EHLO handshake.
        $this->command( 'EHLO ' . $this->get_local_hostname(), 250 );

        // STARTTLS and TLS both perform an explicit in-session TLS upgrade.
        if ( in_array( $encryption, [ self::ENCRYPTION_STARTTLS, self::ENCRYPTION_TLS ], true ) ) {
            $this->command( 'STARTTLS', 220 );

            if ( ! stream_socket_enable_crypto( $this->socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT ) ) {
                throw new EmailTransportException( 'SMTPProvider: STARTTLS negotiation failed.' );
            }

            // Re-introduce ourselves after the TLS upgrade.
            $this->command( 'EHLO ' . $this->get_local_hostname(), 250 );
        }

        // Authenticate if credentials are present.
        $username = $this->settings['username'] ?? '';
        $password = $this->settings['password'] ?? '';

        if ( ! empty( $username ) && ! empty( $password ) ) {
            $this->authenticate( $username, $password );
        }
    }

    /**
     * Send QUIT, close the socket, and reset connection state.
     */
    protected function disconnect(): void {
        if ( $this->socket !== null ) {
            try {
                $this->command( 'QUIT', 221 );
            } catch ( EmailTransportException ) {
                // Suppress — we are tearing down regardless.
            }
            $this->force_disconnect();
        }
    }

    /**
     * Close the socket immediately without sending QUIT.
     *
     * Used after a transmission failure where the connection state is unknown.
     */
    protected function force_disconnect(): void {
        if ( $this->socket !== null ) {
            fclose( $this->socket );
            $this->socket     = null;
            $this->send_count = 0;
        }
    }

    /**
     * Close the connection on object destruction.
     *
     * Guards against dangling sockets when the provider is garbage collected
     * without an explicit close() call.
     */
    public function __destruct() {
        $this->disconnect();
    }

    /*
    |------------------
    | AUTHENTICATION
    |------------------
    */

    /**
     * Perform AUTH LOGIN over the open socket.
     *
     * @throws EmailTransportException
     */
    protected function authenticate( string $username, string $password ): void {
        $this->command( 'AUTH LOGIN', 334 );
        $this->command( base64_encode( $username ), 334 );
        $this->command( base64_encode( $password ), 235 );
    }

    /*
    |----------------------
    | MESSAGE TRANSMISSION
    |----------------------
    */

    /**
     * Transmit the email envelope and DATA payload.
     *
     * Validates the envelope sender and all recipients before issuing any
     * SMTP commands so that obviously bad addresses are caught locally
     * rather than generating a mid-session server rejection.
     *
     * @return string Generated Message-ID.
     * @throws EmailTransportException
     * @throws InvalidArgumentException On invalid envelope addresses.
     */
    protected function transmit( EmailMessage $message ): string {
        $from       = $message->get( 'from' ) ?? [];
        $collection = EmailProviderCollection::instance();
        $from_email = $from['email'] ?? $this->settings['from_email'] ?? $collection->get_default_sender_email();
        $from_name  = $from['name']  ?? $this->settings['from_name']  ?? $collection->get_default_sender_name();

        // Validate envelope sender before opening the SMTP transaction.
        $this->validate_envelope_sender( $from_email );

        // Collect and validate all envelope recipients.
        $all_recipients = array_merge(
            $message->get( 'to',  [] ),
            $message->get( 'cc',  [] ),
            $message->get( 'bcc', [] ),
        );

        $this->validate_recipients( $all_recipients );

        // MAIL FROM envelope.
        $this->command( "MAIL FROM:<{$from_email}>", 250 );

        // RCPT TO envelope.
        foreach ( $all_recipients as $recipient ) {
            $this->command( "RCPT TO:<{$recipient}>", 250 );
        }

        // DATA phase.
        $this->command( 'DATA', 354 );

        $message_id = $this->generate_message_id( $from_email );

        // Build and dot-stuff the payload, then stream it to the socket.
        $this->stream_payload( $message, $from_email, $from_name, $message_id );

        $this->read_response( 250 );

        return $message_id;
    }

    /*
    |-----------------------
    | ENVELOPE VALIDATION
    |-----------------------
    */

    /**
     * Assert the envelope sender is a valid email address.
     *
     * @param string $from_email
     * @throws InvalidArgumentException
     */
    protected function validate_envelope_sender( string $from_email ): void {
        if ( empty( $from_email ) || ! filter_var( $from_email, FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException(
                "SMTPProvider: invalid envelope sender address '{$from_email}'."
            );
        }
    }

    /**
     * Assert the recipient list is non-empty and contains only valid addresses.
     *
     * @param string[] $recipients
     * @throws InvalidArgumentException
     */
    protected function validate_recipients( array $recipients ): void {
        if ( empty( $recipients ) ) {
            throw new InvalidArgumentException(
                'SMTPProvider: at least one recipient is required.'
            );
        }

        foreach ( $recipients as $address ) {
            if ( ! filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
                throw new InvalidArgumentException(
                    "SMTPProvider: invalid recipient address '{$address}'."
                );
            }
        }
    }

    /*
    |--------------------
    | STREAMING PAYLOAD
    |--------------------
    */

    /**
     * Build and stream the full message payload directly to the socket.
     *
     * Rather than assembling the entire message in memory — which is
     * prohibitive for large attachments — this method writes headers first,
     * then streams each body part (text, HTML, attachments) in chunks.
     *
     * The DATA terminator (\r\n.\r\n) is written as the final chunk.
     *
     * @param EmailMessage $message
     * @param string       $from_email
     * @param string       $from_name
     * @param string       $message_id
     * @throws EmailTransportException
     */
    protected function stream_payload(
        EmailMessage $message,
        string $from_email,
        string $from_name,
        string $message_id
    ): void {
        $eol             = "\r\n";
        $mixed_boundary  = 'mixed-' . md5( uniqid( '', true ) );
        $alt_boundary    = 'alt-'   . md5( uniqid( '', true ) );
        $attachments     = $message->get( 'attachments', [] );
        $has_attachments = ! empty( $attachments );

        // --- Headers ---
        $headers   = [];
        $headers[] = 'Message-ID: <' . $message_id . '>';
        $headers[] = 'Date: ' . date( 'r' );
        $headers[] = 'From: ' . ( $from_name ? "{$from_name} <{$from_email}>" : $from_email );
        $headers[] = 'To: ' . implode( ', ', $message->get( 'to', [] ) );

        $cc = $message->get( 'cc', [] );
        if ( ! empty( $cc ) ) {
            $headers[] = 'Cc: ' . implode( ', ', $cc );
        }

        // BCC intentionally omitted from headers — recipients were added via RCPT TO only.

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $headers[] = 'Reply-To: ' . ( $reply_to['name']
                ? "{$reply_to['name']} <{$reply_to['email']}>"
                : $reply_to['email']
            );
        }

        $headers[] = 'Subject: ' . $this->encode_header( $message->get( 'subject', '' ) );
        $headers[] = 'MIME-Version: 1.0';

        // Top-level Content-Type depends on whether attachments are present.
        // With attachments:    multipart/mixed - wraps multipart/alternative + attachments.
        // Without attachments: multipart/alternative - wraps plain text + HTML directly.
        $headers[] = $has_attachments
            ? "Content-Type: multipart/mixed; boundary=\"{$mixed_boundary}\""
            : "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"";

        foreach ( $message->get( 'headers', [] ) as $name => $value ) {
            $headers[] = "{$name}: {$value}";
        }

        // Write headers + blank separator line.
        $this->write_raw( implode( $eol, $headers ) . $eol . $eol );

        if ( $has_attachments ) {
            // Open mixed boundary and embed the alternative block as a nested part.
            $this->write_raw( "--{$mixed_boundary}{$eol}" );
            $this->write_raw( "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"{$eol}{$eol}" );
            $this->stream_alternative_parts( $message, $alt_boundary );
            $this->write_raw( $eol );

            // Stream each attachment directly from disk or memory.
            foreach ( $attachments as $attachment ) {
                $this->stream_attachment( $attachment, $mixed_boundary );
            }

            // Close the mixed boundary.
            $this->write_raw( "--{$mixed_boundary}--{$eol}" );
        } else {
            // No attachments — write alternative parts directly.
            $this->stream_alternative_parts( $message, $alt_boundary );
        }

        // DATA terminator — signals end of message to the server.
        $this->write_raw( "\r\n.\r\n" );
    }

    /**
     * Stream the plain text and HTML alternative parts for a given boundary.
     *
     * Plain text is always written first per RFC 2046 — clients render the
     * last part they understand, so HTML must appear last.
     *
     * Both parts use quoted-printable transfer encoding, which handles
     * non-ASCII UTF-8 content safely and keeps line lengths within RFC limits.
     *
     * @param EmailMessage $message
     * @param string $boundary
     * @throws EmailTransportException
     */
    protected function stream_alternative_parts( EmailMessage $message, string $boundary ): void {
        $eol        = "\r\n";
        $html       = $message->get( 'body', '' );
        $plain_text = $message->get( 'text', '' );

        // Plain text part.
        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: text/plain; charset=UTF-8{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}" );
        $this->write_dotted( quoted_printable_encode( $plain_text ) . $eol );

        // HTML part.
        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: text/html; charset=UTF-8{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}" );
        $this->write_dotted( quoted_printable_encode( $html ) . $eol );

        // Close this boundary.
        $this->write_raw( "--{$boundary}--{$eol}" );
    }

    /**
     * Stream a single attachment to the socket in base64 chunks.
     *
     * Attachment descriptor shape (normalised by EmailMessage::cast()):
     * [
     *     'type'     => 'path' | 'base64',
     *     'content'  => string,
     *     'filename' => string,
     *     'mime'     => string,
     * ]
     *
     * For 'path' attachments the file is read in 57-byte chunks (yielding
     * 76-character base64 lines per RFC 2045) so arbitrarily large files
     * are never fully loaded into memory.
     *
     * For 'base64' attachments the pre-encoded content is chunk-split and
     * written directly.
     *
     * @param array{type: string, content: string, filename: string, mime: string} $attachment
     * @param string $boundary
     * @throws EmailTransportException
     */
    protected function stream_attachment( array $attachment, string $boundary ): void {
        $eol       = "\r\n";
        $filename  = $attachment['filename'] ?? '';
        $mime_type = $attachment['mime']     ?? 'application/octet-stream';
        $encoded_filename = $this->encode_header( $filename );

        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: {$mime_type}; name=\"{$encoded_filename}\"{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: base64{$eol}" );
        $this->write_raw( "Content-Disposition: attachment; filename=\"{$encoded_filename}\"{$eol}{$eol}" );

        if ( $attachment['type'] === 'path' ) {
            $filepath = $attachment['content'] ?? '';

            if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
                // Skip silently — the message still sends without this attachment.
                // Close the boundary part we opened above with an empty base64 body.
                $this->write_raw( $eol );
                return;
            }

            // Stream the file in 57-byte chunks → 76-character base64 lines.
            // This keeps memory usage constant regardless of file size.
            $handle = fopen( $filepath, 'rb' );

            if ( $handle === false ) {
                $this->write_raw( $eol );
                return;
            }

            while ( ! feof( $handle ) ) {
                $chunk = fread( $handle, 57 );
                if ( $chunk !== false && $chunk !== '' ) {
                    $this->write_dotted( base64_encode( $chunk ) . $eol );
                }
            }

            fclose( $handle );
        } else {
            // Pre-encoded base64 content — chunk-split and write.
            $lines = str_split( $attachment['content'] ?? '', 76 );
            foreach ( $lines as $line ) {
                $this->write_dotted( $line . $eol );
            }
        }

        $this->write_raw( $eol );
    }

    /*
    |--------------------
    | SOCKET PRIMITIVES
    |--------------------
    */

    /**
     * Send a command and assert the expected response code.
     *
     * @throws EmailTransportException
     */
    protected function command( string $command, int $expected_code ): string {
        $this->write_raw( $command . "\r\n" );
        return $this->read_response( $expected_code );
    }

    /**
     * Write raw bytes to the socket without appending any line ending.
     *
     * All callers are responsible for their own CRLF termination:
     *   - Commands:        write_raw( $cmd . "\r\n" )
     *   - DATA terminator: write_raw( "\r\n.\r\n" )
     *
     * @throws EmailTransportException
     */
    protected function write_raw( string $data ): void {
        if ( fwrite( $this->socket, $data ) === false ) {
            throw new EmailTransportException( 'SMTPProvider: failed to write to socket.' );
        }
    }

    /**
     * Dot-stuff a line per RFC 5321 §4.5.2 and write it to the socket.
     *
     * Any line beginning with a dot must be prefixed with an additional dot
     * so the server does not interpret it as the DATA terminator.
     *
     * All body content that goes through the DATA phase uses this method.
     * Raw protocol commands and the DATA terminator use write_raw() directly.
     *
     * @throws EmailTransportException
     */
    protected function write_dotted( string $data ): void {
        $this->write_raw( preg_replace( '/^\./m', '..', $data ) );
    }

    /**
     * Read lines from the socket until a final response line is received,
     * then assert the response code matches the expected code.
     *
     * Handles multi-line responses (lines prefixed with "NNN-") correctly.
     * Enforces a hard cap of 512 iterations to guard against a pathological
     * or malicious server sending an infinite stream of continuation lines.
     *
     * @throws EmailTransportException
     */
    protected function read_response( int $expected_code ): string {
        $response  = '';
        $max_lines = 512;
        $lines_read = 0;

        while ( $lines_read < $max_lines ) {
            $line = fgets( $this->socket, self::SOCKET_READ_LENGTH );

            if ( $line === false ) {
                throw new EmailTransportException(
                    'SMTPProvider: connection closed unexpectedly while reading response.'
                );
            }

            $response .= $line;
            $lines_read++;

            // A space in position 3 (vs a dash) signals the final line of a
            // potentially multi-line response (RFC 5321 §4.2.1).
            if ( isset( $line[3] ) && ' ' === $line[3] ) {
                break;
            }
        }

        if ( $lines_read >= $max_lines ) {
            throw new EmailTransportException(
                'SMTPProvider: server response exceeded maximum line limit — possible attack or misconfiguration.'
            );
        }

        $this->last_response = $response;
        $actual_code         = (int) substr( $response, 0, 3 );

        if ( $actual_code !== $expected_code ) {
            throw new EmailTransportException(
                "SMTPProvider: expected {$expected_code}, got {$actual_code}. Server said: " . trim( $response )
            );
        }

        return $response;
    }

    /*
    |---------
    | HELPERS
    |---------
    */

    /**
     * Generate an RFC 5321-compliant Message-ID.
     *
     * @param string $from_email
     * @return string
     */
    protected function generate_message_id( string $from_email ): string {
        $domain = substr( strrchr( $from_email, '@' ) ?: '@localhost', 1 );
        return uniqid( '', true ) . '.' . bin2hex( random_bytes( 4 ) ) . '@' . $domain;
    }

    /**
     * Encode a header value containing non-ASCII characters (RFC 2047).
     *
     * Pure ASCII values are returned unchanged. Everything else is
     * wrapped in a UTF-8 Base64 encoded word so that mail clients
     * correctly decode subjects, sender names, and attachment filenames
     * containing accented or non-Latin characters.
     *
     * @param string $value
     * @return string
     */
    protected function encode_header( string $value ): string {
        if ( mb_detect_encoding( $value, 'ASCII', true ) !== false ) {
            return $value;
        }
        return '=?UTF-8?B?' . base64_encode( $value ) . '?=';
    }

    /**
     * Resolve the local hostname for the EHLO command.
     *
     * @return string
     */
    protected function get_local_hostname(): string {
        return gethostname() ?: 'localhost';
    }
}