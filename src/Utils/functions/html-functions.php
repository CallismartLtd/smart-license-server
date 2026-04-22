<?php
/**
 * HTML Rendering Functions API
 */

use SmartLicenseServer\Core\URL;
use SmartLicenseServer\Exceptions\GlobalErrorHandler;
use SmartLicenseServer\Utils\Sanitizer;

/**
 * Renders a reusable, prefixed toggle switch component.
 *
 * @param array $attrs Associative array of attributes for the input element.
 *                     Supported: id, name, value, class, and any custom data-* or aria-* attributes.
 *                     Example:
 *                     [
 *                        'id' => 'autosave_toggle',
 *                        'name' => 'autosave',
 *                        'value' => 1, // 1 for checked, 0 for unchecked
 *                        'data-group' => 'editor',
 *                        'aria-label' => 'Enable autosave',
 *                     ]
 *
 * @return void
 */
function smliser_render_toggle_switch( $attrs = array() ) {
    $defaults = array(
        'id'    => uniqid( 'smliser_toggle_' ),
        'name'  => 'toggle_switch',
        'value' => 0,
        'class' => 'smliser_toggle-switch-input',
    );

    $attrs = array_merge( $defaults, $attrs );

    // Extract value to determine checked state
    $value = (int) $attrs['value'];
    unset( $attrs['value'] );

    // Build attribute string
    $attr_str = '';
    foreach ( $attrs as $key => $val ) {
        if ( is_bool( $val ) ) {
            $attr_str .= $val ? sprintf( ' %s', esc_attr( $key ) ) : '';
        } else {
            $attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $val ) );
        }
    }

    printf(
        '<div class="smliser_toggle-switch-container">
            <input type="checkbox"%1$s value="1" %2$s />
            <label for="%3$s" class="smliser_toggle-switch-label">
                <span class="smliser_toggle-switch-slider"></span>
            </label>
        </div>',
        $attr_str,
        checked( $value, 1, false ),
        esc_attr( $attrs['id'] )
    );
}

/**
 * Render form input field.
 *
 * Supports an optional help icon that displays a tooltip describing
 * the field. The help icon is rendered inline after the label text,
 * before the input element.
 *
 * Basic usage:
 *
 *   smliser_render_input_field( array(
 *       'label' => 'API Key',
 *       'help'  => 'Your secret API key. Do not share this with anyone.',
 *       'input' => array(
 *           'type'  => 'password',
 *           'name'  => 'api_key',
 *           'value' => '',
 *       ),
 *   ) );
 *
 * The help text is exposed via:
 *   - An aria-label on the icon button (screen readers).
 *   - A data-help attribute (JS tooltip hook).
 *   - A <span class="smliser-help-tooltip"> sibling (CSS tooltip fallback).
 *
 * @param array $args {
 *     @type string $label          Field label text.
 *     @type string $help           Optional help text shown in the tooltip.
 *     @type array  $input {
 *         @type string $type       Input type (text, password, select, etc.). Default 'text'.
 *         @type string $name       Input name attribute.
 *         @type string $value      Input value.
 *         @type string $class      Wrapper/label class. Default 'smliser-form-label-row'.
 *         @type array  $options    Options for select/radio inputs.
 *         @type array  $attr       Additional HTML attributes as key => value pairs.
 *     }
 * }
 */
function smliser_render_input_field( array $args = [] ): void {
    $default_args = array(
        'label' => '',
        'help'  => '',
        'input' => array(
            'type'    => 'text',
            'name'    => '',
            'value'   => '',
            'class'   => 'smliser-form-label-row',
            'options' => array(),
            'attr'    => array(),
        ),
    );

    $parsed_args = parse_args_recursive( $args, $default_args );
    $input       = $parsed_args['input'];
    $type        = $input['type'];
    $help        = trim( $parsed_args['help'] );

    // Build attributes string.
    $attr_str = '';
    if ( ! empty( $input['attr'] ) && is_array( $input['attr'] ) ) {
        foreach ( $input['attr'] as $key => $val ) {
            $attr_str .= sprintf( ' %s="%s"', esc_attr( $key ), esc_attr( $val ) );
        }
    }

    $id = ! empty( $input['attr']['id'] ) ? $input['attr']['id'] : $input['name'];

    // Hidden fields: no label, no help icon.
    if ( 'hidden' === $type ) {
        printf(
            '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s>',
            esc_attr( $type ),
            esc_attr( $input['name'] ),
            esc_attr( $id ),
            esc_attr( $input['value'] ),
            $attr_str
        );
        return;
    }

    // Open label.
    printf(
        '<label for="%1$s" class="%2$s">',
        esc_attr( $id ),
        esc_attr( $input['class'] )
    );

    // Label text + optional help icon, grouped so they sit inline.
    echo '<span class="smliser-field-label-text">';
    echo '<span>' . esc_html( $parsed_args['label'] ) . '</span>';

    if ( '' !== $help ) {
        smliser_render_field_help_icon( $help, $id );
    }

    echo '</span>'; // .smliser-field-label-text

    // Input element.
    switch ( $type ) {
        case 'textarea':
            printf(
                '<textarea name="%1$s" id="%2$s"%3$s>%4$s</textarea>',
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                $attr_str,
                esc_textarea( $input['value'] )
            );
            break;

        case 'select':
            printf(
                '<select name="%1$s" id="%2$s"%3$s>',
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                $attr_str
            );
            foreach ( $input['options'] as $val => $label ) {
                printf(
                    '<option value="%1$s" %2$s>%3$s</option>',
                    esc_attr( $val ),
                    selected( $input['value'], $val, false ),
                    esc_html( $label )
                );
            }
            echo '</select>';
            break;

        case 'checkbox':
        case 'radio':
            printf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s" %5$s%6$s>',
                esc_attr( $type ),
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                checked( $input['value'], true, false ),
                $attr_str
            );
            break;

        case 'password':
            echo '<div class="smliser-password-field-wrapper">';
            printf(
                '<input type="password" name="%1$s" id="%2$s" value="%3$s" class="smliser-password-input"%4$s>',
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                $attr_str
            );
            printf(
                '<button type="button" class="smliser-password-toggle" data-target="%1$s" aria-label="%2$s">
                    <svg class="smliser-eye-icon smliser-eye-show" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <svg class="smliser-eye-icon smliser-eye-hide" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none;" aria-hidden="true">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                </button>',
                esc_attr( $id ),
                esc_attr__( 'Toggle password visibility', 'smliser' )
            );
            echo '</div>';
            break;

        default:
            printf(
                '<input type="%1$s" name="%2$s" id="%3$s" value="%4$s"%5$s>',
                esc_attr( $type ),
                esc_attr( $input['name'] ),
                esc_attr( $id ),
                esc_attr( $input['value'] ),
                $attr_str
            );
            break;
    }

    echo '</label>';
}

/**
 * Render a help icon with an associated tooltip for a form field.
 *
 * Outputs a <button> (so it is focusable and keyboard-accessible) containing
 * a question-mark SVG. The tooltip text is attached three ways:
 *
 *   1. aria-label  — read aloud by screen readers on focus/hover.
 *   2. data-help   — picked up by any JS tooltip library you wire up.
 *   3. A visually-hidden <span class="smliser-help-tooltip"> sibling that
 *      can be shown with pure CSS for environments without JS.
 *
 * The button carries aria-describedby pointing at the tooltip span so
 * assistive technology can also read the full text when the element is
 * focused, not just its label.
 *
 * @param string $help_text  The descriptive text to display.
 * @param string $field_id   The ID of the field this help icon belongs to,
 *                           used to generate a unique tooltip ID.
 */
function smliser_render_field_help_icon( string $help_text, string $field_id ): void {
    $tooltip_id = esc_attr( $field_id ) . '-help-tooltip';

    printf(
        '<span class="smliser-help-icon-wrap">
            <button
                type="button"
                class="smliser-help-icon"
                aria-label="%1$s"
                aria-describedby="%2$s"
                data-help="%1$s"
            >
                <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"></circle>
                    <text x="12" y="17" text-anchor="middle" font-size="13" font-weight="600" font-family="sans-serif" fill="currentColor">?</text>
                </svg>
            </button>
            <span id="%2$s" class="smliser-help-tooltip" role="tooltip">%3$s</span>
        </span>',
        esc_attr( $help_text ),
        esc_attr( $tooltip_id ),
        esc_html( $help_text )
    );
}

/**
 * Render table-style pagination.
 *
 * The $pagination array must contain:
 *
 * - total (int)  Total number of records.
 * - page (int)   Current page (1-based).
 * - limit (int)  Items per page.
 *
 * Example:
 *
 * [
 *     'total' => 125,
 *     'page'  => 3,
 *     'limit' => 25,
 * ]
 *
 * @param array{
 *     total: int,
 *     page: int,
 *     limit: int
 * } $pagination Pagination data.
 * @param string $base_url Optional base URL for pagination links (default: current URL without 'paged' and 'limit' query params).
 * @param string $page_param Optional query parameter name for page number (default: 'paged').
 *
 * @return void
 */
function smliser_render_pagination( array $pagination, string $base_url = '', string $page_param = 'paged' ) : void {

    $total       = isset( $pagination['total'] ) ? (int) $pagination['total'] : 0;
    $page        = isset( $pagination['page'] ) ? max( 1, (int) $pagination['page'] ) : 1;
    $limit       = isset( $pagination['limit'] ) ? max( 1, (int) $pagination['limit'] ) : 20;
    $total_pages = (int) ceil( $total / $limit );

    if ( $total <= 0 ) {
        return;
    }

    $page = min( $page, $total_pages );

    $window = 2;
    $start  = max( 1, $page - $window );
    $end    = min( $total_pages, $page + $window );
    

    $base_url   = $base_url ? new URL( $base_url ) : smliser_get_current_url();
    $base_url   = $base_url->remove_query_param( $page_param, 'limit' );
    $prev_page  = max( 1, $page - 1 );
    $next_page  = min( $total_pages, $page + 1 );

    $offset    = ( $page - 1 ) * $limit;
    $remaining = max( 0, $total - $offset );
    $displayed = min( $limit, $remaining );
    ?>

    <p class="smliser-table-count">
        <?php
        printf(
            esc_html__( '%1$d of %2$d %3$s', 'smliser' ),
            intval( $displayed ),
            intval( $total ),
            esc_html( _n( 'item', 'items', $total, 'smliser' ) )
        );
        ?>
    </p>

    <?php if ( $total_pages > 1 ) : ?>
        <div class="smliser-tablenav-pages">
            <span class="smliser-displaying-num">
                <?php
                printf(
                    esc_html__( 'Page %1$d of %2$d', 'smliser' ),
                    intval( $page ),
                    intval( $total_pages )
                );
                ?>
            </span>

            <span class="smliser-pagination-links">

                <?php if ( $page > 1 ) : ?>
                    <a class="prev-page button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $prev_page, 'limit' => $limit ) ) ); ?>">&laquo;</a>
                <?php else : ?>
                    <span class="smliser-navspan button disabled">&laquo;</span>
                <?php endif; ?>

                <?php
                // First page.
                if ( $start > 1 ) :
                    ?>
                    <a class="button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => 1, 'limit' => $limit ) ) ); ?>">1</a>
                    <?php if ( $start > 2 ) : ?>
                        <span class="smliser-navspan button disabled">…</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php
                // Window pages.
                for ( $i = $start; $i <= $end; $i++ ) :
                    $class = ( $i === $page ) ? 'button current' : 'button';
                    ?>
                    <a class="<?php echo esc_attr( $class ); ?>"
                       href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $i, 'limit' => $limit ) ) ); ?>">
                        <?php echo intval( $i ); ?>
                    </a>
                <?php endfor; ?>

                <?php
                // Last page.
                if ( $end < $total_pages ) :
                    if ( $end < $total_pages - 1 ) :
                        ?>
                        <span class="smliser-navspan button disabled">…</span>
                    <?php
                    endif;
                    ?>
                    <a class="button"
                       href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $total_pages, 'limit' => $limit ) ) ); ?>">
                        <?php echo intval( $total_pages ); ?>
                    </a>
                <?php endif; ?>

                <?php if ( $page < $total_pages ) : ?>
                    <a class="next-page button" href="<?php echo esc_url( $base_url->add_query_params( array( $page_param => $next_page, 'limit' => $limit ) ) ); ?>">&raquo;</a>
                <?php else : ?>
                    <span class="smliser-navspan button disabled">&raquo;</span>
                <?php endif; ?>

            </span>
        </div>
    <?php endif; ?>
    <?php
}

/**
 * Prints an indepth analysis of the given URL.
 * 
 * @param string $url
 */
function smliser_dump_url( $url ) : void {
    $dump   = ( new URL( $url ) )->dump();
    // Pretty print for debugging
    echo '<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 8px; font-family: \'Courier New\', monospace; font-size: 13px; line-height: 1.6; overflow-x: auto;">';
    echo '<strong style="color: #4ec9b0; font-size: 16px;">🔍 URL DEBUG DUMP</strong>' . "\n";
    echo str_repeat('─', 80) . "\n\n";
    
    // Full URL
    echo '<span style="color: #569cd6;">📌 FULL URL:</span> ' . "\n";
    echo '   <span style="color: #ce9178;">' . htmlspecialchars( $dump['url']['full_url'] ) . '</span>' . "\n\n";
    
    // Origin
    echo '<span style="color: #569cd6;">🌐 ORIGIN:</span> ' . "\n";
    echo '   <span style="color: #ce9178;">' . htmlspecialchars( $dump['url']['origin'] ?? 'N/A' ) . '</span>' . "\n\n";
    
    // Components
    echo '<span style="color: #569cd6;">🧩 COMPONENTS:</span>' . "\n";
    foreach ( $dump['components'] as $key => $value ) {
        $color = $value !== null ? '#b5cea8' : '#808080';
        $display = $value ?? '<span style="color: #808080; font-style: italic;">not set</span>';
        echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: %s;">%s</span>' . "\n", 
            str_pad( $key, 10 ), 
            $color, 
            $display 
        );
    }
    echo "\n";
    
    // Query Parameters
    echo '<span style="color: #569cd6;">🔗 QUERY PARAMETERS:</span>' . "\n";
    if ( empty( $dump['query_params'] ) ) {
        echo '   <span style="color: #808080; font-style: italic;">No query parameters</span>' . "\n";
    } else {
        foreach ( $dump['query_params'] as $key => $value ) {
            if ( is_array( $value ) ) {
                echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: #ce9178;">[%s]</span>' . "\n",
                    htmlspecialchars( $key ),
                    htmlspecialchars( implode( ', ', $value ) )
                );
            } else {
                echo sprintf( '   <span style="color: #9cdcfe;">%s:</span> <span style="color: #ce9178;">%s</span>' . "\n",
                    htmlspecialchars( $key ),
                    htmlspecialchars( (string) $value )
                );
            }
        }
    }
    echo "\n";
    
    // Validation States
    echo '<span style="color: #569cd6;">✅ VALIDATION:</span>' . "\n";
    foreach ( $dump['validation'] as $key => $value ) {
        $icon = $value ? '✓' : '✗';
        $color = $value ? '#4ec9b0' : '#f48771';
        echo sprintf( '   <span style="color: %s;">%s</span> <span style="color: #9cdcfe;">%s</span>' . "\n",
            $color,
            $icon,
            str_replace( '_', ' ', $key )
        );
    }
    echo "\n";
    
    // Presence Checks
    echo '<span style="color: #569cd6;">🔎 PRESENCE CHECKS:</span>' . "\n";
    foreach ( $dump['has'] as $key => $value ) {
        $icon = $value ? '●' : '○';
        $color = $value ? '#4ec9b0' : '#808080';
        echo sprintf( '   <span style="color: %s;">%s</span> <span style="color: #9cdcfe;">has_%s</span>' . "\n",
            $color,
            $icon,
            $key
        );
    }
    echo "\n";
    
    // Raw Components
    echo '<span style="color: #569cd6;">⚙️  RAW COMPONENTS ARRAY:</span>' . "\n";
    echo '<span style="color: #808080;">';
    print_r( $dump['raw_components'] );
    echo '</span>';
    
    echo str_repeat('─', 80) . "\n";
    echo '</pre>';
}

/**
 * Kills appliaction execution and displays HTML page with an error message.
 *
 * This function complements the `die()` PHP function. The difference is that
 * HTML will be displayed to the user. It is recommended to use this function
 * only when the execution should not continue any further. It is not recommended
 * to call this function very often, and try to handle as many errors as possible
 * silently or more gracefully.
 *
 * As a shorthand, the desired HTTP response code may be passed as an integer to
 * the `$title` parameter (the default title would apply) or the `$args` parameter.
 *
 *
 * @param string|SmartLicenseServer\Exception  $message Optional. Error message. If this is an error object,
 *                                  and not an Ajax or XML-RPC request, the error's messages are used.
 *                                  Default empty string.
 * @param string|int       $title   Optional. Error title. If `$message` is a `SmartLicenseServer\Exceptions\Exception;` object,
 *                                  error data with the key 'title' may be used to specify the title.
 *                                  If `$title` is an integer, then it is treated as the response code.
 *                                  Default empty string.
 * @param string|array|int $args {
 *     Optional. Arguments to control behavior. If `$args` is an integer, then it is treated
 *     as the response code. Default empty array.
 *
 *     @type int    $response       The HTTP response code. Default 200 for Ajax requests, 500 otherwise.
 *     @type string $link_url       A URL to include a link to. Only works in combination with $link_text.
 *                                  Default empty string.
 *     @type string $link_text      A label for the link to include. Only works in combination with $link_url.
 *                                  Default empty string.
 *     @type bool   $back_link      Whether to include a link to go back. Default false.
 *                                  Default is the value of is_rtl().
 *     @type string $charset        Character set of the HTML output. Default 'utf-8'.
 *     @type string $code           Error code to use. Default is 'smliser_error', or the main error code if $message
 *                                  is a WP_Error.
 *     @type bool   $exit           Whether to exit the process after completion. Default true.
 * }
 */
function smliser_abort_request( $message = '', $title = '', $args = [] ) {
    GlobalErrorHandler::instance()->abort( $message, $title, $args );
}

/**
 * Not found container
 * 
 * @param string $text Message to show
 */
function smliser_not_found_container( $text ) {
    ob_start();
    ?>
    <div class="smliser-not-found-container">
        <p><?php echo Sanitizer::sanitize_html( $text ) ?> </p>
    </div>

    <?php
    return ob_get_clean();
}

/**
 * Rest API documentation page
 */
function smliser_rest_documentation() {
    $rest = smliser_envProvider()->restProvider()->restAPIVersion();
    ?>
        <div class="smliser-admin-api-description-section">
            <h2 class="heading">REST API Documentation</h2>
            <div class="smliser-api-base-url">
                <strong>Base URL:</strong>
                <code><?php echo esc_url( restAPIUrl() ); ?></code>
            </div>
            
            <?php foreach ( $rest::describe_routes() as $path => $html ) : 
                echo $html;
            endforeach; ?>
        </div>

    <?php
}

/**
 * Render the Smart License Server admin top navigation header.
 *
 * @param array $args {
 *     @type array  $breadcrumbs
 *     @type array  $actions
 *     @type string $nav_class
 *     @type string $content_class
 *     @type array  $attributes
 * }
 * @param bool $echo
 *
 * @return string|null
 */
function smliser_print_admin_content_header( array $args = array(), bool $echo = true ) {

    $defaults = array(
        'breadcrumbs'   => array(),
        'actions'       => array(),
        'nav_class'     => '',
        'content_class' => '',
        'attributes'    => array(),
    );

    $args = parse_args( $args, $defaults );

    $print_active = fn ( bool $cond ) => $cond ? ' active' : '';

    /**
     * Helper to render arbitrary attributes safely.
     */
    $render_attributes = static function ( array $attributes ) : string {

        $html = '';

        foreach ( $attributes as $key => $value ) {

            if ( is_bool( $value ) ) {
                if ( $value ) {
                    $html .= ' ' . esc_attr( $key );
                }
                continue;
            }

            $html .= sprintf(
                ' %s="%s"',
                esc_attr( $key ),
                esc_attr( $value )
            );
        }

        return $html;
    };

    ob_start();
    ?>

    <nav
        class="smliser-top-nav <?php echo esc_attr( $args['nav_class'] ); ?>"
        <?php echo $render_attributes( $args['attributes'] ); // phpcs:ignore ?>
    >
        <div class="smliser-top-nav-content <?php echo esc_attr( $args['content_class'] ); ?>">

            <?php if ( ! empty( $args['breadcrumbs'] ) ) : ?>
                <div class="smliser-breadcrumb">

                    <?php
                    $breadcrumb_count = count( $args['breadcrumbs'] );
                    $current_index    = 0;

                    foreach ( $args['breadcrumbs'] as $breadcrumb ) :

                        $current_index++;

                        $breadcrumb = parse_args(
                            $breadcrumb,
                            array(
                                'label'      => '',
                                'url'        => '',
                                'icon'       => '',
                                'class'      => '',
                                'attributes' => array(),
                            )
                        );

                        $tag = ! empty( $breadcrumb['url'] ) ? 'a' : 'span';
                        ?>

                        <<?php echo esc_html( $tag ); ?>
                            class="<?php echo esc_attr( $breadcrumb['class'] ); ?>"
                            <?php if ( 'a' === $tag ) : ?>
                                href="<?php echo esc_url( $breadcrumb['url'] ); ?>"
                            <?php endif; ?>
                            <?php echo $render_attributes( $breadcrumb['attributes'] ); // phpcs:ignore ?>
                        >

                            <?php if ( ! empty( $breadcrumb['icon'] ) ) : ?>
                                <i class="<?php echo esc_attr( $breadcrumb['icon'] ); ?>"></i>
                            <?php endif; ?>

                            <?php echo esc_html( $breadcrumb['label'] ); ?>

                        </<?php echo esc_html( $tag ); ?>>

                        <?php if ( $current_index < $breadcrumb_count ) : ?>
                            <span>/</span>
                        <?php endif; ?>

                    <?php endforeach; ?>

                </div>
            <?php endif; ?>


            <?php if ( ! empty( $args['actions'] ) ) : ?>
                <div class="smliser-quick-actions">

                    <?php foreach ( $args['actions'] as $action ) :

                        $action = parse_args(
                            $action,
                            array(
                                'label'      => '',
                                'url'        => '',
                                'title'      => '',
                                'icon'       => '',
                                'active'     => false,
                                'class'      => '',
                                'attributes' => array(),
                                'target'     => '',
                                'rel'        => '',
                                'data'       => array(),
                            )
                        );

                        /**
                         * Merge data-* attributes
                         */
                        foreach ( (array) $action['data'] as $data_key => $data_value ) {
                            $action['attributes'][ 'data-' . $data_key ] = $data_value;
                        }

                        ?>

                        <a
                            class="smliser-menu-link<?php echo esc_attr( $print_active( (bool) $action['active'] ) ); ?> <?php echo esc_attr( $action['class'] ); ?>"
                            href="<?php echo esc_url( $action['url'] ); ?>"
                            title="<?php echo esc_attr( $action['title'] ); ?>"
                            <?php if ( ! empty( $action['target'] ) ) : ?>
                                target="<?php echo esc_attr( $action['target'] ); ?>"
                            <?php endif; ?>
                            <?php if ( ! empty( $action['rel'] ) ) : ?>
                                rel="<?php echo esc_attr( $action['rel'] ); ?>"
                            <?php endif; ?>
                            <?php echo $render_attributes( $action['attributes'] ); // phpcs:ignore ?>
                        >

                            <?php if ( ! empty( $action['icon'] ) ) : ?>
                                <i class="<?php echo esc_attr( $action['icon'] ); ?>"></i>
                            <?php endif; ?>

                            <?php echo esc_html( $action['label'] ); ?>

                        </a>

                    <?php endforeach; ?>

                </div>
            <?php endif; ?>

        </div>
    </nav>

    <?php
    $output = ob_get_clean();

    if ( true === $echo ) {
        echo $output; // phpcs:ignore
        return null;
    }

    return $output;
}