<?php
/**
 * Payment failed email template.
 *
 * Sent when a payment attempt fails.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Monetization;

use SmartLicenseServer\Email\Templates\EmailTemplate;

defined( 'SMLISER_ABSPATH' ) || exit;

class PaymentFailedEmail extends EmailTemplate {

    /**
     * @param string      $to             Recipient email address.
     * @param string      $recipient_name Name of the recipient.
     * @param string      $amount         Formatted payment amount (e.g. "$49.00").
     * @param string      $currency       Currency code (e.g. "USD").
     * @param string      $payment_date   The date the payment was attempted.
     * @param string      $description    Description of what the payment was for.
     * @param string|null $reason         Optional reason for the failure.
     * @param string|null $retry_url      Optional URL for the user to retry payment.
     */
    public function __construct(
        private readonly string  $to,
        private readonly string  $recipient_name,
        private readonly string  $amount,
        private readonly string  $currency,
        private readonly string  $payment_date,
        private readonly string  $description,
        private readonly ?string $reason    = null,
        private readonly ?string $retry_url = null
    ) {}

    static public function template_key(): string {
        return 'payment_failed';
    }

    protected function subject(): string {
        return 'Payment Failed — Action Required';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Your payment of {$this->amount} {$this->currency} could not be processed. Please review and try again.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{recipient_name}}' => $this->recipient_name,
            '{{amount}}'         => $this->amount,
            '{{currency}}'       => $this->currency,
            '{{payment_date}}'   => $this->payment_date,
            '{{description}}'    => $this->description,
            '{{reason}}'         => $this->reason ?? 'No additional details available.',
            '{{retry_url}}'      => $this->retry_url ?? '',
        ] );
    }

    protected function body(): string {
        $vars           = $this->variables();
        $recipient_name = htmlspecialchars( $vars['{{recipient_name}}'], ENT_QUOTES, 'UTF-8' );
        $amount         = htmlspecialchars( $vars['{{amount}}'],         ENT_QUOTES, 'UTF-8' );
        $currency       = htmlspecialchars( $vars['{{currency}}'],       ENT_QUOTES, 'UTF-8' );
        $payment_date   = htmlspecialchars( $vars['{{payment_date}}'],   ENT_QUOTES, 'UTF-8' );
        $description    = htmlspecialchars( $vars['{{description}}'],    ENT_QUOTES, 'UTF-8' );
        $reason         = htmlspecialchars( $vars['{{reason}}'],         ENT_QUOTES, 'UTF-8' );
        $retry_url      = htmlspecialchars( $vars['{{retry_url}}'],      ENT_QUOTES, 'UTF-8' );
        $support        = htmlspecialchars( $vars['{{support_email}}'],  ENT_QUOTES, 'UTF-8' );

        $retry_button = ! empty( $retry_url )
            ? <<<RETRY
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="margin:0 0 24px;">
                <tr>
                    <td align="center">
                        <a href="{$retry_url}"
                           style="display:inline-block;padding:14px 32px;background-color:#6366f1;
                                  color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;
                                  border-radius:8px;letter-spacing:0.01em;">
                            Retry Payment
                        </a>
                    </td>
                </tr>
            </table>
            RETRY
            : '';

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$recipient_name},
        </p>

        <!-- Failure banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#991b1b;line-height:1.5;">
                        &#10060;&nbsp; Your payment of <strong>{$amount} {$currency}</strong>
                        on <strong>{$payment_date}</strong> could not be processed.
                        Please review your payment details and try again.
                    </p>
                </td>
            </tr>
        </table>

        <!-- Payment details card -->
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
                                    Amount
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$amount} {$currency}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Date Attempted
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$payment_date}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Description
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$description}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Reason
                                </p>
                                <p style="margin:0;font-size:14px;color:#991b1b;line-height:1.6;">
                                    {$reason}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        {$retry_button}

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you continue to experience issues or need assistance,
            please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Payment Failed';
    }
    public function description(): string {
        return 'Sent when a payment attempt fails.';
    }
    public static function preview(): static {
        return new static(
            'preview@example.com',
            'Jane Doe',
            '$49.00',
            'USD',
            gmdate( 'D, d M Y' ),
            'License renewal — My Awesome Plugin (Pro)',
            'Your card was declined. Please check your payment details.',
            'https://example.com/billing/retry'
        );
    }
}