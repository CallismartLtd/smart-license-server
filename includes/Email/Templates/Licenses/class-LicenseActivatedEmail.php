<?php
/**
 * License activated email template.
 *
 * Sent when a license is successfully activated on a domain.
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

class LicenseActivatedEmail extends EmailTemplate {

    /**
     * @param License $license   The activated license.
     * @param string  $to        Recipient email address.
     * @param string  $domain    The domain the license was activated on.
     */
    public function __construct(
        private readonly License $license,
        private readonly string  $to,
        private readonly string  $domain
    ) {}

    static public function template_key(): string {
        return 'license_activated';
    }

    protected function subject(): string {
        return 'License Activated Successfully';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Your license has been activated on {$this->domain}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}' => $this->license->get_licensee_fullname(),
            '{{license_key}}'   => $this->license->get_license_key(),
            '{{domain}}'        => $this->domain,
            '{{end_date}}'      => $this->license->get_end_date()->format( \smliser_datetime_format() ),
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $licensee_name = htmlspecialchars( $vars['{{licensee_name}}'], ENT_QUOTES, 'UTF-8' );
        $license_key   = htmlspecialchars( $vars['{{license_key}}'],   ENT_QUOTES, 'UTF-8' );
        $domain        = htmlspecialchars( $vars['{{domain}}'],        ENT_QUOTES, 'UTF-8' );
        $end_date      = htmlspecialchars( $vars['{{end_date}}'],      ENT_QUOTES, 'UTF-8' );
        $support       = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            Your license has been successfully activated on the domain below.
        </p>

        <!-- Activation details card -->
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
                                    Activated Domain
                                </p>
                                <p style="margin:0;font-size:15px;color:#1a1a2e;font-weight:600;">
                                    {$domain}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:0 0 16px;vertical-align:top;">
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
                        <tr>
                            <td style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    License Expires
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
            If you did not activate this license or do not recognize the domain above,
            please contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'License Activated';
    }

    public function description(): string {
        return 'Sent when a license is successfully activated on a domain.';
    }
    
    public static function preview(): static {
        $license = License::from_array([
            'licensee_fullname' => 'Jane Doe',
            'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
            'end_date'          => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
        ]);
        return new static( $license, 'preview@example.com', 'example.com' );
    }

}