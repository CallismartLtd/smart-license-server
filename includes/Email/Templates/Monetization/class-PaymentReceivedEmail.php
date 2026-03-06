<?php
/**
 * Payment received email template.
 *
 * Sent when a payment is confirmed.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Monetization;

use SmartLicenseServer\Email\Templates\EmailTemplate;

defined( 'SMLISER_ABSPATH' ) || exit;

class PaymentReceivedEmail extends EmailTemplate {

    /**
     * @param string $to               Recipient email address.
     * @param string $recipient_name   Name of the recipient.
     * @param string $amount           Formatted payment amount (e.g. "$49.00").
     * @param string $currency         Currency code (e.g. "USD").
     * @param string $transaction_id   The payment transaction ID.
     * @param string $payment_date     The date the payment was made.
     * @param string $description      Description of what was paid for.
     */
    public function __construct(
        private readonly string $to,
        private readonly string $recipient_name,
        private readonly string $amount,
        private readonly string $currency,
        private readonly string $transaction_id,
        private readonly string $payment_date,
        private readonly string $description
    ) {}

    static public function template_key(): string {
        return 'payment_received';
    }

    protected function subject(): string {
        return 'Payment Received — Thank You';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "We have received your payment of {$this->amount} {$this->currency}. Your receipt is inside.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{recipient_name}}' => $this->recipient_name,
            '{{amount}}'         => $this->amount,
            '{{currency}}'       => $this->currency,
            '{{transaction_id}}' => $this->transaction_id,
            '{{payment_date}}'   => $this->payment_date,
            '{{description}}'    => $this->description,
        ] );
    }

    protected function body(): string {
        $vars           = $this->variables();
        $recipient_name = htmlspecialchars( $vars['{{recipient_name}}'], ENT_QUOTES, 'UTF-8' );
        $amount         = htmlspecialchars( $vars['{{amount}}'],         ENT_QUOTES, 'UTF-8' );
        $currency       = htmlspecialchars( $vars['{{currency}}'],       ENT_QUOTES, 'UTF-8' );
        $transaction_id = htmlspecialchars( $vars['{{transaction_id}}'], ENT_QUOTES, 'UTF-8' );
        $payment_date   = htmlspecialchars( $vars['{{payment_date}}'],   ENT_QUOTES, 'UTF-8' );
        $description    = htmlspecialchars( $vars['{{description}}'],    ENT_QUOTES, 'UTF-8' );
        $support        = htmlspecialchars( $vars['{{support_email}}'],  ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$recipient_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            Thank you for your payment. We have received it successfully
            and your account has been updated accordingly.
        </p>

        <!-- Amount highlight -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td align="center" style="padding:24px;">
                    <p style="margin:0 0 4px;font-size:12px;font-weight:700;
                               text-transform:uppercase;letter-spacing:0.08em;color:#166534;">
                        Amount Paid
                    </p>
                    <p style="margin:0;font-size:32px;font-weight:800;color:#166534;
                               letter-spacing:-0.02em;">
                        {$amount} <span style="font-size:16px;font-weight:600;">{$currency}</span>
                    </p>
                </td>
            </tr>
        </table>

        <!-- Transaction details card -->
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
                                    Transaction ID
                                </p>
                                <p style="margin:0;font-size:13px;color:#1a1a2e;
                                           font-family:monospace;font-weight:600;">
                                    {$transaction_id}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Payment Date
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$payment_date}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Description
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$description}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            Please keep this email as your receipt. If you have any questions about
            this payment, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Payment Received';
    }
    public function description(): string {
        return 'Sent when a payment is confirmed successfully.';
    }
    public static function preview(): static {
        return new static(
            'preview@example.com',
            'Jane Doe',
            '$49.00',
            'USD',
            'TXN-XXXXXXXXXXXX',
            gmdate( 'D, d M Y' ),
            'License renewal — My Awesome Plugin (Pro)'
        );
    }
}