<?php
declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Actors\User;

defined( 'SMLISER_ABSPATH' ) || exit;

class AdminNewUserNotificationEmail extends EmailTemplate {

    public function __construct(
        private readonly User   $user,
        private readonly string $to,
        private readonly string $ip_address,
        private readonly string $account_type,
        private readonly string $signup_time
    ) {}

    public static function template_key(): string {
        return 'admin_new_user_notification';
    }

    protected function subject(): string {
        return 'New User Signup Notification';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'A new user has registered on your platform.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{display_name}}' => $this->user->get_display_name(),
            '{{email}}'        => $this->user->get_email(),
            '{{ip_address}}'   => $this->ip_address,
            '{{account_type}}' => $this->account_type,
            '{{signup_time}}'  => $this->signup_time,
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $display_name = htmlspecialchars( $vars['{{display_name}}'], ENT_QUOTES, 'UTF-8' );
        $email        = htmlspecialchars( $vars['{{email}}'],        ENT_QUOTES, 'UTF-8' );
        $ip           = htmlspecialchars( $vars['{{ip_address}}'],   ENT_QUOTES, 'UTF-8' );
        $type         = htmlspecialchars( $vars['{{account_type}}'], ENT_QUOTES, 'UTF-8' );
        $time         = htmlspecialchars( $vars['{{signup_time}}'],  ENT_QUOTES, 'UTF-8' );
        $app_name     = htmlspecialchars( $vars['{{app_name}}'],     ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            New User Registration
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            A new user has registered on <strong>{$app_name}</strong>.
        </p>

        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">

                        <tr>
                            <td style="padding:0 0 16px;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;">
                                    Full Name
                                </p>
                                <p style="margin:0;font-size:15px;font-weight:600;">
                                    {$display_name}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 0 16px;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;">
                                    Email Address
                                </p>
                                <p style="margin:0;font-size:14px;font-weight:600;">
                                    {$email}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 0 16px;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;">
                                    Account Type
                                </p>
                                <p style="margin:0;font-size:14px;">
                                    {$type}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td style="padding:0 0 16px;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;">
                                    Signup Time
                                </p>
                                <p style="margin:0;font-size:14px;">
                                    {$time}
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;color:#94a3b8;">
                                    IP Address
                                </p>
                                <p style="margin:0;font-size:14px;">
                                    {$ip}
                                </p>
                            </td>
                        </tr>

                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0;font-size:14px;color:#64748b;">
            This is an automated notification for administrative awareness.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Admin: New User Signup';
    }

    public function description(): string {
        return 'Sent to administrators when a new user account is created.';
    }

    public static function preview(): static {
        $user = User::from_array([
            'display_name' => 'Jane Doe',
            'email'        => 'jane@example.com',
        ]);

        return new static(
            $user,
            'admin@example.com',
            '127.0.0.1',
            'resource_owner',
            date('Y-m-d H:i:s')
        );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'heading',
                'type'      => 'text',
                'content'   => 'New User Registration',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'intro',
                'type'      => 'text',
                'content'   => 'A new user has registered on {{app_name}}.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Full Name',     'value' => '{{display_name}}' ],
                    [ 'label' => 'Email Address', 'value' => '{{email}}' ],
                    [ 'label' => 'Account Type',  'value' => '{{account_type}}' ],
                    [ 'label' => 'Signup Time',   'value' => '{{signup_time}}' ],
                    [ 'label' => 'IP Address',    'value' => '{{ip_address}}' ],
                ],
                'editable'  => true,
                'removable' => true,
            ],
        ];
    }
}