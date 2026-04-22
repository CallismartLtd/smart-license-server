<?php
/**
 * Cache settings template.
 *
 * Renders global cache settings and the cache provider selection grid.
 * Variables available from OptionsPage::cache_options():
 *   @var array<string, SmartLicenseServer\Cache\Adapters\CacheAdapterInterface> $providers        — 
 *   @var string|null $default_provider
 *   @var array<int, array> $cache_fields
 *
 * @package SmartLicenseServer\templates
 * @since   0.2.0
 */

use SmartLicenseServer\Admin\OptionsPage;
use SmartLicenseServer\Cache\CacheProviderIcons;

defined( 'SMLISER_ABSPATH' ) || exit;

$menu_args = OptionsPage::get_menu_args( $request );

$current_url = smliser_get_current_url()->remove_query_param( 'message', 'section', 'provider' );
?>
<div class="smliser-admin-page">
    <?php smliser_print_admin_content_header( $menu_args ); ?>

    <div class="smliser-providers-grid">
        <h2 class="smliser-section-title">Cache Adapters</h2>
        <div class="notice notice-info" style="margin: 10px;">
            <p class="smliser-section-description">
                Configure the cache adapter you want to use for object caching.
            </p>            
        </div>


        <div class="smliser-provider-cards">
            <?php foreach ( $providers as $provider_id => $provider ) :
                $is_default   = $default_provider === $provider_id;
                $provider_url = $current_url->add_query_param( 'adapter', $provider_id );
            ?>
                <div class="smliser-provider-card <?php echo esc_attr( $provider_id ); ?> <?php echo $is_default ? 'smliser-provider-card--active' : ''; ?>">

                    <div class="smliser-provider-card__icon-wrap">
                        <?php echo CacheProviderIcons::render( $provider_id, $provider::get_name() ); ?>
                    </div>

                    <div class="smliser-provider-card__body">
                        <span class="smliser-provider-card__name">
                            <?php echo escHtml( $provider::get_name() ); ?>
                        </span>

                        <?php if ( $is_default ) : ?>
                            <span class="smliser-provider-card__badge">&#10003; Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="smliser-provider-card__actions">
                        <?php if ( empty( $provider->get_settings_schema() ) && $provider->is_supported() ) : ?>
                            <a href="<?php echo esc_url( $provider_url ); ?>"
                                class="smliser-button smliser-button--secondary">
                                Check
                            </a>
                        <?php elseif( ! empty( $provider->get_settings_schema() ) && $provider->is_supported() ): ?>
                            <a href="<?php echo esc_url( $provider_url ); ?>"
                                class="smliser-button smliser-button--secondary">
                                Configure</a>
                        <?php endif; ?>

                        <?php if ( ! $provider->is_supported() ) : ?>
                            <span>
                                <i class="ti ti-cancel"></i>
                                Not supported
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>