<?php
/**
 * Email template detail page.
 *
 * Renders a preview of a single email template with management controls.
 * Variables available from OptionsPage::email_template_detail():
 *   $entry        — array from EmailTemplateRegistry::entry()
 *   $preview      — EmailTemplate instance
 *   $preview_html — string rendered HTML of the template
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args   = static::get_menu_args();
$current_url = smliser_get_current_url()->remove_query_param( 'message', 'provider' );
$list_url    = $current_url->add_query_param( 'section', 'templates' )->remove_query_param( 'template' );
$key         = $entry['key'];
$label       = $entry['label'];
$description = $entry['description'];
$is_enabled  = $entry['is_enabled'];
$has_custom  = $entry['has_custom'];
?>

<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>

    <div style="padding:20px 20px 0;">

        <!-- Back link -->
        <a href="<?php echo esc_url( $list_url ); ?>"
           style="display:inline-flex;align-items:center;gap:6px;font-size:13px;
                  color:#6366f1;text-decoration:none;margin-bottom:20px;">
            <span class="dashicons dashicons-arrow-left-alt"
                  style="font-size:16px;width:16px;height:16px;margin-top:1px;"></span>
            Back to Email Templates
        </a>

        <!-- Header row -->
        <div style="display:flex;align-items:flex-start;justify-content:space-between;
                    flex-wrap:wrap;gap:12px;margin-bottom:24px;">

            <!-- Title + meta -->
            <div>
                <h2 style="margin:0 0 6px;font-size:20px;font-weight:700;color:#1a1a2e;">
                    <?php echo esc_html( $label ); ?>
                </h2>
                <p style="margin:0;font-size:13px;color:#64748b;line-height:1.5;max-width:560px;">
                    <?php echo esc_html( $description ); ?>
                </p>

                <!-- Badges -->
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px;">

                    <!-- Enabled/Disabled badge -->
                    <?php if ( $is_enabled ) : ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     font-weight:700;padding:3px 10px;border-radius:9999px;
                                     background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0;">
                            <span style="width:6px;height:6px;border-radius:50%;
                                         background:#16a34a;display:inline-block;"></span>
                            Enabled
                        </span>
                    <?php else : ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     font-weight:700;padding:3px 10px;border-radius:9999px;
                                     background:#f8fafc;color:#94a3b8;border:1px solid #e2e8f0;">
                            <span style="width:6px;height:6px;border-radius:50%;
                                         background:#94a3b8;display:inline-block;"></span>
                            Disabled
                        </span>
                    <?php endif; ?>

                    <!-- Custom badge -->
                    <?php if ( $has_custom ) : ?>
                        <span style="display:inline-flex;align-items:center;gap:5px;font-size:11px;
                                     font-weight:700;padding:3px 10px;border-radius:9999px;
                                     background:#fdf4ff;color:#9333ea;border:1px solid #e9d5ff;">
                            <span class="dashicons dashicons-edit"
                                  style="font-size:11px;width:11px;height:11px;margin-top:1px;"></span>
                            Custom Template
                        </span>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Action buttons -->
            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">

                <!-- Enable / Disable toggle -->
                <button type="button"
                        class="smliser-template-toggle button"
                        data-key="<?php echo esc_attr( $key ); ?>"
                        data-enabled="<?php echo $is_enabled ? '1' : '0'; ?>"
                        style="display:inline-flex;align-items:center;gap:6px;">
                    <span class="dashicons <?php echo $is_enabled ? 'dashicons-hidden' : 'dashicons-visibility'; ?>"
                          style="font-size:15px;width:15px;height:15px;margin-top:2px;"></span>
                    <?php echo $is_enabled ? 'Disable' : 'Enable'; ?>
                </button>

                <!-- Reset to default — only shown when a custom template is stored -->
                <?php if ( $has_custom ) : ?>
                    <button type="button"
                            id="smliser-reset-template"
                            class="button"
                            data-key="<?php echo esc_attr( $key ); ?>"
                            style="display:inline-flex;align-items:center;gap:6px;
                                   color:#991b1b;border-color:#fecaca;">
                        <span class="dashicons dashicons-image-rotate"
                              style="font-size:15px;width:15px;height:15px;margin-top:2px;"></span>
                        Reset to Default
                    </button>
                <?php endif; ?>

                <!-- Edit button — placeholder for future JS editor -->
                <button type="button"
                        id="smliser-edit-template"
                        class="button button-primary"
                        data-key="<?php echo esc_attr( $key ); ?>"
                        disabled
                        title="The visual editor is coming soon."
                        style="display:inline-flex;align-items:center;gap:6px;
                               opacity:0.6;cursor:not-allowed;">
                    <span class="dashicons dashicons-edit"
                          style="font-size:15px;width:15px;height:15px;margin-top:2px;"></span>
                    Edit Template
                    <span style="font-size:10px;font-weight:700;padding:1px 6px;
                                 border-radius:9999px;background:rgba(255,255,255,0.3);
                                 margin-left:2px;">
                        Soon
                    </span>
                </button>

            </div>
        </div>

        <!-- Preview label -->
        <div style="display:flex;align-items:center;justify-content:space-between;
                    margin-bottom:8px;">
            <span style="font-size:13px;font-weight:600;color:#475569;">
                Email Preview
            </span>
            <span style="font-size:12px;color:#94a3b8;">
                This is how the email appears to recipients.
                Placeholder values are used for preview.
            </span>
        </div>

    </div>

    <!-- Preview iframe -->
    <div style="padding:0 20px 32px;">
        <div style="border:1px solid #e2e8f0;border-radius:8px;overflow:hidden;
                    box-shadow:0 2px 8px rgba(0,0,0,0.04);">

            <!-- Fake browser chrome -->
            <div style="background:#f8fafc;border-bottom:1px solid #e2e8f0;
                        padding:10px 16px;display:flex;align-items:center;gap:8px;">
                <span style="width:10px;height:10px;border-radius:50%;
                              background:#fecaca;display:inline-block;"></span>
                <span style="width:10px;height:10px;border-radius:50%;
                              background:#fde68a;display:inline-block;"></span>
                <span style="width:10px;height:10px;border-radius:50%;
                              background:#bbf7d0;display:inline-block;"></span>
                <span style="flex:1;background:#ffffff;border:1px solid #e2e8f0;
                              border-radius:4px;padding:4px 12px;font-size:11px;
                              color:#94a3b8;margin-left:8px;">
                    <?php echo esc_html( $label ); ?> — Email Preview
                </span>
            </div>

            <!-- Iframe -->
            <iframe id="smliser-template-preview"
                    style="width:100%;border:none;display:block;min-height:600px;
                           background:#f4f6f8;"
                    title="<?php echo esc_attr( $label ); ?> Preview">
            </iframe>

        </div>
    </div>

</div>

<script>
( function() {
    // Write preview HTML into the iframe — avoids same-origin issues
    // with srcdoc while keeping the full rendered HTML intact.
    const iframe = document.getElementById( 'smliser-template-preview' );
    const doc    = iframe.contentDocument || iframe.contentWindow.document;

    doc.open();
    doc.write( <?php echo wp_json_encode( $preview_html ); ?> );
    doc.close();

    // Auto-adjust iframe height to content.
    iframe.onload = function() {
        iframe.style.height = iframe.contentDocument.body.scrollHeight + 'px';
    };

    // Reset to default handler.
    const resetBtn = document.getElementById( 'smliser-reset-template' );
    if ( resetBtn ) {
        resetBtn.addEventListener( 'click', async function() {
            const confirmed = await SmliserModal.confirm(
                'This will permanently remove your custom template and restore the system default. Are you sure?',
                'Reset Template'
            );

            if ( ! confirmed ) return;

            const key      = this.dataset.key;
            const url      = new URL( smliser_var.ajaxURL );
            const payLoad  = new FormData;

            payLoad.set( 'action',       'smliser_reset_email_template' );
            payLoad.set( 'security',     smliser_var.nonce );
            payLoad.set( 'template_key', key );

            this.disabled = true;

            smliserFetchJSON( url, {
                method:      'POST',
                credentials: 'same-origin',
                body:        payLoad,
            } ).then( async response => {
                if ( response.success ) {
                    await SmliserModal.success( response?.data?.message || 'Template reset successfully.' );
                    window.location.reload();
                }
            } ).catch( err => {
                SmliserModal.error( err.message, 'Error Occurred' );
            } ).finally( () => {
                this.disabled = false;
            } );
        } );
    }

} )();
</script>