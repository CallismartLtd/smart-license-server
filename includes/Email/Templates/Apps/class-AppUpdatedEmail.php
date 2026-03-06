<?php
/**
 * App updated email template.
 *
 * Sent to the app owner when a new version of their application is uploaded.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Apps;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\Theme;

defined( 'SMLISER_ABSPATH' ) || exit;

class AppUpdatedEmail extends EmailTemplate {

    /**
     * @param HostedAppsInterface $app          The updated application.
     * @param string              $to           Recipient email address.
     * @param string              $old_version  The previous version before update.
     */
    public function __construct(
        private readonly HostedAppsInterface $app,
        private readonly string              $to,
        private readonly string              $old_version
    ) {}

    static public function template_key(): string {
        return 'app_updated';
    }

    protected function subject(): string {
        return "{$this->app->get_name()} Updated to Version {$this->app->get_version()}";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "{$this->app->get_name()} has been updated from version {$this->old_version} to {$this->app->get_version()}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{app_name}}'    => $this->app->get_name(),
            '{{new_version}}' => $this->app->get_version(),
            '{{old_version}}' => $this->old_version,
            '{{app_type}}'    => ucfirst( $this->app->get_type() ),
            '{{app_slug}}'    => $this->app->get_slug(),
        ] );
    }

    protected function body(): string {
        $vars        = $this->variables();
        $app_name    = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $new_version = htmlspecialchars( $vars['{{new_version}}'],   ENT_QUOTES, 'UTF-8' );
        $old_version = htmlspecialchars( $vars['{{old_version}}'],   ENT_QUOTES, 'UTF-8' );
        $app_type    = htmlspecialchars( $vars['{{app_type}}'],      ENT_QUOTES, 'UTF-8' );
        $app_slug    = htmlspecialchars( $vars['{{app_slug}}'],      ENT_QUOTES, 'UTF-8' );
        $support     = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            A new version of your application is now live.
        </p>

        <!-- Success banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#166534;line-height:1.5;">
                        &#10003;&nbsp; <strong>{$app_name}</strong> has been updated from
                        version <strong>{$old_version}</strong> to
                        version <strong>{$new_version}</strong>.
                    </p>
                </td>
            </tr>
        </table>

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
                                    Application Name
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
                                    Previous Version
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$old_version}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    New Version
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:700;">
                                    {$new_version}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not make this update or have any concerns, contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Application Updated';
    }
    public function description(): string {
        return 'Sent to the app owner when a new version of their application is uploaded.';
    }
    public static function preview(): static {
        $app = Theme::from_array_minimal([
            'name'    => 'My Awesome Theme',
            'version' => '2.0.0',
            'type'    => 'theme',
            'slug'    => 'my-awesome-theme',
        ]);
        return new static( $app, 'preview@example.com', '1.0.0' );
    }
}