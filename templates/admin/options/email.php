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
use SmartLicenseServer\Email\EmailProviderIcons;

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

            <div class="smliser-form-label-row">
                <span>Save Defaults</span>
                <span>
                    <button type="submit" id="save_email_settings" class="smliser-submit-button">Save</button>
                </span>
            </div>
            <span class="smliser-spinner"></span>
        </div>
    </form>

    <div class="smliser-providers-grid">
        <h2 class="smliser-section-title">Email Providers</h2>
        <p class="smliser-section-description">
            Configure the email provider you want to use for outgoing system emails.
            The active provider is determined by the Default Email Provider setting above.
        </p>

        <div class="smliser-provider-cards">
            <?php foreach ( $providers as $provider_id => $provider ) :
                $is_default   = $default_provider === $provider_id;
                $provider_url = $current_url->add_query_param( 'provider', $provider_id );
            ?>
                <div class="smliser-provider-card <?php echo esc_attr( $provider_id ); ?> <?php echo $is_default ? 'smliser-provider-card--active' : ''; ?>">

                    <div class="smliser-provider-card__icon-wrap">
                        <?php echo EmailProviderIcons::render( $provider_id, $provider->get_name() ); ?>
                    </div>

                    <div class="smliser-provider-card__body">
                        <span class="smliser-provider-card__name">
                            <?php echo esc_html( $provider->get_name() ); ?>
                        </span>

                        <?php if ( $is_default ) : ?>
                            <span class="smliser-provider-card__badge">&#10003; Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="smliser-provider-card__actions">
                        <a href="<?php echo esc_url( $provider_url ); ?>"
                        class="smliser-button smliser-button--secondary">
                            Configure
                        </a>
                    </div>
                    <?php if ( $provider_id === 'php_mail' ) : ?>
                        <p class="smliser-provider-card__notice">
                            Not recommended for production use.
                        </p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="smliser-test-email" id="send-test-email">
        <h2 class="smliser-section-title">Send Test Email</h2>
        <p class="smliser-section-description">
            Send a test email through any configured provider to verify it is
            working correctly. The provider must have its settings configure and saved before testing.
        </p>

        <form action="" class="smliser-options-form" id="smliser-test-email-form">
            <input type="hidden" name="action" value="smliser_send_test_email" />

            <div class="smliser-options-form_body">

                <?php smliser_render_input_field( [
                    'label' => 'Provider',
                    'help'  => 'Select the provider you want to test.',
                    'input' => [
                        'type'  => 'select',
                        'name'  => 'provider_id',
                        'value' => $default_provider ?? '',
                        'class' => 'smliser-form-label-row smliser-auto-select2',
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

                <div class="smliser-form-label-row">
                    <span style="text-align: right;">
                        <button type="submit" id="send_test_email" class="smliser-submit-button">Send Test Email</button>
                    </span>
                </div>

                <span class="smliser-spinner"></span>                

            </div>
        </form>
    </div>

</div>