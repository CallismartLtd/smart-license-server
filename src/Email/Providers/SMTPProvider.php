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

use SmartLicenseServer\Email\EmailMessage;
use SmartLicenseServer\Email\EmailResponse;
use SmartLicenseServer\Exceptions\EmailTransportException;
use InvalidArgumentException;
use SmartLicenseServer\Email\EmailProvidersRegistry;

defined( 'SMLISER_ABSPATH' ) || exit;

class SMTPProvider implements EmailProviderInterface {

    public const ENCRYPTION_NONE     = '';
    public const ENCRYPTION_SSL      = 'ssl';
    public const ENCRYPTION_TLS      = 'tls';
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

    /**
     * AUTH mechanisms advertised by the server in its EHLO response.
     *
     * Populated by parse_ehlo_response() after every EHLO command.
     * Values are lowercased for case-insensitive comparison.
     * e.g. [ 'plain', 'login', 'xoauth2' ]
     *
     * @var string[]
     */
    protected array $auth_mechanisms = [];

    /**
     * SMTP extensions advertised by the server in its EHLO response.
     *
     * Keyed by lowercase extension keyword, value is any trailing
     * parameter string (or empty string if the extension has no params).
     * e.g. [ 'size' => '52428800', 'pipelining' => '', '8bitmime' => '' ]
     *
     * @var array<string, string>
     */
    protected array $extensions = [];

    /*
    |----------------------
    | INTERFACE — IDENTITY
    |----------------------
    */

    public static function get_id(): string {
        return 'smtp';
    }

    public static function get_name(): string {
        return 'SMTP';
    }

    /*
    |------------------------------
    | INTERFACE — SETTINGS SCHEMA
    |------------------------------
    */

    public static function get_settings_schema(): array {
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
            'helo_hostname' => [
                'type'        => 'text',
                'label'       => 'EHLO Hostname',
                'required'    => false,
                'description' => 'Hostname sent in the EHLO command. Must be an FQDN or IP literal like [1.2.3.4]. Leave blank to auto-detect.',
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
            $this->force_disconnect();
            throw $e;
        }

        return EmailResponse::ok( $message_id );
    }

    /**
     * Explicitly close the connection.
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

        // Read server greeting — some servers send 220 immediately,
        // others send multiple 220 continuation lines. read_response()
        // handles both correctly.
        $this->read_response( 220 );

        $this->ehlo();

        if ( in_array( $encryption, [ self::ENCRYPTION_STARTTLS, self::ENCRYPTION_TLS ], true ) ) {

            // Only attempt STARTTLS if the server advertised it.
            if ( ! isset( $this->extensions['starttls'] ) ) {
                throw new EmailTransportException(
                    'SMTPProvider: STARTTLS requested but server did not advertise STARTTLS capability.'
                );
            }

            $this->command( 'STARTTLS', 220 );

            if ( ! stream_socket_enable_crypto( $this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT ) ) {
                throw new EmailTransportException( 'SMTPProvider: STARTTLS negotiation failed.' );
            }

            // Re-introduce after TLS upgrade — server resets its capability list.
            $this->ehlo();
        }

        $username = trim( $this->settings['username'] ?? '' );
        $password = trim( $this->settings['password'] ?? '' );

        if ( $username !== '' && $password !== '' ) {
            $this->authenticate( $username, $password );
        }
    }

    /**
     * Send EHLO, capture the response, and parse server capabilities.
     *
     * @throws EmailTransportException
     */
    protected function ehlo(): void {
        $response = $this->command( 'EHLO ' . $this->get_local_hostname(), 250 );
        $this->parse_ehlo_response( $response );
    }

    /**
     * Parse the full EHLO multi-line response.
     *
     * Populates both $this->extensions (all advertised capabilities) and
     * $this->auth_mechanisms (the AUTH capability line specifically).
     *
     * Handles all known EHLO response formats:
     *   250-AUTH PLAIN LOGIN XOAUTH2      (space-separated mechanisms)
     *   250-AUTH=PLAIN LOGIN              (= delimiter, mixed separators)
     *   250 AUTH PLAIN                    (single-line response)
     *
     * @param string $response  Full multi-line EHLO response string.
     */
    protected function parse_ehlo_response( string $response ): void {
        $this->extensions    = [];
        $this->auth_mechanisms = [];

        // Normalise line endings — some servers send bare \n instead of \r\n.
        $lines = explode( "\n", str_replace( "\r\n", "\n", $response ) );

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // Each EHLO response line starts with the 3-digit code followed
            // by either a space (final line) or a hyphen (continuation line).
            // Strip the "250 " / "250-" prefix before parsing the keyword.
            if ( ! preg_match( '/^\d{3}[-\s](.+)$/', $line, $m ) ) {
                continue;
            }

            $content = trim( $m[1] );

            // AUTH line — format: AUTH[=]MECH1 MECH2 ...
            // The = sign is an old RFC 2554 format still used by some servers.
            if ( preg_match( '/^AUTH[=\s](.+)$/i', $content, $auth_match ) ) {
                $mechanisms          = preg_split( '/\s+/', trim( $auth_match[1] ) );
                $this->auth_mechanisms = array_map( 'strtolower', array_filter( (array) $mechanisms ) );
                $this->extensions['auth'] = implode( ' ', $this->auth_mechanisms );
                continue;
            }

            // All other extensions — keyword optionally followed by params.
            $parts   = preg_split( '/\s+/', $content, 2 );
            $keyword = strtolower( $parts[0] );
            $params  = isset( $parts[1] ) ? trim( $parts[1] ) : '';

            $this->extensions[ $keyword ] = $params;
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
            $this->socket          = null;
            $this->send_count      = 0;
            $this->extensions      = [];
            $this->auth_mechanisms = [];
        }
    }

    public function __destruct() {
        $this->disconnect();
    }

    /*
    |------------------
    | AUTHENTICATION
    |------------------
    */

    /**
     * Authenticate using the best mechanism the server advertises.
     *
     * Negotiation priority: PLAIN → LOGIN
     *
     * Credentials are trimmed before use — whitespace in stored credentials
     * is a common source of 535 rejections that are otherwise very hard to
     * diagnose because the encoded string gives nothing away.
     *
     * If the server advertised no AUTH mechanisms at all the connection is
     * treated as an open relay and this method returns without attempting
     * authentication.
     *
     * If the server advertised mechanisms but none of the ones we support
     * are available, we throw with a clear message listing what was offered
     * so the operator knows exactly what to configure.
     *
     * @throws EmailTransportException
     */
    protected function authenticate( string $username, string $password ): void {
        // Trim here as a final safety net — credentials may have been stored
        // with trailing whitespace from a form submission or copy-paste.
        $username = trim( $username );
        $password = trim( $password );

        // No mechanisms advertised — open relay.
        if ( empty( $this->auth_mechanisms ) ) {
            return;
        }

        if ( in_array( 'plain', $this->auth_mechanisms, true ) ) {
            $this->authenticate_plain( $username, $password );
            return;
        }

        if ( in_array( 'login', $this->auth_mechanisms, true ) ) {
            $this->authenticate_login( $username, $password );
            return;
        }

        throw new EmailTransportException(
            'SMTPProvider: server does not support AUTH PLAIN or AUTH LOGIN. '
            . 'Offered mechanisms: ' . implode( ', ', $this->auth_mechanisms ) . '.'
        );
    }

    /**
     * Perform AUTH PLAIN authentication (RFC 4616).
     *
     * Sends the initial AUTH PLAIN command and waits for the server's 334
     * challenge before sending the base64-encoded credentials. This two-step
     * flow is more broadly compatible than the single-command inline form
     * (AUTH PLAIN <credentials>) which some servers do not accept.
     *
     * Credential format: \0authcid\0passwd
     * The authzid (first field) is intentionally left empty — it is only
     * needed for proxy authentication scenarios which SMTP does not use.
     *
     * @throws EmailTransportException
     */
    protected function authenticate_plain( string $username, string $password ): void {
        $this->command( 'AUTH PLAIN', 334 );
        // Populate authzid with username instead of leaving it empty.
        $credentials = base64_encode( $username . "\0" . $username . "\0" . $password );
        $this->command( $credentials, 235 );
    }

    /**
     * Perform AUTH LOGIN authentication (RFC 4954 legacy mechanism).
     *
     * Sends username and password as separate base64-encoded responses
     * to successive server challenges. Used as a fallback when the server
     * does not advertise AUTH PLAIN.
     *
     * @throws EmailTransportException
     */
    protected function authenticate_login( string $username, string $password ): void {
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
     * @return string Generated Message-ID.
     * @throws EmailTransportException
     * @throws InvalidArgumentException
     */
    protected function transmit( EmailMessage $message ): string {
        $from       = $message->get( 'from' ) ?? [];
        $collection = smliser_emailProvidersRegistry();
        $from_email = $from['email'] ?? $this->settings['from_email'] ?? $collection->get_default_sender_email();
        $from_name  = $from['name']  ?? $this->settings['from_name']  ?? $collection->get_default_sender_name();

        $this->validate_envelope_sender( $from_email );

        $all_recipients = array_merge(
            $message->get( 'to',  [] ),
            $message->get( 'cc',  [] ),
            $message->get( 'bcc', [] ),
        );

        $this->validate_recipients( $all_recipients );

        $this->command( "MAIL FROM:<{$from_email}>", 250 );

        foreach ( $all_recipients as $recipient ) {
            $this->command( "RCPT TO:<{$recipient}>", 250 );
        }

        $this->command( 'DATA', 354 );

        $message_id = $this->generate_message_id( $from_email );

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

        $headers   = [];
        $headers[] = 'Message-ID: <' . $message_id . '>';
        $headers[] = 'Date: ' . date( 'r' );
        $headers[] = 'From: ' . ( $from_name ? "{$from_name} <{$from_email}>" : $from_email );
        $headers[] = 'To: ' . implode( ', ', $message->get( 'to', [] ) );

        $cc = $message->get( 'cc', [] );
        if ( ! empty( $cc ) ) {
            $headers[] = 'Cc: ' . implode( ', ', $cc );
        }

        $reply_to = $message->get( 'reply_to' );
        if ( ! empty( $reply_to['email'] ) ) {
            $headers[] = 'Reply-To: ' . ( $reply_to['name']
                ? "{$reply_to['name']} <{$reply_to['email']}>"
                : $reply_to['email']
            );
        }

        $headers[] = 'Subject: ' . $this->encode_header( $message->get( 'subject', '' ) );
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = $has_attachments
            ? "Content-Type: multipart/mixed; boundary=\"{$mixed_boundary}\""
            : "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"";

        foreach ( $message->get( 'headers', [] ) as $name => $value ) {
            $headers[] = "{$name}: {$value}";
        }

        $this->write_raw( implode( $eol, $headers ) . $eol . $eol );

        if ( $has_attachments ) {
            $this->write_raw( "--{$mixed_boundary}{$eol}" );
            $this->write_raw( "Content-Type: multipart/alternative; boundary=\"{$alt_boundary}\"{$eol}{$eol}" );
            $this->stream_alternative_parts( $message, $alt_boundary );
            $this->write_raw( $eol );

            foreach ( $attachments as $attachment ) {
                $this->stream_attachment( $attachment, $mixed_boundary );
            }

            $this->write_raw( "--{$mixed_boundary}--{$eol}" );
        } else {
            $this->stream_alternative_parts( $message, $alt_boundary );
        }

        $this->write_raw( "\r\n.\r\n" );
    }

    /**
     * Stream the plain text and HTML alternative parts for a given boundary.
     *
     * @throws EmailTransportException
     */
    protected function stream_alternative_parts( EmailMessage $message, string $boundary ): void {
        $eol        = "\r\n";
        $html       = $message->get( 'body', '' );
        $plain_text = $message->get( 'text', '' );

        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: text/plain; charset=UTF-8{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}" );
        $this->write_dotted( quoted_printable_encode( $plain_text ) . $eol );

        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: text/html; charset=UTF-8{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: quoted-printable{$eol}{$eol}" );
        $this->write_dotted( quoted_printable_encode( $html ) . $eol );

        $this->write_raw( "--{$boundary}--{$eol}" );
    }

    /**
     * Stream a single attachment to the socket in base64 chunks.
     *
     * @throws EmailTransportException
     */
    protected function stream_attachment( array $attachment, string $boundary ): void {
        $eol              = "\r\n";
        $filename         = $attachment['filename'] ?? '';
        $mime_type        = $attachment['mime']     ?? 'application/octet-stream';
        $encoded_filename = $this->encode_header( $filename );

        $this->write_raw( "--{$boundary}{$eol}" );
        $this->write_raw( "Content-Type: {$mime_type}; name=\"{$encoded_filename}\"{$eol}" );
        $this->write_raw( "Content-Transfer-Encoding: base64{$eol}" );
        $this->write_raw( "Content-Disposition: attachment; filename=\"{$encoded_filename}\"{$eol}{$eol}" );

        if ( $attachment['type'] === 'path' ) {
            $filepath = $attachment['content'] ?? '';

            if ( ! file_exists( $filepath ) || ! is_readable( $filepath ) ) {
                $this->write_raw( $eol );
                return;
            }

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
        $response = $this->read_response( $expected_code );
        return $response;
    }

    /**
     * Write raw bytes to the socket.
     *
     * @throws EmailTransportException
     */
    protected function write_raw( string $data ): void {
        if ( fwrite( $this->socket, $data ) === false ) {
            throw new EmailTransportException( 'SMTPProvider: failed to write to socket.' );
        }
    }

    /**
     * Dot-stuff and write body content per RFC 5321 §4.5.2.
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
     * Handles:
     * - Multi-line responses (lines prefixed with "NNN-")
     * - Servers that send bare \n instead of \r\n
     * - Responses where the reason phrase is empty (e.g. "334 \r\n")
     * - A hard cap of 512 lines guards against pathological servers
     *
     * @throws EmailTransportException
     */
    protected function read_response( int $expected_code ): string {
        $response   = '';
        $max_lines  = 512;
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

            // RFC 5321 §4.2.1 — a space at position 3 signals the final
            // line of a (possibly multi-line) response. A hyphen means
            // more lines follow.
            //
            // We use rtrim() on the line before checking position 3 because
            // some servers send trailing spaces before CRLF which shifts the
            // character positions. We check the raw $line for the delimiter
            // since it still has its original structure.
            if ( strlen( $line ) >= 4 && $line[3] === ' ' ) {
                break;
            }

            // Guard: a very short line with no delimiter — stop reading to
            // avoid hanging on a malformed server response.
            if ( strlen( $line ) < 4 && trim( $line ) !== '' ) {
                break;
            }
        }

        if ( $lines_read >= $max_lines ) {
            throw new EmailTransportException(
                'SMTPProvider: server response exceeded maximum line limit.'
            );
        }

        $this->last_response = $response;
        $actual_code         = (int) substr( trim( $response ), 0, 3 );

        if ( $actual_code !== $expected_code ) {
            throw new EmailTransportException(
                "SMTPProvider: expected {$expected_code}, got {$actual_code}. "
                . 'Server said: ' . trim( $response )
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
     */
    protected function generate_message_id( string $from_email ): string {
        $domain = substr( strrchr( $from_email, '@' ) ?: '@localhost', 1 );
        return uniqid( '', true ) . '.' . bin2hex( random_bytes( 4 ) ) . '@' . $domain;
    }

    /**
     * Encode a header value containing non-ASCII characters (RFC 2047).
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
     * Priority:
     *   1. Explicit override from settings (helo_hostname)
     *   2. gethostname() if it resolves to an FQDN (contains a dot)
     *   3. gethostbyname() resolution of the system hostname
     *   4. Outbound IP as an RFC 2821 literal
     *   5. [127.0.0.1] as the last-resort safe fallback
     *
     * RFC 2821 §4.1.1.1 requires either a valid FQDN (must contain at
     * least one dot) or an IP address literal wrapped in square brackets.
     */
    public function get_local_hostname(): string {
        // 1. Explicit settings override.
        $override = trim( $this->settings['helo_hostname'] ?? '' );
        if ( $override !== '' ) {
            return $override;
        }

        // 2. System hostname — use if it is already an FQDN.
        $hostname = gethostname();
        if ( $hostname && strpos( $hostname, '.' ) !== false ) {
            // Wrap in brackets if it resolved to a bare IP.
            return filter_var( $hostname, FILTER_VALIDATE_IP )
                ? '[' . $hostname . ']'
                : $hostname;
        }

        // 3. Try to resolve the unqualified hostname to an FQDN or IP.
        if ( $hostname ) {
            $resolved = gethostbyname( $hostname );
            if ( $resolved !== $hostname ) {
                return filter_var( $resolved, FILTER_VALIDATE_IP )
                    ? '[' . $resolved . ']'
                    : $resolved;
            }
        }

        // 4. Resolve the OS node name.
        $ip = gethostbyname( php_uname( 'n' ) );
        if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
            return '[' . $ip . ']';
        }

        // 5. Safe fallback.
        return '[127.0.0.1]';
    }
}