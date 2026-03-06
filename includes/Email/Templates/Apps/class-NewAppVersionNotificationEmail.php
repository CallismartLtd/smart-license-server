<?php
/**
 * New app version notification email template.
 *
 * Sent to licensees when an application they hold a license for
 * has been updated to a new version.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Apps;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\Software;
use SmartLicenseServer\Monetization\License;

defined( 'SMLISER_ABSPATH' ) || exit;

class NewAppVersionNotificationEmail extends EmailTemplate {

    /**
     * @param HostedAppsInterface $app      The updated application.
     * @param License             $license  The licensee's license.
     * @param string              $to       Recipient email address.
     */
    public function __construct(
        private readonly HostedAppsInterface $app,
        private readonly License             $license,
        private readonly string              $to
    ) {}

    static public function template_key(): string {
        return 'new_app_version_notification';
    }

    protected function subject(): string {
        return "Update Available — {$this->app->get_name()} {$this->app->get_version()}";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "A new version of {$this->app->get_name()} is available for your license.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{licensee_name}}' => $this->license->get_licensee_fullname(),
            '{{app_name}}'      => $this->app->get_name(),
            '{{app_version}}'   => $this->app->get_version(),
            '{{app_type}}'      => ucfirst( $this->app->get_type() ),
            '{{license_key}}'   => $this->license->get_license_key(),
        ] );
    }

    protected function body(): string {
        $vars          = $this->variables();
        $licensee_name = htmlspecialchars( $vars['{{licensee_name}}'], ENT_QUOTES, 'UTF-8' );
        $app_name      = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $app_version   = htmlspecialchars( $vars['{{app_version}}'],   ENT_QUOTES, 'UTF-8' );
        $app_type      = htmlspecialchars( $vars['{{app_type}}'],      ENT_QUOTES, 'UTF-8' );
        $license_key   = htmlspecialchars( $vars['{{license_key}}'],   ENT_QUOTES, 'UTF-8' );
        $support       = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$licensee_name},
        </p>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            A new version of <strong>{$app_name}</strong> is now available.
            Your active license entitles you to this update.
        </p>

        <!-- App details card -->
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
                                    Application
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$app_name}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Type
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$app_type}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td width="50%" style="padding:0 16px 0 0;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    New Version
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:700;">
                                    {$app_version}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Your License Key
                                </p>
                                <p style="margin:0;font-size:13px;color:#334155;
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
            To download the update, use your license key through the standard update
            mechanism on your site. If you need help, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'New Application Version Available';
    }
    public function description(): string {
        return 'Sent to licensees when an application they hold a license for is updated.';
    }
    public static function preview(): static {
        $app = Software::from_array_minimal([
            'name'    => 'My Awesome Plugin',
            'version' => '2.0.0',
            'type'    => 'plugin',
            'slug'    => 'my-awesome-plugin',
        ]);

        $license = License::from_array([
            'licensee_fullname' => 'Jane Doe',
            'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
        ]);
        return new static( $app, $license, 'preview@example.com' );
    }
}