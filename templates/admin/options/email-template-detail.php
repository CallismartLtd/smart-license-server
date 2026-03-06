<?php
/**
 * Email template editor page.
 *
 * Full-page visual editor for a single email template type.
 * Replaces the WordPress admin layout entirely — rendered as a
 * standalone HTML document so the editor can occupy the full viewport.
 *
 * Variables available from OptionsPage::email_template_detail():
 *   $entry        — array   from EmailTemplateRegistry::entry()
 *   $preview      — EmailTemplate instance
 *   $preview_html — string  rendered HTML of the system default template
 * @var \SmartLicenseServer\Core\URL $current_url
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

defined( 'SMLISER_ABSPATH' ) || exit;

$key        = $entry['key'];
$label      = $entry['label'];
$is_enabled = $entry['is_enabled'];
$has_custom = $entry['has_custom'];
$back_url   = $current_url->remove_query_param( 'template', 'noheader' );

$assets = ( new \SmartLicenseServer\Environments\WordPress\ScriptManager() )->get_editor_assets();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ); ?> — Email Editor</title>

    <?php foreach ( $assets['styles'] as $style ) : ?>
        <link rel="stylesheet"
              href="<?php echo htmlspecialchars( $style['src'], ENT_QUOTES, 'UTF-8' ); ?>"
              id="<?php echo htmlspecialchars( $style['handle'], ENT_QUOTES, 'UTF-8' ); ?>-css">
    <?php endforeach; ?>

    <script>
        /**
         * Email editor bootstrap data.
         *
         * Populated server-side from the EmailTemplate instance and
         * EmailTemplateRegistry entry. The editor JS reads this object
         * on init — no AJAX round trip needed to get initial state.
         *
         * ajaxURL and nonce are embedded here because smliser_var is
         * printed by wp_localize_script which only fires inside the
         * normal WordPress admin shell — not on this standalone page.
         */
        window.smliserEmailEditor = {
            key:        <?php echo json_encode( $key ); ?>,
            label:      <?php echo json_encode( $label ); ?>,
            blocks:     <?php echo json_encode( $preview->get_blocks() ); ?>,
            styles:     <?php echo json_encode( $preview->resolve_styles() ); ?>,
            isEnabled:  <?php echo json_encode( $is_enabled ); ?>,
            hasCustom:  <?php echo json_encode( $has_custom ); ?>,
            backURL:    <?php echo json_encode( $back_url ); ?>,
            previewHTML:<?php echo json_encode( $preview_html ); ?>,
            ajaxURL:    <?php echo json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
            nonce:      <?php echo json_encode( wp_create_nonce( 'smliser_nonce' ) ); ?>,
        };
    </script>
</head>
<body class="smliser-editor-page">

<div class="smliser-email-editor" id="smliser-email-editor">

    <!-- =====================================================================
         TOOLBAR
    ====================================================================== -->
    <div class="smliser-editor-toolbar" id="smliser-editor-toolbar">

        <!-- Left — back link + template name + status badges -->
        <div class="smliser-editor-toolbar__left">

            <a href="<?php echo htmlspecialchars( $back_url->get_href(), ENT_QUOTES, 'UTF-8' ); ?>"
               class="smliser-editor-back"
               id="smliser-editor-back"
               title="Back to Email Templates">
                <span class="ti ti-arrow-left"></span>
            </a>

            <div class="smliser-editor-toolbar__meta">
                <span class="smliser-editor-toolbar__name">
                    <?php echo htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ); ?>
                </span>

                <span class="smliser-editor-badge <?php echo $is_enabled
                        ? 'smliser-editor-badge--enabled'
                        : 'smliser-editor-badge--disabled'; ?>"
                      id="smliser-status-badge">
                    <span class="smliser-editor-badge__dot"></span>
                    <span id="smliser-status-label">
                        <?php echo $is_enabled ? 'Enabled' : 'Disabled'; ?>
                    </span>
                </span>

                <span class="smliser-editor-badge smliser-editor-badge--custom"
                      id="smliser-custom-badge"
                      style="<?php echo $has_custom ? '' : 'display:none;'; ?>">
                    Custom
                </span>

                <span class="smliser-editor-badge smliser-editor-badge--unsaved"
                      id="smliser-unsaved-badge"
                      style="display:none;">
                    Unsaved changes
                </span>
            </div>

        </div>

        <!-- Right — action buttons -->
        <div class="smliser-editor-toolbar__right">

            <!-- Enable / Disable -->
            <button type="button"
                    class="smliser-editor-btn smliser-editor-btn--ghost"
                    id="smliser-toggle-btn"
                    data-key="<?php echo htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ); ?>"
                    data-enabled="<?php echo $is_enabled ? '1' : '0'; ?>">
                <span class="ti <?php echo $is_enabled ? 'ti-eye-off' : 'ti-eye'; ?>"
                      id="smliser-toggle-icon"></span>
                <span id="smliser-toggle-label">
                    <?php echo $is_enabled ? 'Disable' : 'Enable'; ?>
                </span>
            </button>

            <!-- Reset to default -->
            <button type="button"
                    class="smliser-editor-btn smliser-editor-btn--danger"
                    id="smliser-reset-btn"
                    data-key="<?php echo htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ); ?>"
                    <?php echo ! $has_custom ? 'disabled' : ''; ?>>
                <span class="ti ti-rotate"></span>
                Reset
            </button>

            <!-- Save -->
            <button type="button"
                    class="smliser-editor-btn smliser-editor-btn--primary"
                    id="smliser-save-btn"
                    data-key="<?php echo htmlspecialchars( $key, ENT_QUOTES, 'UTF-8' ); ?>">
                <span class="ti ti-cloud-upload" id="smliser-save-icon"></span>
                <span id="smliser-save-label">Save Template</span>
            </button>

        </div>
    </div>
    <!-- /TOOLBAR -->

    <!-- =====================================================================
         EDITOR BODY — sidebar + canvas
    ====================================================================== -->
    <div class="smliser-editor-layout">

        <!-- =================================================================
             SIDEBAR
        ================================================================== -->
        <aside class="smliser-editor-sidebar" id="smliser-editor-sidebar">

            <!-- Sidebar tabs -->
            <div class="smliser-sidebar-tabs">
                <button type="button"
                        class="smliser-sidebar-tab is-active"
                        data-tab="styles"
                        id="smliser-tab-styles">
                    <span class="ti ti-palette"></span>
                    Styles
                </button>
                <button type="button"
                        class="smliser-sidebar-tab"
                        data-tab="block"
                        id="smliser-tab-block">
                    <span class="ti ti-box"></span>
                    Block
                </button>
            </div>

            <!-- ---------------------------------------------------------
                 STYLES PANEL
            ---------------------------------------------------------- -->
            <div class="smliser-sidebar-panel is-active"
                 id="smliser-styles-panel"
                 data-panel="styles">

                <!-- Header -->
                <div class="smliser-style-group">
                    <div class="smliser-style-group__title">
                        <span class="ti ti-layout-navbar"></span>
                        Header
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Background</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="header_bg">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="header_bg"
                                   maxlength="7"
                                   placeholder="#1a1a2e">
                        </div>
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Text Color</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="header_color">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="header_color"
                                   maxlength="7"
                                   placeholder="#ffffff">
                        </div>
                    </div>
                </div>

                <!-- Body -->
                <div class="smliser-style-group">
                    <div class="smliser-style-group__title">
                        <span class="ti ti-layout-bottombar"></span>
                        Body
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Background</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="body_bg">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="body_bg"
                                   maxlength="7"
                                   placeholder="#f4f6f8">
                        </div>
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Primary Text</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="text_primary">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="text_primary"
                                   maxlength="7"
                                   placeholder="#334155">
                        </div>
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Muted Text</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="text_muted">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="text_muted"
                                   maxlength="7"
                                   placeholder="#64748b">
                        </div>
                    </div>
                </div>

                <!-- Accent -->
                <div class="smliser-style-group">
                    <div class="smliser-style-group__title">
                        <span class="ti ti-color-swatch"></span>
                        Accent Color
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Buttons &amp; Links</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="accent">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="accent"
                                   maxlength="7"
                                   placeholder="#6366f1">
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="smliser-style-group">
                    <div class="smliser-style-group__title">
                        <span class="ti ti-layout-navbar-inactive"></span>
                        Footer
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Background</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="footer_bg">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="footer_bg"
                                   maxlength="7"
                                   placeholder="#f8fafc">
                        </div>
                    </div>
                    <div class="smliser-style-row">
                        <label class="smliser-style-label">Text Color</label>
                        <div class="smliser-color-field">
                            <input type="color"
                                   class="smliser-color-picker"
                                   data-style="footer_color">
                            <input type="text"
                                   class="smliser-color-hex"
                                   data-style="footer_color"
                                   maxlength="7"
                                   placeholder="#94a3b8">
                        </div>
                    </div>
                </div>

            </div>
            <!-- /STYLES PANEL -->

            <!-- ---------------------------------------------------------
                 BLOCK EDITOR PANEL
            ---------------------------------------------------------- -->
            <div class="smliser-sidebar-panel"
                 id="smliser-block-panel"
                 data-panel="block">

                <!-- Empty state — shown when no block is selected -->
                <div class="smliser-block-editor-empty" id="smliser-block-editor-empty">
                    <span class="ti ti-cursor-text" style="font-size:40px;color:#e2e8f0;"></span>
                    <p>Click a block on the canvas to edit it here.</p>
                </div>

                <!-- Block editor fields — shown when a block is selected -->
                <div class="smliser-block-editor-fields"
                     id="smliser-block-editor-fields"
                     style="display:none;">

                    <!-- Block type header + close -->
                    <div class="smliser-block-editor__header">
                        <span class="smliser-block-editor__type" id="smliser-active-block-type"></span>
                        <button type="button"
                                class="smliser-block-editor__close"
                                id="smliser-block-editor-close"
                                title="Close">
                            <span class="ti ti-x"></span>
                        </button>
                    </div>

                    <!-- Content — greeting, text, banner, closing -->
                    <div class="smliser-field-group" id="smliser-field-content" style="display:none;">
                        <label class="smliser-field-label">Content</label>
                        <textarea class="smliser-field-textarea"
                                  id="smliser-block-content"
                                  rows="5"
                                  placeholder="Block content..."></textarea>
                        <p class="smliser-field-help">
                            Use <code>{{token}}</code> placeholders for dynamic values.
                        </p>
                    </div>

                    <!-- Tone — banner only -->
                    <div class="smliser-field-group" id="smliser-field-tone" style="display:none;">
                        <label class="smliser-field-label">Tone</label>
                        <div class="smliser-tone-picker" id="smliser-tone-picker">
                            <button type="button" class="smliser-tone-btn" data-tone="success">
                                <span class="smliser-tone-dot smliser-tone-dot--success"></span>
                                Success
                            </button>
                            <button type="button" class="smliser-tone-btn" data-tone="warning">
                                <span class="smliser-tone-dot smliser-tone-dot--warning"></span>
                                Warning
                            </button>
                            <button type="button" class="smliser-tone-btn" data-tone="error">
                                <span class="smliser-tone-dot smliser-tone-dot--error"></span>
                                Error
                            </button>
                            <button type="button" class="smliser-tone-btn" data-tone="info">
                                <span class="smliser-tone-dot smliser-tone-dot--info"></span>
                                Info
                            </button>
                        </div>
                    </div>

                    <!-- Button label + URL — button block only -->
                    <div class="smliser-field-group" id="smliser-field-button-label" style="display:none;">
                        <label class="smliser-field-label">Button Label</label>
                        <input type="text"
                               class="smliser-field-input"
                               id="smliser-block-button-label"
                               placeholder="Click Here">
                    </div>

                    <div class="smliser-field-group" id="smliser-field-button-url" style="display:none;">
                        <label class="smliser-field-label">Button URL</label>
                        <input type="text"
                               class="smliser-field-input"
                               id="smliser-block-button-url"
                               placeholder="{{reset_url}}">
                        <p class="smliser-field-help">
                            Use a <code>{{token}}</code> or a full URL.
                        </p>
                    </div>

                    <!-- Detail card rows -->
                    <div class="smliser-field-group" id="smliser-field-rows" style="display:none;">
                        <label class="smliser-field-label">Rows</label>
                        <div class="smliser-card-rows" id="smliser-card-rows"></div>
                        <p class="smliser-field-help">
                            Labels and values support <code>{{token}}</code> placeholders.
                        </p>
                    </div>

                </div>
                <!-- /Block editor fields -->

            </div>
            <!-- /BLOCK EDITOR PANEL -->

        </aside>
        <!-- /SIDEBAR -->

        <!-- =================================================================
             CANVAS — block list + preview
        ================================================================== -->
        <div class="smliser-editor-canvas" id="smliser-editor-canvas">

            <!-- Block list -->
            <div class="smliser-block-list-wrap">
                <div class="smliser-block-list-header">
                    <span class="ti ti-layout-list"></span>
                    Blocks
                    <span class="smliser-block-list-hint">
                        Drag to reorder &nbsp;·&nbsp; Click to edit
                    </span>
                </div>
                <div class="smliser-block-list" id="smliser-block-list">
                    <!-- Populated by BlockCanvas.render() -->
                </div>
            </div>

            <!-- Preview pane -->
            <div class="smliser-preview-wrap">
                <div class="smliser-preview-header">
                    <span class="ti ti-eye"></span>
                    Preview
                    <span class="smliser-preview-note">
                        Placeholder values are used — live data resolves on send.
                    </span>
                    <div class="smliser-preview-loader" id="smliser-preview-loader" style="display:none;">
                        <span class="smliser-spinner"></span>
                        Updating preview...
                    </div>
                </div>

                <!-- Fake browser chrome -->
                <div class="smliser-preview-browser">
                    <div class="smliser-preview-browser__chrome">
                        <span class="smliser-preview-browser__dot smliser-preview-browser__dot--red"></span>
                        <span class="smliser-preview-browser__dot smliser-preview-browser__dot--yellow"></span>
                        <span class="smliser-preview-browser__dot smliser-preview-browser__dot--green"></span>
                        <span class="smliser-preview-browser__bar">
                            <?php echo htmlspecialchars( $label, ENT_QUOTES, 'UTF-8' ); ?> — Preview
                        </span>
                    </div>
                    <iframe id="smliser-preview-frame"
                            class="smliser-preview-frame"
                            title="Email Preview">
                    </iframe>
                </div>
            </div>

        </div>
        <!-- /CANVAS -->

    </div>
    <!-- /EDITOR LAYOUT -->

</div>
<!-- /EMAIL EDITOR -->

<?php foreach ( $assets['scripts'] as $script ) : ?>
    <script src="<?php echo htmlspecialchars( $script['src'], ENT_QUOTES, 'UTF-8' ); ?>"
            id="<?php echo htmlspecialchars( $script['handle'], ENT_QUOTES, 'UTF-8' ); ?>-js">
    </script>
<?php endforeach; ?>

</body>
</html>
<?php exit; ?>