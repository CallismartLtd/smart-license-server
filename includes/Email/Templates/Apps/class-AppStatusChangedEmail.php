<?php
/**
 * App status changed email template.
 *
 * Sent to the app owner when the status of their hosted application
 * is changed by an administrator.
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

class AppStatusChangedEmail extends EmailTemplate {

    /**
     * @param HostedAppsInterface $app        The application whose status changed.
     * @param string              $to         Recipient email address.
     * @param string              $old_status The previous status.
     * @param string              $new_status The new status.
     * @param string|null         $reason     Optional reason for the status change.
     */
    public function __construct(
        private readonly HostedAppsInterface $app,
        private readonly string              $to,
        private readonly string              $old_status,
        private readonly string              $new_status,
        private readonly ?string             $reason = null
    ) {}

    static public function template_key(): string {
        return 'app_status_changed';
    }

    protected function subject(): string {
        return "{$this->app->get_name()} Status Changed to " . ucfirst( $this->new_status );
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "The status of {$this->app->get_name()} has been changed from {$this->old_status} to {$this->new_status}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{app_name}}'   => $this->app->get_name(),
            '{{app_type}}'   => ucfirst( $this->app->get_type() ),
            '{{old_status}}' => ucfirst( $this->old_status ),
            '{{new_status}}' => ucfirst( $this->new_status ),
            '{{reason}}'     => $this->reason ?? 'No reason provided.',
        ] );
    }

    protected function body(): string {
        $vars       = $this->variables();
        $app_name   = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $app_type   = htmlspecialchars( $vars['{{app_type}}'],      ENT_QUOTES, 'UTF-8' );
        $old_status = htmlspecialchars( $vars['{{old_status}}'],    ENT_QUOTES, 'UTF-8' );
        $new_status = htmlspecialchars( $vars['{{new_status}}'],    ENT_QUOTES, 'UTF-8' );
        $reason     = htmlspecialchars( $vars['{{reason}}'],        ENT_QUOTES, 'UTF-8' );
        $support    = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        $is_negative = in_array(
            strtolower( $this->new_status ),
            [ 'suspended', 'inactive', 'rejected', 'disabled' ],
            true
        );

        [ $banner_bg, $banner_border, $banner_text_color, $banner_icon ] = $is_negative
            ? [ '#fef2f2', '#fecaca', '#991b1b', '&#128683;' ]
            : [ '#f0fdf4', '#bbf7d0', '#166534', '&#10003;' ];

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            A status update for your application.
        </p>

        <!-- Status banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$banner_bg};border:1px solid {$banner_border};
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;
                               color:{$banner_text_color};line-height:1.5;">
                        {$banner_icon}&nbsp; The status of <strong>{$app_name}</strong>
                        has been changed from <strong>{$old_status}</strong>
                        to <strong>{$new_status}</strong>.
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
                            <td colspan="2" style="vertical-align:top;">
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
            If you have questions about this change or believe it was made in error,
            please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Application Status Changed';
    }
    public function description(): string {
        return 'Sent to the app owner when the status of their application is changed by an administrator.';
    }
    public static function preview(): static {
        $app = Plugin::from_array_minimal([
            'name' => 'My Awesome Plugin',
            'type' => 'plugin',
        ]);
        return new static( $app, 'preview@example.com', 'active', 'suspended', 'Violation of terms of service.' );
    }
}