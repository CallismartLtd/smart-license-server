<?php
/**
 * License deactivated email template.
 *
 * Sent when a license is deactivated from a domain.
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

class LicenseDeactivatedEmail extends EmailTemplate {

    /**
     * @param License $license   The license that was deactivated.
     * @param string  $to        Recipient email address.
     * @param string  $domain    The domain the license was deactivated from.
     */
    public function __construct(
        private readonly License $license,
        private readonly string  $to,
        private readonly string  $domain
    ) {}

    static public function template_key(): string {
        return 'license_deactivated';
    }

    protected function subject(): string {
        return 'License Deactivated';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Your license has been deactivated from {$this->domain}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}' => $this->license->get_licensee_fullname(),
            '{{license_key}}'   => $this->license->get_license_key(),
            '{{domain}}'        => $this->domain,
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $licensee_name = htmlspecialchars( $vars['{{licensee_name}}'], ENT_QUOTES, 'UTF-8' );
        $license_key   = htmlspecialchars( $vars['{{license_key}}'],   ENT_QUOTES, 'UTF-8' );
        $domain        = htmlspecialchars( $vars['{{domain}}'],        ENT_QUOTES, 'UTF-8' );
        $support       = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            Your license has been deactivated from the domain below.
            You can reactivate it on the same or a different domain at any time.
        </p>

        <!-- Deactivation details card -->
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
                                    Deactivated Domain
                                </p>
                                <p style="margin:0;font-size:15px;color:#1a1a2e;font-weight:600;">
                                    {$domain}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    License Key
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;
                                           font-family:monospace;font-weight:600;letter-spacing:0.05em;">
                                    {$license_key}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not request this deactivation, please contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'License Deactivated';
    }
    public function description(): string {
        return 'Sent when a license is deactivated from a domain.';
    }
    public static function preview(): static {
        $license = License::from_array([
            'licensee_fullname' => 'Jane Doe',
            'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
        ]);
        return new static( $license, 'preview@example.com', 'example.com' );
    }
}