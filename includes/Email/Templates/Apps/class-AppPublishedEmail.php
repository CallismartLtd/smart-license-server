<?php
/**
 * App published email template.
 *
 * Sent when a hosted application is published.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Apps;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\HostedApps\HostedAppsInterface;
use SmartLicenseServer\HostedApps\Plugin;

defined( 'SMLISER_ABSPATH' ) || exit;

class AppPublishedEmail extends EmailTemplate {

    /**
     * @param HostedAppsInterface $app  The published application.
     * @param string              $to   Recipient email address.
     */
    public function __construct(
        private readonly HostedAppsInterface $app,
        private readonly string              $to
    ) {}

    static public function template_key(): string {
        return 'app_published';
    }

    protected function subject(): string {
        return "{$this->app->get_name()} Has Been Published";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "{$this->app->get_name()} is now live.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{app_name}}'    => $this->app->get_name(),
            '{{app_version}}' => $this->app->get_version(),
            '{{app_type}}'    => ucfirst( $this->app->get_type() ),
            '{{app_slug}}'    => $this->app->get_slug(),
        ] );
    }

    protected function body(): string {
        $vars        = $this->variables();
        $app_name    = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $app_version = htmlspecialchars( $vars['{{app_version}}'],   ENT_QUOTES, 'UTF-8' );
        $app_type    = htmlspecialchars( $vars['{{app_type}}'],      ENT_QUOTES, 'UTF-8' );
        $app_slug    = htmlspecialchars( $vars['{{app_slug}}'],      ENT_QUOTES, 'UTF-8' );
        $support     = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Your application is now live.
        </p>

        <!-- Success banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#166534;line-height:1.5;">
                        &#10003;&nbsp; <strong>{$app_name}</strong> has been published successfully.
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
                                    Version
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$app_version}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Slug
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;
                                           font-family:monospace;font-weight:600;">
                                    {$app_slug}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you have any questions or concerns, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Application Published';
    }
    public function description(): string {
        return 'Sent to the app owner when a hosted application is published.';
    }
    public static function preview(): static {
        $app = Plugin::from_array_minimal([
            'name'    => 'My Awesome Plugin',
            'version' => '0.2.0',
            'type'    => 'plugin',
            'slug'    => 'my-awesome-plugin',
        ]);
        return new static( $app, 'preview@example.com' );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'Your application is now live.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'banner',
                'type'      => 'banner',
                'tone'      => 'success',
                'content'   => '{{app_name}} has been published successfully and is now available to licensed users.',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Application Name', 'value' => '{{app_name}}' ],
                    [ 'label' => 'Type',             'value' => '{{app_type}}' ],
                    [ 'label' => 'Version',          'value' => '{{app_version}}' ],
                    [ 'label' => 'Slug',             'value' => '{{app_slug}}' ],
                ],
                'editable'  => true,
                'removable' => true,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you have any questions or concerns, contact us at {{support_email}}.',
                'editable'  => true,
                'removable' => false,
            ],
        ];
    }
}