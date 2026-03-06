<?php
/**
 * License issued email template.
 *
 * Sent when a new license is created and assigned to a licensee.
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

class LicenseIssuedEmail extends EmailTemplate {

    /**
     * @param License $license   The issued license.
     * @param string  $recipient Recipient email address.
     */
    public function __construct(
        private readonly License $license,
        private readonly string  $to
    ) {}

    static public function template_key(): string {
        return 'license_issued';
    }

    protected function subject(): string {
        return 'Your License Key is Ready';
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return 'Your license key has been issued. Find your key and activation details inside.';
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}'   => $this->license->get_licensee_fullname(),
            '{{license_key}}'     => $this->license->get_license_key(),
            '{{start_date}}'      => $this->license->get_start_date()->format( \smliser_datetime_format() ),
            '{{end_date}}'        => $this->license->get_end_date()->format( \smliser_datetime_format() ),
            '{{activation_limit}}' => (string) $this->license->get_max_allowed_domains(),
        ] );
    }

    protected function body(): string {
        $vars = $this->variables();

        $licensee_name    = htmlspecialchars( $vars['{{licensee_name}}'],    ENT_QUOTES, 'UTF-8' );
        $license_key      = htmlspecialchars( $vars['{{license_key}}'],      ENT_QUOTES, 'UTF-8' );
        $start_date       = htmlspecialchars( $vars['{{start_date}}'],       ENT_QUOTES, 'UTF-8' );
        $end_date         = htmlspecialchars( $vars['{{end_date}}'],         ENT_QUOTES, 'UTF-8' );
        $activation_limit = htmlspecialchars( $vars['{{activation_limit}}'], ENT_QUOTES, 'UTF-8' );
        $support          = htmlspecialchars( $vars['{{support_email}}'],    ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            Your license has been successfully issued. Below are your license details.
            Please keep this information safe.
        </p>

        <!-- License details card -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">

                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                               text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                        License Key
                    </p>
                    <p style="margin:0 0 20px;font-size:18px;font-weight:700;
                               color:#1a1a2e;font-family:monospace;letter-spacing:0.05em;">
                        {$license_key}
                    </p>

                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td width="50%" style="padding:0 16px 0 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Start Date
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$start_date}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Expiry Date
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$end_date}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Activation Limit
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$activation_limit} site(s)
                                </p>
                            </td>
                        </tr>
                    </table>

                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not request this license or believe this was issued in error,
            please contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'License Issued';
    }
    
    public function description(): string {
        return 'Sent when a new license is created and assigned to a licensee.';
    }

    public static function preview(): static {
        $license = License::from_array([
            'licensee_fullname'   => 'Jane Doe',
            'license_key'         => 'SMLISER-XXXX-XXXX-XXXX',
            'start_date'          => gmdate( 'Y-m-d' ),
            'end_date'            => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
            'max_allowed_domains' => 3,
        ]);

        return new static( $license, 'preview@example.com' );
    }

}