<?php
/**
 * The APP Download token class file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer
 * @subpackage Monetization
 */

namespace SmartLicenseServer\Monetization;

use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\HostedApps\AbstractHostedApp;
use SmartLicenseServer\Monetization\License;
use SmartLicenseServer\Utils\CommonQueryTrait;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

/**
 * Represents the token given to the client in exchange for download access
 * to hosted applications in the repository.
 */
class DownloadToken {
    use CommonQueryTrait, TokenDeliveryTrait;
    /**
     * Token ID.
     * 
     * @var int
     */
    protected $id = 0;

    /**
     * Associated application properties.
     * 
     * @var array
     */
    protected $app_prop = [
        'app_type' => '',
        'app_slug' => ''
    ];

    /**
     * License key associated with this token.
     * 
     * @var string
     */
    protected $license_key = '';

    /**
     * The download token string.
     * 
     * @var string
     */
    protected $token = '';

    /**
     * Token expiry timestamp.
     * 
     * @var int
     */
    protected $expiry = 0;

    /**
     * Class constructor.
     */
    public function __construct() {}

    /*
    |-----------------
    | SETTERS
    |-----------------
    */

    /**
     * Set the token ID.
     * 
     * @param int $id
     * @return self
     */
    public function set_id( int $id ) : self {
        $this->id = max( 0, $id );
        return $this;
    }

    /**
     * Set application properties.
     * 
     * @param string $type App type
     * @param string $slug App slug
     * @return self
     */
    public function set_app_prop( string $type, string $slug ) : self {
        $this->app_prop['app_type'] = $type;
        $this->app_prop['app_slug'] = $slug;
        return $this;
    }

    /**
     * Set the associated license key.
     * 
     * @param string $license_key
     * @return self
     */
    public function set_license_key( string $license_key ) : self {
        $this->license_key = sanitize_text_field( unslash( $license_key ) );
        return $this;
    }

    /**
     * Set the download token string.
     * 
     * @param string $token
     * @return self
     */
    public function set_token( string $token ) : self {
        $this->token = sanitize_text_field( unslash( $token ) );
        return $this;
    }

    /**
     * Set the expiry timestamp.
     * 
     * @param int $expiry Unix timestamp
     * @return self
     */
    public function set_expiry( int $expiry ) : self {
        $this->expiry = max( 0, $expiry );
        return $this;
    }

    /*
    |-----------------
    | GETTERS
    |-----------------
    */

    /**
     * Get token ID.
     * 
     * @return int
     */
    public function get_id() : int {
        return $this->id;
    }

    /**
     * Get application properties.
     * 
     * @param string $context 'view' returns formatted string, 'edit' returns raw array
     * @return array|string
     */
    public function get_app_prop( string $context = 'view' ) : array|string {
        if ( 'view' === $context ) {
            $prop = array_filter( $this->app_prop );
            return implode( '/', $prop );
        }
        return $this->app_prop;
    }

    /**
     * Get license key.
     * 
     * @return string
     */
    public function get_license_key() : string {
        return $this->license_key;
    }

    /**
     * Get download token string.
     * 
     * @return string
     */
    public function get_token() : string {
        return $this->token;
    }

    /**
     * Get expiry timestamp.
     * 
     * @return int
     */
    public function get_expiry() : int {
        return $this->expiry;
    }

    /*
    |-----------------
    | CRUD METHODS
    |-----------------
    */

    /**
     * Save the token to the database.
     * 
     * @return bool True on success, false otherwise
     */
    private function save() : bool {
        $db = \smliser_dbclass();
        $table = \SMLISER_APP_DOWNLOAD_TOKEN_TABLE;

        $data = [
            'app_prop'    => $this->get_app_prop( 'view' ),
            'license_key' => $this->license_key,
            'token'       => $this->token,
            'expiry'      => $this->expiry
        ];

        if ( $this->id ) {
            $updated = $db->update( $table, $data, ['id' => $this->id] );
            return false !== $updated;
        }

        $inserted = $db->insert( $table, $data );
        if ( $inserted ) {
            $this->set_id( $db->get_insert_id() );
            return true;
        }

        return false;
    }

    /**
     * Delete the token from the database.
     * 
     * @return bool
     */
    public function delete() : bool {
        if ( ! $this->id ) {
            return false;
        }

        $db = \smliser_dbclass();
        $table = \SMLISER_APP_DOWNLOAD_TOKEN_TABLE;

        $deleted = $db->delete( $table, ['id' => $this->id] );
        return false !== $deleted;
    }

    /**
     * Load a token by ID.
     * 
     * @param int $id
     * @return self|null
     */
    public static function get_by_id( int $id ) : ?self {
        return self::get_self_by_id( $id, \SMLISER_APP_DOWNLOAD_TOKEN_TABLE );
    }

    /**
     * Load a token by token string.
     * 
     * @param string $token
     * @return self|null
     */
    public static function get_by_token( string $token ) : ?self {
        $db = \smliser_dbclass();
        $table = \SMLISER_APP_DOWNLOAD_TOKEN_TABLE;

        $row = $db->get_row( "SELECT * FROM {$table} WHERE token = ?", [$token] );
        return $row ? self::from_array( $row ) : null;
    }

    /**
     * Clean expired tokens
     */
    public static function clean_expired_tokens() {
        $db     = \smliser_dbclass();
        $table  = \SMLISER_APP_DOWNLOAD_TOKEN_TABLE;

        $sql    = "DELETE FROM {$table} WHERE expiry < ?";

        $db->query( $sql, [\time()] );

    }

    /*
    |-----------------
    | UTILITY METHODS
    |-----------------
    */

    /**
     * Check whether the token is expired.
     * 
     * @return bool
     */
    public function is_expired() : bool {
        return $this->expiry > 0 && time() > $this->expiry;
    }

    /**
     * Generate a unique token string.
     * 
     * @param int $length Length of random token (default 32)
     * @return string
     */
    private static function generate_token( int $length = 32 ) : string {
        return sprintf( 'smliser_%s', bin2hex( random_bytes( $length ) ) );
    }

    /**
     * Convert database row or array into a DownloadToken object.
     * 
     * @param array $data
     * @return self
     */
    private static function from_array( array $data ) : self {
        $self = new self();
        $self->set_id( $data['id'] ?? 0 );
        if ( ! empty( $data['app_prop'] ) && preg_match( '#^[^/]+/[^/]+$#', $data['app_prop'] ) ) {
            list( $type, $slug ) = explode( '/', $data['app_prop'], 2 );
            $self->set_app_prop( $type, $slug );
        }
        $self->set_license_key( $data['license_key'] ?? '' );
        $self->set_token( $data['token'] ?? '' );
        $self->set_expiry( intval( $data['expiry'] ?? 0 ) );

        return $self;
    }

    /**
    |-------------------------------------------
    | TOKEN GENERATION AND VERIFICATION METHODS
    |-------------------------------------------
     */

    /**
     * Generate a new download token for the app associated with a license
     * 
     * @param License $license The license object.
     * @param int $expiry The license expiry duration in seconds.
     * @throws SmartLicenseServer\Exception
     */
    public static function create_token( License $license, $expiry = 86400 ) {
        // We cant issue token for a license that is not issued
        if ( ! $license->is_issued() ) {
            throw new Exception( 'download_token_error', 'License must be issued to an application first', ['status' => 400] );
        }

        $self = new self();
        $app_type   = $license->get_app()->get_type();
        $app_slug   = $license->get_app()->get_slug() ;
        $expiry     = time() + (int) $expiry;

        $self->set_app_prop( $app_type, $app_slug );
        $self->set_license_key( $license->get_license_key() );
        $self->set_expiry( $expiry );

        $raw_token  = self::generate_token();
        $secret     = self::derive_key();

        $self->set_token( \hash_hmac( 'sha256', $raw_token, $secret ) );

        $payload = [
            'license_id'    => $license->get_id(),
            'app_prop'      => $license->get_app_prop(),
            'expiry_ts'     => $expiry,
            'timestamp'     => time(),
            'token'         => $raw_token
        ];

        $encoded_payload    = \smliser_safe_json_encode( $payload );
        $signature          = \hash_hmac( 'sha256', $encoded_payload, $secret );


        if ( ! $self->save() ) {
            throw new Exception( 'download_token_error', 'Unable to save token in the database', ['status' => 500] );
        }

        $token = sprintf( '%s.%s', $encoded_payload, $signature );

        return self::base64url_encode( $token );

    }

    /**
     * Verify the download token issued to the app in context.
     *
     * @param string $client_token
     * @param AbstractHostedApp $app
     * @return self
     */
    public static function verify_token_for_app( string $client_token, AbstractHostedApp $app ) : self {
        $decoded = self::base64url_decode( $client_token );

        $parts = explode('.', $decoded, 2);
        if (count($parts) !== 2) {
            throw new Exception('download_token_invalid', 'Malformed token', ['status' => 400]);
        }
        [$encoded_payload, $signature] = $parts;
        
        $secret = self::derive_key();

        if ( ! hash_equals( hash_hmac( 'sha256', $encoded_payload, $secret ), $signature ) ) {
            throw new Exception( 'download_token_invalid', 'Invalid token signature', ['status' => 403] );
        }

        $payload = json_decode( $encoded_payload, true );
        if ( ! $payload || empty( $payload['token'] ) ) {
            throw new Exception( 'download_token_invalid', 'Invalid payload', ['status' => 400] );
        }

        $self = self::get_by_token( hash_hmac( 'sha256', $payload['token'], $secret ) );
        if ( ! $self ) {
            throw new Exception( 'download_token_invalid', 'Token not found', ['status' => 404] );
        }

        if ( $self->is_expired() ) {
            throw new Exception( 'download_token_expired', 'Token has expired', ['status' => 403] );
        }

        $app_type   = $app->get_type();
        $app_slug   = $app->get_slug();
        // Context check: make sure token belongs to requested app
        $token_app_prop = $self->get_app_prop('view');
        $requested_app_prop = sprintf('%s/%s', $app_type, $app_slug);

        if ( $token_app_prop !== $requested_app_prop ) {
            throw new Exception( 'download_token_invalid', 'Token does not match requested app', ['status' => 403] );
        }

        return $self;
    }
}
