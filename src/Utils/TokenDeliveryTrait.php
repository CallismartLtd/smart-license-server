<?php
/**
 * Token delivery trait file
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

use LogicException;

/**
 * Key derivation and token helpers.
 *
 * The using class sets $secret (and $salt) in its constructor from values
 * the container passes in; nothing here reads global constants.
 */
trait TokenDeliveryTrait {

    /**
     * Application secret keys are derived from.
     *
     * @var string
     */
    protected string $secret = '';

    /**
     * Application salt used by key derivation.
     *
     * @var string
     */
    protected string $salt = '';

    /**
     * Derive a secure key using HKDF with salts.
     *
     * Each context gives an unrelated key, so keys derived for one purpose
     * reveal nothing about keys for another.
     *
     * @param string $context Purpose of the key.
     * @return string 32-byte key.
     * @throws LogicException When the using class did not set the secret.
     */
    protected function derive_key( string $context = 'default' ) : string {
        if ( '' === $this->secret ) {
            throw new LogicException( sprintf( '%s must set its secret before deriving keys.', static::class ) );
        }

        return hash_hkdf( 'sha256', $this->secret, 32, $context, $this->salt );
    }

    /**
     * Encode a string to URL-safe Base64.
     *
     * @param string $data
     * @return string
     */
    private static function base64url_encode( string $data ) : string {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Decode a URL-safe Base64 string.
     *
     * @param string $data
     * @return string
     */
    private static function base64url_decode( string $data ) : string {
        $padding = 4 - ( strlen( $data ) % 4 );
        if ( $padding < 4 ) {
            $data .= str_repeat( '=', $padding );
        }
        return base64_decode( strtr( $data, '-_', '+/' ) );
    }

    /**
     * Generate secure random token.
     *
     * @param int $length
     * @return string
     */
    private static function generate_secure_token( int $length = 32 ) : string {
        return \bin2hex( \random_bytes( $length ) );
    }

    /**
     * Wrapper for PHP's password_hash function.
     *
     * @param string $password
     * @param int $algo Default is PASSWORD_BCRYPT
     * @return string
     */
    private static function hash_password( string $password, string|int|null $algo = PASSWORD_BCRYPT ) : string {
        return password_hash( $password, $algo );
    }

    /**
     * Wrapper for PHP's password_verify function.
     *
     * @param string $password
     * @param string $hash
     * @return bool
     */
    private static function verify_password( string $password, string $hash ) : bool {
        return password_verify( $password, $hash );
    }

    /**
     * Wrapper for PHP's hash_hmac function.
     *
     * @param string $data
     * @param string $key
     * @param string $algo Default is 'sha256'
     * @return string
     */
    private static function hmac_hash( string $data, string $key, string $algo = 'sha256' ) : string {
        return hash_hmac( $algo, $data, $key );
    }

    private static function generate_uuid_v4() : string {
        return smliser_generate_uuid_v4();
    }

}