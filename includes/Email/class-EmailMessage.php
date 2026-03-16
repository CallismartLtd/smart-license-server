<?php
/**
 * Email message class file.
 *
 * Represents a normalized email message to be sent via any provider.
 *
 * @package SmartLicenseServer\Email
 * @since 0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Core\DTO;
use InvalidArgumentException;

/**
 * EmailMessage DTO
 *
 * Standardized, validated email message object.
 *
 * Required keys must be present at construction time; passing invalid
 * values (bad addresses, empty subject, etc.) also throws immediately.
 *
 * Normalisation applied automatically via cast():
 *   - 'to', 'cc', 'bcc'            → always string[]
 *   - 'from', 'reply_to'           → always ['email' => string, 'name' => string]
 *   - 'attachments'                → always ['path'|'base64', 'content', 'filename', 'mime'][]
 *   - 'headers'                    → always string[]
 *   - 'subject', 'body', 'text'    → always string
 */
class EmailMessage extends DTO {

    /**
     * Keys that must be present in the initial data array.
     *
     * @var string[]
     */
    protected array $required_keys = [ 'to', 'subject', 'body' ];

    /**
     * Keys that may never be removed once set.
     *
     * @var string[]
     */
    protected array $immovable_keys = [ 'to', 'subject', 'body' ];

    /*----------------------------------------------------------
     * CONSTRUCTOR
     *---------------------------------------------------------*/

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Initial message data.
     *
     * @throws InvalidArgumentException If a required key is missing
     *                                  or any value fails validation.
     */
    public function __construct( array $data = [] ) {
        $this->assert_required_keys( $data );
        parent::__construct( $data );
        $this->validate();
    }

    /*----------------------------------------------------------
     * SCHEMA
     *---------------------------------------------------------*/

    /**
     * Allowed keys for the email message.
     *
     * @return string[]
     */
    protected function allowed_keys(): array {
        return [
            'from',        // array: ['email' => string, 'name' => string]
            'to',          // string[]: one or more recipient addresses
            'cc',          // string[]: optional CC recipients
            'bcc',         // string[]: optional BCC recipients
            'subject',     // string
            'body',        // string: HTML
            'text',        // string: plain text
            'attachments', // array: normalised attachment descriptors
            'headers',     // string[]: custom headers
            'reply_to',    // array: ['email' => string, 'name' => string]
        ];
    }

    /*----------------------------------------------------------
     * NORMALISATION
     *---------------------------------------------------------*/

    /**
     * Cast and normalise values before storage.
     *
     * @param string $key
     * @param mixed  $value
     * @return mixed
     */
    protected function cast( string $key, mixed $value ): mixed {
        return match ( true ) {
            in_array( $key, [ 'to', 'cc', 'bcc' ], true )   => $this->normalise_address_list( $value ),
            in_array( $key, [ 'from', 'reply_to' ], true )  => $this->normalise_address( $value ),
            $key === 'attachments'                          => $this->normalise_attachments( $value ),
            $key === 'headers'                              => is_array( $value ) ? $value : [],
            in_array( $key, [ 'subject', 'body' ], true )   => (string) $value,
            default                                         => $value,
        };
    }

    /**
     * Normalise a single address to a canonical shape.
     *
     * Accepts:
     *   - 'email@example.com'
     *   - ['email@example.com' => 'Display Name']
     *   - ['email' => 'email@example.com', 'name' => 'Display Name']
     *
     * Returns: ['email' => string, 'name' => string]
     *
     * @param mixed $value
     * @return array{email: string, name: string}
     */
    protected function normalise_address( mixed $value ): array {
        if ( is_string( $value ) ) {
            return [ 'email' => trim( $value ), 'name' => '' ];
        }

        if ( is_array( $value ) ) {
            // ['email' => '...', 'name' => '...'] — already canonical.
            if ( array_key_exists( 'email', $value ) ) {
                return [
                    'email' => trim( (string) ( $value['email'] ?? '' ) ),
                    'name'  => trim( (string) ( $value['name']  ?? '' ) ),
                ];
            }

            // ['email@example.com' => 'Display Name'] — map format.
            $email = (string) array_key_first( $value );
            $name  = (string) reset( $value );
            return [ 'email' => trim( $email ), 'name' => trim( $name ) ];
        }

        return [ 'email' => '', 'name' => '' ];
    }

    /**
     * Normalise a list of addresses to string[].
     *
     * Accepts a single address string or a plain array of strings.
     *
     * @param mixed $value
     * @return string[]
     */
    protected function normalise_address_list( mixed $value ): array {
        if ( is_string( $value ) ) {
            return [ trim( $value ) ];
        }

        if ( is_array( $value ) ) {
            return array_values(
                array_map( 'trim', array_filter( $value, 'is_string' ) )
            );
        }

        return [];
    }

    /**
     * Normalise attachments to a canonical descriptor array.
     *
     * Each attachment in the returned array has the shape:
     * [
     *     'type'     => 'path' | 'base64',
     *     'content'  => string,   // file path or base64-encoded string
     *     'filename' => string,   // name shown in the email
     *     'mime'     => string,   // MIME type, e.g. 'application/pdf'
     * ]
     *
     * @param mixed $value
     * @return array<int, array{type: string, content: string, filename: string, mime: string}>
     */
    protected function normalise_attachments( mixed $value ): array {
        if ( ! is_array( $value ) ) {
            return [];
        }

        $normalised = [];

        foreach ( $value as $attachment ) {
            // Already in canonical form.
            if ( is_array( $attachment ) && array_key_exists( 'type', $attachment ) ) {
                $normalised[] = [
                    'type'     => $attachment['type']     ?? 'path',
                    'content'  => $attachment['content']  ?? '',
                    'filename' => $attachment['filename'] ?? '',
                    'mime'     => $attachment['mime']     ?? 'application/octet-stream',
                ];
                continue;
            }

            // Bare file path string.
            if ( is_string( $attachment ) ) {
                $normalised[] = [
                    'type'     => 'path',
                    'content'  => $attachment,
                    'filename' => basename( $attachment ),
                    'mime'     => 'application/octet-stream',
                ];
            }
        }

        return $normalised;
    }

    /*----------------------------------------------------------
     * VALIDATION
     *---------------------------------------------------------*/

    /**
     * Assert all required keys are present in the raw data array.
     *
     * Called before parent::__construct() so we fail before any
     * assignment or casting takes place.
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    protected function assert_required_keys( array $data ): void {
        foreach ( $this->required_keys as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                throw new InvalidArgumentException(
                    "EmailMessage: required field '{$key}' is missing."
                );
            }
        }
    }

    /**
     * Validate the current state of the message.
     *
     * Called automatically at the end of construction. May also be
     * called manually after incremental mutation to re-confirm validity.
     *
     * @throws InvalidArgumentException On any invalid value.
     */
    public function validate(): void {
        $this->validate_address_list( 'to' );
        $this->validate_scalar( 'subject' );
        $this->validate_scalar( 'body' );

        if ( $this->has( 'from' ) ) {
            $this->validate_single_address( 'from' );
        }

        if ( $this->has( 'reply_to' ) ) {
            $this->validate_single_address( 'reply_to' );
        }

        foreach ( [ 'cc', 'bcc' ] as $field ) {
            if ( $this->has( $field ) ) {
                $this->validate_address_list( $field );
            }
        }
    }

    /**
     * Assert a stored address list is non-empty and contains valid addresses.
     *
     * @param string $field
     * @throws InvalidArgumentException
     */
    protected function validate_address_list( string $field ): void {
        $list = $this->get( $field, [] );

        if ( empty( $list ) ) {
            throw new InvalidArgumentException(
                "EmailMessage: '{$field}' must contain at least one address."
            );
        }

        foreach ( $list as $address ) {
            if ( ! filter_var( $address, FILTER_VALIDATE_EMAIL ) ) {
                throw new InvalidArgumentException(
                    "EmailMessage: '{$address}' in '{$field}' is not a valid email address."
                );
            }
        }
    }

    /**
     * Assert a normalised address descriptor contains a valid email.
     *
     * @param string $field
     * @throws InvalidArgumentException
     */
    protected function validate_single_address( string $field ): void {
        $address = $this->get( $field );

        if ( ! is_array( $address ) || empty( $address['email'] ) ) {
            throw new InvalidArgumentException(
                "EmailMessage: '{$field}' must contain a valid email address."
            );
        }

        if ( ! filter_var( $address['email'], FILTER_VALIDATE_EMAIL ) ) {
            throw new InvalidArgumentException(
                "EmailMessage: '{$address['email']}' in '{$field}' is not a valid email address."
            );
        }
    }

    /**
     * Assert a scalar field is a non-empty string after casting.
     *
     * @param string $field
     * @throws InvalidArgumentException
     */
    protected function validate_scalar( string $field ): void {
        $value = $this->get( $field, '' );

        if ( ! is_string( $value ) || trim( $value ) === '' ) {
            throw new InvalidArgumentException(
                "EmailMessage: '{$field}' must be a non-empty string."
            );
        }
    }

    /*----------------------------------------------------------
     * GUARD AGAINST REMOVAL OF REQUIRED FIELDS
     *---------------------------------------------------------*/

    /**
     * Prevent required fields from being removed.
     *
     * @param string $key
     * @return static
     * @throws InvalidArgumentException
     */
    public function remove( string $key ): static {
        if ( in_array( $key, $this->immovable_keys, true ) ) {
            throw new InvalidArgumentException(
                "EmailMessage: required field '{$key}' cannot be removed."
            );
        }
        return parent::remove( $key );
    }
}