<?php
/**
 * Abstract email template base class.
 *
 * Responsible for the HTML skeleton of all outgoing system emails.
 * Subclasses provide the body content, block structure, and subject line.
 * Custom templates stored via the template editor override the system
 * default skeleton transparently.
 *
 * ## Rendering pipeline
 *
 * On a normal send:
 *   to_message() → render() → resolve_custom_template()
 *                           ↓ (no custom)
 *                         skeleton( body() )
 *                           ├── render_header()
 *                           ├── body content
 *                           └── render_footer()
 *
 * On an editor save / live preview:
 *   render_from_blocks( $blocks, $styles ) → skeleton( render_blocks( $blocks ) )
 *
 * ## Adding a new template type
 *
 * 1. Extend this class in the appropriate subdirectory namespace.
 * 2. Implement all abstract methods.
 * 3. Register the class in EmailTemplateRegistry::boot().
 *
 * @author Callistus Nwachukwu
 * @package SmartLicenseServer\Email\Templates
 * @since 0.2.0
 */

declare( strict_types=1 );

namespace SmartLicenseServer\Email\Templates;

use SmartLicenseServer\Email\EmailMessage;

defined( 'SMLISER_ABSPATH' ) || exit;

abstract class EmailTemplate {

    /**
     * Resolved style tokens for the current render cycle.
     *
     * Populated by render() and render_from_blocks() before any
     * region or block renderer method is called. All region methods
     * (render_header, render_footer, render_*_block) read their
     * color and style values from this property — never from
     * hardcoded strings — so that editor style overrides propagate
     * automatically through the entire rendered output.
     *
     * @var array<string, string>
     */
    private array $styles = [];

    // =========================================================================
    // Abstract interface — subclasses MUST implement all of the following
    // =========================================================================

    /**
     * The unique string key identifying this template type.
     *
     * Used as the storage key suffix for custom templates and enable/disable
     * state, and as the identifier throughout the registry and editor UI.
     *
     * Convention: lowercase snake_case, e.g. 'license_issued', 'welcome'.
     * Must be unique across all registered templates.
     *
     * @return string
     */
    abstract public static function template_key(): string;

    /**
     * The email subject line shown in the recipient's inbox.
     *
     * May reference instance data (e.g. days remaining, app name) but
     * must not contain raw HTML. Keep it concise — most email clients
     * truncate subjects beyond ~60 characters.
     *
     * @return string
     */
    abstract protected function subject(): string;

    /**
     * The recipient's email address for this specific send.
     *
     * Typically sourced from the constructor argument (e.g. `$this->to`).
     * Used by to_message() to populate the EmailMessage DTO and exposed
     * as the {{recipient}} token in variables().
     *
     * @return string
     */
    abstract protected function recipient(): string;

    /**
     * The inner body HTML content for this email.
     *
     * Should return only the content region HTML — no doctype, no
     * <html>, no skeleton wrapper. The base class wraps this output
     * in the full email skeleton via skeleton().
     *
     * For system default rendering this method is called directly.
     * For editor-customised rendering, render_from_blocks() is called
     * instead and this method is bypassed.
     *
     * Best practice: build the body by composing the render_*_block()
     * helpers rather than writing raw HTML, so the block renderers
     * and body() stay visually consistent:
     *
     *   protected function body(): string {
     *       $this->styles = $this->resolve_styles();
     *       return $this->render_greeting_block( [ 'content' => 'Hi {{licensee_name}},' ] )
     *           . $this->render_text_block( [ 'content' => 'Your license has been issued.' ] )
     *           . $this->render_detail_card_block( [ 'rows' => [ ... ] ] );
     *   }
     *
     * @return string
     */
    abstract protected function body(): string;

    /**
     * Human-readable label for this template type.
     *
     * Displayed in the template list UI and the editor page heading.
     * Should be short and descriptive, e.g. 'License Issued',
     * 'Password Reset', 'System Alert'.
     *
     * @return string
     */
    abstract public function label(): string;

    /**
     * One-sentence description of when this email is sent.
     *
     * Displayed beneath the label in the template list UI to help
     * administrators understand the purpose of each template without
     * opening it. e.g. 'Sent when a new license is created and
     * assigned to a licensee.'
     *
     * @return string
     */
    abstract public function description(): string;

    /**
     * Return a fully constructed preview instance of this template.
     *
     * Used by the registry and editor to render the template without
     * requiring real domain objects. Implementations should use
     * Model::from_array() with realistic placeholder values so the
     * preview output looks representative of a real email:
     *
     *   public static function preview(): static {
     *       $license = License::from_array( [
     *           'licensee_fullname' => 'Jane Doe',
     *           'license_key'       => 'SMLISER-XXXX-XXXX-XXXX',
     *           'end_date'          => gmdate( 'Y-m-d', strtotime( '+1 year' ) ),
     *       ] );
     *       return new static( $license, 'preview@example.com' );
     *   }
     *
     * @return static
     */
    abstract public static function preview(): static;

    /**
     * Return the structured block data that describes the body of this email.
     *
     * The editor uses this array to build the drag-and-drop block canvas.
     * Each element is an associative array describing one content block.
     *
     * ## Supported block types and their required keys:
     *
     * **greeting** — Opening salutation line.
     *   [ 'type' => 'greeting', 'content' => 'Hi {{licensee_name}},' ]
     *
     * **text** — A plain paragraph of body copy.
     *   [ 'type' => 'text', 'content' => 'Your license has been issued.' ]
     *
     * **banner** — A coloured alert/notice box.
     *   [ 'type' => 'banner', 'content' => 'Expires in {{days_left}} days.',
     *     'tone' => 'warning' ]
     *   Tone values: 'success' | 'warning' | 'error' | 'info'
     *
     * **detail_card** — A structured table of label/value pairs.
     *   [ 'type' => 'detail_card', 'rows' => [
     *       [ 'label' => 'License Key', 'value' => '{{license_key}}' ],
     *       [ 'label' => 'Expiry Date', 'value' => '{{end_date}}' ],
     *   ] ]
     *   Rows are rendered two per table row. Odd rows span full width.
     *
     * **button** — A centred CTA button.
     *   [ 'type' => 'button', 'label' => 'Reset My Password',
     *     'url' => '{{reset_url}}' ]
     *
     * **closing** — The support contact paragraph at the bottom.
     *   [ 'type' => 'closing',
     *     'content' => 'Questions? Contact us at {{support_email}}.' ]
     *   {{support_email}} is automatically converted to a mailto link.
     *
     * ## Common optional keys (all block types):
     *   'id'       => string  — stable identifier for the editor (recommended)
     *   'visible'  => bool    — false hides the block (default: true)
     *   'removable'=> bool    — whether the editor allows deletion (default: true)
     *   'editable' => bool    — whether the editor allows content editing (default: true)
     *
     * ## Example implementation:
     *
     *   public function get_blocks(): array {
     *       return [
     *           [ 'id' => 'greeting',    'type' => 'greeting',
     *             'content'  => 'Hi {{licensee_name}},',
     *             'editable' => true, 'removable' => false ],
     *
     *           [ 'id' => 'intro',       'type' => 'text',
     *             'content'  => 'Your license has been issued.' ],
     *
     *           [ 'id' => 'details',     'type' => 'detail_card',
     *             'rows'     => [
     *                 [ 'label' => 'License Key', 'value' => '{{license_key}}' ],
     *                 [ 'label' => 'Expiry Date', 'value' => '{{end_date}}' ],
     *             ] ],
     *
     *           [ 'id' => 'closing',     'type' => 'closing',
     *             'content'  => 'Questions? Contact us at {{support_email}}.',
     *             'editable' => true, 'removable' => false ],
     *       ];
     *   }
     *
     * @return array<int, array<string, mixed>>
     */
    abstract public function get_blocks(): array;

    // =========================================================================
    // Style tokens — global design values editable via the editor sidebar
    // =========================================================================

    /**
     * Return the default style tokens for this template.
     *
     * These tokens drive every color and shadow value in the rendered
     * output. The editor sidebar exposes them as controls and merges
     * user changes on top via resolve_styles() before each render.
     *
     * Subclasses may override individual tokens to change defaults for
     * their specific template type — e.g. a system alert template might
     * default header_bg to a darker red. Always call parent::get_styles()
     * and merge on top rather than replacing entirely:
     *
     *   public function get_styles(): array {
     *       return array_merge( parent::get_styles(), [
     *           'header_bg' => '#7f1d1d',
     *       ] );
     *   }
     *
     * ## Available tokens:
     *   header_bg     — Header region background color.
     *   header_color  — Header text (app name) color.
     *   body_bg       — Page background color behind the email card.
     *   card_bg       — Email card background color.
     *   card_shadow   — Email card box-shadow value.
     *   accent        — Link color, button background, and highlight color.
     *   text_primary  — Primary body text color.
     *   text_muted    — Secondary / helper text color.
     *   footer_bg     — Footer region background color.
     *   footer_color  — Footer text color.
     *   footer_border — Footer top border color.
     *
     * @return array<string, string>
     */
    public function get_styles(): array {
        return [
            'header_bg'     => '#1a1a2e',
            'header_color'  => '#ffffff',
            'body_bg'       => '#f4f6f8',
            'card_bg'       => '#ffffff',
            'card_shadow'   => '0 2px 8px rgba(0,0,0,0.06)',
            'accent'        => '#6366f1',
            'text_primary'  => '#334155',
            'text_muted'    => '#64748b',
            'footer_bg'     => '#f8fafc',
            'footer_color'  => '#94a3b8',
            'footer_border' => '#e2e8f0',
        ];
    }

    /**
     * Merge the class default styles with any editor overrides.
     *
     * Called internally by render() and render_from_blocks() to
     * produce the final resolved token map before any region or
     * block renderer runs. Unknown keys in $overrides are ignored
     * by array_merge semantics — only recognised token keys have
     * any effect on the rendered output.
     *
     * @param  array<string, string> $overrides Style tokens from the editor.
     * @return array<string, string>            Fully resolved token map.
     */
    final public function resolve_styles( array $overrides = [] ): array {
        return array_merge( $this->get_styles(), $overrides );
    }

    // =========================================================================
    // Token variables — placeholder replacement
    // =========================================================================

    /**
     * Return the token-to-value map for placeholder replacement.
     *
     * Tokens are replaced throughout the final HTML output — in both
     * system-rendered and editor-customised templates — via interpolate().
     * This ensures {{app_name}}, {{support_email}} etc. always resolve
     * to live settings values at send time, even in saved custom templates.
     *
     * Subclasses must override this method to add their own tokens and
     * always call parent::variables() to include the base set:
     *
     *   protected function variables(): array {
     *       return array_merge( parent::variables(), [
     *           '{{licensee_name}}' => $this->license->get_licensee_fullname(),
     *           '{{license_key}}'   => $this->license->get_license_key(),
     *       ] );
     *   }
     *
     * ## Base tokens always available:
     *   {{app_name}}      — Repository name from system settings.
     *   {{support_email}} — Support email from system settings.
     *   {{year}}          — Current four-digit year.
     *   {{recipient}}     — Value of recipient().
     *
     * @return array<string, string> Token => resolved value map.
     */
    protected function variables(): array {
        $settings = smliser_settings_adapter();

        return [
            '{{app_name}}'      => (string) $settings->get( 'repository_name', SMLISER_APP_NAME, true ),
            '{{support_email}}' => (string) $settings->get( 'support_email', '', true ),
            '{{year}}'          => gmdate( 'Y' ),
            '{{recipient}}'     => $this->recipient(),
        ];
    }

    /**
     * Replace all registered tokens in an HTML string with their values.
     *
     * Called at the end of every render path to resolve tokens in the
     * final assembled HTML. Works identically for system-rendered and
     * editor-customised templates so token replacement is always applied
     * regardless of which render path produced the HTML.
     *
     * @param  string $html Raw HTML string containing {{token}} placeholders.
     * @return string       HTML with all tokens replaced by their values.
     */
    protected function interpolate( string $html ): string {
        return str_replace(
            array_keys( $this->variables() ),
            array_values( $this->variables() ),
            $html
        );
    }

    // =========================================================================
    // Rendering pipeline
    // =========================================================================

    /**
     * Render the final HTML for this email ready for sending.
     *
     * Resolution order:
     * 1. If a custom template has been saved via the editor,
     *    interpolate its tokens and return it directly.
     * 2. Otherwise resolve default styles, call body() to get
     *    the content HTML, and wrap it in skeleton().
     *
     * Marked final — subclasses must not override this method.
     * To customise rendered output, override body(), render_header(),
     * render_footer(), or get_styles() instead.
     *
     * @return string Complete, self-contained HTML email document.
     */
    final public function render(): string {
        $custom = $this->resolve_custom_template();

        if ( $custom !== null ) {
            return $this->interpolate( $custom );
        }

        $this->styles = $this->resolve_styles();

        return $this->skeleton( $this->body() );
    }

    /**
     * Render the email from editor block data and style overrides.
     *
     * Called by the editor save handler and the live preview AJAX
     * endpoint. Reconstructs the full HTML from the structured block
     * array so the saved custom template is a complete, self-contained
     * HTML string — no runtime block data needed to serve it later.
     *
     * Style overrides are merged on top of get_styles() defaults so
     * the editor only needs to send changed values, not the full map.
     *
     * Marked final — the block-to-HTML pipeline must not be bypassed.
     *
     * @param  array<int, array<string, mixed>> $blocks         Ordered block data from the editor.
     * @param  array<string, string>            $style_overrides Style token overrides from the editor.
     * @return string Complete, self-contained HTML email document.
     */
    final public function render_from_blocks( array $blocks, array $style_overrides = [] ): string {
        $this->styles = $this->resolve_styles( $style_overrides );

        return $this->skeleton( $this->render_blocks( $blocks ) );
    }

    /**
     * Render an ordered array of blocks into a body HTML string.
     *
     * Iterates the block array in order, skipping any block where
     * 'visible' is explicitly false, and dispatches each to its
     * dedicated renderer method. Unknown block types produce no output.
     *
     * Called internally by render_from_blocks(). May also be called
     * directly when building body() in a subclass.
     *
     * Marked final — block dispatch must not be overridden. To change
     * how a specific block type renders, override the corresponding
     * render_*_block() method.
     *
     * @param  array<int, array<string, mixed>> $blocks Ordered block data array.
     * @return string                                   Concatenated block HTML.
     */
    final public function render_blocks( array $blocks ): string {
        $html = '';

        foreach ( $blocks as $block ) {
            if ( isset( $block['visible'] ) && ! $block['visible'] ) {
                continue;
            }

            $html .= match( $block['type'] ) {
                'greeting'    => $this->render_greeting_block( $block ),
                'text'        => $this->render_text_block( $block ),
                'banner'      => $this->render_banner_block( $block ),
                'detail_card' => $this->render_detail_card_block( $block ),
                'button'      => $this->render_button_block( $block ),
                'closing'     => $this->render_closing_block( $block ),
                default       => '',
            };
        }

        return $html;
    }

    // =========================================================================
    // Block renderers — one method per supported block type
    // =========================================================================

    /**
     * Render a greeting block.
     *
     * Produces a bold salutation paragraph styled with text_primary.
     * Typically the first block in every template body.
     *
     * Expected block keys:
     *   'content' (string) — The greeting text, e.g. 'Hi {{licensee_name}},'
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_greeting_block( array $block ): string {
        $content = htmlspecialchars( $block['content'] ?? '', ENT_QUOTES, 'UTF-8' );
        $color   = $this->styles['text_primary'];

        return <<<HTML
        <p style="margin:0 0 24px;font-size:16px;font-weight:600;color:{$color};">
            {$content}
        </p>
        HTML;
    }

    /**
     * Render a text block.
     *
     * Produces a standard body paragraph styled with text_primary.
     * Use multiple text blocks for multi-paragraph introductions.
     *
     * Expected block keys:
     *   'content' (string) — The paragraph text.
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_text_block( array $block ): string {
        $content = htmlspecialchars( $block['content'] ?? '', ENT_QUOTES, 'UTF-8' );
        $color   = $this->styles['text_primary'];

        return <<<HTML
        <p style="margin:0 0 16px;font-size:15px;color:{$color};line-height:1.6;">
            {$content}
        </p>
        HTML;
    }

    /**
     * Render a banner block.
     *
     * Produces a full-width coloured alert box. The visual tone is
     * controlled by the 'tone' key and maps to a predefined color set:
     *
     *   success — green  — for confirmations and positive outcomes.
     *   warning — amber  — for expiry reminders and caution notices.
     *   error   — red    — for failures, suspensions, and urgent alerts.
     *   info    — blue   — for neutral informational notices (default).
     *
     * Expected block keys:
     *   'content' (string) — The banner message text.
     *   'tone'    (string) — 'success' | 'warning' | 'error' | 'info'
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_banner_block( array $block ): string {
        $content = htmlspecialchars( $block['content'] ?? '', ENT_QUOTES, 'UTF-8' );
        $tone    = $block['tone'] ?? 'info';

        [ $bg, $border, $color, $icon ] = match( $tone ) {
            'success' => [ '#f0fdf4', '#bbf7d0', '#166534', '&#10003;' ],
            'warning' => [ '#fffbeb', '#fde68a', '#92400e', '&#9888;'  ],
            'error'   => [ '#fef2f2', '#fecaca', '#991b1b', '&#10060;' ],
            default   => [ '#eff6ff', '#bfdbfe', '#1e40af', '&#8505;'  ],
        };

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$bg};border:1px solid {$border};
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:16px 20px;">
                    <p style="margin:0;font-size:14px;font-weight:600;
                               color:{$color};line-height:1.5;">
                        {$icon}&nbsp; {$content}
                    </p>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * Render a detail card block.
     *
     * Produces a styled table card containing label/value data rows.
     * Rows are laid out two per row in a two-column grid. If the total
     * number of rows is odd the last row spans the full width.
     *
     * Expected block keys:
     *   'rows' (array) — Array of [ 'label' => string, 'value' => string ] pairs.
     *                    Token placeholders in values are resolved by interpolate().
     *
     * Example:
     *   'rows' => [
     *       [ 'label' => 'License Key', 'value' => '{{license_key}}' ],
     *       [ 'label' => 'Expiry Date', 'value' => '{{end_date}}' ],
     *   ]
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_detail_card_block( array $block ): string {
        $rows    = $block['rows'] ?? [];
        $card_bg = $this->styles['card_bg'];
        $color   = $this->styles['text_primary'];

        $rows_html = '';

        foreach ( array_chunk( $rows, 2 ) as $pair ) {
            $rows_html .= '<tr>';

            foreach ( $pair as $index => $row ) {
                $label = htmlspecialchars( $row['label'] ?? '', ENT_QUOTES, 'UTF-8' );
                $value = htmlspecialchars( $row['value'] ?? '', ENT_QUOTES, 'UTF-8' );
                $pad   = $index === 0 && count( $pair ) === 2
                    ? 'padding:0 16px 16px 0;'
                    : 'padding:0 0 16px;';

                $rows_html .= <<<HTML
                <td width="50%" style="{$pad}vertical-align:top;">
                    <p style="margin:0 0 4px;font-size:11px;font-weight:700;
                               text-transform:uppercase;letter-spacing:0.08em;color:#94a3b8;">
                        {$label}
                    </p>
                    <p style="margin:0;font-size:14px;color:{$color};font-weight:600;">
                        {$value}
                    </p>
                </td>
                HTML;
            }

            if ( count( $pair ) === 1 ) {
                $rows_html .= '<td width="50%"></td>';
            }

            $rows_html .= '</tr>';
        }

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="background-color:{$card_bg};border:1px solid #e2e8f0;
                      border-radius:8px;margin:0 0 24px;">
            <tr>
                <td style="padding:24px;">
                    <table role="presentation" width="100%" cellpadding="0"
                           cellspacing="0" border="0">
                        {$rows_html}
                    </table>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * Render a button block.
     *
     * Produces a centred CTA button styled with the accent color token.
     * The button URL should typically be a token placeholder that resolves
     * to a real URL at send time via interpolate().
     *
     * Expected block keys:
     *   'label' (string) — Button text,  e.g. 'Reset My Password'.
     *   'url'   (string) — Button href,  e.g. '{{reset_url}}'.
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_button_block( array $block ): string {
        $label  = htmlspecialchars( $block['label'] ?? 'Click Here', ENT_QUOTES, 'UTF-8' );
        $url    = htmlspecialchars( $block['url']   ?? '#',           ENT_QUOTES, 'UTF-8' );
        $accent = $this->styles['accent'];

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
               style="margin:0 0 24px;">
            <tr>
                <td align="center">
                    <a href="{$url}"
                       style="display:inline-block;padding:14px 32px;
                              background-color:{$accent};color:#ffffff;
                              font-size:15px;font-weight:700;text-decoration:none;
                              border-radius:8px;letter-spacing:0.01em;">
                        {$label}
                    </a>
                </td>
            </tr>
        </table>
        HTML;
    }

    /**
     * Render a closing block.
     *
     * Produces the support contact paragraph that appears at the bottom
     * of every email. Styled with text_muted. The {{support_email}} token
     * is automatically converted to a live mailto anchor using the accent
     * color — it does not need to be manually linked in the content string.
     *
     * Expected block keys:
     *   'content' (string) — The closing paragraph text. Should include
     *                        {{support_email}} where the linked address
     *                        should appear.
     *
     * Example:
     *   'content' => 'Questions? Contact us at {{support_email}}.'
     *
     * @param  array<string, mixed> $block Block data array.
     * @return string
     */
    protected function render_closing_block( array $block ): string {
        $content = htmlspecialchars( $block['content'] ?? '', ENT_QUOTES, 'UTF-8' );
        $color   = $this->styles['text_muted'];
        $accent  = $this->styles['accent'];

        $vars    = $this->variables();
        $support = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );

        // Convert the support email token to a live mailto link.
        $content = str_replace(
            '{{support_email}}',
            "<a href=\"mailto:{$support}\" style=\"color:{$accent};text-decoration:none;\">{$support}</a>",
            $content
        );

        return <<<HTML
        <p style="margin:0 0 16px;font-size:14px;color:{$color};line-height:1.6;">
            {$content}
        </p>
        HTML;
    }

    // =========================================================================
    // Skeleton regions — independently overridable by subclasses
    // =========================================================================

    /**
     * Wrap body content in the full HTML email skeleton.
     *
     * Assembles the complete email document by calling render_header()
     * and render_footer() as independently overridable region methods,
     * then wraps everything in the outer table structure.
     *
     * Uses inline CSS throughout — <style> blocks and external
     * stylesheets are stripped by most email clients.
     *
     * All color values are read from $this->styles which must be
     * populated by the calling render method before skeleton() runs.
     * Do not call skeleton() directly — use render() or render_from_blocks().
     *
     * @param  string $body_content Inner body HTML from body() or render_blocks().
     * @return string               Complete HTML email document.
     */
    protected function skeleton( string $body_content ): string {
        $subject   = htmlspecialchars( $this->subject(),    ENT_QUOTES, 'UTF-8' );
        $preheader = htmlspecialchars( $this->preheader(),  ENT_QUOTES, 'UTF-8' );
        $header    = $this->render_header();
        $footer    = $this->render_footer();
        $body_bg   = $this->styles['body_bg']    ?? '#f4f6f8';
        $card_bg   = $this->styles['card_bg']    ?? '#ffffff';
        $shadow    = $this->styles['card_shadow'] ?? '0 2px 8px rgba(0,0,0,0.06)';

        $html = <<<HTML
        <!DOCTYPE html>
        <html lang="en" xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta name="format-detection" content="telephone=no">
            <title>{$subject}</title>
            <!--[if mso]>
            <noscript>
                <xml><o:OfficeDocumentSettings>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings></xml>
            </noscript>
            <![endif]-->
        </head>
        <body style="margin:0;padding:0;background-color:{$body_bg};
                     font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;">

            <!-- Preheader — hidden preview text shown in inbox before opening -->
            <div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">
                {$preheader}
                &nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
            </div>

            <!-- Outer wrapper -->
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="background-color:{$body_bg};min-width:100%;">
                <tr>
                    <td align="center" style="padding:32px 16px;">

                        <!-- Email card -->
                        <table role="presentation" width="100%" cellpadding="0"
                               cellspacing="0" border="0"
                               style="max-width:600px;background-color:{$card_bg};
                                      border-radius:8px;box-shadow:{$shadow};
                                      overflow:hidden;">

                            {$header}

                            <!-- Body -->
                            <tr>
                                <td style="padding:40px 40px 32px;">
                                    {$body_content}
                                </td>
                            </tr>

                            {$footer}

                        </table>
                        <!-- /Email card -->

                    </td>
                </tr>
            </table>
            <!-- /Outer wrapper -->

        </body>
        </html>
        HTML;

        return $this->interpolate( $html );
    }

    /**
     * Render the email header region.
     *
     * Produces the dark banner at the top of the email card containing
     * the app name. Colors are read from the header_bg and header_color
     * style tokens so the editor sidebar controls update this region.
     *
     * Subclasses may override this method to add a logo image or change
     * the header layout entirely. When overriding, always read colors
     * from $this->styles rather than hardcoding values so the editor
     * style controls remain effective:
     *
     *   protected function render_header(): string {
     *       $bg    = $this->styles['header_bg'];
     *       $color = $this->styles['header_color'];
     *       // custom markup...
     *   }
     *
     * @return string Header <tr> HTML ready to be placed inside the email card table.
     */
    protected function render_header(): string {
        $vars     = $this->variables();
        $app_name = htmlspecialchars( $vars['{{app_name}}'], ENT_QUOTES, 'UTF-8' );
        $bg       = $this->styles['header_bg']    ?? '#1a1a2e';
        $color    = $this->styles['header_color'] ?? '#ffffff';

        return <<<HTML
        <tr>
            <td align="center" style="background-color:{$bg};padding:28px 40px;">
                <span style="font-size:20px;font-weight:700;color:{$color};
                             letter-spacing:-0.02em;text-decoration:none;">
                    {$app_name}
                </span>
            </td>
        </tr>
        HTML;
    }

    /**
     * Render the email footer region.
     *
     * Produces the light-background footer containing the copyright line
     * and support email link. Colors are read from the footer_bg,
     * footer_color, footer_border, and accent style tokens.
     *
     * The support email link is only rendered when a support_email value
     * is present in system settings. Subclasses may override this method
     * to add unsubscribe links, social icons, or a physical address.
     * When overriding, always read colors from $this->styles:
     *
     *   protected function render_footer(): string {
     *       $bg     = $this->styles['footer_bg'];
     *       $color  = $this->styles['footer_color'];
     *       $accent = $this->styles['accent'];
     *       // custom markup...
     *   }
     *
     * @return string Footer <tr> HTML ready to be placed inside the email card table.
     */
    protected function render_footer(): string {
        $vars     = $this->variables();
        $app_name = htmlspecialchars( $vars['{{app_name}}'],      ENT_QUOTES, 'UTF-8' );
        $year     = htmlspecialchars( $vars['{{year}}'],          ENT_QUOTES, 'UTF-8' );
        $support  = htmlspecialchars( $vars['{{support_email}}'], ENT_QUOTES, 'UTF-8' );
        $bg       = $this->styles['footer_bg']     ?? '#f8fafc';
        $color    = $this->styles['footer_color']  ?? '#94a3b8';
        $border   = $this->styles['footer_border'] ?? '#e2e8f0';
        $accent   = $this->styles['accent']        ?? '#6366f1';

        $support_line = ! empty( $support )
            ? <<<SUPPORT
            <p style="margin:0;">
                Need help? Contact us at
                <a href="mailto:{$support}"
                   style="color:{$accent};text-decoration:none;">{$support}</a>
            </p>
            SUPPORT
            : '';

        return <<<HTML
        <tr>
            <td style="background-color:{$bg};padding:24px 40px;
                       border-top:1px solid {$border};">
                <table role="presentation" width="100%" cellpadding="0"
                       cellspacing="0" border="0">
                    <tr>
                        <td style="font-size:12px;color:{$color};line-height:1.6;">
                            <p style="margin:0 0 4px 0;">
                                &copy; {$year} {$app_name}. All rights reserved.
                            </p>
                            {$support_line}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        HTML;
    }

    /**
     * Preheader text shown as the inbox preview line before the email is opened.
     *
     * Rendered as hidden text at the top of the email body — visible in
     * inbox list views but not in the opened email itself. Most email
     * clients show approximately 90–140 characters of preheader text.
     *
     * Subclasses should override this to provide a meaningful one-line
     * summary that complements the subject line rather than repeating it:
     *
     *   protected function preheader(): string {
     *       return "Your license expires in {$this->days_left} day(s). Renew now.";
     *   }
     *
     * Defaults to the subject line if not overridden.
     *
     * @return string Plain text preheader — no HTML tags.
     */
    protected function preheader(): string {
        return $this->subject();
    }

    // =========================================================================
    // Custom template storage and state management
    // =========================================================================

    /**
     * Look up any custom template HTML stored for this template type.
     *
     * Returns null if no custom template has been saved, which causes
     * render() to fall back to the system default skeleton. The stored
     * value is the complete HTML output of render_from_blocks() — a
     * self-contained email document that still contains {{token}}
     * placeholders so live values (app name, support email) are resolved
     * fresh at send time via interpolate().
     *
     * @return string|null Stored custom HTML, or null if none exists.
     */
    protected function resolve_custom_template(): ?string {
        $stored = smliser_settings_adapter()->get(
            'email_template_' . static::template_key(),
            null,
            true
        );

        return ! empty( $stored ) ? (string) $stored : null;
    }

    /**
     * Persist a custom template for this template type.
     *
     * Called by the editor save handler after render_from_blocks()
     * produces the final HTML. The HTML is stored as-is and served
     * directly by render() on subsequent sends — bypassing skeleton(),
     * body(), and all block renderers.
     *
     * Token placeholders ({{app_name}} etc.) are preserved in the
     * stored HTML so they resolve to current settings values at send
     * time rather than being frozen at save time.
     *
     * @param  string $html Complete email HTML from render_from_blocks().
     * @return bool         True on success, false on failure.
     */
    public function save_custom_template( string $html ): bool {
        return smliser_settings_adapter()->set(
            'email_template_' . static::template_key(),
            $html,
            true
        );
    }

    /**
     * Delete the stored custom template for this type.
     *
     * After deletion, render() falls back to the system default
     * skeleton on the next send. Corresponds to the Reset to Default
     * action in the editor toolbar.
     *
     * @return bool True on success, false on failure.
     */
    public function reset_to_default(): bool {
        return smliser_settings_adapter()->delete(
            'email_template_' . static::template_key(),
            true
        );
    }

    /**
     * Check whether a custom template has been stored for this type.
     *
     * Used by the registry to populate the 'has_custom' flag in UI
     * entries and by the editor toolbar to decide whether to show the
     * Reset to Default button.
     *
     * @return bool True if a custom template is stored, false otherwise.
     */
    public function has_custom_template(): bool {
        return $this->resolve_custom_template() !== null;
    }

    /**
     * Check whether this email type is currently enabled.
     *
     * Disabled templates cause to_message() to return null, preventing
     * any email from being sent for this type regardless of the call site.
     * All templates are enabled by default — the flag only exists in
     * storage after an explicit disable() call.
     *
     * @return bool True if enabled (default), false if explicitly disabled.
     */
    public function is_enabled(): bool {
        $stored = smliser_settings_adapter()->get(
            'email_enabled_' . static::template_key(),
            null,
            true
        );

        return $stored === null || (bool) $stored;
    }

    /**
     * Enable this email type.
     *
     * Reverses a previous disable() call. Once enabled, to_message()
     * resumes returning a populated EmailMessage for this template type.
     *
     * @return bool True on success, false on failure.
     */
    public function enable(): bool {
        return smliser_settings_adapter()->set(
            'email_enabled_' . static::template_key(),
            1,
            true
        );
    }

    /**
     * Disable this email type.
     *
     * Once disabled, to_message() returns null for this template type
     * so no email is sent — regardless of which call site triggers it.
     * The stored custom template and style data are preserved and
     * remain active once the template is re-enabled.
     *
     * @return bool True on success, false on failure.
     */
    public function disable(): bool {
        return smliser_settings_adapter()->set(
            'email_enabled_' . static::template_key(),
            0,
            true
        );
    }

    // =========================================================================
    // EmailMessage bridge
    // =========================================================================

    /**
     * Convert this template to an EmailMessage DTO ready for sending.
     *
     * Returns null if this template type is disabled — callers must
     * check the return value before passing to Mailer::send():
     *
     *   $message = $template->to_message();
     *   if ( $message ) {
     *       $mailer->send( $message );
     *   }
     *
     * Attachments and additional headers can be added to the returned
     * object before sending:
     *
     *   $message = $template->to_message();
     *   $message?->attach( $invoice_pdf_path );
     *   $mailer->send( $message );
     *
     * @return EmailMessage|null Populated DTO, or null if the template is disabled.
     */
    public function to_message(): ?EmailMessage {
        if ( ! $this->is_enabled() ) {
            return null;
        }

        return new EmailMessage( [
            'to'      => $this->recipient(),
            'subject' => $this->subject(),
            'body'    => $this->render(),
            'text'    => $this->text(),
        ] );
    }

    /**
     * Plain text fallback for email clients that do not render HTML.
     *
     * Sent alongside the HTML part in multipart MIME emails. The default
     * strips all HTML tags from body() output. Subclasses may override
     * to provide a properly formatted plain text version:
     *
     *   public function text(): string {
     *       return "Hi {$this->license->get_licensee_fullname()},\n\n"
     *           . "Your license key is: {$this->license->get_license_key()}\n";
     *   }
     *
     * @return string Plain text email body.
     */
    public function text(): string {
        return strip_tags( $this->body() );
    }
}