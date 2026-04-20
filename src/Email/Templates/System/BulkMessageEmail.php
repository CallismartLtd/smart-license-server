<?php
/**
 * Bulk message email template.
 *
 * Sent for admin-initiated bulk messages to licensees.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\System;

use SmartLicenseServer\Email\Templates\EmailTemplate;

defined( 'SMLISER_ABSPATH' ) || exit;

class BulkMessageEmail extends EmailTemplate {

    /**
     * @param string $to               Recipient email address.
     * @param string $recipient_name   Name of the recipient.
     * @param string $subject          The message subject.
     * @param string $message_body     The message body HTML content.
     */
    public function __construct(
        private readonly string $to,
        private readonly string $recipient_name,
        private readonly string $subject,
        private readonly string $message_body
    ) {}

    static public function template_key(): string {
        return 'bulk_message';
    }

    protected function subject(): string {
        return $this->subject;
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "A message from the administrator — {$this->subject}.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{recipient_name}}' => $this->recipient_name,
            '{{message_body}}'   => $this->message_body,
        ] );
    }

    protected function body(): string {
        $vars           = $this->variables();
        $recipient_name = htmlspecialchars( $vars['{{recipient_name}}'], ENT_QUOTES, 'UTF-8' );
        $support        = htmlspecialchars( $vars['{{support_email}}'],  ENT_QUOTES, 'UTF-8' );

        // message_body is admin-authored HTML — not escaped so formatting is preserved.
        $message_body = $vars['{{message_body}}'];

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$recipient_name},
        </p>

        <!-- Message body -->
        <div style="font-size:15px;color:#334155;line-height:1.7;margin:0 0 24px;">
            {$message_body}
        </div>

        <p style="margin:0 0 16px;font-size:14px;color:#64748b;line-height:1.6;">
            If you have any questions regarding this message, please contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Bulk Message';
    }
    public function description(): string {
        return 'Sent for admin-initiated bulk messages to licensees.';
    }
    public static function preview(): static {
        return new static(
            'preview@example.com',
            'Jane Doe',
            'Important Update Regarding Your License',
            '<p>We wanted to let you know about some important changes coming to your license plan next month.</p><p>Please review the details on our website and contact support if you have any questions.</p>'
        );
    }

    public function get_blocks(): array {
        return [
            [
                'id'        => 'greeting',
                'type'      => 'greeting',
                'content'   => 'Hi {{recipient_name}},',
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'message',
                'type'      => 'text',
                'content'   => $this->message_body,
                'editable'  => true,
                'removable' => false,
            ],
            [
                'id'        => 'closing',
                'type'      => 'closing',
                'content'   => 'If you have any questions regarding this message, please contact us at {{support_email}}.',
                'editable'  => true,
                'removable' => true,
            ],
        ];
    }
}