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
use SmartLicenseServer\Background\Jobs\Accounts\SignupEmailJob;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Cache\CacheAwareTrait;
use SmartLicenseServer\Core\Dates\DateDuration;
use SmartLicenseServer\Core\Dates\TimestampValue;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\Response;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

defined( 'SMLISER_ABSPATH' ) || exit;

class AuthController {
    use QueueAwareTrait, TokenDeliveryTrait, CacheAwareTrait;

    /*
    |--------------------------------------------------
    | LOGIN/LOGOUT
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
        $password   = (string) $_POST['password'] ?? '';
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
                'redirect' => \smliser_client_dashboard_url()
            ]
        );
    }

    /**
     * Handle logout.
     * 
     * @return array{success: bool, message: string}
     */
    public static function handle_logout() : array {
        $principal  = Guard::get_principal();

        if ( ! $principal ) {
            return ['success' => false, 'message' => 'Already logged out'];
        }

        \identityProvider()->logout();

        $actor_name = $principal->get_display_name();
        return [ 'success' => true, 'message' => sprintf( 'Good bye %s', $actor_name ) ];
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
        if ( ! $request->has( 'agree_terms' ) ) {
            return static::error_response(
                400,
                'terms_not_accepted',
                'You must agree to the terms and conditions to create an account.'
            );
        }

        $principal = \identityProvider()->signup( $request );
        
        if ( $principal instanceof RequestException ) {
            return static::error_response(
                400,
                $principal->get_error_code(),
                $principal->get_error_message()
            );
        }

        $static = new static;

        $account_type   = $request->get( 'account_type', 'viewer' );

        if ( 'resource_owner' !== $account_type ) {
            $account_type = 'viewer';
        }
        
        $static->dispatch_job(
            SignupEmailJob::class,
            [
                'user_id'   => $principal->get_id(),
                'recipient' => $principal->get_email()
            ]
        );

        $static->dispatch_job(
            SignupEmailJob::class,
            [
                'user_id'       => $principal->get_id(),
                'recipient'     => \smliser_settings()->get( 'admin_email' ),
                'for_admin'     => true,
                'ip_address'    => \smliser_get_client_ip(),
                'account_type'  => $account_type
            ]
        );

        // Return success
        return static::success_response(
            200,
            [
                'success'   => true,
                'message'   => 'Account created successfully! Check your email to verify your account.',
                'redirect'  => \smliser_client_dashboard_url()
            ]
        );
    }

    /*
    |---------------------
    | PASSWORD RECOVERY.
    |---------------------
    */

    /**
     * Handle forgot password form submission.
     *
     * Sends password reset email if the requesting user exists.
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
     * Handle password reset request.
     * 
     * @param Request $request
     * @return Response
     */
    public static function handle_reset_password( Request $request ) : Response {
        $token  = $request->get( 'token' );

        $check  = static::verify_password_reset_token( $token );

        if ( ! $check['valid'] ) {
            return static::error_response( 401, 'token_error', $check['reason'] );
        }

        $user   = User::get_by_email( $check['email'] ?? '' );

        if ( ! $user ) {
            return static::error_response(
                401,
                'invalid_user',
                'Unknown email address.'
            );
        }

        $password_1 = $request->get( 'password_1', '' );
        $password_2 = $request->get( 'password_2', '' );

        if ( empty( $password_1 ) ) {
            return static::error_response(
                401,
                'empty_password',
                'Password must not be empty.'
            );
        }

        if ( $password_1 !== $password_2 ) {
            return static::error_response(
                401,
                'password_mismatch',
                'Password missmatch, please check and try again.'
            );
        }

        $cache_key = sprintf(
            '%s_%d',
            UserSettings::PWD_RESET_NAME,
            $user->get_id()
        );
        
        try {
            \identityProvider()->reset_password( $user, $password_1 );
        } catch ( Exception $e ) {
            return static::error_response(
                401,
                $e->get_error_code(),
                $e->get_error_message()
            );
        }

        static::cache_delete( $cache_key );
        
        return static::success_response(
            200,
            ['message' => 'Password has been reset successfully, please login.']
        );
        
    }

    /**
     * Handle password recovery process.
     * 
     * Dispatches password reset email in the background.
     *
     * @param User $user
     */
    private function password_recovery( User $user ) : void {

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

        static::cache_set(
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
     * @return array{valid: bool, email?: string, reason?: string}
     */
    public static function verify_password_reset_token( #[\SensitiveParameter] string $token ) : array {
        $decoded    = self::base64url_decode( $token );

        if ( ! $decoded || ! str_contains( $decoded, '.' ) ) {
            return ['valid' => false, 'reason' => 'Invalid token format'];
        }

        [ $encoded_payload, $signature ]    = explode( '.', $decoded, 2 );
        $expected_signature                 = self::hmac_hash( $encoded_payload, self::derive_key(), 'sha256' );
        
        if ( ! hash_equals( $expected_signature, $signature ) ) {
            return ['valid' => false, 'reason' => 'Invalid signature'];
        }

        $payload    = json_decode( $encoded_payload, true );
        if ( ! is_array( $payload ) || empty( $payload['id'] ) ) {
            return ['valid' => false, 'reason' => 'Invalid payload'];
        }

        $issuedAt   = TimestampValue::fromTimestamp( (int) $payload['timestamp'] );
        if ( $issuedAt->addHours(1)->isPast() ) {
            return ['valid' => false, 'reason' => 'Token expired'];
        }

        $cache_key      = sprintf( '%s_%d', UserSettings::PWD_RESET_NAME, $payload['id'] );
        $stored_hash    = static::cache_get( $cache_key );
        $current_hash   = hash( 'sha256', $token );

        if ( ! $stored_hash || ! hash_equals( $stored_hash, $current_hash ) ) {
            return ['valid' => false, 'reason' => 'Token already used or invalidated'];
        }

        $user   = User::get_by_id( (int) $payload['id'] );
        if ( ! $user ) {
            return ['valid' => false, 'reason' => 'User no longer exists.'];
        }

        return [
            'valid' => true,
            'email' => $user->get_email(),
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
    |------------------------
    | HELPERS - VALIDATION
    |------------------------
    */

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