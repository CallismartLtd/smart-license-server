<?php
/**
 * System alert email template.
 *
 * Sent to the administration email address when a critical
 * system error or event requires immediate attention.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

use SmartLicenseServer\Email\Templates\EmailTemplate;

defined( 'SMLISER_ABSPATH' ) || exit;

class SystemAlertEmail extends EmailTemplate {

    const SEVERITY_INFO     = 'info';
    const SEVERITY_WARNING  = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    /**
     * @param string      $to          Recipient email address (typically admin email).
     * @param string      $alert_title Short title describing the alert.
     * @param string      $alert_body  Full description of the alert or error.
     * @param string      $severity    Severity level — use class constants.
     * @param string|null $context     Optional additional context or stack trace.
     */
    public function __construct(
        private readonly string  $to,
        private readonly string  $alert_title,
        private readonly string  $alert_body,
        private readonly string  $severity = self::SEVERITY_WARNING,
        private readonly ?string $context  = null
    ) {}

    static public function template_key(): string {
        return 'system_alert';
    }

    protected function subject(): string {
        $prefix = match( $this->severity ) {
            self::SEVERITY_CRITICAL => '[CRITICAL]',
            self::SEVERITY_WARNING  => '[WARNING]',
            default                 => '[INFO]',
        };

        return "{$prefix} {$this->alert_title}";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "System alert — {$this->alert_title}. Immediate review may be required.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{alert_title}}' => $this->alert_title,
            '{{alert_body}}'  => $this->alert_body,
            '{{severity}}'    => ucfirst( $this->severity ),
            '{{occurred_at}}' => gmdate( 'D, d M Y H:i T' ),
            '{{context}}'     => $this->context ?? '',
        ] );
    }

    protected function body(): string {
        $vars        = $this->variables();
        $alert_title = htmlspecialchars( $vars['{{alert_title}}'], ENT_QUOTES, 'UTF-8' );
        $alert_body  = htmlspecialchars( $vars['{{alert_body}}'],  ENT_QUOTES, 'UTF-8' );
        $severity    = htmlspecialchars( $vars['{{severity}}'],    ENT_QUOTES, 'UTF-8' );
        $occurred_at = htmlspecialchars( $vars['{{occurred_at}}'], ENT_QUOTES, 'UTF-8' );
        $context     = htmlspecialchars( $vars['{{context}}'],     ENT_QUOTES, 'UTF-8' );
        $app_name    = htmlspecialchars( $vars['{{app_name}}'],    ENT_QUOTES, 'UTF-8' );
        $support     = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        [ $banner_bg, $banner_border, $banner_text_color, $banner_icon ] = match( $this->severity ) {
            self::SEVERITY_CRITICAL => [ '#fef2f2', '#fecaca', '#991b1b', '&#128683;' ],
            self::SEVERITY_WARNING  => [ '#fffbeb', '#fde68a', '#92400e', '&#9888;'   ],
            default                 => [ '#eff6ff', '#bfdbfe', '#1e40af', '&#8505;'   ],
        };

        $context_block = ! empty( $context )
            ? <<<CONTEXT
            <tr>
                <td style="padding-top:16px;vertical-align:top;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                               text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                        Additional Context
                    </p>
                    <pre style="margin:0;font-size:12px;color:#334155;
                                font-family:monospace;white-space:pre-wrap;
                                word-break:break-all;background-color:#f1f5f9;
                                padding:12px;border-radius:6px;line-height:1.6;">{$context}</pre>
                </td>
            </tr>
            CONTEXT
            : '';

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            System Alert — {$app_name}
        </p>

        <!-- Severity banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$banner_bg};border:1px solid {$banner_border};
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;
                               color:{$banner_text_color};line-height:1.5;">
                        {$banner_icon}&nbsp; <strong>[{$severity}]</strong> {$alert_title}
                    </p>
                </td>
            </tr>
        </table>

        <!-- Alert details card -->
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
                                    Severity
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$severity}
                                </p>
                            </td>
                            <td width="50%" style="padding:0 0 16px;vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Occurred At
                                </p>
                                <p style="margin:0;font-size:14px;color:#1a1a2e;font-weight:600;">
                                    {$occurred_at}
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="vertical-align:top;">
                                <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                                           text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                                    Details
                                </p>
                                <p style="margin:0;font-size:14px;color:#334155;line-height:1.6;">
                                    {$alert_body}
                                </p>
                            </td>
                        </tr>
                        {$context_block}
                    </table>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            This is an automated alert from <strong>{$app_name}</strong>.
            If this issue requires support, contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'System Alert';
    }
    public function description(): string {
        return 'Sent to the admin email address when a critical system event requires attention.';
    }
    public static function preview(): static {
        return new static(
            'preview@example.com',
            'Database Connection Failed',
            'The system was unable to connect to the database after 3 consecutive attempts.',
            SystemAlertEmail::SEVERITY_CRITICAL,
            "Error: SQLSTATE[HY000] [2002] Connection refused\n#0 /var/www/html/Database.php(42)"
        );
    }

    public function get_blocks(): array {
        $blocks = [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'System Alert — {{app_name}}',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'banner',
                'type'      => 'banner',
                'tone'      => match( $this->severity ) {
                    self::SEVERITY_CRITICAL => 'error',
                    self::SEVERITY_WARNING  => 'warning',
                    default                 => 'info',
                },
                'content'   => '[{{severity}}] {{alert_title}}',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'details',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Severity',    'value' => '{{severity}}' ],
                    [ 'label' => 'Occurred At', 'value' => '{{occurred_at}}' ],
                    [ 'label' => 'Details',     'value' => '{{alert_body}}' ],
                ],
                'editable'  => true,
                'removable' => false,
            ],
        ];

        // Context block is optional — only included when context data is present.
        if ( ! empty( $this->context ) ) {
            $blocks[] = [
                'id'        => 'context',
                'type'      => 'detail_card',
                'rows'      => [
                    [ 'label' => 'Additional Context', 'value' => '{{context}}' ],
                ],
                'editable'  => false,
                'removable' => true,
            ];
        }

        $blocks[] = [
            'id'        => 'closing',
            'type'      => 'closing',
            'content'   => 'This is an automated alert from {{app_name}}. If this issue requires support, contact us at {{support_email}}.',
            'editable'  => true,
            'removable' => false,
        ];

        return $blocks;
    }
}