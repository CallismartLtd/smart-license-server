<?php
/**
 * Individual email provider settings form.
 *
 * @var bool $is_default
 * @var string $provider_id
 * @var string $provider_name
 * @var string $provider_key
 * @var \SmartLicenseServer\Core\Request $request
 * @var array $schema
 * @var array $saved_settings
 * @var class-string<\SmartLicenseServer\Email\Providers\EmailProviderInterface>|null $provider
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;

defined( 'SMLISER_ROOT' ) || exit;

$menu_args = OptionsPage::get_menu_args( $request );
$current_label  = end( $menu_args['breadcrumbs'] )['label'];
$menu_args['breadcrumbs'][1]  = array(
    'label' => $current_label,
    'url'   => smliser_get_current_url()->remove_query_param( 'provider' ),
    'icon'  => 'ti ti-mail'

);

$menu_args['breadcrumbs'][2]['label']   = $provider_name;

$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'provider' );
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>
    <?php if ( ! $provider ) : ?>
        <?php printf(
            smliser_not_found_container( 'The email provider "%s" does not exists. <a href="%s">Go Back</a>' ),
            $provider_key,
            $current_url
        ); ?>

    <?php else: ?>
        <form action="" class="smliser-options-form">
            <span> <a href="<?php echo escUrl( $current_url->get_href() ) ?>" class="smliser-btn"> <i class="ti ti-arrow-back"></i></a></span>
            <input type="hidden" name="action"      value="smliser_save_email_provider_settings" />
            <input type="hidden" name="provider_id" value="<?php echo escAttr( $provider_id ); ?>" />

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
                        $field['input']['options']  = $field_schema['options'];
                        $field['input']['class']    = 'smliser-form-label-row smliser-auto-select2';
                    }

                    // Mask password fields so saved values are not exposed in the DOM.
                    if ( $field_schema['type'] === 'password' ) {
                        $field['input']['value'] =  '';
                        $field['input']['attr']  = [
                            'autocomplete' => 'new-password',
                            'data-masked'  => 'true',
                            'placeholder'  => '••••••••••••••••',
                        ];
                    }
                ?>
                    <?php smliser_render_input_field( $field ); ?>
                <?php endforeach; ?>

                <div class="smliser-form-label-row">
                    <strong id="set-as-default-label">Make Default <?php 
                        smliser_render_field_help_icon(
                            'Set this email provider as the default provider for all mailing operations.',
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
                </div>
                <span class="smliser-spinner"></span>
            </div>
        </form>
    <?php endif; ?>
</div>