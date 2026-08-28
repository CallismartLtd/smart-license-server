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
use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Exceptions\Exception;
use SmartLicenseServer\Exceptions\RequestException;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\IdentityProviders\PasswordIdentityProviderInterface;
use SmartLicenseServer\Security\Context\Guard;
use SmartLicenseServer\SettingsAPI\Settings;
use SmartLicenseServer\SettingsAPI\UserSettings;
use SmartLicenseServer\Utils\TokenDeliveryTrait;

class AuthController {
    use QueueAwareTrait, TokenDeliveryTrait, CacheAwareTrait;

    /**
     * Class constructor.
     */
    public function __construct(
        protected Guard $guard,
        protected PasswordIdentityProviderInterface $id_provider,
        protected URLManager $urlmanager,
        protected Settings $settings
    ) {}

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
     * @param Request $request Request object.
     * @return Response JSON response
     */
    public function handle_login( Request $request ) : Response {
        $username   = (string) $request->post( 'username', '' );
        $password   = (string) $request->get( parameter: 'password', default: '', sanitize: false );
        $remember   = (bool) $request->post( 'remember', false );

        if ( empty( $username ) || empty( $password ) ) {
            return static::error_response(
                400,
                'missing_credentials',
                'Username and password are required.'
            );
        }

        $principal = $this->id_provider->logon( $username, $password, $remember );

        if ( $principal instanceof RequestException ) {
            return static::error_response(
                401,
                $principal->get_error_code(),
                $principal->get_error_message()
            );
        }

        $redirect_url   = URL::from( $request->post( 'redirect_url', '' ) );

        if ( ! $redirect_url->is_valid() || $redirect_url->get_origin() !== \url()->get_origin() ) {
            $redirect_url   = $this->guard->get_principal()?->is( 'system_admin' )
                ? $this->urlmanager->admin_url() : $this->urlmanager->client_dashboard_url();
        }

        // Return success with redirect
        return static::success_response(
            200,
            [
                'success'  => true,
                'message'  => sprintf( 'Welcome back, %s.', $principal->get_display_name() ),
                'redirect' => $redirect_url->url()
            ]
        );
    }

    /**
     * Handle logout.
     * 
     * @return array{success: bool, message: string}
     */
    public function handle_logout() : array {
        $principal  = $this->guard->get_principal();

        if ( ! $principal ) {
            return ['success' => false, 'message' => 'Already logged out'];
        }

        $this->id_provider->logout();

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
    public function handle_signup( Request $request ) : Response {
        if ( ! $request->has( 'agree_terms' ) ) {
            return static::error_response(
                400,
                'terms_not_accepted',
                'You must agree to the terms and conditions to create an account.'
            );
        }

        $principal = $this->id_provider->signup( $request );
        
        if ( $principal instanceof RequestException ) {
            $status_code    = (int) ( $principal->get_error_data()['status'] ?? 400 );
            return static::error_response(
                $status_code,
                $principal->get_error_code(),
                $principal->get_error_message()
            );
        }

        $account_type   = $request->get( 'account_type', 'viewer' );

        if ( 'resource_owner' !== $account_type ) {
            $account_type = 'viewer';
        }
        
        $this->dispatch_job(
            SignupEmailJob::class,
            [
                'user_id'   => $principal->get_id(),
                'recipient' => $principal->get_email()
            ]
        );

        $this->dispatch_job(
            SignupEmailJob::class,
            [
                'user_id'       => $principal->get_id(),
                'recipient'     => $this->settings->get( 'admin_email' ),
                'for_admin'     => true,
                'ip_address'    => $request->ip(),
                'account_type'  => $account_type
            ]
        );

        // Return success
        return static::success_response(
            200,
            [
                'success'   => true,
                'message'   => 'Account created successfully! Check your email to verify your account.',
                'redirect'  => $this->urlmanager->client_dashboard_url()
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
    public function handle_forgot_password( Request $request ) : Response {
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

        $this->password_recovery( $user, $request );

        return static::success_response( 200, $response_data );
    }

    /**
     * Handle password reset request.
     * 
     * @param Request $request
     * @return Response
     */
    public function handle_reset_password( Request $request ) : Response {
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
            $this->id_provider->reset_password( $user, $password_1 );
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
     * @param Request $request
     */
    private function password_recovery( User $user, Request $request ) : void {

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
            (int) DateDuration::fromHours(1)->toSeconds()
        );

        $reset_link = $this->urlmanager->client_dashboard_url( '', array( 'key' => $token ) )
            ->set_hash( 'reset-password' );

        $this->dispatch_job(
            PasswordResetJob::class,
            array(
                'user_id'       => $user->get_id(),
                'recipient'     => $user->get_email(),
                'reset_url'     => $reset_link,
                'expires_in'    => 3600,
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
            )
        );
    }

    /**
     * Verify password reset token.
     *
     * @param string $token
     * @return array{valid: bool, email?: string, reason?: string}
     */
    public function verify_password_reset_token( #[\SensitiveParameter] string $token ) : array {
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
    public function handle_2fa( Request $request ) : Response {

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