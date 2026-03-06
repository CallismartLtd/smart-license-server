<?php
/**
 * Welcome email template.
 *
 * Sent when a new user account is created.
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

class WelcomeEmail extends EmailTemplate {

    /**
     * @param User   $user  The newly created user.
     * @param string $to    Recipient email address.
     */
    public function __construct(
        private readonly User   $user,
        private readonly string $to
    ) {}

    static public function template_key(): string {
        return 'welcome';
    }

    protected function subject(): string {
        return 'Welcome — Your Account is Ready';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'Your account has been created successfully. Get started today.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{display_name}}' => $this->user->get_display_name(),
            '{{email}}'        => $this->user->get_email(),
        ] );
    }

    protected function body(): string {
        $vars         = $this->variables();
        $display_name = htmlspecialchars( $vars['{{display_name}}'],  ENT_QUOTES, 'UTF-8' );
        $email        = htmlspecialchars( $vars['{{email}}'],         ENT_QUOTES, 'UTF-8' );
        $app_name     = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $support      = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Welcome, {$display_name}!
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            Your account on <strong>{$app_name}</strong> has been created successfully.
            You can now log in and start managing your licenses.
        </p>

        <!-- Account details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Full Name
                                </p>
                                <p style="margin:0;font-size:15px;color:#1a1a2e;font-weight:600;">
                                    {$display_name}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Email Address
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$email}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not create this account or have any concerns, please contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Welcome';
    }
    public function description(): string {
        return 'Sent when a new user account is created.';
    }
    public static function preview(): static {
        $user = User::from_array([
            'display_name' => 'Jane Doe',
            'email'        => 'preview@example.com',
        ]);
        return new static( $user, 'preview@example.com' );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'Welcome, {{display_name}}!',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'intro',
                'type'      => 'text',
                'content'   => 'Your account on {{app_name}} has been created successfully. You can now log in and start managing your licenses.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Full Name',      'value' => '{{display_name}}' ],
                    [ 'label' => 'Email Address',  'value' => '{{email}}' ],
                ],
                'editable'  => true,
                'removable' => true,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you did not create this account or have any concerns, please contact us immediately at {{support_email}}.',
                'editable'  => true,
                'removable' => false,
            ],
        ];
    }
}