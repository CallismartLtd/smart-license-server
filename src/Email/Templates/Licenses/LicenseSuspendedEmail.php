<?php
/**
 * License suspended email template.
 *
 * Sent when a license is suspended by an administrator.
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

class LicenseSuspendedEmail extends EmailTemplate {

    /**
     * @param License     $license The suspended license.
     * @param string      $to      Recipient email address.
     * @param string|null $reason  Optional reason for suspension.
     */
    public function __construct(
        private readonly License  $license,
        private readonly string   $to,
        private readonly ?string  $reason = null
    ) {}

    static public function template_key(): string {
        return 'license_suspended';
    }

    protected function subject(): string {
        return 'Your License Has Been Suspended';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'Your license has been suspended. Please contact support for more information.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}' => $this->license->get_licensee_fullname(),
            '{{license_key}}'   => $this->license->get_license_key(),
            '{{reason}}'        => $this->reason ?? 'No reason provided.',
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $licensee_name = htmlspecialchars( $vars['{{licensee_name}}'], ENT_QUOTES, 'UTF-8' );
        $license_key   = htmlspecialchars( $vars['{{license_key}}'],   ENT_QUOTES, 'UTF-8' );
        $reason        = htmlspecialchars( $vars['{{reason}}'],        ENT_QUOTES, 'UTF-8' );
        $support       = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <!-- Suspension banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#991b1b;line-height:1.5;">
                        &#128683;&nbsp; Your license has been suspended and is currently inactive.
                        Please contact support to resolve this.
                    </p>
                </td>
            </tr>
        </table>

        <!-- Details card -->
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
            If you believe this suspension was made in error or need further assistance,
            please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'License Suspended';
    }
    public function description(): string {
        return 'Sent when a license is suspended by an administrator.';
    }
    public static function preview(): static {
        $license = License::from_array([
            'licensee_fullname' => 'Jane Doe',
            'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
        ]);
        return new static( $license, 'preview@example.com', 'Suspicious activity detected.' );
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
                'tone'      => 'error',
                'content'   => 'Your license has been suspended and is currently inactive. Please contact support to resolve this.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'License Key', 'value' => '{{license_key}}' ],
                    [ 'label' => 'Reason',      'value' => '{{reason}}' ],
                ],
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you believe this suspension was made in error or need further assistance, please contact us at {{support_email}}.',
                'editable'  => true,
                'removable' => false,
            ],
        ];
    }
}