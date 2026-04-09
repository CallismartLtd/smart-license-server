<?php
/**
 * Individual cache adapter settings template.
 *
 * Variables available from OptionsPage::cache_adapter_settings():
 *   $adapter_name  — string
 *   $adapter_id    — string
 *   $schema         — array<string, array>   field schema from get_settings_schema()
 *   $saved_settings — array<string, mixed>   persisted values keyed by field name
 *   $is_default     — bool
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = OptionsPage::get_menu_args( $request );
$current_label  = end( $menu_args['breadcrumbs'] )['label'];
$menu_args['breadcrumbs'][1]  = array(
    'label' => $current_label,
    'url'   => smliser_get_current_url()->remove_query_param( 'adapter' ),
    'icon'  => 'ti ti-mail'

);

$menu_args['breadcrumbs'][2]['label']   = $adapter_name;

$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'adapter' );
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <?php if ( ! $adapter ) : ?>
        <?php printf(
            smliser_not_found_container( 'The cache adapter "%s" does not exists. <a href="%s">Go Back</a>' ),
            $adapter_key,
            $current_url->get_href()
        ); ?>

    <?php else: ?>
        <form action="" class="smliser-options-form">
            <span> <a href="<?php echo esc_url( $current_url->get_href() ) ?>" class="smliser-btn"> <i class="ti ti-arrow-back"></i></a></span>
            <input type="hidden" name="action"      value="smliser_save_cache_adapter_settings" />
            <input type="hidden" name="adapter_id" value="<?php echo esc_attr( $adapter_id ); ?>" />

            <div class="smliser-options-form_body">
                <?php smliser_render_input_field([
                    'label' => 'Default TTL',
                    'help'  => 'Default cache expiration duration in seconds.',
                    'input' => array(
                        'type'  => 'number',
                        'name'  => 'default_cache_ttl',
                        'value' => smliser_settings()->get( 'default_cache_ttl', 0, true ),
                        'attr'  => array(
                            'min'   => 0
                        )
                    )
                ]); ?>
    
                <?php foreach ( $schema as $key => $field_schema ) :
                    // Build the field definition in the same shape smliser_render_input_field() expects.
                    $field = [
                        'label' => $field_schema['label'] ?? '',
                        'help'  => $field_schema['description'] ?? '',
                        'input' => [
                            'type'     => $field_schema['type'] ?? 'text',
                            'name'     => $key,
                            'value'    => ( empty( $saved_settings[ $key ] ) ? null : $saved_settings[ $key ] ) ?? $field_schema['default'] ?? '',
                        ],
                    ];

                    if ( isset( $field_schema['required'] ) && $field_schema['required'] ) {
                        $field['input']['attr']['required']     = true;
                        $field['input']['attr']['field_name']   = $field['label'];
                    }

                    // Pass options through for select fields.
                    if ( isset( $field_schema['options'] ) ) {
                        $field['input']['options']  = $field_schema['options'];
                        $field['input']['class']    = 'smliser-form-label-row smliser-auto-select2';
                    }

                    // Mask password fields so saved values are not exposed in the DOM.
                    if ( $field_schema['type'] === 'password' ) {
                        $field['input']['value'] = $saved_settings[ $key ] !== '' ? '********' : '';
                        $field['input']['attr']  = [
                            'autocomplete' => 'new-password',
                            'data-masked'  => 'true',
                        ];
                    }
                ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>

                <div class="smliser-form-label-row">
                    <span>Set as Default Provider</span>
                    <?php smliser_render_toggle_switch([
                        'name'  => 'set_as_default',
                        'value' => $is_default
                    ]); ?>
                    
                </div>

                <div class="smliser-form-label-row submit-row">
                    <button type="submit" class="smliser-submit-button">Save</button>
                    <button type="button" class="smliser-btn test-cache-btn">Test</button>
                    
                </div>
                <span class="smliser-spinner"></span>
            </div>
        </form>
    <?php endif; ?>
</div>