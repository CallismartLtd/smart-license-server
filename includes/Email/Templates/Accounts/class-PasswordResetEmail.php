<?php
/**
 * Password reset email template.
 *
 * Sent when a user requests a password reset.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

defined( 'SMLISER_ABSPATH' ) || exit;

class PasswordResetEmail extends EmailTemplate {

    /**
     * @param User   $user       The user requesting the reset.
     * @param string $to         Recipient email address.
     * @param string $reset_url  The password reset link.
     * @param int    $expires_in Number of minutes until the reset link expires.
     */
    public function __construct(
        private readonly User   $user,
        private readonly string $to,
        private readonly string $reset_url,
        private readonly int    $expires_in = 30
    ) {}

    static public function template_key(): string {
        return 'password_reset';
    }

    protected function subject(): string {
        return 'Reset Your Password';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'We received a request to reset your password. Use the link inside to proceed.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{display_name}}' => $this->user->get_display_name(),
            '{{reset_url}}'    => $this->reset_url,
            '{{expires_in}}'   => (string) $this->expires_in,
        ] );
    }

    protected function body(): string {
        $vars         = $this->variables();
        $display_name = htmlspecialchars( $vars['{{display_name}}'],  ENT_QUOTES, 'UTF-8' );
        $reset_url    = htmlspecialchars( $vars['{{reset_url}}'],     ENT_QUOTES, 'UTF-8' );
        $expires_in   = htmlspecialchars( $vars['{{expires_in}}'],    ENT_QUOTES, 'UTF-8' );
        $support      = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$display_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            We received a request to reset the password for your account.
            Click the button below to choose a new password.
        </p>

        <!-- Reset button -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:0 0 24px;">
            <tr>
                <td align="center">
                    <a href="{$reset_url}"
                       style="display:inline-block;padding:14px 32px;background-color:#6366f1;
                              color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;
                              border-radius:8px;letter-spacing:0.01em;">
                        Reset My Password
                    </a>
                </td>
            </tr>
        </table>

        <!-- Expiry notice -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:14px 20px;">
                    <p style="margin:0;font-size:13px;color:#92400e;line-height:1.5;">
                        &#9888;&nbsp; This link will expire in <strong>{$expires_in} minutes</strong>.
                        If it expires, you can request a new one from the login page.
                    </p>
                </td>
            </tr>
        </table>

        <!-- Fallback URL -->
        <p style="margin:0 0 8px;font-size:13px;color:#64748b;line-height:1.6;">
            If the button above does not work, copy and paste the link below into your browser:
        </p>
        <p style="margin:0 0 24px;font-size:12px;color:#6366f1;word-break:break-all;">
            <a href="{$reset_url}" style="color:#6366f1;text-decoration:none;">{$reset_url}</a>
        </p>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not request a password reset, you can safely ignore this email.
            Your password will not change. If you have concerns, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Password Reset';
    }
    
    public function description(): string {
        return 'Sent when a user requests a password reset.';
    }

    public static function preview(): static {
        $user = User::from_array( [ 'display_name' => 'Jane Doe' ] );
        return new static( $user, 'preview@example.com', 'https://example.com/reset?token=xxxx', 30 );
    }
}