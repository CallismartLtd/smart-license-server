<?php
/**
 * Base security email template.
 *
 * Shared layout for every email that tells a user something happened to
 * their sign-in security: a one-time code, a passkey or a second factor
 * being added or removed. Every one of them shows the same slots, so the
 * recipient can tell at a glance whether it was them:
 *
 *   - the action that happened,
 *   - when it happened (UTC),
 *   - the IP address it came from,
 *   - the device it came from,
 *   - a "wasn't you?" call to action that points at account recovery.
 *
 * ## Adding a security email
 *
 * 1. Extend this class in the Security namespace.
 * 2. Implement template_key(), label(), description(), preview(),
 *    action() and summary().
 * 3. Optionally override tone(), subject() or lead_blocks().
 * 4. Register the class in EmailTemplateRegistry::boot().
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Security;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

abstract class SecurityEmailTemplate extends EmailTemplate {

	/**
	 * When the action happened, as a Unix timestamp.
	 *
	 * Queued emails can be sent well after the action, so callers pass the
	 * moment of the action itself. Defaults to the moment of construction.
	 *
	 * @var int
	 */
	protected readonly int $occurred_at;

	/**
	 * @param User     $user        The user whose account the action happened on.
	 * @param string   $to          Recipient email address.
	 * @param string   $ip_address  IP address the action came from.
	 * @param string   $user_agent  User agent the action came from.
	 * @param string   $secure_url  Where the "wasn't you?" button leads. Falls back to a mailto link to support when empty.
	 * @param int|null $occurred_at Unix timestamp of the action.
	 */
	public function __construct(
		protected readonly User   $user,
		protected readonly string $to,
		protected readonly string $ip_address = 'unknown',
		protected readonly string $user_agent = 'unknown',
		protected readonly string $secure_url = '',
		?int $occurred_at = null
	) {
		$this->occurred_at = $occurred_at ?? time();
	}

	/*
	|-----------------------------------------------------------------------
	|Subclass interface
	|-----------------------------------------------------------------------
	*/

	/**
	 * Short name of what happened, e.g. 'Passkey added'.
	 *
	 * Shown in the "Action" slot and used in the default subject and preheader.
	 *
	 * @return string Plain text.
	 */
	abstract protected function action() : string;

	/**
	 * One sentence explaining what happened, shown in the banner.
	 *
	 * @return string Plain text — no HTML, no {{tokens}}.
	 */
	abstract protected function summary() : string;

	/**
	 * Banner tone: 'success' | 'warning' | 'error' | 'info'.
	 *
	 * Defaults to 'warning' because every change to sign-in security is
	 * worth a second look from the account owner.
	 *
	 * @return string
	 */
	protected function tone() : string {
		return 'warning';
	}

	/**
	 * Extra blocks placed between the banner and the detail card.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	protected function lead_blocks() : array {
		return [];
	}

	/*
	|-----------------------------------------------------------------------
	|EmailTemplate implementation
	|-----------------------------------------------------------------------
	*/

	protected function subject() : string {
		return 'Security alert: ' . $this->action();
	}

	protected function recipient() : string {
		return $this->to;
	}

	protected function preheader() : string {
		return $this->action() . ' on your account. If this was not you, secure your account now.';
	}

	/**
	 * Tokens for the security slots.
	 *
	 * Values are HTML-escaped here, not in the block renderers: interpolate()
	 * runs after escaping, so an unescaped value would reach the HTML as
	 * written. The IP address and user agent come from the request.
	 *
	 * @return array<string, string>
	 */
	protected function variables() : array {
		$vars    = parent::variables();
		$support = \escHtml( $vars['{{support_email}}'] );

		return array_merge( $vars, [
			'{{display_name}}' => \escHtml( $this->user->get_display_name() ),
			'{{action}}'       => \escHtml( $this->action() ),
			'{{occurred_at}}'  => \escHtml( $this->format_time() ),
			'{{ip_address}}'   => \escHtml( $this->ip_address ),
			'{{device}}'       => \escHtml( $this->device() ),
			'{{secure_url}}'   => '' !== $this->secure_url
				? \escHtml( $this->secure_url )
				: 'mailto:' . $support,
		] );
	}

	protected function body() : string {
		return $this->render_blocks( $this->get_blocks() );
	}

	/**
	 * Plain text version.
	 *
	 * Built from the raw values rather than from body(): body() relies on the
	 * style tokens that render() sets, which are absent when a custom
	 * template is stored and render() returns early.
	 *
	 * @return string
	 */
	public function text() : string {
		return implode( "\n", [
			'Hi ' . $this->user->get_display_name() . ',',
			'',
			$this->summary(),
			'',
			'Action:     ' . $this->action(),
			'Time:       ' . $this->format_time(),
			'IP address: ' . $this->ip_address,
			'Device:     ' . $this->device(),
			'',
			"Wasn't you? Someone else may have access to your account. Secure it now: " . ( '' !== $this->secure_url ? $this->secure_url : 'contact support' ),
			'',
			'If this was you, no action is needed.',
			'',
		] );
	}

	public function get_blocks() : array {
		return [
			[
				'id'        => 'greeting',
				'type'      => 'greeting',
				'content'   => 'Hi {{display_name}},',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'alert',
				'type'      => 'banner',
				'tone'      => $this->tone(),
				'content'   => $this->summary(),
				'editable'  => true,
				'removable' => false,
			],
			...$this->lead_blocks(),
			[
				'id'        => 'details',
				'type'      => 'detail_card',
				'rows'      => [
					[ 'label' => 'Action',     'value' => '{{action}}' ],
					[ 'label' => 'Time',       'value' => '{{occurred_at}}' ],
					[ 'label' => 'IP address', 'value' => '{{ip_address}}' ],
					[ 'label' => 'Device',     'value' => '{{device}}' ],
				],
				'editable'  => false,
				'removable' => false,
			],
			[
				'id'        => 'wasnt_you',
				'type'      => 'text',
				'content'   => "Wasn't you? Someone else may have access to your account. Secure it now by resetting your password, then review your sign-in methods.",
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'secure_account',
				'type'      => 'button',
				'label'     => "This wasn't me — secure my account",
				'url'       => '{{secure_url}}',
				'editable'  => true,
				'removable' => false,
			],
			[
				'id'        => 'closing',
				'type'      => 'closing',
				'content'   => 'If this was you, no action is needed. Questions? Contact us at {{support_email}}.',
				'editable'  => true,
				'removable' => false,
			],
		];
	}

	/*
	|-----------------------------------------------------------------------
	|Helpers
	|-----------------------------------------------------------------------
	*/

	/**
	 * Human-readable device for the user agent, e.g. "Chrome on Windows".
	 *
	 * @return string
	 */
	protected function device() : string {
		$agent = trim( $this->user_agent );

		if ( '' === $agent || 'unknown' === $agent ) {
			return 'Unknown device';
		}

		return \smliser_parse_user_agent( $agent );
	}

	/**
	 * The action time in the format the other account emails use.
	 *
	 * @return string
	 */
	protected function format_time() : string {
		return gmdate( 'D, d M Y H:i T', $this->occurred_at );
	}

	/**
	 * Placeholder user for preview() implementations.
	 *
	 * @return User
	 */
	protected static function preview_user() : User {
		return User::from_array( [ 'display_name' => 'Jane Doe' ] );
	}

	/**
	 * Placeholder request details for preview() implementations.
	 *
	 * @return array{0: string, 1: string, 2: string} IP address, user agent, secure URL.
	 */
	protected static function preview_context() : array {
		return [
			'203.0.113.42',
			'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36',
			'https://example.com/login/forgot-password/',
		];
	}
}
