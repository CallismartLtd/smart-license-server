<?php
/**
 * Email response class file.
 *
 * Represents a normalized response from an email provider.
 *
 * @package SmartLicenseServer\Email
 * @since 0.2.0
 */
declare( strict_types = 1 );

namespace SmartLicenseServer\Email;

use SmartLicenseServer\Core\DTO;
use InvalidArgumentException;

/**
 * EmailResponse DTO
 *
 * Standardized email response object returned by every provider
 * after a send attempt.
 *
 * Construct directly or via named constructors:
 *
 *   EmailResponse::ok( 'msg-abc123' );
 *   EmailResponse::fail( 'Connection timed out.' );
 *
 * Normalisation applied automatically via cast():
 *   - 'success'      → always bool
 *   - 'message_id'   → string | null
 *   - 'error'        → string | null
 *   - 'raw_response' → mixed (passed through)
 *   - 'timestamp'    → always int (defaults to now)
 */
class EmailResponse extends DTO {

    /**
     * Keys that must be present at construction time.
     *
     * @var string[]
     */
    protected array $required_keys = [ 'success' ];

    /*----------------------------------------------------------
     * CONSTRUCTOR
     *---------------------------------------------------------*/

    /**
     * Constructor.
     *
     * @param array<string, mixed> $data Response data.
     * @throws InvalidArgumentException If 'success' is missing.
     */
    public function __construct( array $data = [] ) {
        $this->assert_required_keys( $data );
        parent::__construct( $data );
    }

    /*----------------------------------------------------------
     * NAMED CONSTRUCTORS
     *---------------------------------------------------------*/

    /**
     * Create a successful response.
     *
     * @param string               $message_id Provider-assigned message ID.
     * @param mixed                $raw        Optional raw provider payload.
     * @return static
     */
    public static function ok( string $message_id, mixed $raw = null ): static {
        return new static( [
            'success'      => true,
            'message_id'   => $message_id,
            'raw_response' => $raw,
        ] );
    }

    /**
     * Create a failed response.
     *
     * @param string $error Human-readable error description.
     * @param mixed  $raw   Optional raw provider payload.
     * @return static
     */
    public static function fail( string $error, mixed $raw = null ): static {
        return new static( [
            'success'      => false,
            'error'        => $error,
            'raw_response' => $raw,
        ] );
    }

    /*----------------------------------------------------------
     * SCHEMA
     *---------------------------------------------------------*/

    /**
     * Allowed keys for the email response.
     *
     * @return string[]
     */
    protected function allowed_keys(): array {
        return [
            'success',       // bool: whether the provider accepted the message
            'message_id',    // string|null: provider-generated message ID
            'error',         // string|null: error description on failure
            'raw_response',  // mixed: full provider payload for logging/debug
            'timestamp',     // int: Unix timestamp of when the response was created
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
        return match ( $key ) {
            'success'    => (bool) $value,
            'message_id' => $value !== null ? (string) $value : null,
            'error'      => $value !== null ? (string) $value : null,
            'timestamp'  => $value !== null ? (int) $value : time(),
            default      => $value,
        };
    }

    /*----------------------------------------------------------
     * VALIDATION
     *---------------------------------------------------------*/

    /**
     * Assert required keys are present in the raw data array.
     *
     * @param array<string, mixed> $data
     * @throws InvalidArgumentException
     */
    protected function assert_required_keys( array $data ): void {
        foreach ( $this->required_keys as $key ) {
            if ( ! array_key_exists( $key, $data ) ) {
                throw new InvalidArgumentException(
                    "EmailResponse: required field '{$key}' is missing."
                );
            }
        }
    }

    /*----------------------------------------------------------
     * CONVENIENCE HELPERS
     *---------------------------------------------------------*/

    /**
     * Whether the send was accepted by the provider.
     *
     * @return bool
     */
    public function is_success(): bool {
        return (bool) $this->get( 'success' );
    }

    /**
     * Whether the send failed.
     *
     * @return bool
     */
    public function is_failure(): bool {
        return ! $this->is_success();
    }
}