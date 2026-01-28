<?php
/**
 * Token delivery trait file
 * 
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Utils
 */

namespace SmartLicenseServer\Utils;

use function defined;

defined( 'SMLISER_ABSPATH' ) || exit;

trait TokenDeliveryTrait {
    /**
     * Derive a secure key using HKDF with salts.
     *
     * @return string 32-byte encryption key.
     */
    public static function derive_key() {
        $secret = defined( 'SECURE_AUTH_KEY' ) ? SECURE_AUTH_KEY : 'zNqTILD04P3jQ8$Ev[v$Tec[Wf*(6>RxYukbrv{|qUOUr>($1@uO:ur%*<VX@:]&';
        $salt   = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : '7RY@sFBq:]9?JR`21~_45]$Iy.*bF8bO4(L{Ks9(yBxur +i=3D9@`b-fRF_K8JJ';

        return hash_hkdf( 'sha256', $secret, 32, '', $salt );
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
}