<?php
/**
 * License expiry reminder email template.
 *
 * Sent X days before a license is due to expire.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Licenses;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ABSPATH' ) || exit;

class LicenseExpiryReminderEmail extends EmailTemplate {

    /**
     * @param License $license     The license nearing expiry.
     * @param string  $to          Recipient email address.
     * @param int     $days_left   Number of days remaining before expiry.
     */
    public function __construct(
        private readonly License $license,
        private readonly string  $to,
        private readonly int     $days_left
    ) {}

    static public function template_key(): string {
        return 'license_expiry_reminder';
    }

    protected function subject(): string {
        return "Your License Expires in {$this->days_left} Day(s)";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Action required — your license expires in {$this->days_left} day(s). Renew now to avoid interruption.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}' => $this->license->get_licensee_fullname(),
            '{{license_key}}'   => $this->license->get_license_key(),
            '{{end_date}}'      => $this->license->get_end_date()->format( \smliser_datetime_format() ),
            '{{days_left}}'     => (string) $this->days_left,
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $licensee_name = htmlspecialchars( $vars['{{licensee_name}}'], ENT_QUOTES, 'UTF-8' );
        $license_key   = htmlspecialchars( $vars['{{license_key}}'],   ENT_QUOTES, 'UTF-8' );
        $end_date      = htmlspecialchars( $vars['{{end_date}}'],      ENT_QUOTES, 'UTF-8' );
        $days_left     = htmlspecialchars( $vars['{{days_left}}'],     ENT_QUOTES, 'UTF-8' );
        $support       = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <!-- Urgency banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fffbeb;border:1px solid #fde68a;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#92400e;line-height:1.5;">
                        &#9888;&nbsp; Your license expires in <strong>{$days_left} day(s)</strong>
                        on <strong>{$end_date}</strong>.
                        Renew now to avoid any interruption to your service.
                    </p>
                </td>
            </tr>
        </table>

        <!-- License details card -->
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
                                    License Key
                                </p>
                                <p style="margin:0;font-size:15px;color:#1a1a2e;
                                           font-family:monospace;font-weight:700;letter-spacing:0.05em;">
                                    {$license_key}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Expiry Date
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$end_date}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you have already renewed or have any questions, please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'License Expiry Reminder';
    }
    public function description(): string {
        return 'Sent a set number of days before a license is due to expire.';
    }
    public static function preview(): static {
        $license = License::from_array([
            'licensee_fullname' => 'Jane Doe',
            'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
            'end_date'          => gmdate( 'Y-m-d', strtotime( '+7 days' ) ),
        ]);
        return new static( $license, 'preview@example.com', 7 );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'Hi {{licensee_name}},',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'banner',
                'type'      => 'banner',
                'tone'      => 'warning',
                'content'   => 'Your license expires in {{days_left}} day(s) on {{end_date}}. Renew now to avoid any interruption to your service.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'License Key',   'value' => '{{license_key}}' ],
                    [ 'label' => 'Expiry Date',   'value' => '{{end_date}}' ],
                    [ 'label' => 'Days Remaining','value' => '{{days_left}}' ],
                ],
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you have already renewed or have any questions, please contact us at {{support_email}}.',
                'editable'  => true,
                'removable' => false,
            ],
        ];
    }
}