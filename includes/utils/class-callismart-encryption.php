<?php
namespace Callismart\Utilities;
/**
 * Utility class for encrypting and decrypting API keys.
 */
class Encryption {
    /**
     * Encryption method
     * 
     * @var string $cipher_algo The encryption algorithm.
     */
    private static $cipher_algo = 'AES-256-CBC';

    /**
     * Secret Key for encryption.
     * 
     * @var string $secret_key The WordPress Secure Auth Key.
     */
    private static $secret_key  = SECURE_AUTH_KEY;

    /**
     * The encryption salt.
     * 
     * @var string $salt The WordPress salt.
     */
    private static $salt        = SECURE_AUTH_SALT;

    /**
     * IV Length
     * 
     * @var int $iv_length The initialization vector length.
     */
    private static $iv_length; // IV length based on the encryption method.

    /**
     * Static initializer for the class.
     */
    private static function initialize() {
        if ( is_null( self::$iv_length ) ) {
            self::$iv_length = openssl_cipher_iv_length( self::$cipher_algo );
        }
    }

    /**
     * Encrypt data.
     *
     * @param string $data The data to encrypt.
     * @return string|WP_Error Encrypted data or WP_Error on failure.
     */
    public static function encrypt( $data ) {
        self::initialize();

        if ( ! is_string( $data ) || empty( $data ) ) {
            return new \WP_Error( 'invalid_input', 'Data must be a string.' );
        }

        $iv = random_bytes( self::$iv_length );

        $encryption_key = self::derive_key();

        $encrypted = openssl_encrypt(
            $data,
            self::$cipher_algo,
            $encryption_key,
            0,
            $iv
        );

        if ( false === $encrypted ) {
            return new \WP_Error( 'encryption_failed', 'Failed to encrypt the data.' );
        }

        // Store IV with the encrypted data (base64-encoded for storage)
        return base64_encode( $iv . $encrypted );
    }

    /**
     * Decrypt an encrypted data.
     *
     * @param string $encrypted_data The encrypted data.
     * @return string|WP_Error Decrypted data or WP_Error on failure.
     */
    public static function decrypt( $encrypted_data ) {
        self::initialize();

        if ( ! is_string( $encrypted_data ) || empty( $encrypted_data ) ) {
            return new \WP_Error( 'invalid_input', 'Encrypted data must be a string.' );
        }

        $decoded_data = base64_decode( $encrypted_data );

        if ( false === $decoded_data ) {
            return new \WP_Error( 'decoding_failed', 'Failed to decode the encrypted data.' );
        }

        $iv = substr( $decoded_data, 0, self::$iv_length );
        $encrypted_string = substr( $decoded_data, self::$iv_length );

        if ( empty( $iv ) || empty( $encrypted_string ) ) {
            return new \WP_Error( 'invalid_encrypted_data', 'The encrypted data is malformed.' );
        }

        $decryption_key = self::derive_key();

        $decrypted = openssl_decrypt(
            $encrypted_string,
            self::$cipher_algo,
            $decryption_key,
            0,
            $iv
        );

        if ( false === $decrypted ) {
            return new \WP_Error( 'decryption_failed', 'Failed to decrypt the data.' );
        }

        return $decrypted;
    }

    /**
     * Derive a cryptographically secure key from the secret and salt.
     *
     * @return string Derived key.
     */
    private static function derive_key() {
        return hash_hkdf( 'sha256', self::$secret_key, 32, '', self::$salt );
    }
}
