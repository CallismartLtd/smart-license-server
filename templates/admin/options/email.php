<?php
/**
 * Email settings template.
 *
 * Renders global email settings and the provider selection grid.
 * Variables available from OptionsPage::email_options():
 *   $providers        — array<string, EmailProviderInterface>
 *   $default_provider — string|null
 *   $email_fields     — array<int, array>
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\Menu;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = static::get_menu_args();

$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'provider' );
?>
<div class="smliser-admin-page">
    <?php Menu::print_admin_top_menu( $menu_args ); ?>

    <form action="" class="smliser-options-form">
        <input type="hidden" name="action" value="smliser_save_default_email_settings" />

        <div class="smliser-options-form_body">
            <?php foreach ( $email_fields as $field ) : ?>
                <?php smliser_render_input_field( $field ); ?>
            <?php endforeach; ?>

            <label for="save_email_settings" class="smliser-form-label-row">
                <span>Save Settings</span>
                <button type="submit" id="save_email_settings" class="smliser-submit-button">Save</button>
            </label>
            <span class="smliser-spinner"></span>
        </div>
    </form>

    <div class="smliser-email-provider-grid">
        <h2 class="smliser-section-title">Email Providers</h2>
        <p class="smliser-section-description">
            Configure the email provider you want to use for outgoing system emails.
            The active provider is determined by the Default Email Provider setting above.
        </p>

        <div class="smliser-email-provider-cards">
            <?php foreach ( $providers as $provider_id => $provider ) :
                $is_default   = $default_provider === $provider_id;
                $provider_url = $current_url->add_query_param( 'provider', $provider_id );
            ?>
                <div class="smliser-email-provider-card <?php echo esc_attr( $provider_id ); ?> <?php echo $is_default ? 'smliser-provider-card--active' : ''; ?>">

                    <div class="smliser-email-provider-card__header">
                        <span class="smliser-provider-card__name">
                            <?php echo esc_html( $provider->get_name() ); ?>
                        </span>

                        <?php if ( $is_default ) : ?>
                            <span class="smliser-email-provider-card__badge">Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="smliser-email-provider-card__actions">
                        <a href="<?php echo esc_url( $provider_url ); ?>"
                           class="smliser-button smliser-button--secondary">
                            Configure
                        </a>
                    </div>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="smliser-test-email">
        <h2 class="smliser-section-title">Send Test Email</h2>
        <p class="smliser-section-description">
            Send a test email through any configured provider to verify it is
            working correctly. The provider must have its settings saved before testing.
        </p>

        <form action="" class="smliser-options-form" id="smliser-test-email-form">
            <input type="hidden" name="action" value="smliser_send_test_email" />

            <div class="smliser-options-form_body">

                <?php smliser_render_input_field( [
                    'label' => 'Provider',
                    'help'  => 'Select the provider you want to test.',
                    'input' => [
                        'type'    => 'select',
                        'name'    => 'provider_id',
                        'value'   => $default_provider ?? '',
                        'options' => array_map(
                            fn( $p ) => $p->get_name(),
                            $providers
                        ),
                    ],
                ] ); ?>

                <?php smliser_render_input_field( [
                    'label' => 'Send To',
                    'help'  => 'Email address to deliver the test message to.',
                    'input' => [
                        'type'  => 'text',
                        'name'  => 'test_email',
                        'value' => '',
                        'attr'  => [
                            'placeholder'  => 'you@example.com',
                            'autocomplete' => 'email',
                            'spellcheck'   => 'off',
                        ],
                    ],
                ] ); ?>

                <label for="send_test_email" class="smliser-form-label-row">
                    <span>Send Test</span>
                    <button type="submit"
                            id="send_test_email"
                            class="smliser-submit-button">
                        Send Test Email
                    </button>
                </label>

                <span class="smliser-spinner"></span>                

            </div>
        </form>
    </div>

</div>