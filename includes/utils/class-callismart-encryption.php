<?php
namespace Callismart\Utilities;

use SmartLicenseServer\Exception;

defined( 'ABSPATH' ) || exit;

/**
 * Utility class for encrypting and decrypting data securely and URL-safely.
 */
class Encryption {

    /**
     * The encryption cipher algorithm.
     *
     * @var string
     */
    private static $cipher_algo = 'AES-256-CBC';

    /**
     * Initialization vector length.
     *
     * @var int
     */
    private static $iv_length;

    /**
     * Initialize any dynamic/static values.
     */
    private static function initialize() {
        if ( is_null( self::$iv_length ) ) {
            self::$iv_length = openssl_cipher_iv_length( self::$cipher_algo );
        }
    }

    /**
     * Encrypt the given string data into a URL-safe base64 string.
     *
     * @param string $data Plain text data to encrypt.
     * @return string|Exception URL-safe encrypted string or WP_Error on failure.
     */
    public static function encrypt( $data ) {
        self::initialize();

        if ( ! is_string( $data ) || empty( $data ) ) {
            return new Exception( 'invalid_input', 'Data must be a non-empty string.' );
        }

        $iv             = random_bytes( self::$iv_length );
        $key            = self::derive_key();
        $encrypted      = openssl_encrypt( $data, self::$cipher_algo, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $encrypted ) {
            return new Exception( 'encryption_failed', 'Unable to encrypt the data.' );
        }

        return self::base64url_encode( $iv . $encrypted );
    }

    /**
     * Decrypt the previously encrypted string.
     *
     * @param string $encrypted_data The URL-safe encrypted string.
     * @return string|Exception The decrypted plain text or WP_Error on failure.
     */
    public static function decrypt( $encrypted_data ) {
        self::initialize();

        if ( ! is_string( $encrypted_data ) || empty( $encrypted_data ) ) {
            return new Exception( 'invalid_input', 'Encrypted data must be a non-empty string.' );
        }

        $decoded_data = self::base64url_decode( $encrypted_data );

        if ( false === $decoded_data || strlen( $decoded_data ) <= self::$iv_length ) {
            return new Exception( 'invalid_encrypted_data', 'The encrypted data is malformed or too short.' );
        }

        $iv                 = substr( $decoded_data, 0, self::$iv_length );
        $encrypted_string   = substr( $decoded_data, self::$iv_length );
        $key                = self::derive_key();

        $decrypted = openssl_decrypt( $encrypted_string, self::$cipher_algo, $key, OPENSSL_RAW_DATA, $iv );

        if ( false === $decrypted ) {
            return new Exception( 'decryption_failed', 'Failed to decrypt the data.' );
        }

        return $decrypted;
    }

    /**
     * Derive a secure key using HKDF with WordPress salts.
     *
     * @return string 32-byte encryption key.
     */
    private static function derive_key() {
        $secret = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : wp_salt();
        $salt   = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : wp_salt();

        return hash_hkdf( 'sha256', $secret, 32, '', $salt );
    }

    /**
     * Encode binary data into URL-safe base64.
     *
     * @param string $data The binary data to encode.
     * @return string Base64url-encoded string.
     */
    private static function base64url_encode( $data ) {
        return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
    }

    /**
     * Decode URL-safe base64 string into raw binary.
     *
     * @param string $data The base64url-encoded string.
     * @return string|false Decoded binary string or false on failure.
     */
    private static function base64url_decode( $data ) {
        $data = strtr( $data, '-_', '+/' );
        $pad = strlen( $data ) % 4;
        if ( $pad > 0 ) {
            $data .= str_repeat( '=', 4 - $pad );
        }
        return base64_decode( $data );
    }
}
