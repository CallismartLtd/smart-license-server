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
use SmartLicenseServer\Background\Queue\JobQueue;
use SmartLicenseServer\Background\Queue\QueueAwareTrait;
use SmartLicenseServer\Cache\Cache;
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
    use QueueAwareTrait, TokenDeliveryTrait;

    /**
     * Class constructor.
     */
    public function __construct(
        protected Guard $guard,
        protected PasswordIdentityProviderInterface $id_provider,
        protected URLManager $urlmanager,
        protected Settings $settings,
        protected Cache $cache,
        JobQueue $job_queue
    ) {
        $this->job_queue = $job_queue;
    }

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

        if ( ! $redirect_url->is_valid() || $redirect_url->get_origin() !== $this->urlmanager->url()->get_origin() ) {
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
     * @param Request $request
     * @return Response JSON response or HTML page
     */
    public function handle_logout( Request $request ): Response {
        $principal  = $this->guard->get_principal();

        if ( ! $principal ) {
            $data   = ['success' => false, 'message' => 'Already logged out'];
        } else{
            $this->id_provider->logout();

            $actor_name = $principal->get_display_name();
            $data   = [ 'success' => true, 'message' => sprintf( 'Good bye %s', $actor_name ) ];            
        }

        if ( $request->wantsJson() ) {
            return Response::json( $data, 200 );
        }

        return Response::make(
            $this->logout_document( $data['message'], $this->urlmanager->url()->url(), $data['success'] ),
            200
        )
        ->set_header( 'Content-Type', 'text/html; charset=utf-8' );
    }

    /**
     * Render a complete HTML document for the logout confirmation page.
     *
     * @param string $message User-facing message ("Good bye X" or "Already logged out").
     * @param string $home_url URL to return the user to.
     * @param bool $session_ended True if an active session was just ended,
     *                             false if there was no session to end.
     * @return string Complete HTML document.
     */
    private function logout_document( string $message, string $home_url, bool $session_ended ): string {
        $safe_message  = htmlspecialchars( $message, ENT_QUOTES, 'UTF-8' );
        $safe_home_url = escUrl( $home_url );

        $accent         = $session_ended ? '#15803d' : '#475569';
        $accent_bg      = $session_ended ? '#ecfdf3' : '#f1f5f9';
        $accent_dark    = $session_ended ? '#4ade80' : '#94a3b8';
        $accent_bg_dark = $session_ended ? '#0d2818' : '#1e2530';

        if ( $session_ended ) {
            // Connection just closed by this request — mark it explicitly.
            $status_copy = 'Your session has been closed. You will need to sign in again to continue.';
            $diagram = <<<SVG
                <svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="20" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
                    <circle cx="34" cy="70" r="3" fill="var(--ink)" />
                    <line x1="46" y1="63" x2="66" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
                    <line x1="46" y1="77" x2="66" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

                    <rect x="184" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
                    <circle cx="198" cy="70" r="3" fill="var(--ink)" />
                    <line x1="210" y1="63" x2="230" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
                    <line x1="210" y1="77" x2="230" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

                    <path d="M76 70 H110" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" />
                    <path d="M150 70 H184" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" />

                    <circle cx="130" cy="70" r="14" fill="var(--accent-bg)" stroke="var(--accent)" stroke-width="1.5" />
                    <path d="M124 70 L129 75 L138 64" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none" />
                </svg>
                SVG;
        } else {
            // No session existed — a closed connection, but no action was taken.
            $status_copy = 'There was no active session on this device.';
            $diagram = <<<SVG
                <svg viewBox="0 0 260 140" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <rect x="20" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" />
                    <circle cx="34" cy="70" r="3" fill="var(--ink)" />
                    <line x1="46" y1="63" x2="66" y2="63" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
                    <line x1="46" y1="77" x2="66" y2="77" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />

                    <rect x="184" y="54" width="56" height="32" rx="8" stroke="var(--ink)" stroke-width="1.5" opacity="0.5" />
                    <circle cx="198" cy="70" r="3" fill="var(--ink)" opacity="0.5" />

                    <path d="M76 70 H110" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" />
                    <path d="M150 70 H184" stroke="var(--accent)" stroke-width="2" stroke-linecap="round" opacity="0.5" />
                    <circle cx="110" cy="70" r="3" fill="var(--accent)" />
                    <circle cx="150" cy="70" r="3" fill="var(--accent)" opacity="0.5" />
                </svg>
                SVG;
        }

        return <<<HTML
            <!DOCTYPE html>
            <html lang="en">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <meta name="color-scheme" content="light dark">
                <title>{$safe_message}</title>

                <style>
                    :root {
                        color-scheme: light dark;

                        --bg: #f5f6f8;
                        --dot: #dde1e6;
                        --ink: #12151a;
                        --muted: #5b6270;
                        --border: #d8dce2;

                        --accent: {$accent};
                        --accent-bg: {$accent_bg};
                    }

                    @media (prefers-color-scheme: dark) {
                        :root {
                            --bg: #0b0e14;
                            --dot: #1c212b;
                            --ink: #eef1f5;
                            --muted: #8b93a1;
                            --border: #232935;

                            --accent: {$accent_dark};
                            --accent-bg: {$accent_bg_dark};
                        }
                    }

                    * {
                        box-sizing: border-box;
                    }

                    html, body {
                        height: 100%;
                        margin: 0;
                    }

                    body {
                        display: flex;
                        min-height: 100dvh;
                        background:
                            radial-gradient(var(--dot) 1px, transparent 1px) 0 0 / 24px 24px,
                            var(--bg);
                        color: var(--ink);
                        font-family: ui-sans-serif, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                        font-size: 15px;
                        line-height: 1.6;
                        -webkit-font-smoothing: antialiased;
                        text-rendering: optimizeLegibility;
                    }

                    main {
                        flex: 1;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 48px 24px;
                    }

                    .session-shell {
                        display: grid;
                        grid-template-columns: 260px 1fr;
                        align-items: center;
                        gap: 48px;
                        max-width: 760px;
                        width: 100%;
                    }

                    .session-visual svg {
                        width: 100%;
                        height: auto;
                    }

                    .session-copy {
                        text-align: left;
                    }

                    .session-heading {
                        margin: 0 0 14px;
                        font-size: 2rem;
                        font-weight: 600;
                        letter-spacing: -0.02em;
                        line-height: 1.25;
                    }

                    .session-message {
                        margin: 0 0 28px;
                        max-width: 46ch;
                        color: var(--muted);
                    }

                    .btn {
                        display: inline-block;
                        padding: 9px 18px;
                        border: 1px solid var(--border);
                        border-radius: 8px;
                        background: transparent;
                        color: var(--ink);
                        text-decoration: none;
                        font-weight: 600;
                        font-size: 13.5px;
                    }

                    .btn:hover {
                        border-color: var(--accent);
                        color: var(--accent);
                    }

                    @media (max-width: 640px) {
                        .session-shell {
                            grid-template-columns: 1fr;
                            gap: 28px;
                            text-align: center;
                        }

                        .session-copy {
                            text-align: center;
                        }

                        .session-message {
                            max-width: none;
                        }

                        .session-visual svg {
                            max-width: 220px;
                            margin: 0 auto;
                        }
                    }
                </style>
            </head>

            <body>
                <main>
                    <div class="session-shell">
                        <div class="session-visual">
                            {$diagram}
                        </div>

                        <div class="session-copy">
                            <h2 class="session-heading">{$safe_message}</h2>
                            <p class="session-message">
                                {$status_copy}
                            </p>
                            <a class="btn" href="{$safe_home_url}">Return to Home</a>
                        </div>
                    </div>
                </main>
            </body>
            </html>
            HTML;
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
                'recipient'     => $this->settings->get( Settings::ADMIN_EMAIL ),
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

        $this->cache->delete( $cache_key );
        
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
     * @param User|null $user
     * @param Request $request
     */
    private function password_recovery( ?User $user, Request $request ) : void {

        $raw_key = static::generate_secure_token();

        $payload = [
            'id'        => $user?->get_id() ?? null,
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
            $user?->get_id() ?? null
        );

        $duration   = DateDuration::fromMinutes(15);

        $this->cache->set(
            $cache_key,
            hash( 'sha256', $token ),
            (int) $duration->toSeconds()
        );

        $reset_link = $this->urlmanager->client_dashboard_url( '', array( 'key' => $token ) )
            ->set_hash( 'reset-password' );

        // Enqueue to run in the background.
        $this->dispatch_job(
            PasswordResetJob::class,
            array(
                'user_id'       => $user?->get_id() ?? null,
                'recipient'     => $user?->get_email() ?? null,
                'reset_url'     => $reset_link,
                'expires_in'    => (int) $duration->toMinutes(),
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
        $stored_hash    = $this->cache->get( $cache_key );
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