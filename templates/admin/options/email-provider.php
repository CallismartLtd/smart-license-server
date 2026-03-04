<?php
/**
 * Individual email provider settings template.
 *
 * Variables available from OptionsPage::email_provider_settings():
 *   $provider_name  — string
 *   $provider_id    — string
 *   $schema         — array<string, array>   field schema from get_settings_schema()
 *   $saved_settings — array<string, mixed>   persisted values keyed by field name
 *   $is_default     — bool
 *
 * @package SmartLicenseServer\templates
 * @since   1.0.0
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args();
$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'provider' );
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>
    <form action="" class="smliser-options-form">
        <input type="hidden" name="action"      value="smliser_save_email_provider_settings" />
        <input type="hidden" name="provider_id" value="<?php echo esc_attr( $provider_id ); ?>" />

        <div class="smliser-options-form_body">
            <?php foreach ( $schema as $key => $field_schema ) :
                // Build the field definition in the same shape smliser_render_input_field() expects.
                $field = [
                    'label' => $field_schema['label'],
                    'help'  => $field_schema['description'] ?? '',
                    'input' => [
                        'type'     => $field_schema['type'],
                        'name'     => $key,
                        'value'    => $saved_settings[ $key ] ?? '',
                        'required' => $field_schema['required'] ?? false,
                    ],
                ];

                // Pass options through for select fields.
                if ( isset( $field_schema['options'] ) ) {
                    $field['input']['options'] = $field_schema['options'];
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

            <?php if ( ! $is_default ) : ?>
                <div class="smliser-form-label-row">
                    <span>Set as Default Provider</span>
                    <label class="smliser-toggle">
                        <input type="checkbox"
                                name="set_as_default"
                                value="1" />
                        <span class="smliser-toggle__slider"></span>
                    </label>
                </div>
            <?php else : ?>
                <div class="smliser-notice smliser-notice--info">
                    This is the currently active email provider.
                </div>
            <?php endif; ?>

            <label for="save_provider_settings" class="smliser-form-label-row">
                <span>Save Settings</span>
                <button type="submit"
                        id="save_provider_settings"
                        class="smliser-submit-button">
                    Save
                </button>
            </label>
            <span class="smliser-spinner"></span>
        </div>
    </form>
</div>