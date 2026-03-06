<?php
/**
 * Organization member removed email template.
 *
 * Sent when a member is removed from an organization.
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates\Accounts;

use SmartLicenseServer\Email\Templates\EmailTemplate;
use SmartLicenseServer\Security\Owner;

defined( 'SMLISER_ABSPATH' ) || exit;

class OrganizationMemberRemovedEmail extends EmailTemplate {

    /**
     * @param Owner  $organization The organization the member was removed from.
     * @param string $to           Recipient email address.
     * @param string $member_name  The name of the member being removed.
     */
    public function __construct(
        private readonly Owner  $organization,
        private readonly string $to,
        private readonly string $member_name
    ) {}

    static public function template_key(): string {
        return 'organization_member_removed';
    }

    protected function subject(): string {
        return "You Have Been Removed from {$this->organization->get_name()}";
    }

    protected function recipient(): string {
        return $this->to;
    }

    protected function preheader(): string {
        return "Your membership in {$this->organization->get_name()} has been ended.";
    }

    protected function variables(): array {
        return array_merge( parent::variables(), [
            '{{member_name}}'       => $this->member_name,
            '{{organization_name}}' => $this->organization->get_name(),
        ] );
    }

    protected function body(): string {
        $vars              = $this->variables();
        $member_name       = htmlspecialchars( $vars['{{member_name}}'],       ENT_QUOTES, 'UTF-8' );
        $organization_name = htmlspecialchars( $vars['{{organization_name}}'], ENT_QUOTES, 'UTF-8' );
        $support           = htmlspecialchars( $vars['{{support_email}}'],     ENT_QUOTES, 'UTF-8' );

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:#1a1a2e;">
            Hi {$member_name},
        </p>

        <!-- Notice banner -->
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:#fef2f2;border:1px solid #fecaca;border-radius:8px;
                      margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;color:#991b1b;line-height:1.5;">
                        &#128683;&nbsp; Your membership in
                        <strong>{$organization_name}</strong>
                        has been ended. You no longer have access to this organization's
                        resources.
                    </p>
                </td>
            </tr>
        </table>

        <p style="margin:0 0 16px;font-size:15px;color:#334155;line-height:1.6;">
            If you believe this was done in error or would like more information,
            please reach out to the organization administrator or contact us at
            <a href="mailto:{$support}" style="color:#6366f1;text-decoration:none;">{$support}</a>.
        </p>
        HTML;
    }

    public function label(): string {
        return 'Organization Member Removed';
    }
    public function description(): string {
        return 'Sent when a member is removed from an organization.';
    }
    public static function preview(): static {
        $org = Owner::from_array([ 'name' => 'Acme Corporation' ]);
        return new static( $org, 'preview@example.com', 'Jane Doe' );
    }
}