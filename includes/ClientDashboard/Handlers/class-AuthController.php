<?php
/**
 * Authentication Controller
 *
 * Centralized handler for all authentication actions:
 * - Login
 * - Signup
 * - Password reset requests
 * - 2FA verification
 *
 * Environment-agnostic. Delegates to environment provider for business logic.
 * No WordPress or framework-specific dependencies.
 *
 * @package SmartLicenseServer\RESTAPI\Controllers
 */

namespace SmartLicenseServer\ClientDashboard\Handlers;

use SmartLicenseServer\Background\Jobs\Accounts\PasswordResetJob;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Core\Dates\DateDuration;
use SmartLicenseServer\Core\Dates\TimestampValue;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

class AuthController {
    use QueueAwareTrait, TokenDeliveryTrait;

    /*
    |--------------------------------------------------
    | LOGIN
    |--------------------------------------------------
    */

    /**
     * Handle login form submission.
     *
     * Authenticates user with username/email and password.
     * Sets authenticated session on success.
     *
     * @param Request $request Contains: username, password, remember, _wpnonce_login
     * @return Response JSON response
     */
    public static function handle_login( Request $request ) : Response {
        $username   = (string) $request->get( 'username', '' );
        $password   = (string) $request->get( 'password', '', false );
        $remember   = (bool) $request->get( 'remember', false );

        if ( empty( $username ) || empty( $password ) ) {
            return static::error_response(
                400,
                'missing_credentials',
                'Username and password are required.'
            );
        }

        $principal = \identityProvider()->logon( $username, $password, $remember );

        if ( $principal instanceof RequestException ) {
            return static::error_response(
                401,
                $principal->get_error_code(),
                $principal->get_error_message()
            );
        }

        // Return success with redirect
        return static::success_response(
            200,
            [
                'success'  => true,
                'message'  => sprintf( 'Welcome back, %s.', $principal->get_display_name() ),
                'redirect' => '/dashboard',
            ]
        );
    }

    /*
    |--------------------------------------------------
    | SIGNUP
    |--------------------------------------------------
    */

    /**
     * Handle signup form submission.
     *
     * Creates new user account with email and password.
     * Sends verification email.
     *
     * @param Request $request Contains: full_name, email, password, password_confirm, agree_terms, _wpnonce_signup
     * @return Response JSON response
     */
    public static function handle_signup( Request $request ) : Response {

        // Extract request data
        $full_name         = (string) $request->get( 'full_name', '' );
        $email             = (string) $request->get( 'email', '' );
        $password          = (string) $request->get( 'password', '' );
        $password_confirm  = (string) $request->get( 'password_confirm', '' );
        $agree_terms       = (bool) $request->get( 'agree_terms', false );
        $nonce             = (string) $request->get( '_wpnonce_signup', '' );

        // Verify CSRF nonce
        if ( ! static::verify_nonce( $nonce, 'smliser_auth_signup' ) ) {
            return static::error_response(
                401,
                'invalid_nonce',
                'Security token expired. Please try again.'
            );
        }

        // Validate input
        $validation = static::validate_signup_input(
            $full_name,
            $email,
            $password,
            $password_confirm,
            $agree_terms
        );

        if ( ! $validation['valid'] ) {
            return static::error_response(
                400,
                'validation_error',
                $validation['message']
            );
        }

        // Create user account via environment provider
        $result = \smliser_envProvider()->create_user(
            [
                'full_name' => $full_name,
                'email'     => $email,
                'password'  => $password,
            ]
        );

        if ( ! $result['success'] ) {
            return static::error_response(
                400,
                'account_creation_failed',
                $result['message'] ?? 'Failed to create account. Please try again.'
            );
        }

        // Send verification email
        static::send_verification_email( $result['principal'] ?? null, $email );

        // Return success
        return static::success_response(
            200,
            [
                'success' => true,
                'message' => 'Account created successfully! Check your email to verify your account.',
            ]
        );
    }

    /*
    |---------------------
    | FORGOT PASSWORD
    |---------------------
    */

    /**
     * Handle forgot password form submission.
     *
     * Sends password reset link to email address.
     *
     * @param Request $request
     * @return Response JSON response
     */
    public static function handle_forgot_password( Request $request ) : Response {
        $email = (string) $request->get( 'email', '' );

        // Validate email
        if ( empty( $email ) || ! static::is_valid_email( $email ) ) {
            return static::error_response(
                400,
                'invalid_email',
                'Please provide a valid email address.'
            );
        }

        $response_data  = [
            'success' => true,
            'message' => 'If an account exists for this email, you will receive a password reset link shortly.',
        ];

        $user   = User::get_by_email( $email );

        if ( ! $user ) {
            return static::success_response( 200, $response_data );
        }

        ( new static )->password_recovery( $user );

        return static::success_response( 200, $response_data );
    }

    /**
     * Handle password reset.
     * 
     * @param Request $request
     * @return Response
     */
    // public static function handle_reset_password( Request $request ) : Response {
        
    // }

    /**
     * Handle password recovery process.
     *
     * @param User $user
     */
    private function password_recovery( User $user ) : void {

        $cache = \smliser_cache();

        $raw_key = static::generate_secure_token();

        $payload = [
            'id'        => $user->get_id(),
            'timestamp' => time(),
            'nonce'     => $raw_key,
        ];

        $encoded_payload = \smliser_safe_json_encode( $payload );

        $secret = self::derive_key();

        // Signature now includes full payload INCLUDING nonce.
        $signature = self::hmac_hash( $encoded_payload, $secret, 'sha256' );

        $token = self::base64url_encode(
            sprintf( '%s.%s', $encoded_payload, $signature )
        );

        // Store hashed token for single-use protection.
        $cache_key = sprintf(
            '%s_%d',
            UserSettings::PWD_RESET_NAME,
            $user->get_id()
        );

        $cache->set(
            $cache_key,
            hash( 'sha256', $token ),
            DateDuration::fromHours(1)->toSeconds()
        );

        $reset_link = smliser_client_dashboard_url()
            ->add_query_params( array( 'key' => $token ) )
            ->set_hash( 'reset-password' );

        ( new static )->dispatch_job(
            PasswordResetJob::class,
            array(
                'user_id'    => $user->get_id(),
                'recipient'  => $user->get_email(),
                'reset_url'  => $reset_link,
                'expires_in' => 3600,
                'ip_address' => \smliser_get_client_ip(),
                'user_agent' => \smliser_get_user_agent(),
            )
        );
    }

    /**
     * Verify password reset token.
     *
     * @param string $token
     * @return array{valid: bool, user_id?: int, reason?: string}
     */
    public static function verify_password_reset_token( #[\SensitiveParameter] string $token ) : array {
        $cache = \smliser_cache();
        $decoded = self::base64url_decode( $token );

        if ( ! $decoded || ! str_contains( $decoded, '.' ) ) {
            return ['valid' => false, 'reason' => 'Invalid token format'];
        }

        [ $encoded_payload, $signature ] = explode( '.', $decoded, 2 );

        $expected_signature = self::hmac_hash( $encoded_payload, self::derive_key(), 'sha256' );
        
        if ( ! hash_equals( $expected_signature, $signature ) ) {
            return ['valid' => false, 'reason' => 'Invalid signature'];
        }

        $payload = json_decode( $encoded_payload, true );
        if ( ! is_array( $payload ) || empty( $payload['id'] ) ) {
            return ['valid' => false, 'reason' => 'Invalid payload'];
        }

        $issuedAt = TimestampValue::fromTimestamp( (int) $payload['timestamp'] );
        if ( $issuedAt->addHours(1)->isPast() ) {
            return ['valid' => false, 'reason' => 'Token expired'];
        }

        $cache_key = sprintf( '%s_%d', UserSettings::PWD_RESET_NAME, $payload['id'] );
        $stored_hash = $cache->get( $cache_key );
        $current_hash = hash( 'sha256', $token );

        if ( ! $stored_hash || ! hash_equals( $stored_hash, $current_hash ) ) {
            return ['valid' => false, 'reason' => 'Token already used or invalidated'];
        }

        $cache->delete( $cache_key );

        return [
            'valid'   => true,
            'user_id' => (int) $payload['id'],
        ];
    }

    /*
    |--------------------------------------------------
    | TWO-FACTOR AUTHENTICATION
    |--------------------------------------------------
    */

    /**
     * Handle 2FA verification.
     *
     * Verifies TOTP code or backup code and completes authentication.
     *
     * @param Request $request Contains: verification_code OR backup_code, _wpnonce_2fa
     * @return Response JSON response
     */
    public static function handle_2fa( Request $request ) : Response {

        // Get partially authenticated principal from session
        $principal = static::get_partial_auth_principal();

        if ( ! $principal ) {
            return static::error_response(
                401,
                'no_partial_auth',
                'Session expired. Please log in again.'
            );
        }

        // Extract request data
        $verification_code = (string) $request->get( 'verification_code', '' );
        $backup_code       = (string) $request->get( 'backup_code', '' );
        $nonce             = (string) $request->get( '_wpnonce_2fa', '' );

        // Verify CSRF nonce
        if ( ! static::verify_nonce( $nonce, 'smliser_auth_2fa' ) ) {
            return static::error_response(
                401,
                'invalid_nonce',
                'Security token expired. Please try again.'
            );
        }

        // Validate that at least one code is provided
        if ( empty( $verification_code ) && empty( $backup_code ) ) {
            return static::error_response(
                400,
                'missing_code',
                'Please provide a verification code.'
            );
        }

        // Verify code via environment provider
        $is_valid = false;

        if ( ! empty( $verification_code ) ) {
            $is_valid = \smliser_envProvider()->verify_totp_code(
                $principal,
                $verification_code
            );
        } elseif ( ! empty( $backup_code ) ) {
            $is_valid = \smliser_envProvider()->verify_backup_code(
                $principal,
                $backup_code
            );
        }

        if ( ! $is_valid ) {
            return static::error_response(
                401,
                'invalid_code',
                'Invalid verification code. Please try again.'
            );
        }

        // Complete authentication - set full principal
        \SmartLicenseServer\Security\Context\Guard::set_principal( $principal );

        // Clear partial auth session
        static::clear_partial_auth_session();

        // Return success
        return static::success_response(
            200,
            [
                'success'  => true,
                'message'  => 'Authentication successful',
                'redirect' => '/dashboard',
            ]
        );
    }

    /*
    |--------------------------------------------------
    | HELPERS - VALIDATION
    |--------------------------------------------------
    */

    /**
     * Validate signup form input.
     *
     * @param string $full_name
     * @param string $email
     * @param string $password
     * @param string $password_confirm
     * @param bool $agree_terms
     *
     * @return array{valid: bool, message?: string}
     */
    private static function validate_signup_input(
        string $full_name,
        string $email,
        string $password,
        string $password_confirm,
        bool $agree_terms
    ) : array {

        // Full name validation
        if ( empty( $full_name ) || strlen( $full_name ) < 3 ) {
            return [
                'valid'   => false,
                'message' => 'Full name must be at least 3 characters.',
            ];
        }

        // Email validation
        if ( empty( $email ) || ! static::is_valid_email( $email ) ) {
            return [
                'valid'   => false,
                'message' => 'Please provide a valid email address.',
            ];
        }

        // Check if email already exists
        $existing = \smliser_envProvider()->find_user_by_email( $email );
        if ( $existing ) {
            return [
                'valid'   => false,
                'message' => 'An account with this email already exists.',
            ];
        }

        // Password validation
        if ( empty( $password ) || strlen( $password ) < 8 ) {
            return [
                'valid'   => false,
                'message' => 'Password must be at least 8 characters.',
            ];
        }

        // Password confirmation
        if ( $password !== $password_confirm ) {
            return [
                'valid'   => false,
                'message' => 'Passwords do not match.',
            ];
        }

        // Terms agreement
        if ( ! $agree_terms ) {
            return [
                'valid'   => false,
                'message' => 'You must agree to the terms of service.',
            ];
        }

        return [ 'valid' => true ];
    }

    /**
     * Validate email format.
     *
     * @param string $email
     * @return bool
     */
    private static function is_valid_email( string $email ) : bool {
        return filter_var( $email, FILTER_VALIDATE_EMAIL ) !== false;
    }

    /*
    |--------------------------------------------------
    | HELPERS - SESSION MANAGEMENT
    |--------------------------------------------------
    */

    /**
     * Get partially authenticated principal (for 2FA flow).
     *
     * @return mixed|null Principal or null
     */
    private static function get_partial_auth_principal() {
        // TODO: Implement partial auth session retrieval
        // This should be stored in cache/session during initial login attempt
        // before 2FA verification
        return null;
    }

    /**
     * Clear partial auth session (after 2FA verification).
     *
     * @return void
     */
    private static function clear_partial_auth_session() : void {
        // TODO: Implement partial auth session cleanup
    }

    /*
    |--------------------------------------------------
    | HELPERS - NONCE VERIFICATION
    |--------------------------------------------------
    */

    /*
    |--------------------------------------------------
    | HELPERS - RESPONSES
    |--------------------------------------------------
    */

    /**
     * Return error response.
     *
     * @param int $status HTTP status code
     * @param string $code Error code
     * @param string $message Error message
     * @return Response
     */
    private static function error_response(
        int $status,
        string $code,
        string $message
    ) : Response {
        return ( new Response( $status ) )
            ->set_body( [
                'success' => false,
                'code'    => $code,
                'message' => $message,
            ] )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
    }

    /**
     * Return success response.
     *
     * @param int $status HTTP status code
     * @param array $data Response data
     * @return Response
     */
    private static function success_response( int $status, array $data ) : Response {
        return ( new Response( $status ) )
            ->set_body( $data )
            ->set_header( 'Content-Type', 'application/json; charset=utf-8' );
    }
}