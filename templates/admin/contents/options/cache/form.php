<?php
/**
 * Individual cache adapter settings template.
 *
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 * @var \SmartLicenseServer\Core\Request $request
 * @var string $adapter_name
 * @var string $adapter_id
 * @var array<string, array> $schema
 * @var array<string, mixed> $saved_settings 
 * @var bool $is_default
 * @var object $adapter
 * @var string $adapter_key
 * @var \SmartLicenseServer\Core\URLManager $urlmanager
 * @var \SmartLicenseServer\Admin\ContentHandlers\OptionsPage $page_handler
 * @var \SmartLicenseServer\SettingsAPI\Settings $settings
 */

defined( 'SMLISER_ROOT' ) || exit;

$menu_args = $page_handler->get_menu_args( $request );
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
            <span> <a href="<?php echo escUrl( $current_url->get_href() ) ?>" class="smliser-btn"> <i class="ti ti-arrow-back"></i></a></span>
            <input type="hidden" name="action"      value="smliser_save_cache_adapter_settings" />
            <input type="hidden" name="adapter_id" value="<?php echo escAttr( $adapter_id ); ?>" />

            <div class="smliser-options-form_body">
                <?php smliser_render_input_field([
                    'label' => 'Default TTL',
                    'help'  => 'Default cache expiration duration in seconds.',
                    'input' => array(
                        'type'  => 'number',
                        'name'  => 'default_cache_ttl',
                        'value' => $settings->get( 'default_cache_ttl', 0, true ),
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
                        $field['input']['value'] = '';
                        $field['input']['attr']  = [
                            'autocomplete'  => 'new-password',
                            'data-masked'   => 'true',
                            'placeholder'   => '••••••••••••••••',
                        ];
                    }
                ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>

                <div class="smliser-form-label-row">
                    <strong id="set-as-default-label">Make Default <?php 
                        smliser_render_field_help_icon(
                            'Set this cache adapter as the default provider for all caching operations.',
                            'set-as-default-label'
                        ) ?>
                    </strong>
                    <?php smliser_render_toggle_switch([
                        'name'  => 'set_as_default',
                        'value' => $is_default,
                    ]); ?>
                    
                </div>

                <div class="smliser-form-label-row submit-row">
                    <button type="submit" class="smliser-submit-button">Save</button>
                    <button type="button" class="smliser-btn test-cache-btn">Test</button>
                    
                    <?php if ( ! empty( $saved_settings ) ) : ?>
                        <button type="button" class="smliser-btn reset-cache-btn">Reset</button>
                    <?php endif; ?>

                </div>
                <span class="smliser-spinner"></span>
            </div>
        </form>
    <?php endif; ?>
</div>