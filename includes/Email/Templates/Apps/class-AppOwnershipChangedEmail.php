<?php
/**
 * App ownership changed email template.
 *
 * Sent to both the previous and new owner when ownership of a
 * hosted application is transferred.
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

class AppOwnershipChangedEmail extends EmailTemplate {

    /**
     * @param HostedAppsInterface $app            The application being transferred.
     * @param string              $to             Recipient email address.
     * @param string              $recipient_name Name of the recipient.
     * @param string              $previous_owner Name of the previous owner.
     * @param string              $new_owner      Name of the new owner.
     * @param bool                $is_new_owner   True if recipient is the new owner,
     *                                            false if they are the previous owner.
     */
    public function __construct(
        private readonly HostedAppsInterface $app,
        private readonly string              $to,
        private readonly string              $recipient_name,
        private readonly string              $previous_owner,
        private readonly string              $new_owner,
        private readonly bool                $is_new_owner = true
    ) {}

    static public function template_key(): string {
        return 'app_ownership_changed';
    }

    protected function subject(): string {
        return $this->is_new_owner
            ? "Ownership of {$this->app->get_name()} Has Been Transferred to You"
            : "You Are No Longer the Owner of {$this->app->get_name()}";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return $this->is_new_owner
            ? "You are now the owner of {$this->app->get_name()}."
            : "Ownership of {$this->app->get_name()} has been transferred to {$this->new_owner}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{recipient_name}}' => $this->recipient_name,
            '{{app_name}}'       => $this->app->get_name(),
            '{{app_type}}'       => ucfirst( $this->app->get_type() ),
            '{{app_slug}}'       => $this->app->get_slug(),
            '{{previous_owner}}' => $this->previous_owner,
            '{{new_owner}}'      => $this->new_owner,
        ] );
    }

    protected function body(): string {
        $vars           = $this->variables();
        $recipient_name = htmlspecialchars( $vars['{{recipient_name}}'], ENT_QUOTES, 'UTF-8' );
        $app_name       = htmlspecialchars( $vars['{{app_name}}'],       ENT_QUOTES, 'UTF-8' );
        $app_type       = htmlspecialchars( $vars['{{app_type}}'],       ENT_QUOTES, 'UTF-8' );
        $app_slug       = htmlspecialchars( $vars['{{app_slug}}'],       ENT_QUOTES, 'UTF-8' );
        $previous_owner = htmlspecialchars( $vars['{{previous_owner}}'], ENT_QUOTES, 'UTF-8' );
        $new_owner      = htmlspecialchars( $vars['{{new_owner}}'],      ENT_QUOTES, 'UTF-8' );
        $support        = htmlspecialchars( $vars['{{support_email}}'],  ENT_QUOTES, 'UTF-8' );

        [ $banner_bg, $banner_border, $banner_text_color, $banner_icon, $banner_message ] =
            $this->is_new_owner
            ? [
                '#f0fdf4', '#bbf7d0', '#166534', '&#10003;',
                "You are now the owner of <strong>{$app_name}</strong>. You have full control over this application."
              ]
            : [
                '#fffbeb', '#fde68a', '#92400e', '&#9888;',
                "Ownership of <strong>{$app_name}</strong> has been transferred to <strong>{$new_owner}</strong>. You no longer have owner-level access."
              ];

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$recipient_name},
        </p>

        <!-- Ownership banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$banner_bg};border:1px solid {$banner_border};
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;
                               color:{$banner_text_color};line-height:1.5;">
                        {$banner_icon}&nbsp; {$banner_message}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Transfer details card -->
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
                                    Previous Owner
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;font-weight:600;">
                                    {$previous_owner}
                                </p>
                            </td>
                            <td width="50%" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    New Owner
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:700;">
                                    {$new_owner}
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you did not expect this change or believe it was made in error,
            please contact us immediately at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Application Ownership Changed';
    }
    public function description(): string {
        return 'Sent to both the previous and new owner when application ownership is transferred.';
    }
    public static function preview(): static {
        $app = Plugin::from_array_minimal([
            'name' => 'My Awesome Plugin',
            'type' => 'plugin',
            'slug' => 'my-awesome-plugin',
        ]);
        return new static( $app, 'preview@example.com', 'Jane Doe', 'John Smith', 'Jane Doe', true );
    }
}