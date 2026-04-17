<?php
/**
 * Subscription cancelled email template.
 *
 * Sent when a subscription is cancelled.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Monetization;

use SmartLicenseServer\Email\Templates\EmailTemplate;

defined( 'SMLISER_ABSPATH' ) || exit;

class SubscriptionCancelledEmail extends EmailTemplate {

    /**
     * @param string      $to               Recipient email address.
     * @param string      $recipient_name   Name of the recipient.
     * @param string      $plan_name        Name of the cancelled subscription plan.
     * @param string      $cancelled_on     Date the subscription was cancelled.
     * @param string      $access_until     Date until access remains active.
     * @param bool        $self_cancelled   True if the user cancelled themselves,
     *                                      false if cancelled by an administrator.
     * @param string|null $reason           Optional reason for cancellation.
     */
    public function __construct(
        private readonly string  $to,
        private readonly string  $recipient_name,
        private readonly string  $plan_name,
        private readonly string  $cancelled_on,
        private readonly string  $access_until,
        private readonly bool    $self_cancelled = true,
        private readonly ?string $reason         = null
    ) {}

    static public function template_key(): string {
        return 'subscription_cancelled';
    }

    protected function subject(): string {
        return 'Your Subscription Has Been Cancelled';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Your {$this->plan_name} subscription has been cancelled. Your access continues until {$this->access_until}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{recipient_name}}' => $this->recipient_name,
            '{{plan_name}}'      => $this->plan_name,
            '{{cancelled_on}}'   => $this->cancelled_on,
            '{{access_until}}'   => $this->access_until,
            '{{reason}}'         => $this->reason ?? 'No reason provided.',
        ] );
    }

    protected function body(): string {
        $vars           = $this->variables();
        $recipient_name = htmlspecialchars( $vars['{{recipient_name}}'], ENT_QUOTES, 'UTF-8' );
        $plan_name      = htmlspecialchars( $vars['{{plan_name}}'],      ENT_QUOTES, 'UTF-8' );
        $cancelled_on   = htmlspecialchars( $vars['{{cancelled_on}}'],   ENT_QUOTES, 'UTF-8' );
        $access_until   = htmlspecialchars( $vars['{{access_until}}'],   ENT_QUOTES, 'UTF-8' );
        $reason         = htmlspecialchars( $vars['{{reason}}'],         ENT_QUOTES, 'UTF-8' );
        $support        = htmlspecialchars( $vars['{{support_email}}'],  ENT_QUOTES, 'UTF-8' );

        $cancelled_by = $this->self_cancelled
            ? 'You have cancelled your subscription.'
            : 'Your subscription has been cancelled by an administrator.';

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$recipient_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            {$cancelled_by}
        </p>

        <!-- Access notice -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#92400e;line-height:1.5;">
                        &#9888;&nbsp; Your access to <strong>{$plan_name}</strong> features
                        will remain active until <strong>{$access_until}</strong>,
                        after which your account will be downgraded.
                    </p>
                </td>
            </tr>
        </table>

        <!-- Cancellation details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="50%" style="padding:0 16px 16px 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Plan
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$plan_name}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Cancelled On
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$cancelled_on}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td width="50%" style="padding:0 16px 0 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Access Until
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$access_until}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Reason
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$reason}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not request this cancellation or would like to reactivate
            your subscription, please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Subscription Cancelled';
    }
    public function description(): string {
        return 'Sent when a subscription is cancelled by the user or an administrator.';
    }
    public static function preview(): static {
        return new static(
            'preview@example.com',
            'Jane Doe',
            'My Awesome Plugin — Pro Plan',
            gmdate( 'D, d M Y' ),
            gmdate( 'D, d M Y', strtotime( '+30 days' ) ),
            true,
            'Switching to a different plan.'
        );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'Hi {{recipient_name}},',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'intro',
                'type'      => 'text',
                'content'   => $this->self_cancelled
                    ? 'You have cancelled your subscription.'
                    : 'Your subscription has been cancelled by an administrator.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'banner',
                'type'      => 'banner',
                'tone'      => 'warning',
                'content'   => 'Your access to {{plan_name}} features will remain active until {{access_until}}, after which your account will be downgraded.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Plan',          'value' => '{{plan_name}}' ],
                    [ 'label' => 'Cancelled On',  'value' => '{{cancelled_on}}' ],
                    [ 'label' => 'Access Until',  'value' => '{{access_until}}' ],
                    [ 'label' => 'Reason',        'value' => '{{reason}}' ],
                ],
                'editable'  => true,
                'removable' => true,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you did not request this cancellation or would like to reactivate your subscription, please contact us at {{support_email}}.',
                'editable'  => true,
                'removable' => false,
            ],
        ];
    }
}