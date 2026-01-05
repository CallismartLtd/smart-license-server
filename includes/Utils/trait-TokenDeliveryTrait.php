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
}