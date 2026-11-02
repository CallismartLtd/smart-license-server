<?php
/**
 * Account security settings page.
 */

declare( strict_types = 1 );

namespace SmartLicenseServer\ClientDashboard\ContentHandlers;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use SmartLicenseServer\ClientDashboard\Contracts\ClientPageInterface;
use SmartLicenseServer\Core\Request;
use SmartLicenseServer\Core\URLManager;
use SmartLicenseServer\Environments\Application\Theming\View;
use SmartLicenseServer\Security\Actors\User;
use SmartLicenseServer\Security\Authentication\AuthenticationFactorManager;
use SmartLicenseServer\Security\Context\Guard;

/** Self-service management of a user's additional authentication factors. */
final class SecurityPage implements ClientPageInterface {
	public function __construct(
		private Guard $guard,
		private URLManager $urls,
		private AuthenticationFactorManager $factors
	) {}

	public function get_menu_key() : string {
		return 'security';
	}

	public function get_menu_data() : array {
		return [
			'title'      => 'Security',
			'slug'       => 'security',
			'handler'    => $this,
			'icon'       => 'ti ti-shield-lock',
			'visibility' => fn () : bool => $this->guard->get_principal()?->get_actor() instanceof User,
		];
	}

	public function get_submenu() : array {
		return [];
	}

	public function index_page_handler() : callable {
		return [ $this, 'security' ];
	}

	public function security( Request $request ) : View {
		$user = $this->guard->get_principal()?->get_actor();
		if ( ! $user instanceof User ) {
			return View::make( 'errors.404', [] )->set_status_code( 404 );
		}

		$user_id = (int) $user->get_id();
		$status  = $this->factors->status( $user_id );
		$email_token = (string) $request->get( 'email_challenge', '', false );
		$totp_token  = (string) $request->get( 'totp_challenge', '', false );
		$email_challenge = $this->factors->get_challenge( $email_token, 'enroll_email' );
		$email_disable_token = (string) $request->get( 'email_disable_challenge', '', false );
		$email_disable = $this->factors->get_challenge( $email_disable_token, 'disable_email' );
		$totp_challenge  = $this->factors->get_challenge( $totp_token, 'enroll_totp' );
		$email_challenge = $email_challenge && (int) $email_challenge['user_id'] === $user_id ? $email_challenge : null;
		$email_disable = $email_disable && (int) $email_disable['user_id'] === $user_id ? $email_disable : null;
		$totp_challenge  = $totp_challenge && (int) $totp_challenge['user_id'] === $user_id ? $totp_challenge : null;
		$totp_secret     = $totp_challenge
			? $this->factors->decrypt_totp_secret( (string) ( $totp_challenge['payload']['secret'] ?? '' ) )
			: null;
		$issuer = (string) SMLISER_APP_NAME;
		$label  = rawurlencode( $issuer . ':' . $user->get_email() );
		$uri    = null !== $totp_secret
			? sprintf( 'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30', $label, rawurlencode( $totp_secret ), rawurlencode( $issuer ) )
			: '';
		$qr_code = '';
		if ( '' !== $uri ) {
			$qr_code = ( new SvgWriter() )->write( new QrCode( $uri, errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 240, margin: 12 ) )->getDataUri();
		}
		$active_tab = (string) $request->get( 'security_tab', '' );
		if ( '' !== $totp_token ) {
			$active_tab = 'authenticator';
		}
		if ( ! in_array( $active_tab, [ 'email', 'authenticator', 'passkeys' ], true ) ) {
			$active_tab = 'email';
		}

		return View::make( 'client.security', [
			'email'               => $user->get_email(),
			'email_verified'      => $user->is_email_verified(),
			'email_enabled'       => $status['email'],
			'totp_enabled'        => $status['totp'],
			'passkeys'            => $status['passkeys'],
			'recovery_code_count' => $status['recovery_codes'],
			'email_challenge'     => $email_challenge ? $email_token : '',
			'email_disable_challenge' => $email_disable ? $email_disable_token : '',
			'totp_challenge'      => $totp_challenge ? $totp_token : '',
			'totp_secret'         => $totp_secret ?? '',
			'totp_uri'            => $uri,
			'totp_qr_code'        => $qr_code,
			'active_tab'          => $active_tab,
			'actions'             => [
				'email_start'     => $this->urls->client_dashboard_url( 'json/security/email/start' )->url(),
				'email_verify'    => $this->urls->client_dashboard_url( 'json/security/email/verify' )->url(),
				'totp_start'      => $this->urls->client_dashboard_url( 'json/security/totp/start' )->url(),
				'totp_verify'     => $this->urls->client_dashboard_url( 'json/security/totp/verify' )->url(),
				'factor_disable'  => $this->urls->client_dashboard_url( 'json/security/disable' )->url(),
				'passkey_start'   => $this->urls->client_dashboard_url( 'json/security/passkey/start' )->url(),
				'passkey_finish'  => $this->urls->client_dashboard_url( 'json/security/passkey/finish' )->url(),
				'passkey_revoke'  => $this->urls->client_dashboard_url( 'json/security/passkey/revoke' )->url(),
			],
		] )
			->set_title( 'Account security' )
			->set_header( 'Cache-Control', 'no-store' )
			->set_header( 'Referrer-Policy', 'no-referrer' )
			->add_script( View::FORMS_SCRIPT )
			->add_script( 'passkeys' )
			->add_script( 'security-tabs' );
	}
}
