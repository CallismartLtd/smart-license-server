<?php
/**
 * Password changed email template.
 *
 * Sent after a user's password has been successfully changed.
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

class PasswordChangedEmail extends EmailTemplate {

    /**
     * @param User   $user  The user whose password was changed.
     * @param string $to    Recipient email address.
     */
    public function __construct(
        private readonly User   $user,
        private readonly string $to
    ) {}

    static public function template_key(): string {
        return 'password_changed';
    }

    protected function subject(): string {
        return 'Your Password Has Been Changed';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'Your password was changed successfully. If this was not you, contact support immediately.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{display_name}}' => $this->user->get_display_name(),
            '{{changed_at}}'   => gmdate( 'D, d M Y H:i T' ),
        ] );
    }

    protected function body(): string {
        $vars         = $this->variables();
        $display_name = htmlspecialchars( $vars['{{display_name}}'],  ENT_QUOTES, 'UTF-8' );
        $changed_at   = htmlspecialchars( $vars['{{changed_at}}'],    ENT_QUOTES, 'UTF-8' );
        $support      = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$display_name},
        </p>

        <!-- Success banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#166534;line-height:1.5;">
                        &#10003;&nbsp; Your password was changed successfully on
                        <strong>{$changed_at}</strong>.
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            This is a confirmation that your account password has been updated.
            No further action is needed.
        </p>

        <!-- Security notice -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:13px;font-weight:600;color:#991b1b;line-height:1.5;">
                        &#128683;&nbsp; If you did not make this change, your account may be
                        compromised. Please contact us immediately at
                        <a href="mailto:{$support}"
                           style="color:#991b1b;text-decoration:underline;">{$support}</a>
                        so we can secure your account.
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you made this change yourself, you can safely ignore this email.
            For any other questions, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Password Changed';
    }
    public function description(): string {
        return 'Sent after a user\'s password has been successfully changed.';
    }
    public static function preview(): static {
        $user = User::from_array( [ 'display_name' => 'Jane Doe' ] );
        return new static( $user, 'preview@example.com' );
    }
}